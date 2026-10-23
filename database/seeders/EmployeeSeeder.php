<?php

namespace Database\Seeders;

use App\Domain\CoreHR\EmployeeWriter;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Job;

class EmployeeSeeder extends LocalSeeder
{
    protected array $dependencies = [BranchSeeder::class, JobSeeder::class];

    public const PEOPLE = [
        'LOCAL-MGR-001' => ['Contoh - Manajer Operasional', 'LOCAL-JKT', 'LOCAL-OPS', 'LOCAL-OPS-MGR', null],
        'LOCAL-MGR-002' => ['Contoh - Manajer HR', 'LOCAL-JKT', 'LOCAL-HR', 'LOCAL-HR-MGR', null],
        'LOCAL-EMP-001' => ['Contoh - Staf Operasional A', 'LOCAL-JKT', 'LOCAL-OPS', 'LOCAL-OPS-STAFF', 'LOCAL-MGR-001'],
        'LOCAL-EMP-002' => ['Contoh - Staf Operasional B', 'LOCAL-JKT', 'LOCAL-OPS', 'LOCAL-OPS-STAFF', 'LOCAL-MGR-001'],
        'LOCAL-EMP-003' => ['Contoh - Staf HR', 'LOCAL-JKT', 'LOCAL-HR', 'LOCAL-HR-STAFF', 'LOCAL-MGR-002'],
        'LOCAL-EMP-004' => ['Contoh - Staf Operasional Bandung', 'LOCAL-BDG', 'LOCAL-OPS', 'LOCAL-OPS-STAFF', 'LOCAL-MGR-001'],
    ];

    protected function seedLocal(): void
    {
        foreach (self::PEOPLE as $number => [$name, $branch, $department, $job, $manager]) {
            if (Employee::where('employee_number', $number)->exists()) {
                continue;
            }
            app(EmployeeWriter::class)->create($this->hr(), [
                'employee_number' => $number, 'name' => $name,
                'work_email' => strtolower($number).'@synthetic.invalid',
                'starts_on' => today()->subYear()->startOfYear()->toDateString(),
                'branch_id' => Branch::where('code', $branch)->firstOrFail()->id,
                'department_id' => Department::where('code', $department)->firstOrFail()->id,
                'job_id' => Job::where('code', $job)->firstOrFail()->id,
                'manager_employee_id' => $manager ? Employee::where('employee_number', $manager)->firstOrFail()->id : null,
            ]);
        }
    }
}
