<?php

namespace Database\Seeders;

use App\Domain\Leave\LeaveService;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaveBalanceSeeder extends LocalSeeder
{
    protected array $dependencies = [EmployeeSeeder::class, LeaveTypeSeeder::class];

    protected function seedLocal(): void
    {
        foreach (Employee::whereIn('employee_number', array_keys(EmployeeSeeder::PEOPLE))->get() as $employee) {
            $source = 'LOCAL-SAMPLE:LEAVE:'.today()->year.':'.$employee->employee_number;
            if (! DB::table('leave_credit_lots')->where('source_key', $source)->exists()) {
                app(LeaveService::class)->grant($this->hr(), (string) Str::uuid(), ['employee_id' => $employee->id, 'type_code' => 'LOCAL-ANNUAL', 'entitlement_year' => today()->year, 'valid_from' => today()->startOfYear()->toDateString(), 'valid_until' => today()->addYear()->startOfYear()->toDateString(), 'minutes' => 5760, 'source_key' => $source, 'source_type' => 'grant', 'reason' => 'CONTOH TESTING: 12 shift x 480 menit. Bukan kebijakan perusahaan yang disahkan.']);
            }
        }
    }
}
