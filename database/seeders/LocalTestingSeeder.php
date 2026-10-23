<?php

namespace Database\Seeders;

class LocalTestingSeeder extends LocalSeeder
{
    protected array $dependencies = [
        CompanySeeder::class, UserSeeder::class, BranchSeeder::class, DepartmentSeeder::class,
        JobSeeder::class, EmployeeSeeder::class, StaffUserSeeder::class, LeaveTypeSeeder::class,
        WorkLocationSeeder::class, ShiftTemplateSeeder::class, RosterSeeder::class,
        LeaveBalanceSeeder::class, WorkflowSeeder::class,
    ];

    protected function seedLocal(): void
    {
        $this->command?->info('Data CONTOH TESTING tersedia. Payroll tetap draft; tidak ada clocking, email atau pembayaran nyata yang dibuat.');
    }
}
