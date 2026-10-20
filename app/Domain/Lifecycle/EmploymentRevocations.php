<?php

namespace App\Domain\Lifecycle;

use App\Domain\Identity\Audit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmploymentRevocations
{
    public function reconcile(): void
    {
        DB::transaction(function () {
            DB::table('companies')->where('id', 1)->lockForUpdate()->first();
            foreach (User::whereNotNull('employee_id')->get() as $user) {
                if ($user->employee->statusAt(today()->toDateString()) !== 'ENDED') {
                    continue;
                }
                $period = $user->employee->periods()->where('ends_on', '<=', today()->toDateString())->orderByDesc('ends_on')->first();
                if (! $period) {
                    continue;
                }
                $created = DB::table('employment_access_revocations')->insertOrIgnore(['employment_period_id' => $period->id, 'user_id' => $user->id, 'revoked_at' => now()]);
                if ($created) {
                    $user->increment('security_version', 1, ['remember_token' => null]);
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                    Audit::record(null, 'employment.access_revoked', 'user', $user->id, ['employment_period_id' => $period->id]);
                }
            }
        });
    }
}
