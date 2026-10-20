<?php

namespace App\Domain\Identity;

use App\Models\User;

class Access
{
    public const ROLES = ['employee', 'manager', 'hr_officer', 'hr_admin', 'payroll_officer', 'finance_approver', 'it_asset_officer', 'auditor', 'platform_admin'];

    public const EMPLOYEE_READERS = ['employee', 'manager', 'hr_officer', 'hr_admin', 'auditor'];

    public static function companyPermission(User $user, string $permission): bool
    {
        $roles = match ($permission) {
            'accounts.manage' => ['platform_admin'],
            'organization.manage' => ['hr_admin'],
            'employees.manage' => ['hr_admin', 'hr_officer'],
            default => [],
        };

        return $user->is_active && $user->grants()->whereIn('role', $roles)->where('scope', 'company')->exists();
    }
}
