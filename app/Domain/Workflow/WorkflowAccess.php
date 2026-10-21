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
        $handler = app(WorkflowRegistry::class)->handler($request->kind);
        if ($handler instanceof ApproverPolicy && ! $handler->mayApprove($user, $request, $step->role)) {
            return false;
        }
        if ($step->role === 'counterparty') {
            $handler = app(WorkflowRegistry::class)->handler($request->kind);

            return AccountSecurity::available($user) && $handler instanceof ParticipantWorkflow && $handler->participantEligible($user, $request, $step->role);
        }

        return self::eligible($user, $step->role, $request->employee_id) || app(Delegations::class)->decisionId($user, $request, $step) !== null;
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

    public static function eligibleForRequest(User $user, WorkflowRequest $request, string $role): bool
    {
        $handler = app(WorkflowRegistry::class)->handler($request->kind);

        return self::eligible($user, $role, $request->employee_id) && (! $handler instanceof ApproverPolicy || $handler->mayApprove($user, $request, $role));
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
        if (in_array($request->kind, ['compensation', 'opening_payroll', 'bank_change', 'tax_change', 'expense'], true) && self::eligible($user, 'finance_approver', $request->employee_id)) {
            return true;
        }
        $hrKinds = ['leave', 'leave_cancel', 'correction', 'overtime', 'swap', 'contract', 'contract_cancel', 'letter', 'career', 'onboarding', 'offboarding'];

        return in_array($request->kind, $hrKinds, true) && $request->employee_id && $user->grants()->whereIn('role', ['hr_admin', 'hr_officer'])->exists() && EmployeeVisibility::query($user, today()->toDateString(), ['hr_admin', 'hr_officer'])->whereKey($request->employee_id)->exists();
    }

    public static function operators(WorkflowRequest $request): array
    {
        $role = in_array($request->kind, ['compensation', 'opening_payroll', 'bank_change', 'tax_change', 'expense'], true) ? 'finance_approver' : 'hr_admin';

        return User::where('is_active', true)->whereHas('grants', fn ($q) => $q->where('role', $role))->get()->filter(fn ($u) => self::eligible($u, $role, $request->employee_id))->pluck('id')->all();
    }
}
