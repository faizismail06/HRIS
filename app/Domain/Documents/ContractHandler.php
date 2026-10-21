<?php

namespace App\Domain\Documents;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContractHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'documents.manage'), 403);
        $data = Validator::make($input, ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'employee_version' => ['required', 'integer', 'min:1'], 'employment_period_id' => ['required', 'integer'], 'document_template_id' => ['required', 'integer'], 'contract_type' => ['required', 'string', 'max:80'], 'renewal_sequence' => ['required', 'integer', 'between:0,100'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'], 'amends_id' => ['nullable', 'integer'], 'renews_id' => ['nullable', 'integer'], 'terms' => ['required', 'string', 'max:10000'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        $this->renewal($data);
        $employee = Employee::findOrFail($data['employee_id']);
        CoreWrite::version($employee->version, $data['employee_version']);
        $period = $employee->periods()->findOrFail($data['employment_period_id']);
        abort_unless($period->starts_on->toDateString() <= $data['starts_on'] && (! $period->ends_on || $period->ends_on->toDateString() >= $data['ends_on']), 422, 'Kontrak harus berada dalam periode kerja.');
        abort_unless(DB::table('document_templates')->where('id', $data['document_template_id'])->where('kind', 'contract')->where('is_active', true)->exists(), 422);
        if (! empty($data['amends_id'])) {
            abort_unless(DB::table('employment_contracts')->where('id', $data['amends_id'])->where('employee_id', $employee->id)->where('status', 'approved')->whereNull('amends_id')->where('starts_on', '<=', $data['starts_on'])->where('ends_on', '>=', $data['ends_on'])->exists(), 422, 'Amendemen harus merujuk kontrak primer yang melingkupinya.');
        }

        return ['employee_id' => $employee->id, 'payload' => $data, 'summary' => array_intersect_key($data, array_flip(['contract_type', 'renewal_sequence', 'starts_on', 'ends_on', 'amends_id']))];
    }

    public function submitted(WorkflowRequest $r): void
    {
        $p = $r->payload;
        DB::table('employment_contracts')->insert(['employee_id' => $r->employee_id, 'employment_period_id' => $p['employment_period_id'], 'workflow_request_id' => $r->id, 'contract_type' => $p['contract_type'], 'renewal_sequence' => $p['renewal_sequence'], 'starts_on' => $p['starts_on'], 'ends_on' => $p['ends_on'], 'amends_id' => $p['amends_id'] ?? null, 'renews_id' => $p['renews_id'] ?? null, 'terms' => encrypt($p['terms']), 'created_at' => now(), 'updated_at' => now()]);
        if (! empty($p['renews_id'])) {
            DB::table('contract_review_tasks')->insertOrIgnore(['employment_contract_id' => $p['renews_id'], 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function approved(WorkflowRequest $r): void
    {
        $p = $r->payload;
        $this->renewal($p);
        if (! empty($p['renews_id'])) {
            abort_unless(DB::table('contract_review_tasks')->where('employment_contract_id', $p['renews_id'])->where('status', 'hr_reviewed')->exists(), 422, 'Renewal memerlukan rekomendasi manager dan review HR (atau pengecualian beralasan bila manager tidak ditetapkan).');
        }
        $employee = Employee::findOrFail($r->employee_id);
        $period = $employee->periods()->findOrFail($p['employment_period_id']);
        abort_unless(! $period->ends_on || $period->ends_on->toDateString() >= $p['ends_on'], 409, 'Periode kerja telah berubah.');
        if (empty($p['amends_id'])) {
            abort_if(DB::table('employment_contracts')->where('employee_id', $r->employee_id)->where('status', 'approved')->whereNull('amends_id')->where('starts_on', '<', $p['ends_on'])->where('ends_on', '>', $p['starts_on'])->exists(), 422, 'Kontrak primer bertumpuk.');
        }
        $doc = app(DocumentService::class)->issue($r, 'contract', ['contract_type' => $p['contract_type'], 'contract_start' => $p['starts_on'], 'contract_end' => $p['ends_on'], 'terms' => $p['terms']]);
        DB::table('employment_contracts')->where('workflow_request_id', $r->id)->update(['status' => 'approved', 'issued_document_id' => $doc, 'version' => 2, 'updated_at' => now()]);
        if (! empty($p['renews_id'])) {
            DB::table('contract_review_tasks')->where('employment_contract_id', $p['renews_id'])->update(['status' => 'resolved', 'resolved_by' => $r->effectActorId(), 'updated_at' => now()]);
        }
    }

    public function released(WorkflowRequest $r): void
    {
        DB::table('employment_contracts')->where('workflow_request_id', $r->id)->update(['status' => 'cancelled', 'version' => 2, 'updated_at' => now()]);
    }

    private function renewal(array $p): void
    {
        abort_if(! empty($p['amends_id']) && ! empty($p['renews_id']), 422, 'Pilih amendemen atau renewal, bukan keduanya.');
        abort_if(empty($p['amends_id']) && empty($p['renews_id']) && (int) $p['renewal_sequence'] > 0, 422, 'Renewal sequence memerlukan referensi kontrak sebelumnya.');
        if (! empty($p['amends_id'])) {
            abort_unless(DB::table('employment_contracts')->where('id', $p['amends_id'])->where('employee_id', $p['employee_id'])->where('employment_period_id', $p['employment_period_id'])->where('status', 'approved')->whereNull('amends_id')->where('starts_on', '<=', $p['starts_on'])->where('ends_on', '>=', $p['ends_on'])->where('renewal_sequence', $p['renewal_sequence'])->exists(), 422, 'Sumber amendemen berubah atau tidak sesuai.');
        }
        if (empty($p['renews_id'])) {
            return;
        }
        $prior = DB::table('employment_contracts')->where('id', $p['renews_id'])->where('employee_id', $p['employee_id'])->where('employment_period_id', $p['employment_period_id'])->where('status', 'approved')->whereNull('amends_id')->firstOrFail();
        abort_unless($prior->ends_on === $p['starts_on'] && (int) $p['renewal_sequence'] === $prior->renewal_sequence + 1, 422, 'Renewal harus berurutan dan mulai pada akhir eksklusif kontrak sebelumnya.');
    }
}
