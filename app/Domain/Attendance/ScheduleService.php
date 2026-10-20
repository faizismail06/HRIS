<?php

namespace App\Domain\Attendance;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\RosterShift;
use App\Models\RosterVersion;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WorkLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ScheduleService
{
    public function location(User $actor, string $key, array $input): array
    {
        $data = Validator::make($input, [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)], 'code' => ['required', 'string', 'max:40', 'unique:work_locations,code'], 'name' => ['required', 'string', 'max:160'], 'timezone' => ['required', 'timezone'],
            'latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'], 'radius_m' => ['required', 'integer', 'between:1,10000'], 'max_accuracy_m' => ['required', 'integer', 'between:1,1000'], 'requires_photo' => ['required', 'boolean'],
        ])->validate();

        return Mutation::run($actor, 'location.create', $key, $data, fn ($u) => abort_unless(Access::companyPermission($u, 'schedules.manage'), 403), function ($u) use ($data) {
            $location = WorkLocation::create($data);
            Audit::record($u->id, 'location.created', 'work_location', $location->id, ['branch_id' => $location->branch_id]);

            return ['id' => $location->id, 'version' => 1];
        });
    }

    public function template(User $actor, string $key, array $input): array
    {
        $data = Validator::make($input, [
            'code' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:160'], 'work_location_id' => ['required', 'integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'starts_local' => ['required', 'date_format:H:i'], 'ends_local' => ['required', 'date_format:H:i'], 'end_day_offset' => ['required', 'integer', 'between:0,1'],
            'unpaid_breaks' => ['present', 'array', 'max:8'], 'unpaid_breaks.*' => ['required', 'array:start,end'], 'unpaid_breaks.*.start' => ['required', 'integer', 'min:0'], 'unpaid_breaks.*.end' => ['required', 'integer', 'min:1'],
            'half_day_split_minute' => ['nullable', 'integer', 'min:1'], 'tolerance_minutes' => ['required', 'integer', 'between:0,120'], 'clock_in_before_minutes' => ['required', 'integer', 'between:0,360'], 'clock_out_after_minutes' => ['required', 'integer', 'between:0,720'],
        ])->validate();
        $duration = $this->minutes($data['ends_local']) + 1440 * $data['end_day_offset'] - $this->minutes($data['starts_local']);
        abort_unless($duration > 0 && $duration <= 1440, 422, 'Durasi shift tidak valid.');
        $breaks = $data['unpaid_breaks'];
        usort($breaks, fn ($a, $b) => $a['start'] <=> $b['start']);
        $last = 0;
        foreach ($breaks as $break) {
            abort_unless($break['start'] >= $last && $break['end'] > $break['start'] && $break['end'] <= $duration, 422, 'Istirahat bertumpuk atau di luar shift.');
            $last = $break['end'];
        }
        abort_unless(array_sum(array_map(fn ($b) => $b['end'] - $b['start'], $breaks)) < $duration, 422, 'Shift harus memiliki waktu kerja.');
        abort_if(isset($data['half_day_split_minute']) && $data['half_day_split_minute'] >= $duration, 422);

        return Mutation::run($actor, 'shift-template.create', $key, $data, fn ($u) => abort_unless(Access::companyPermission($u, 'schedules.manage'), 403), function ($u) use ($data, $breaks) {
            $version = (int) ShiftTemplate::where('code', $data['code'])->max('version') + 1;
            ShiftTemplate::where('code', $data['code'])->update(['is_active' => false]);
            $template = ShiftTemplate::create(array_replace($data, ['unpaid_breaks' => $breaks, 'version' => $version, 'created_by' => $u->id]));
            Audit::record($u->id, 'shift_template.version_created', 'shift_template', $template->id, ['version' => $version]);

            return ['id' => $template->id, 'version' => $version];
        });
    }

    public function preview(User $actor, array $input): array
    {
        $this->authorized($actor);
        if (isset($input['pattern'])) {
            $pattern = Validator::make($input['pattern'], ['employee_ids' => ['required', 'array', 'min:1', 'max:200'], 'employee_ids.*' => ['required', 'integer', 'distinct'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'working_days' => ['present', 'array', 'max:7'], 'working_days.*' => ['integer', 'between:1,7', 'distinct'], 'shift_template_id' => ['required', 'integer']])->validate();
            $first = CarbonImmutable::parse($pattern['starts_on']);
            $last = CarbonImmutable::parse($pattern['ends_on']);
            abort_unless($first->diffInDays($last) <= 90, 422);
            $expanded = [];
            foreach ($pattern['employee_ids'] as $employee) {
                for ($day = $first; $day <= $last; $day = $day->addDay()) {
                    $off = ! in_array($day->isoWeekday(), array_map('intval', $pattern['working_days']), true);
                    $expanded[] = ['employee_id' => $employee, 'work_date' => $day->toDateString(), 'is_day_off' => $off, 'shift_template_id' => $off ? null : $pattern['shift_template_id']];
                }
            }
            $input = ['rows' => $expanded];
        }
        $data = Validator::make($input, ['rows' => ['required', 'array', 'min:1', 'max:5000'], 'rows.*' => ['required', 'array:employee_id,work_date,shift_template_id,is_day_off'], 'rows.*.employee_id' => ['required', 'integer', 'exists:employees,id'], 'rows.*.work_date' => ['required', 'date_format:Y-m-d'], 'rows.*.shift_template_id' => ['nullable', 'integer', 'exists:shift_templates,id'], 'rows.*.is_day_off' => ['required', 'boolean']])->validate();
        $this->authorized($actor);
        $rows = [];
        $seen = [];
        foreach ($data['rows'] as $row) {
            $employee = EmployeeVisibility::query($actor, $row['work_date'], $this->editableRoles($actor))->whereKey($row['employee_id'])->firstOrFail();
            abort_unless(in_array($employee->statusAt($row['work_date']), ['ACTIVE', 'OFFBOARDING']), 422, 'Karyawan tidak bekerja pada tanggal roster.');
            $placement = $employee->assignments()->at($row['work_date'])->firstOrFail();
            $unique = $employee->id.'|'.$row['work_date'];
            abort_if(isset($seen[$unique]), 422, 'Tanggal kerja karyawan duplikat.');
            $seen[$unique] = true;
            if ($row['is_day_off']) {
                $rows[] = ['employee_id' => $employee->id, 'work_date' => $row['work_date'], 'is_day_off' => true, 'starts_at' => null, 'ends_at' => null, 'timezone' => config('app.timezone'), 'snapshot' => ['branch_id' => $placement->branch_id, 'department_id' => $placement->department_id]];

                continue;
            }
            $template = ShiftTemplate::where('is_active', true)->findOrFail($row['shift_template_id'] ?? null);
            $location = WorkLocation::where('is_active', true)->findOrFail($template->work_location_id);
            abort_unless($location->branch_id === $placement->branch_id, 422, 'Lokasi shift tidak sesuai cabang penempatan.');
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $row['work_date'].' '.substr($template->starts_local, 0, 5), $location->timezone);
            $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', CarbonImmutable::parse($row['work_date'])->addDays($template->end_day_offset)->format('Y-m-d').' '.substr($template->ends_local, 0, 5), $location->timezone);
            abort_unless($start->format('Y-m-d H:i') === $row['work_date'].' '.substr($template->starts_local, 0, 5) && $end > $start, 422, 'Waktu lokal tidak valid pada zona ini.');
            $snapshot = ['template_id' => $template->id, 'template_version' => $template->version, 'location' => $location->only(['id', 'version', 'branch_id', 'latitude', 'longitude', 'radius_m', 'max_accuracy_m', 'requires_photo']), 'branch_id' => $placement->branch_id, 'department_id' => $placement->department_id, 'job_id' => $placement->job_id, 'breaks' => $template->unpaid_breaks, 'half_day_split_minute' => $template->half_day_split_minute, 'tolerance_minutes' => $template->tolerance_minutes, 'clock_in_before_minutes' => $template->clock_in_before_minutes, 'clock_out_after_minutes' => $template->clock_out_after_minutes];
            $rows[] = ['employee_id' => $employee->id, 'work_date' => $row['work_date'], 'is_day_off' => false, 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'timezone' => $location->timezone, 'snapshot' => $snapshot];
        }
        usort($rows, fn ($a, $b) => [$a['employee_id'], $a['work_date']] <=> [$b['employee_id'], $b['work_date']]);
        $conflicts = [];
        foreach ($rows as $i => $row) {
            if (! $row['is_day_off']) {
                foreach (array_slice($rows, 0, $i) as $other) {
                    if ($other['employee_id'] === $row['employee_id'] && ! $other['is_day_off'] && CarbonImmutable::parse($other['starts_at']) < CarbonImmutable::parse($row['ends_at']) && CarbonImmutable::parse($other['ends_at']) > CarbonImmutable::parse($row['starts_at'])) {
                        $conflicts[] = ['employee_id' => $row['employee_id'], 'work_date' => $row['work_date'], 'reason' => 'overlap_in_batch'];
                    }
                }
            }
            $existing = RosterShift::where('employee_id', $row['employee_id'])->where('status', 'published')->where(function ($q) use ($row) {
                $q->where('work_date', $row['work_date']);
                if (! $row['is_day_off']) {
                    $q->orWhere(fn ($q) => $q->where('starts_at', '<', $row['ends_at'])->where('ends_at', '>', $row['starts_at']));
                }
            })->exists();
            if ($existing) {
                $conflicts[] = ['employee_id' => $row['employee_id'], 'work_date' => $row['work_date'], 'reason' => 'published_roster_exists'];
            }
        }

        return ['rows' => $rows, 'conflicts' => $conflicts, 'checksum' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
    }

    public function draft(User $actor, string $key, array $input): array
    {
        return Mutation::run($actor, 'roster.draft', $key, $input, fn ($u) => $this->authorized($u), function ($u) use ($input) {
            $preview = $this->preview($u, $input);
            $roster = RosterVersion::create(['created_by' => $u->id, 'checksum' => $preview['checksum'], 'version' => 1]);
            foreach ($preview['rows'] as $row) {
                RosterShift::create($row + ['roster_version_id' => $roster->id]);
            }
            Audit::record($u->id, 'roster.drafted', 'roster_version', $roster->id, ['rows' => count($preview['rows']), 'checksum' => $preview['checksum']]);

            return ['id' => $roster->id, 'version' => 1, 'checksum' => $preview['checksum'], 'conflicts' => $preview['conflicts']];
        });
    }

    public function publish(User $actor, int $id, int $version, string $key, string $checksum): array
    {
        return CoreWrite::run(fn () => Mutation::run($actor, 'roster.publish', $key, compact('id', 'version', 'checksum'), fn ($u) => $this->authorized($u), function ($u) use ($id, $version, $checksum) {
            $r = RosterVersion::query()->lockForUpdate()->findOrFail($id);
            CoreWrite::version($r->version, $version);
            abort_unless($r->status === 'draft' && hash_equals($r->checksum, $checksum), 409);
            $shifts = RosterShift::where('roster_version_id', $id)->orderBy('employee_id')->orderBy('work_date')->get();
            foreach ($shifts as $shift) {
                $employee = EmployeeVisibility::query($u, $shift->work_date, $this->editableRoles($u))->whereKey($shift->employee_id)->firstOrFail();
                abort_unless(in_array($employee->statusAt($shift->work_date), ['ACTIVE', 'OFFBOARDING']), 422);
                $placement = $employee->assignments()->at($shift->work_date)->firstOrFail();
                abort_unless($placement->branch_id === $shift->snapshot['branch_id'] && $placement->department_id === $shift->snapshot['department_id'], 409, 'Penempatan berubah sejak draft dibuat.');
                if (! $shift->is_day_off) {
                    abort_unless(WorkLocation::whereKey($shift->snapshot['location']['id'])->where('is_active', true)->exists(), 422);
                }
                $shift->update(['status' => 'published', 'version' => $shift->version + 1]);
            }
            $r->update(['status' => 'published', 'version' => $r->version + 1, 'published_at' => now()]);
            Audit::record($u->id, 'roster.published', 'roster_version', $id, ['checksum' => $checksum]);

            return ['id' => $id, 'status' => 'published', 'version' => $r->version];
        }));
    }

    private function authorized(User $user): void
    {
        abort_unless(Access::companyPermission($user, 'schedules.manage') || $user->grants()->where('role', 'manager')->where('scope', 'team')->exists(), 403);
    }

    private function editableRoles(User $user): array
    {
        return Access::companyPermission($user, 'schedules.manage') ? ['hr_admin', 'hr_officer'] : ['manager'];
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }
}
