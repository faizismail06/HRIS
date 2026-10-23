<?php

namespace Database\Seeders;

use App\Domain\CoreHR\OrganizationWriter;
use App\Models\Department;

class DepartmentSeeder extends LocalSeeder
{
    protected array $dependencies = [UserSeeder::class];

    protected function seedLocal(): void
    {
        foreach (['LOCAL-OPS' => 'Contoh - Operasional', 'LOCAL-HR' => 'Contoh - Human Resources'] as $code => $name) {
            if (! Department::where('code', $code)->exists()) {
                app(OrganizationWriter::class)->save($this->hr(), 'departments', null, ['code' => $code, 'name' => $name, 'parent_id' => null, 'is_active' => true]);
            }
        }
    }
}
