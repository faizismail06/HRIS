<?php

namespace App\Domain\Payroll;

use App\Domain\Workflow\StageWorkflow;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OpeningPayrollHandler implements StageWorkflow, WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['finance_approver'];
    }

    public function prepare(User $actor, array $input): array
    {
        PayrollService::authorize($actor);
        $d = Validator::make($input, ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'tax_year' => ['required', 'integer', 'between:2000,2200'], 'through_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'source_key' => ['required', 'string', 'max:100'], 'gross_minor' => ['required', 'integer', 'between:0,1000000000000000'], 'tax_paid_minor' => ['required', 'integer', 'between:0,1000000000000000'], 'reason' => ['required', 'string', 'max:4000']])->validate();
        foreach (['employee_id', 'tax_year', 'gross_minor', 'tax_paid_minor'] as $field) {
            $d[$field] = (int) $d[$field];
        }
        $this->validateSource($d);

        return ['employee_id' => $d['employee_id'], 'payload' => $d, 'summary' => ['tax_year' => $d['tax_year'], 'through_on' => $d['through_on'], 'source_key' => $d['source_key']]];
    }

    private function validateSource(array $d, ?int $requestId = null): void
    {
        abort_unless((int) substr($d['through_on'], 0, 4) === $d['tax_year'], 422);
        abort_unless(Employee::findOrFail($d['employee_id'])->periods()->where('starts_on', '<=', $d['through_on'])->exists(), 422);
        abort_if(DB::table('payroll_openings')->where(fn ($q) => $q->where('source_key', $d['source_key'])->orWhere(fn ($r) => $r->where('employee_id', $d['employee_id'])->where('tax_year', $d['tax_year'])->whereIn('status', ['pending', 'approved'])))->when($requestId, fn ($q) => $q->where('workflow_request_id', '<>', $requestId))->exists(), 422, 'Opening payroll sudah tercatat; koreksi hasil disahkan memakai adjustment.');
        abort_if(DB::table('payroll_rows')->join('payroll_batches', 'payroll_batches.id', '=', 'payroll_rows.payroll_batch_id')->join('attendance_periods', 'attendance_periods.id', '=', 'payroll_batches.attendance_period_id')->where('payroll_rows.employee_id', $d['employee_id'])->where('payroll_batches.status', 'finalized')->where('payroll_batches.kind', 'regular')->where('attendance_periods.starts_on', '<=', $d['through_on'])->where('attendance_periods.ends_on', '>', $d['tax_year'].'-01-01')->exists(), 422, 'Opening bertumpuk dengan payroll final tahun ini.');
    }

    public function submitted(WorkflowRequest $r): void
    {
        $this->validateSource($r->payload);
        DB::table('payroll_openings')->insert(['employee_id' => $r->employee_id, 'tax_year' => $r->payload['tax_year'], 'through_on' => $r->payload['through_on'], 'source_key' => $r->payload['source_key'], 'workflow_request_id' => $r->id, 'snapshot' => encrypt($r->payload), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function approved(WorkflowRequest $r): void
    {
        $this->validateSource($r->payload, $r->id);
        DB::table('payroll_openings')->where('workflow_request_id', $r->id)->update(['status' => 'approved', 'approved_by' => $r->effectActorId(), 'updated_at' => now()]);
    }

    public function stageApproved(WorkflowRequest $request, WorkflowStep $step): void
    {
        $decision = DB::table('workflow_decisions')->where('workflow_step_id', $step->id)->firstOrFail();
        abort_unless($decision->reason && trim(decrypt($decision->reason)) !== '', 422, 'Finance wajib mencatat verifikasi bukti opening payroll.');
    }

    public function released(WorkflowRequest $r): void
    {
        DB::table('payroll_openings')->where('workflow_request_id', $r->id)->where('status', 'pending')->update(['status' => 'rejected', 'updated_at' => now()]);
    }
}
