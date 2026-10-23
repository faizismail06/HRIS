<?php

namespace Database\Seeders;

use App\Domain\Leave\LeaveService;
use App\Models\LeaveType;
use Illuminate\Support\Str;

class LeaveTypeSeeder extends LocalSeeder
{
    protected array $dependencies = [UserSeeder::class];

    protected function seedLocal(): void
    {
        foreach (['LOCAL-ANNUAL' => ['Contoh - Cuti Tahunan', true, true, false, 2], 'LOCAL-SICK' => ['Contoh - Izin Sakit', true, false, true, 0], 'LOCAL-PERSONAL' => ['Contoh - Izin Pribadi', false, false, false, 0]] as $code => [$name, $paid, $deducts, $evidence, $notice]) {
            if (! LeaveType::where('code', $code)->exists()) {
                app(LeaveService::class)->type($this->hr(), (string) Str::uuid(), ['code' => $code, 'name' => $name.' (testing)', 'is_paid' => $paid, 'deducts_balance' => $deducts, 'allows_half_day' => true, 'requires_evidence' => $evidence, 'notice_days' => $notice, 'minimum_tenure_days' => 0]);
            }
        }
    }
}
