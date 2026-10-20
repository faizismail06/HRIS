<?php

namespace App\Domain\Payroll;

use App\Domain\Identity\Access;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompensationHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['finance_approver'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'payroll.prepare'), 403);
        $p = Validator::make($input, ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'valid_from' => ['required', 'date_format:Y-m-d'], 'monthly_base_minor' => ['required', 'integer', 'between:0,1000000000000000'], 'base_compensation_id' => ['nullable', 'integer'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        abort_unless(Employee::findOrFail($p['employee_id'])->periods()->where('starts_on', '<=', $p['valid_from'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $p['valid_from']))->exists(), 422);
        $this->check($p);

        return ['employee_id' => $p['employee_id'], 'payload' => $p, 'summary' => ['valid_from' => $p['valid_from'], 'base_compensation_id' => $p['base_compensation_id'] ?? null]];
    }

    private function check(array $p): void
    {
        $current = DB::table('employee_compensations')->where('employee_id', $p['employee_id'])->where('valid_from', '<=', $p['valid_from'])->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', $p['valid_from']))->first();
        abort_unless(($current?->id ?? null) === ($p['base_compensation_id'] ?? null), 409, 'Versi kompensasi berubah.');
        abort_if($current && $current->valid_from >= $p['valid_from'], 422, 'Gunakan tanggal efektif sesudah awal versi sebelumnya.');
        abort_if(DB::table('payroll_batches')->join('attendance_periods', 'attendance_periods.id', '=', 'payroll_batches.attendance_period_id')->join('payroll_rows', 'payroll_rows.payroll_batch_id', '=', 'payroll_batches.id')->where('payroll_rows.employee_id', $p['employee_id'])->where('payroll_batches.status', 'finalized')->where('attendance_periods.ends_on', '>', $p['valid_from'])->exists(), 422, 'Kompensasi mengenai payroll final; gunakan penyesuaian terpisah.');
    }

    public function submitted(WorkflowRequest $r): void
    {
        $this->check($r->payload);
    }

    public function approved(WorkflowRequest $r): void
    {
        $p = $r->payload;
        $this->check($p);
        $until = null;
        if (! empty($p['base_compensation_id'])) {
            $old = DB::table('employee_compensations')->where('id', $p['base_compensation_id'])->firstOrFail();
            $until = $old->valid_until;
            DB::table('employee_compensations')->where('id', $old->id)->update(['valid_until' => $p['valid_from'], 'updated_at' => now()]);
        } else {
            $until = DB::table('employee_compensations')->where('employee_id', $r->employee_id)->where('valid_from', '>', $p['valid_from'])->min('valid_from');
        }
        DB::table('employee_compensations')->insert(['employee_id' => $r->employee_id, 'valid_from' => $p['valid_from'], 'valid_until' => $until, 'monthly_base_minor' => encrypt((int) $p['monthly_base_minor']), 'workflow_request_id' => $r->id, 'approved_by' => $r->effectActorId(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function released(WorkflowRequest $r): void {}
}
