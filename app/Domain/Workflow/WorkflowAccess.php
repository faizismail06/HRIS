<?php

namespace App\Domain\Workflow;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\AccountSecurity;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;

class WorkflowAccess
{
    public static function stepEligible(User $user, WorkflowRequest $request, WorkflowStep $step): bool
    {
        if ($step->role === 'counterparty') {
            $handler = app(WorkflowRegistry::class)->handler($request->kind);

            return AccountSecurity::available($user) && $handler instanceof ParticipantWorkflow && $handler->participantEligible($user, $request, $step->role);
        }

        return self::eligible($user, $step->role, $request->employee_id);
    }

    public static function eligible(User $user, string $role, ?int $employeeId): bool
    {
        if (! AccountSecurity::available($user)) {
            return false;
        }
        $assignment = $employeeId ? Employee::find($employeeId)?->assignments()->at(today()->toDateString())->first() : null;
        if ($role === 'manager') {
            return $assignment && $user->employee_id && $assignment->manager_employee_id === $user->employee_id && $user->grants()->where('role', 'manager')->where('scope', 'team')->exists();
        }

        return $user->grants()->where('role', $role)->get()->contains(fn ($g) => $g->scope === 'company' || ($assignment && (($g->scope === 'branch' && $g->branch_id === $assignment->branch_id) || ($g->scope === 'department' && $g->department_id === $assignment->department_id))));
    }

    public static function visible(User $user, WorkflowRequest $request): bool
    {
        if (! AccountSecurity::available($user)) {
            return false;
        }
        if ($request->submitted_by === $user->id || ($user->employee_id && $user->employee_id === $request->employee_id)) {
            return true;
        }
        foreach ($request->steps()->get() as $step) {
            if (in_array($user->id, $step->approver_ids, true) && self::stepEligible($user, $request, $step)) {
                return true;
            }
        }
        $hrKinds = ['leave', 'leave_cancel', 'correction', 'overtime', 'swap', 'contract', 'letter', 'career', 'onboarding', 'offboarding'];

        return in_array($request->kind, $hrKinds, true) && $request->employee_id && $user->grants()->whereIn('role', ['hr_admin', 'hr_officer'])->exists() && EmployeeVisibility::query($user, today()->toDateString(), ['hr_admin', 'hr_officer'])->whereKey($request->employee_id)->exists();
    }
}
