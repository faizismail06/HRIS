<?php

namespace App\Policies;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\Access;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->grants()->whereIn('role', Access::EMPLOYEE_READERS)->exists();
    }

    public function view(User $user, Employee $employee): Response
    {
        return EmployeeVisibility::query($user, today()->toDateString())->whereKey($employee->id)->exists() ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Employee $employee): bool
    {
        return false;
    }

    public function delete(User $user, Employee $employee): bool
    {
        return false;
    }
}
