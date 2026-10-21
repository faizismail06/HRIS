<?php

namespace App\Domain\Operations;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\Access;
use App\Domain\Identity\AccountSecurity;
use App\Domain\Lifecycle\LifecycleService;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\RosterShift;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;

class NotificationAccess
{
    public static function visible(User $user, object $event): bool
    {
        if (! AccountSecurity::available($user)) {
            return false;
        }
        if ($event->subject_type === 'workflow_request') {
            $r = WorkflowRequest::find($event->subject_id);

            return $r && WorkflowAccess::visible($user, $r);
        }
        if ($event->subject_type === 'employment_contract') {
            $c = DB::table('employment_contracts')->find($event->subject_id);

            return $c && (WorkflowAccess::eligible($user, 'hr_admin', $c->employee_id) || WorkflowAccess::eligible($user, 'manager', $c->employee_id));
        }
        if ($event->subject_type === 'lifecycle_task') {
            $t = DB::table('lifecycle_tasks')->find($event->subject_id);
            $c = $t ? DB::table('lifecycle_cases')->find($t->lifecycle_case_id) : null;

            return $c && $t->owner_id === $user->id && app(LifecycleService::class)->ownerEligible($user, $t->role, $c->employee_id, $c->last_working_on ?? $c->effective_on);
        }
        if ($event->subject_type === 'payroll_row') {
            $row = DB::table('payroll_rows')->find($event->subject_id);

            return $row && (($user->employee_id === $row->employee_id && $user->grants()->where('role', 'employee')->where('scope', 'self')->exists()) || Access::companyPermission($user, 'payroll.prepare') || Access::companyPermission($user, 'payroll.approve'));
        }
        if ($event->subject_type === 'roster_shift') {
            $shift = RosterShift::find($event->subject_id);

            return $shift && EmployeeVisibility::query($user, $shift->work_date)->whereKey($shift->employee_id)->exists();
        }
        if ($event->subject_type === 'lifecycle_case') {
            $case = DB::table('lifecycle_cases')->find($event->subject_id);

            return $case && EmployeeVisibility::query($user, $case->last_working_on ?? $case->effective_on, ['hr_admin', 'hr_officer'])->whereKey($case->employee_id)->exists();
        }

        return false;
    }
}
