<?php

namespace App\Domain\Payroll;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Operations\Outbox;
use App\Models\User;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PayrollService
{
    public function __construct(private PayrollCalculator $calculator) {}

    public static function authorize(User $actor, bool $finance = false): void
    {
        abort_unless(Access::companyPermission($actor, $finance ? 'payroll.approve' : 'payroll.prepare'), 403);
    }

    public function create(User $actor, string $key, array $input): array
    {
        self::authorize($actor);
        $d = Validator::make($input, ['attendance_period_id' => ['required', 'integer', 'exists:attendance_periods,id'], 'payroll_policy_id' => ['required', 'integer', 'exists:payroll_policies,id']])->validate();

        return CoreWrite::run(fn () => Mutation::run($actor, 'payroll.batch.create', $key, $d, fn ($u) => self::authorize($u), function ($u) use ($d) {
            $id = DB::table('payroll_batches')->insertGetId($d + ['public_id' => (string) Str::uuid(), 'prepared_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'payroll.batch_created', 'payroll_batch', $id);

            return ['id' => $id, 'version' => 1, 'status' => 'draft'];
        }));
    }

    public function calculate(User $actor, int $id, int $version, string $key): array
    {
        return Mutation::run($actor, 'payroll.calculate', $key, compact('id', 'version'), fn ($u) => self::authorize($u), function ($u) use ($id, $version) {
            $batch = $this->batch($id, $version);
            abort_if(in_array($batch->status, ['finalized', 'cancelled']), 409);
            $result = $this->calculator->collect($batch);
            DB::table('payroll_rows')->where('payroll_batch_id', $id)->delete();
            foreach ($result['rows'] as $row) {
                DB::table('payroll_rows')->insert(['payroll_batch_id' => $id, 'employee_id' => $row['employee_id'], 'snapshot' => encrypt($row), 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('payroll_batches')->where('id', $id)->update(['status' => 'calculated', 'version' => $version + 1, 'source_checksum' => $result['checksum'], 'totals' => encrypt(['net_minor' => $result['total_minor']]), 'blockers' => json_encode($result['blockers']), 'prepared_by' => $u->id, 'reviewed_by' => null, 'approved_by' => null, 'updated_at' => now()]);
            Audit::record($u->id, 'payroll.calculated', 'payroll_batch', $id, ['rows' => count($result['rows']), 'source_checksum' => $result['checksum'], 'blocker_count' => count($result['blockers'])]);

            return ['id' => $id, 'status' => 'calculated', 'version' => $version + 1, 'blockers' => $result['blockers']];
        });
    }

    public function transition(User $actor, int $id, int $version, string $key, string $action, array $input): array
    {
        abort_unless(in_array($action, ['review', 'approve', 'finalize']), 404);
        if ($action === 'finalize') {
            Validator::make($input, ['release_at' => ['required', 'date_format:Y-m-d\TH:i:sP']])->validate();
        }

        return Mutation::run($actor, 'payroll.'.$action, $key, compact('id', 'version', 'action', 'input'), fn ($u) => self::authorize($u, $action !== 'review'), function ($u) use ($id, $version, $action, $input) {
            $b = $this->batch($id, $version);
            $expected = ['review' => 'calculated', 'approve' => 'reviewed', 'finalize' => 'approved'][$action];
            abort_unless($b->status === $expected, 409);
            if ($action !== 'review') {
                abort_if($u->id === $b->prepared_by, 403, 'Finance approver harus berbeda pembuat perhitungan.');
            }
            $result = $this->calculator->collect($b);
            if (! hash_equals($b->source_checksum, $result['checksum'])) {
                DB::table('payroll_batches')->where('id', $id)->update(['status' => 'draft', 'version' => $version + 1, 'reviewed_by' => null, 'approved_by' => null, 'updated_at' => now()]);
                Audit::record($u->id, 'payroll.source_invalidated', 'payroll_batch', $id);

                return ['id' => $id, 'version' => $version + 1, 'status' => 'draft', 'http_status' => 409, 'code' => 'source_changed_recalculate'];
            }
            abort_unless(empty($result['blockers']), 422, 'Payroll memiliki penghambat; lihat detail hasil perhitungan.');
            $status = ['review' => 'reviewed', 'approve' => 'approved', 'finalize' => 'finalized'][$action];
            $changes = ['status' => $status, 'version' => $version + 1, 'updated_at' => now()];
            if ($action === 'review') {
                $changes['reviewed_by'] = $u->id;
            }
            if ($action === 'approve') {
                $changes['approved_by'] = $u->id;
            }
            if ($action === 'finalize') {
                abort_unless($b->approved_by && $b->approved_by !== $b->prepared_by, 403);
                $changes['finalized_at'] = now();
                $changes['release_at'] = $input['release_at'];
                foreach (DB::table('payroll_rows')->where('payroll_batch_id', $id)->orderBy('id')->get() as $row) {
                    $snapshot = decrypt($row->snapshot);
                    $pdf = $this->pdf($snapshot, $b->public_id);
                    DB::table('payroll_final_snapshots')->insert(['payroll_row_id' => $row->id, 'snapshot' => $row->snapshot, 'pdf_content' => encrypt(base64_encode($pdf)), 'sha256' => hash('sha256', $pdf), 'created_at' => now()]);
                    $owners = User::where('employee_id', $row->employee_id)->where('is_active', true)->pluck('id')->all();
                    Outbox::notify($owners, 'payroll.slip_scheduled', 'payroll_row', $row->id, 'slip:'.$row->id);
                }
                DB::table('attendance_periods')->where('id', $b->attendance_period_id)->update(['payroll_finalized' => true]);
            }
            DB::table('payroll_batches')->where('id', $id)->update($changes);
            Audit::record($u->id, 'payroll.'.$status, 'payroll_batch', $id, ['source_checksum' => $b->source_checksum]);

            return ['id' => $id, 'status' => $status, 'version' => $version + 1];
        });
    }

    private function batch(int $id, int $version): object
    {
        $b = DB::table('payroll_batches')->where('id', $id)->lockForUpdate()->firstOrFail();
        CoreWrite::version($b->version, $version);

        return $b;
    }

    private function pdf(array $snapshot, string $number): string
    {
        $watermark = app()->environment('demo') ? '<h2>DEMO — DATA SINTETIS</h2>' : '';
        $html = '<html><meta charset="utf-8"><body style="font-family:DejaVu Sans;font-size:10pt">'.$watermark.'<h1>Slip gaji</h1><p>'.e($number).'</p><p>'.e($snapshot['employee_name']).' / '.e($snapshot['employee_number']).'</p><p>'.e(implode(' — ', $snapshot['period'])).' (akhir eksklusif)</p><table>';
        foreach ($snapshot['lines'] as $line) {
            $html .= '<tr><td>'.e($line['code']).'</td><td>'.e($line['kind']).'</td><td>IDR '.e(IntegerMoney::format($line['amount_minor'])).'</td></tr>';
        }
        $html .= '</table><p>Net IDR '.e(IntegerMoney::format($snapshot['net_minor'])).'</p><p>Kebijakan #'.e($snapshot['policy_id']).' versi '.e($snapshot['policy_version']).'; revisi kehadiran '.e($snapshot['attendance_revision']).'</p></body></html>';
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]));
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    public function show(User $actor, int $id): array
    {
        abort_unless(Access::companyPermission($actor, 'payroll.prepare') || Access::companyPermission($actor, 'payroll.approve'), 403);
        $b = DB::table('payroll_batches')->where('id', $id)->firstOrFail();
        $current = $b->status === 'finalized' ? null : $this->calculator->collect($b);
        Audit::record($actor->id, 'payroll.batch_read', 'payroll_batch', $id);

        return ['id' => $id, 'public_id' => $b->public_id, 'version' => $b->version, 'status' => $b->status, 'source_stale' => $current && $b->source_checksum ? ! hash_equals($current['checksum'], $b->source_checksum) : false, 'blockers' => $current['blockers'] ?? json_decode($b->blockers, true), 'totals' => $b->totals ? decrypt($b->totals) : null, 'rows' => DB::table('payroll_rows')->where('payroll_batch_id', $id)->orderBy('id')->get()->map(fn ($r) => ['id' => $r->id, 'employee_id' => $r->employee_id, 'snapshot' => decrypt($r->snapshot)])];
    }

    public function payslip(User $actor, int $id)
    {
        $row = DB::table('payroll_rows')->where('id', $id)->firstOrFail();
        $batch = DB::table('payroll_batches')->where('id', $row->payroll_batch_id)->firstOrFail();
        $owner = $actor->employee_id === $row->employee_id && $actor->grants()->where('role', 'employee')->where('scope', 'self')->exists();
        abort_unless($owner || Access::companyPermission($actor, 'payroll.prepare') || Access::companyPermission($actor, 'payroll.approve'), 404);
        abort_unless($batch->status === 'finalized' && CarbonImmutable::parse($batch->release_at) <= now(), 404);
        $file = DB::table('payroll_final_snapshots')->where('payroll_row_id', $id)->firstOrFail();
        $pdf = base64_decode(decrypt($file->pdf_content), true);
        abort_unless($pdf !== false && hash_equals($file->sha256, hash('sha256', $pdf)), 503);
        Audit::record($actor->id, 'payroll.slip_downloaded', 'payroll_row', $id);

        return response($pdf)->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="payslip-'.$id.'.pdf"')->header('Cache-Control', 'no-store, private');
    }
}
