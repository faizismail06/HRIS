<?php

namespace App\Domain\Documents;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Demo\DemoBoundary;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Clock;
use App\Domain\Operations\Mutation;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DocumentService
{
    public const TOKENS = ['number', 'issued_on', 'employee_name', 'employee_number', 'branch_name', 'department_name', 'job_name', 'period_start', 'period_end', 'contract_type', 'contract_start', 'contract_end', 'terms'];

    public const KINDS = ['employment_certificate', 'service_letter', 'assignment_letter', 'contract'];

    public function template(User $actor, string $key, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'documents.manage'), 403);
        $data = Validator::make($input, ['code' => ['required', 'regex:/^[A-Z0-9_-]{2,30}$/'], 'kind' => ['required', Rule::in(self::KINDS)], 'title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:15000']])->validate();
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $data['body'], $matches);
        abort_if(array_diff(array_map('trim', $matches[1]), self::TOKENS), 422, 'Template mengandung placeholder yang tidak diizinkan.');
        abort_if(str_contains(preg_replace('/\{\{\s*([^{}]+?)\s*\}\}/', '', $data['body']), '{{'), 422, 'Placeholder tidak valid.');

        return Mutation::run($actor, 'documents.template', $key, $data, fn ($u) => abort_unless(Access::companyPermission($u, 'documents.manage'), 403), function ($u) use ($data) {
            $version = (int) DB::table('document_templates')->where('code', $data['code'])->max('version') + 1;
            DB::table('document_templates')->where('code', $data['code'])->update(['is_active' => false]);
            $id = DB::table('document_templates')->insertGetId($data + ['version' => $version, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'document.template_versioned', 'document_template', $id, ['code' => $data['code'], 'version' => $version]);

            return ['id' => $id, 'version' => $version];
        });
    }

    public static function visible(User $actor, int $employee): bool
    {
        return ($actor->employee_id === $employee && $actor->grants()->where('role', 'employee')->where('scope', 'self')->exists()) || EmployeeVisibility::query($actor, today()->toDateString(), ['hr_admin', 'hr_officer'])->whereKey($employee)->exists();
    }

    public function preview(User $actor, int $requestId): array
    {
        $r = WorkflowRequest::whereIn('kind', ['letter', 'contract'])->findOrFail($requestId);
        abort_unless(WorkflowAccess::visible($actor, $r) && self::visible($actor, $r->employee_id), 404);
        $p = $r->payload;
        $employee = Employee::findOrFail($r->employee_id);
        CoreWrite::version($employee->version, $p['employee_version']);
        $period = $employee->periods()->findOrFail($p['employment_period_id']);
        $kind = $r->kind === 'contract' ? 'contract' : $p['letter_kind'];
        $template = DB::table('document_templates')->where('id', $p['document_template_id'])->where('kind', $kind)->firstOrFail();
        $date = app(Clock::class)->now()->toDateString();
        $reference = $kind === 'service_letter' && $period->ends_on ? $period->ends_on->subDay()->toDateString() : ($p['starts_on'] ?? $date);
        $assignment = $employee->assignments()->where('employment_period_id', $period->id)->at($reference)->first();
        $values = ['number' => 'DRAFT', 'issued_on' => $date, 'employee_name' => $employee->name, 'employee_number' => $employee->employee_number, 'period_start' => $period->starts_on->toDateString(), 'period_end' => $period->ends_on?->toDateString() ?? '', 'branch_name' => $assignment ? DB::table('branches')->where('id', $assignment->branch_id)->value('name') : '', 'department_name' => $assignment ? DB::table('departments')->where('id', $assignment->department_id)->value('name') : '', 'job_name' => $assignment ? DB::table('job_positions')->where('id', $assignment->job_id)->value('name') : '', 'contract_type' => $p['contract_type'] ?? '', 'contract_start' => $p['starts_on'] ?? '', 'contract_end' => $p['ends_on'] ?? '', 'terms' => $p['terms'] ?? ''];
        $body = preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/', fn ($m) => (string) ($values[trim($m[1])] ?? ''), $template->body);
        Audit::record($actor->id, 'document.draft_previewed', 'workflow_request', $r->id, ['template_version' => $template->version]);

        return ['draft' => true, 'number' => null, 'title' => $template->title, 'body_text' => $body, 'template_version' => $template->version, 'employee_version' => $employee->version, 'watermark' => 'DRAFT — BELUM DITERBITKAN'.(DemoBoundary::enabled() ? ' / DEMO — DATA SINTETIS' : '')];
    }

    public function issue(WorkflowRequest $r, string $kind, array $extra = []): int
    {
        $p = $r->payload;
        $employee = Employee::findOrFail($r->employee_id);
        CoreWrite::version($employee->version, $p['employee_version']);
        $period = $employee->periods()->findOrFail($p['employment_period_id']);
        $template = DB::table('document_templates')->where('id', $p['document_template_id'])->firstOrFail();
        abort_unless($template->kind === $kind, 422);
        $date = app(Clock::class)->now()->toDateString();
        if ($kind === 'service_letter') {
            abort_unless($period->ends_on && $period->ends_on->toDateString() <= $date, 422, 'Surat pengalaman memerlukan periode kerja selesai.');
        }
        if ($kind !== 'service_letter' && $kind !== 'contract') {
            abort_unless($period->starts_on->toDateString() <= $date && (! $period->ends_on || $period->ends_on->toDateString() > $date), 409, 'Periode kerja berubah sebelum penerbitan.');
        }
        $reference = $kind === 'service_letter' ? $period->ends_on->subDay()->toDateString() : ($extra['contract_start'] ?? $date);
        $assignment = $employee->assignments()->where('employment_period_id', $period->id)->at($reference)->first();
        $series = $kind === 'contract' ? 'CON' : 'LTR';
        $year = (int) substr($date, 0, 4);
        DB::table('document_sequences')->insertOrIgnore(['series' => $series, 'year' => $year, 'next_value' => 1]);
        $sequence = DB::table('document_sequences')->where('series', $series)->where('year', $year)->lockForUpdate()->firstOrFail();
        $number = $series.'/'.$year.'/'.str_pad((string) $sequence->next_value, 6, '0', STR_PAD_LEFT);
        DB::table('document_sequences')->where('id', $sequence->id)->increment('next_value');
        $values = $extra + ['number' => $number, 'issued_on' => $date, 'employee_name' => $employee->name, 'employee_number' => $employee->employee_number, 'period_start' => $period->starts_on->toDateString(), 'period_end' => $period->ends_on?->toDateString() ?? '', 'branch_name' => $assignment ? DB::table('branches')->where('id', $assignment->branch_id)->value('name') : '', 'department_name' => $assignment ? DB::table('departments')->where('id', $assignment->department_id)->value('name') : '', 'job_name' => $assignment ? DB::table('job_positions')->where('id', $assignment->job_id)->value('name') : ''];
        $body = preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/', fn ($m) => (string) ($values[trim($m[1])] ?? ''), $template->body);
        $demo = DemoBoundary::enabled() ? 'DEMO — DATA SINTETIS / '.config('hris.demo_scenario_id') : '';
        $html = '<!doctype html><html><meta charset="utf-8"><body style="font-family:DejaVu Sans;font-size:11pt"><p>'.e($demo).'</p><h1>'.e($template->title).'</h1><p>'.e($number).'</p><div style="white-space:pre-wrap">'.nl2br(e($body)).'</div></body></html>';
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $bytes = $pdf->output();
        $id = DB::table('issued_documents')->insertGetId(['number' => $number, 'kind' => $kind, 'employee_id' => $employee->id, 'employment_period_id' => $period->id, 'document_template_id' => $template->id, 'workflow_request_id' => $r->id, 'reissues_id' => $p['reissues_id'] ?? null, 'snapshot' => json_encode(['values' => $values, 'body' => $body, 'title' => $template->title, 'employee_version' => $employee->version, 'template_version' => $template->version, 'assignment_id' => $assignment?->id, 'watermark' => $demo], JSON_THROW_ON_ERROR), 'pdf_content' => encrypt(base64_encode($bytes)), 'sha256' => hash('sha256', $bytes), 'issued_by' => $r->effectActorId(), 'issued_at' => app(Clock::class)->now()]);
        Audit::record($r->effectActorId(), 'document.issued', 'issued_document', $id, ['number' => $number, 'kind' => $kind, 'template_version' => $template->version]);

        return $id;
    }

    public function download(User $actor, int $id)
    {
        $doc = DB::table('issued_documents')->where('id', $id)->firstOrFail();
        abort_unless(self::visible($actor, $doc->employee_id), 404);
        $bytes = base64_decode(decrypt($doc->pdf_content), true);
        abort_unless($bytes !== false && hash_equals($doc->sha256, hash('sha256', $bytes)), 503);
        Audit::record($actor->id, 'document.downloaded', 'issued_document', $id);

        return response($bytes)->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="document-'.$id.'.pdf"')->header('Cache-Control', 'no-store, private')->header('X-Content-Type-Options', 'nosniff');
    }
}
