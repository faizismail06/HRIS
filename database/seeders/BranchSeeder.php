<?php

namespace Database\Seeders;

use App\Domain\CoreHR\OrganizationWriter;
use App\Models\Branch;

class BranchSeeder extends LocalSeeder
{
    protected array $dependencies = [UserSeeder::class];

    protected function seedLocal(): void
    {
        foreach (['LOCAL-JKT' => 'Contoh - Jakarta', 'LOCAL-BDG' => 'Contoh - Bandung'] as $code => $name) {
            if (! Branch::where('code', $code)->exists()) {
                app(OrganizationWriter::class)->save($this->hr(), 'branches', null, ['code' => $code, 'name' => $name, 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
            }
        }
    }
}
