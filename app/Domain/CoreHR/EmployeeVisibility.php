<?php

namespace App\Domain\CoreHR;

use App\Domain\Identity\Access;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EmployeeVisibility
{
    public static function query(User $user, string $date, array $roles = Access::EMPLOYEE_READERS): Builder
    {
        $grants = $user->grants()->whereIn('role', array_intersect($roles, Access::EMPLOYEE_READERS))->get();

        return Employee::query()->where(function (Builder $query) use ($user, $date, $grants) {
            $query->whereRaw('1=0');
            if (! $user->is_active) {
                return;
            }
            foreach ($grants as $grant) {
                if ($grant->role === 'employee' && $grant->scope === 'self' && $user->employee_id) {
                    $query->orWhereKey($user->employee_id);
                } elseif ($grant->role === 'manager' && $grant->scope === 'team' && $user->employee_id) {
                    $query->orWhereHas('assignments', fn ($q) => $q->at($date)->where('manager_employee_id', $user->employee_id));
                } elseif (in_array($grant->role, ['hr_officer', 'hr_admin', 'auditor'])) {
                    if ($grant->scope === 'company') {
                        $query->orWhereRaw('1=1');
                    } elseif ($grant->scope === 'branch' && $grant->branch_id) {
                        $query->orWhereHas('assignments', fn ($q) => $q->at($date)->where('branch_id', $grant->branch_id));
                    } elseif ($grant->scope === 'department' && $grant->department_id) {
                        $query->orWhereHas('assignments', fn ($q) => $q->at($date)->where('department_id', $grant->department_id));
                    }
                }
            }
        });
    }
}
