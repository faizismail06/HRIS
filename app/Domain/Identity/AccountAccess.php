<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountAccess
{
    public function replace(User $actor, User $target, array $grants, bool $active, bool $auditorExports = false): void
    {
        DB::transaction(function () use ($actor, $target, $grants, $active, $auditorExports) {
            DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(AccountSecurity::available($actor) && Access::companyPermission($actor, 'accounts.manage'), 403);
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $this->validate($target, $grants);
            $old = $target->grants()->get(['role', 'scope', 'branch_id', 'department_id'])->toArray();
            $wasAdmin = $target->is_active && collect($old)->contains(fn ($g) => $g['role'] === 'platform_admin' && $g['scope'] === 'company');
            $staysAdmin = $active && collect($grants)->contains(fn ($g) => $g['role'] === 'platform_admin' && $g['scope'] === 'company');
            $otherAdmin = User::query()->where('is_active', true)->whereKeyNot($target->id)->whereHas('grants', fn ($q) => $q->where('role', 'platform_admin')->where('scope', 'company'))->get()->contains(fn ($u) => AccountSecurity::available($u) && ! DB::table('account_suspensions')->where('user_id', $u->id)->whereNull('revoked_at')->exists());
            if ($wasAdmin && ! $staysAdmin && ! $otherAdmin) {
                throw ValidationException::withMessages(['grants' => 'Admin platform aktif terakhir tidak dapat dicabut.']);
            }
            $target->grants()->delete();
            $target->grants()->createMany($grants);
            $auditorExports = $auditorExports && collect($grants)->contains(fn ($g) => $g['role'] === 'auditor');
            $target->forceFill(['is_active' => $active, 'auditor_export_enabled' => $auditorExports, 'security_version' => $target->security_version + 1, 'remember_token' => null])->save();
            Audit::record($actor->id, 'account.access_changed', 'user', $target->id, ['before' => $old, 'after' => $grants, 'active' => $active, 'auditor_export_enabled' => $auditorExports]);
        });
    }

    public function validate(User $user, array $grants): void
    {
        foreach ($grants as $grant) {
            $role = $grant['role'] ?? '';
            $scope = $grant['scope'] ?? '';
            $valid = in_array($role, Access::ROLES) && match ($role) {
                'employee' => $scope === 'self' && $user->employee_id !== null,
                'manager' => $scope === 'team' && $user->employee_id !== null,
                default => in_array($scope, ['company', 'branch', 'department']),
            };
            if ($scope === 'branch') {
                $valid = $valid && DB::table('branches')->where('id', $grant['branch_id'] ?? null)->where('is_active', true)->exists();
            }
            if ($scope === 'department') {
                $valid = $valid && DB::table('departments')->where('id', $grant['department_id'] ?? null)->where('is_active', true)->exists();
            }
            $valid = $valid
                && ($scope === 'branch' || empty($grant['branch_id']))
                && ($scope === 'department' || empty($grant['department_id']));
            if (! $valid || array_diff(array_keys($grant), ['role', 'scope', 'branch_id', 'department_id'])) {
                throw ValidationException::withMessages(['grants' => 'Peran, scope atau target tidak valid.']);
            }
        }
    }
}
