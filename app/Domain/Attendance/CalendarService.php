<?php

namespace App\Domain\Attendance;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CalendarService
{
    public function date(User $actor, string $key, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'schedules.manage'), 403);
        $data = Validator::make($input, ['branch_id' => ['required', 'integer', 'exists:branches,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'label' => ['required', 'string', 'max:160'], 'is_holiday' => ['required', 'boolean']])->validate();

        return Mutation::run($actor, 'calendar.date', $key, $data, fn ($u) => abort_unless(Access::companyPermission($u, 'schedules.manage'), 403), function ($u) use ($data) {
            $version = (int) DB::table('work_calendar_dates')->where('branch_id', $data['branch_id'])->where('work_date', $data['work_date'])->max('version') + 1;
            $id = DB::table('work_calendar_dates')->insertGetId($data + ['version' => $version, 'created_by' => $u->id, 'created_at' => now()->toIso8601String()]);
            Audit::record($u->id, 'work_calendar.date_versioned', 'work_calendar_date', $id, ['version' => $version, 'is_holiday' => $data['is_holiday']]);

            return ['id' => $id, 'version' => $version];
        });
    }

    public function team(User $actor, array $input): array
    {
        $data = Validator::make($input, ['starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on']])->validate();
        abort_unless(CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on'])) <= 31, 422);
        $rows = [];
        foreach (RosterShift::where('status', 'published')->whereBetween('work_date', [$data['starts_on'], $data['ends_on']])->orderBy('work_date')->orderBy('id')->limit(5000)->get() as $shift) {
            if (! EmployeeVisibility::query($actor, $shift->work_date)->whereKey($shift->employee_id)->exists()) {
                continue;
            }
            $leave = DB::table('leave_segments')->where('roster_shift_id', $shift->id)->where('state', 'approved')->exists();
            $rows[] = ['employee_id' => $shift->employee_id, 'employee_name' => Employee::findOrFail($shift->employee_id)->name, 'work_date' => $shift->work_date, 'timezone' => $shift->timezone, 'starts_at' => $shift->starts_at?->toIso8601String(), 'ends_at' => $shift->ends_at?->toIso8601String(), 'availability' => $shift->is_day_off ? 'day_off' : ($leave ? 'unavailable' : 'scheduled')];
        }

        return $rows;
    }
}
