<?php

namespace Database\Seeders;

use App\Domain\CoreHR\OrganizationWriter;
use App\Models\Department;
use App\Models\Job;

class JobSeeder extends LocalSeeder
{
    protected array $dependencies = [DepartmentSeeder::class];

    protected function seedLocal(): void
    {
        foreach (['LOCAL-OPS-MGR' => ['LOCAL-OPS', 'Contoh - Manajer Operasional'], 'LOCAL-OPS-STAFF' => ['LOCAL-OPS', 'Contoh - Staf Operasional'], 'LOCAL-HR-MGR' => ['LOCAL-HR', 'Contoh - Manajer HR'], 'LOCAL-HR-STAFF' => ['LOCAL-HR', 'Contoh - Staf HR']] as $code => [$department, $name]) {
            if (! Job::where('code', $code)->exists()) {
                app(OrganizationWriter::class)->save($this->hr(), 'jobs', null, ['code' => $code, 'name' => $name, 'department_id' => Department::where('code', $department)->firstOrFail()->id, 'is_active' => true]);
            }
        }
    }
}
