<?php

namespace Database\Seeders;

use App\Domain\Identity\AccountAccess;
use App\Domain\Identity\Audit;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Str;

class StaffUserSeeder extends LocalSeeder
{
    protected array $dependencies = [EmployeeSeeder::class];

    protected function seedLocal(): void
    {
        $credentials = [];
        foreach (['manager.sample@hris.test' => ['LOCAL-MGR-001', 'manager', 'team'], 'employee.sample@hris.test' => ['LOCAL-EMP-001', 'employee', 'self'], 'employee2.sample@hris.test' => ['LOCAL-EMP-002', 'employee', 'self']] as $email => [$number, $role, $scope]) {
            if (User::where('email', $email)->exists()) {
                continue;
            }
            $employee = Employee::where('employee_number', $number)->firstOrFail();
            $password = Str::password(20);
            $user = new User;
            $user->forceFill(['name' => $employee->name, 'email' => $email, 'password' => $password, 'employee_id' => $employee->id, 'is_active' => true, 'email_verified_at' => now()]);
            $grant = ['role' => $role, 'scope' => $scope];
            app(AccountAccess::class)->validate($user, [$grant]);
            $user->save();
            $user->grants()->create($grant);
            Audit::record(null, 'account.seeded_local_staff', 'user', $user->id, ['role' => $role, 'scope' => $scope]);
            $credentials[] = [$email, $password];
        }
        if ($credentials && $this->command) {
            $this->command->table(['Email contoh', 'Password awal (simpan sekarang)'], $credentials);
        }
    }
}
