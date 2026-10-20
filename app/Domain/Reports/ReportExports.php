<?php

namespace App\Domain\Reports;

use App\Domain\Identity\AccountSecurity;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Payroll\PaymentService;
use App\Jobs\BuildReportExport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExports
{
    public function request(User $actor, string $kind, string $key, array $input): array
    {
        app(ReportService::class)->authorize($actor, $kind);
        $d = Validator::make($input, ['format' => ['required', 'in:csv,xlsx'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        $filters = app(ReportService::class)->filters($input);

        return Mutation::run($actor, 'report.export.request', $key, compact('kind', 'd', 'filters'), fn ($u) => app(ReportService::class)->authorize($u, $kind), function ($u) use ($kind, $d, $filters) {
            $id = DB::table('report_exports')->insertGetId(['created_by' => $u->id, 'security_version' => $u->security_version, 'kind' => $kind, 'format' => $d['format'], 'filters' => json_encode($filters), 'reason' => encrypt($d['reason']), 'expires_at' => now()->addHours(24), 'created_at' => now(), 'updated_at' => now()]);
            DB::afterCommit(fn () => BuildReportExport::dispatch($id));
            Audit::record($u->id, 'report.export_requested', 'report_export', $id, ['kind' => $kind, 'format' => $d['format'], 'reason_provided' => true]);

            return ['id' => $id, 'status' => 'queued'];
        });
    }

    public function build(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
            $e = DB::table('report_exports')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($e->status !== 'queued') {
                return;
            }
            $actor = User::findOrFail($e->created_by);
            abort_unless(AccountSecurity::available($actor) && $actor->security_version === $e->security_version && CarbonImmutable::parse($e->expires_at) > now(), 403);
            $report = app(ReportService::class)->build($actor, $e->kind, json_decode($e->filters, true));
            $columns = $report['data'] ? array_keys($report['data'][0]) : ['record_id', 'employee_id', 'work_date'];
            if ($report['watermark']) {
                $columns[] = 'watermark';
            }
            $rows = [];
            foreach ($report['data'] as $row) {
                if ($report['watermark']) {
                    $row['watermark'] = $report['watermark'];
                }
                $rows[] = array_map(fn ($column) => is_bool($row[$column] ?? null) ? (($row[$column] ?? false) ? '1' : '0') : (string) ($row[$column] ?? ''), $columns);
            }
            if ($e->format === 'csv') {
                $stream = fopen('php://temp', 'w+');
                fputcsv($stream, $columns, ',', '"', '');
                foreach ($rows as $row) {
                    fputcsv($stream, array_map(fn ($v) => PaymentService::safeCell($v), $row), ',', '"', '');
                }
                rewind($stream);
                $bytes = stream_get_contents($stream);
                fclose($stream);
            } else {
                $book = new Spreadsheet;
                try {
                    foreach (array_merge([$columns], $rows) as $i => $row) {
                        foreach ($row as $j => $value) {
                            $book->getActiveSheet()->setCellValueExplicit([$j + 1, $i + 1], $value, DataType::TYPE_STRING);
                        }
                    }
                    ob_start();
                    try {
                        (new Xlsx($book))->save('php://output');
                        $bytes = ob_get_clean();
                    } catch (\Throwable $error) {
                        ob_end_clean();
                        throw $error;
                    }
                } finally {
                    $book->disconnectWorksheets();
                }
            }
            DB::table('report_exports')->where('id', $id)->update(['status' => 'ready', 'content' => encrypt(base64_encode($bytes)), 'scope_snapshot' => encrypt(array_column($report['data'], 'record_id')), 'sha256' => hash('sha256', $bytes), 'updated_at' => now()]);
        });
    }

    public function status(User $actor, int $id): array
    {
        $e = $this->owned($actor, $id);

        return ['id' => $id, 'status' => CarbonImmutable::parse($e->expires_at) <= now() ? 'expired' : $e->status, 'format' => $e->format, 'expires_at' => $e->expires_at];
    }

    private function owned(User $actor, int $id): object
    {
        $e = DB::table('report_exports')->where('id', $id)->where('created_by', $actor->id)->firstOrFail();
        app(ReportService::class)->authorize($actor, $e->kind);
        abort_unless($actor->security_version === $e->security_version, 403);

        return $e;
    }

    public function download(User $actor, int $id)
    {
        $e = $this->owned($actor, $id);
        abort_unless($e->status === 'ready' && CarbonImmutable::parse($e->expires_at) > now(), 404);
        $current = app(ReportService::class)->build($actor, $e->kind, json_decode($e->filters, true));
        abort_if(array_diff(decrypt($e->scope_snapshot), array_column($current['data'], 'record_id')), 403, 'Scope atau sumber berubah; buat export baru.');
        $bytes = base64_decode(decrypt($e->content), true);
        abort_unless($bytes !== false && hash_equals($e->sha256, hash('sha256', $bytes)), 503);
        Audit::record($actor->id, 'report.export_downloaded', 'report_export', $id, ['checksum' => $e->sha256]);

        return response($bytes)->header('Content-Type', $e->format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition', 'attachment; filename="report-'.$id.'.'.$e->format.'"')->header('Cache-Control', 'no-store, private')->header('X-Content-Type-Options', 'nosniff');
    }
}
