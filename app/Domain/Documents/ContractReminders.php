<?php

namespace App\Domain\Documents;

use App\Domain\Operations\Clock;
use App\Domain\Operations\Outbox;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ContractReminders
{
    public function dispatch(): void
    {
        DB::transaction(function () {
            DB::table('companies')->where('id', 1)->lockForUpdate()->first();
            $today = app(Clock::class)->now()->startOfDay();
            foreach (DB::table('employment_contracts')->where('status', 'approved')->whereNull('amends_id')->where('ends_on', '>', $today->toDateString())->where('ends_on', '<=', $today->addDays(60)->toDateString())->get() as $contract) {
                DB::table('contract_review_tasks')->insertOrIgnore(['employment_contract_id' => $contract->id, 'created_at' => now(), 'updated_at' => now()]);
                if (DB::table('contract_review_tasks')->where('employment_contract_id', $contract->id)->where('status', 'resolved')->exists()) {
                    continue;
                }
                $days = (int) $today->diffInDays(CarbonImmutable::parse($contract->ends_on, $today->timezone));
                $threshold = collect([7, 14, 30, 60])->first(fn ($n) => $days <= $n);
                $users = User::where('is_active', true)->get()->filter(fn ($u) => WorkflowAccess::eligible($u, 'hr_admin', $contract->employee_id) || WorkflowAccess::eligible($u, 'manager', $contract->employee_id))->pluck('id')->all();
                Outbox::notify($users, 'contract.expiry_reminder', 'employment_contract', $contract->id, 'contract:'.$contract->id.':'.$threshold);
            }
        });
    }
}
