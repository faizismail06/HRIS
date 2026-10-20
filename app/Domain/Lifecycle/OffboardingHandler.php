<?php

namespace App\Domain\Lifecycle;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OffboardingHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'employees.manage'), 403);
        $p = Validator::make($input, ['employee_id' => ['required', 'integer'], 'employee_version' => ['required', 'integer', 'min:1'], 'employment_period_id' => ['required', 'integer'], 'lifecycle_template_id' => ['required', 'integer'], 'effective_end_on' => ['required', 'date_format:Y-m-d', 'after:today'], 'last_working_on' => ['required', 'date_format:Y-m-d', 'before:effective_end_on'], 'reason_class' => ['required', Rule::in(['resignation', 'retirement', 'contract_end', 'termination', 'other'])], 'reason' => ['required', 'string', 'max:2000'], 'owners' => ['required', 'array'], 'owners.*' => ['required', 'integer', 'exists:users,id']])->validate();
        $employee = Employee::findOrFail($p['employee_id']);
        CoreWrite::version($employee->version, $p['employee_version']);
        $period = $employee->periods()->findOrFail($p['employment_period_id']);
        abort_unless($period->starts_on->toDateString() <= $p['last_working_on'] && (! $period->ends_on || $period->ends_on->toDateString() >= $p['effective_end_on']) && $period->activation_state === 'active', 422, 'Tanggal exit tidak sesuai periode aktif.');

        return ['employee_id' => $employee->id, 'payload' => $p, 'summary' => ['employment_period_id' => $period->id, 'last_working_on' => $p['last_working_on'], 'effective_end_on' => $p['effective_end_on']]];
    }

    public function submitted(WorkflowRequest $r): void
    {
        $p = $r->payload;
        app(LifecycleService::class)->createCase($r->employee_id, $p['employment_period_id'], 'offboarding', $p['lifecycle_template_id'], $p['effective_end_on'], $p['owners'], ['workflow_request_id' => $r->id, 'status' => 'in_review', 'last_working_on' => $p['last_working_on'], 'reason_class' => $p['reason_class'], 'reason' => encrypt($p['reason'])]);
    }

    public function approved(WorkflowRequest $r): void
    {
        $p = $r->payload;
        $employee = Employee::findOrFail($r->employee_id);
        CoreWrite::version($employee->version, $p['employee_version']);
        $end = $p['effective_end_on'];
        abort_unless($end > today()->toDateString(), 409, 'Tanggal exit terlewati; ajukan ulang agar tidak mengubah periode tertutup diam-diam.');
        $period = $employee->periods()->findOrFail($p['employment_period_id']);
        $employee->assignments()->where('employment_period_id', $period->id)->where('valid_from', '>=', $end)->whereNull('superseded_at')->update(['superseded_at' => now()]);
        $employee->assignments()->where('employment_period_id', $period->id)->whereNull('superseded_at')->where('valid_from', '<', $end)->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $end))->update(['valid_to' => $end]);
        $period->update(['ends_on' => $end, 'offboarding' => true, 'offboarding_from' => today()->toDateString()]);
        $employee->increment('version');
        DB::table('lifecycle_cases')->where('workflow_request_id', $r->id)->update(['status' => 'approved', 'version' => 2, 'updated_at' => now()]);
        Audit::record($r->effectActorId(), 'lifecycle.exit_scheduled', 'employee', $employee->id, ['employment_period_id' => $period->id, 'effective_end_on' => $end, 'last_working_on' => $p['last_working_on']]);
    }

    public function released(WorkflowRequest $r): void
    {
        DB::table('lifecycle_cases')->where('workflow_request_id', $r->id)->update(['status' => 'cancelled', 'version' => 2, 'updated_at' => now()]);
    }
}
