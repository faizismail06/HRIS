<?php

namespace App\Domain\Documents;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LetterHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        $data = Validator::make($input, ['employee_id' => ['required', 'integer'], 'employee_version' => ['required', 'integer', 'min:1'], 'employment_period_id' => ['required', 'integer'], 'document_template_id' => ['required', 'integer'], 'letter_kind' => ['required', Rule::in(['employment_certificate', 'service_letter', 'assignment_letter'])], 'reissues_id' => ['nullable', 'integer'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        abort_unless(DocumentService::visible($actor, $data['employee_id']), 404);
        $employee = Employee::findOrFail($data['employee_id']);
        CoreWrite::version($employee->version, $data['employee_version']);
        $period = $employee->periods()->findOrFail($data['employment_period_id']);
        abort_unless(DB::table('document_templates')->where('id', $data['document_template_id'])->where('kind', $data['letter_kind'])->where('is_active', true)->exists(), 422, 'Template tidak aktif atau salah jenis.');
        if ($data['letter_kind'] === 'service_letter') {
            abort_unless($period->ends_on && $period->ends_on->toDateString() <= today()->toDateString(), 422, 'Periode kerja belum selesai.');
        } else {
            abort_unless($period->starts_on->toDateString() <= today()->toDateString() && (! $period->ends_on || $period->ends_on->toDateString() > today()->toDateString()), 422, 'Surat ini memerlukan periode kerja berjalan.');
        }
        if (! empty($data['reissues_id'])) {
            abort_unless(DB::table('issued_documents')->where('id', $data['reissues_id'])->where('employee_id', $employee->id)->where('kind', $data['letter_kind'])->exists(), 422);
        }

        return ['employee_id' => $employee->id, 'payload' => $data, 'summary' => ['letter_kind' => $data['letter_kind'], 'employment_period_id' => $period->id, 'reissues_id' => $data['reissues_id'] ?? null]];
    }

    public function submitted(WorkflowRequest $r): void {}

    public function approved(WorkflowRequest $r): void
    {
        app(DocumentService::class)->issue($r, $r->payload['letter_kind']);
    }

    public function released(WorkflowRequest $r): void {}
}
