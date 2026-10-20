<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountSecurity
{
    public static function available(User $user): bool
    {
        if (DB::table('account_suspensions')->where('user_id', $user->id)->whereNull('revoked_at')->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists()) {
            return false;
        }

        return $user->is_active && (! $user->employee_id || ! $user->employee->periods()->exists() || $user->employee->statusAt(today()->toDateString()) !== 'ENDED');
    }

    public static function changed(User $user, string $action, bool $confirmed = false): void
    {
        DB::transaction(function () use ($user, $action) {
            User::query()->whereKey($user->id)->increment('security_version', 1, ['remember_token' => null]);
            Audit::record($user->id, $action, 'user', $user->id);
        });
        $user->refresh();
        if (request()->hasSession() && request()->user()?->id === $user->id) {
            request()->session()->put('auth.security_version', $user->security_version);
            request()->session()->forget('auth.mfa_user');
            if ($confirmed) {
                request()->session()->put('auth.mfa_user', $user->id);
            }
        }
    }
}
