<?php

namespace Database\Seeders;

use App\Domain\Attendance\ScheduleService;
use App\Models\ShiftTemplate;
use App\Models\WorkLocation;
use Illuminate\Support\Str;

class ShiftTemplateSeeder extends LocalSeeder
{
    protected array $dependencies = [WorkLocationSeeder::class];

    protected function seedLocal(): void
    {
        foreach (['LOCAL-JKT', 'LOCAL-BDG'] as $location) {
            $code = $location.'-DAY';
            if (! ShiftTemplate::where('code', $code)->exists()) {
                app(ScheduleService::class)->template($this->hr(), (string) Str::uuid(), ['code' => $code, 'name' => 'Contoh - Shift 08.00–17.00 '.$location, 'work_location_id' => WorkLocation::where('code', $location)->firstOrFail()->id, 'starts_local' => '08:00', 'ends_local' => '17:00', 'end_day_offset' => 0, 'unpaid_breaks' => [['start' => 240, 'end' => 300]], 'half_day_split_minute' => 240, 'tolerance_minutes' => 0, 'clock_in_before_minutes' => 120, 'clock_out_after_minutes' => 240]);
            }
        }
    }
}
