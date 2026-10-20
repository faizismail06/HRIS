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

class OnboardingHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'employees.manage'), 403);
        $p = Validator::make($input, ['lifecycle_case_id' => ['required', 'integer'], 'case_version' => ['required', 'integer', 'min:1']])->validate();
        $case = DB::table('lifecycle_cases')->where('kind', 'onboarding')->where('status', 'open')->where('id', $p['lifecycle_case_id'])->firstOrFail();
        CoreWrite::version($case->version, $p['case_version']);

        return ['employee_id' => $case->employee_id, 'payload' => $p, 'summary' => ['lifecycle_case_id' => $case->id, 'effective_on' => $case->effective_on]];
    }

    public function submitted(WorkflowRequest $r): void
    {
        $this->ready($r);
        DB::table('lifecycle_cases')->where('id', $r->payload['lifecycle_case_id'])->update(['status' => 'in_review', 'workflow_request_id' => $r->id]);
    }

    private function ready(WorkflowRequest $r): object
    {
        $case = DB::table('lifecycle_cases')->where('id', $r->payload['lifecycle_case_id'])->firstOrFail();
        CoreWrite::version($case->version, $r->payload['case_version']);
        abort_if(DB::table('lifecycle_tasks')->where('lifecycle_case_id', $case->id)->where('mandatory', true)->whereNotIn('status', ['completed', 'waived'])->exists(), 422, 'Task mandatory harus selesai atau memiliki pengecualian HR sebelum aktivasi.');
        abort_if($case->effective_on > today()->toDateString(), 422, 'Aktivasi paling awal pada tanggal mulai kerja.');

        return $case;
    }

    public function approved(WorkflowRequest $r): void
    {
        $case = $this->ready($r);
        DB::table('employment_periods')->where('id', $case->employment_period_id)->update(['activation_state' => 'active', 'updated_at' => now()]);
        DB::table('lifecycle_cases')->where('id', $case->id)->update(['status' => 'completed', 'version' => $case->version + 1, 'updated_at' => now()]);
        Employee::whereKey($case->employee_id)->increment('version');
        Audit::record($r->effectActorId(), 'lifecycle.activated', 'lifecycle_case', $case->id, ['period_id' => $case->employment_period_id]);
    }

    public function released(WorkflowRequest $r): void
    {
        DB::table('lifecycle_cases')->where('workflow_request_id', $r->id)->update(['status' => 'open', 'workflow_request_id' => null, 'updated_at' => now()]);
    }
}
