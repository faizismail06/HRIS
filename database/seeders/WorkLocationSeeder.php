<?php

namespace Database\Seeders;

use App\Domain\Attendance\ScheduleService;
use App\Models\Branch;
use App\Models\WorkLocation;
use Illuminate\Support\Str;

class WorkLocationSeeder extends LocalSeeder
{
    protected array $dependencies = [BranchSeeder::class];

    protected function seedLocal(): void
    {
        foreach (['LOCAL-JKT' => [-6.2, 106.8], 'LOCAL-BDG' => [-6.91, 107.61]] as $code => [$latitude, $longitude]) {
            if (! WorkLocation::where('code', $code)->exists()) {
                app(ScheduleService::class)->location($this->hr(), (string) Str::uuid(), ['branch_id' => Branch::where('code', $code)->firstOrFail()->id, 'code' => $code, 'name' => 'Contoh - Lokasi '.$code, 'timezone' => 'Asia/Jakarta', 'attendance_mode' => 'onsite', 'latitude' => $latitude, 'longitude' => $longitude, 'radius_m' => 100, 'max_accuracy_m' => 100, 'requires_photo' => false]);
            }
        }
    }
}
