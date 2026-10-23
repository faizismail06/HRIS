<?php

namespace Database\Seeders;

use App\Domain\Attendance\ScheduleService;
use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\ShiftTemplate;
use Illuminate\Support\Str;

class RosterSeeder extends LocalSeeder
{
    protected array $dependencies = [EmployeeSeeder::class, ShiftTemplateSeeder::class];

    protected function seedLocal(): void
    {
        $rows = [];
        foreach (Employee::whereIn('employee_number', array_keys(EmployeeSeeder::PEOPLE))->get() as $employee) {
            for ($day = 0; $day < 14; $day++) {
                $date = today()->addDays($day);
                // Respect all existing drafts/history for this person/date, not only sample shifts.
                if (RosterShift::where('employee_id', $employee->id)->where('work_date', $date->toDateString())->exists()) {
                    continue;
                }
                if (! in_array($employee->statusAt($date->toDateString()), ['ACTIVE', 'OFFBOARDING'])) {
                    continue;
                }
                $placement = $employee->assignments()->at($date->toDateString())->with('branch')->first();
                $code = $placement?->branch?->code;
                if (! in_array($code, ['LOCAL-JKT', 'LOCAL-BDG'])) {
                    continue;
                }
                $rows[] = ['employee_id' => $employee->id, 'work_date' => $date->toDateString(), 'is_day_off' => $date->isWeekend(), 'shift_template_id' => $date->isWeekend() ? null : ShiftTemplate::where('code', $code.'-DAY')->where('is_active', true)->firstOrFail()->id];
            }
        }
        if ($rows) {
            $service = app(ScheduleService::class);
            $actor = $this->hr();
            $draft = $service->draft($actor, (string) Str::uuid(), ['rows' => $rows]);
            // Do not silently acknowledge company staffing warnings.
            $service->publish($actor, $draft['id'], 1, (string) Str::uuid(), $draft['checksum']);
        }
    }
}
