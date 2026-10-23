<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Optional local-only seeders are deliberately excluded from the repository.
        if (app()->environment('local') && ! config('hris.demo_features_enabled') && class_exists(LocalTestingSeeder::class)) {
            $this->call(LocalTestingSeeder::class);
        }
    }
}
