<?php

namespace App\Domain\CoreHR;

use App\Domain\Identity\Access;
use App\Domain\Workflow\StageWorkflow;
use App\Domain\Workflow\WorkflowRouting;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CareerRetrospectiveHandler extends CareerHandler implements StageWorkflow, WorkflowRouting
{
    public function approverRoles(): array
    {
        return ['hr_admin', 'finance_approver'];
    }

    public function validateSteps(array $steps): void
    {
        abort_unless(array_column($steps, 'role') === ['hr_admin', 'finance_approver'], 422, 'Review retrospektif wajib HR lalu Finance independen.');
    }

    public function mayApprove(User $actor, WorkflowRequest $request, string $role): bool
    {
        if ($role === 'finance_approver' && DB::table('workflow_decisions')->join('workflow_steps', 'workflow_steps.id', '=', 'workflow_decisions.workflow_step_id')->where('workflow_steps.workflow_request_id', $request->id)->where('workflow_steps.role', 'hr_admin')->where('workflow_decisions.actor_id', $actor->id)->where('workflow_decisions.decision', 'approve')->exists()) {
            return false;
        }

        return Access::companyPermission($actor, $role === 'hr_admin' ? 'employees.review' : 'payroll.approve');
    }

    public function prepare(User $actor, array $input): array
    {
        $date = Validator::make($input, ['retrospective_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']])->validate()['retrospective_on'];
        $prepared = parent::prepare($actor, $input);
        $old = Employee::findOrFail($prepared['employee_id'])->assignments()->at($date)->firstOrFail();
        $prepared['payload']['retrospective_on'] = $date;
        $prepared['summary']['retrospective_on'] = $date;
        $prepared['summary']['retrospective_before'] = $old->only(['branch_id', 'department_id', 'job_id', 'manager_employee_id']);
        $prepared['summary']['closed_period_ids'] = DB::table('attendance_periods')->where('branch_id', $old->branch_id)->where('starts_on', '<=', $date)->where('ends_on', '>', $date)->where('status', 'closed')->pluck('id')->all();

        return $prepared;
    }

    public function approved(WorkflowRequest $r): void
    {
        $hrId = (int) DB::table('workflow_decisions')->join('workflow_steps', 'workflow_steps.id', '=', 'workflow_decisions.workflow_step_id')->where('workflow_steps.workflow_request_id', $r->id)->where('workflow_steps.role', 'hr_admin')->where('workflow_decisions.decision', 'approve')->value('workflow_decisions.actor_id');
        abort_if(! $hrId || $hrId === $r->effectActorId(), 403, 'Finance reviewer harus berbeda dari reviewer HR.');
        $change = EmployeeChangeRequest::where('workflow_request_id', $r->id)->firstOrFail();
        app(EmployeeWriter::class)->review(User::findOrFail($hrId), $change->id, ['version' => $change->version, 'decision' => 'approve', 'review_note' => 'Prospective correction after independent retrospective review #'.$r->id], $r->id);
        DB::table('career_retrospectives')->insert(['workflow_request_id' => $r->id, 'employee_change_request_id' => $change->id, 'employee_id' => $r->employee_id, 'retrospective_on' => $r->payload['retrospective_on'], 'apply_from' => $r->payload['effective_on'], 'snapshot' => encrypt(['proposal' => $r->payload, 'summary' => $r->summary, 'historical_assignments_unchanged' => true, 'payroll_adjustment_requires_separate_amount_review' => true]), 'hr_reviewer_id' => $hrId, 'finance_reviewer_id' => $r->effectActorId(), 'created_at' => now()]);
    }

    public function stageApproved(WorkflowRequest $request, WorkflowStep $step): void
    {
        $reason = DB::table('workflow_decisions')->where('workflow_step_id', $step->id)->value('reason');
        abort_unless($reason && trim(decrypt($reason)) !== '', 422, 'Reviewer wajib mencatat penilaian koreksi historis dan dampak payroll.');
    }
}
