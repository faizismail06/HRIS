<?php

namespace App\Domain\Attendance;

use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\RosterShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StaffingService
{
    public function define(User $actor, string $key, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'employees.review'), 403);
        $d = Validator::make($input, ['code' => ['required', 'regex:/^[A-Z0-9_-]{2,40}$/'], 'work_location_id' => ['required', 'integer', 'exists:work_locations,id'], 'job_id' => ['nullable', 'integer', 'exists:job_positions,id'], 'valid_from' => ['required', 'date_format:Y-m-d'], 'valid_until' => ['required', 'date_format:Y-m-d', 'after:valid_from'], 'working_days' => ['required', 'array', 'min:1', 'max:7'], 'working_days.*' => ['integer', 'between:1,7', 'distinct'], 'start_local' => ['required', 'date_format:H:i'], 'end_local' => ['required', 'date_format:H:i'], 'end_day_offset' => ['required', 'integer', 'between:0,1'], 'minimum_staff' => ['required', 'integer', 'between:1,1000'], 'is_blocking' => ['required', 'boolean']])->validate();
        $minutes = fn ($s) => (int) substr($s, 0, 2) * 60 + (int) substr($s, 3, 2);
        $duration = $minutes($d['end_local']) + 1440 * $d['end_day_offset'] - $minutes($d['start_local']);
        abort_unless($duration > 0 && $duration <= 1440, 422);
        $d['working_days'] = array_map('intval', $d['working_days']);

        return Mutation::run($actor, 'staffing.define', $key, $d, fn ($u) => abort_unless(Access::companyPermission($u, 'employees.review'), 403), function ($u) use ($d) {
            $version = (int) DB::table('staffing_policies')->where('code', $d['code'])->max('version') + 1;
            DB::table('staffing_policies')->where('code', $d['code'])->update(['is_active' => false]);
            $id = DB::table('staffing_policies')->insertGetId(array_replace($d, ['version' => $version, 'working_days' => json_encode($d['working_days']), 'approved_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]));
            Audit::record($u->id, 'staffing.policy_versioned', 'staffing_policy', $id, ['version' => $version]);

            return ['id' => $id, 'version' => $version];
        });
    }

    public function check(array $proposed, array $excluded = []): array
    {
        $dates = array_values(array_unique(array_column($proposed, 'work_date')));
        sort($dates);
        $branches = array_values(array_unique(array_map(fn ($r) => $r['snapshot']['branch_id'], $proposed)));
        $policies = DB::table('staffing_policies')->join('work_locations', 'work_locations.id', '=', 'staffing_policies.work_location_id')->whereIn('work_locations.branch_id', $branches)->where('staffing_policies.is_active', true)->where('valid_from', '<=', max($dates))->where('valid_until', '>', min($dates))->orderBy('staffing_policies.id')->get(['staffing_policies.*', 'work_locations.timezone']);
        $warnings = [];
        $sources = [];
        foreach ($policies as $policy) {
            foreach ($dates as $date) {
                $day = CarbonImmutable::parse($date, $policy->timezone);
                if ($date < $policy->valid_from || $date >= $policy->valid_until || ! in_array($day->isoWeekday(), json_decode($policy->working_days, true), true)) {
                    continue;
                }
                $start = CarbonImmutable::parse($date.' '.$policy->start_local, $policy->timezone)->getTimestamp();
                $end = CarbonImmutable::parse($day->addDays($policy->end_day_offset)->toDateString().' '.$policy->end_local, $policy->timezone)->getTimestamp();
                $published = RosterShift::where('status', 'published')->whereNotIn('id', $excluded)->where('starts_at', '<', CarbonImmutable::createFromTimestamp($end)->toIso8601String())->where('ends_at', '>', CarbonImmutable::createFromTimestamp($start)->toIso8601String())->where('snapshot->location->id', $policy->work_location_id)->orderBy('id')->get();
                $shifts = array_merge($published->toArray(), $proposed);
                $events = [$start => 0, $end => 0];
                foreach ($shifts as $shift) {
                    if ($shift['is_day_off'] || ($shift['snapshot']['location']['id'] ?? null) !== $policy->work_location_id || ($policy->job_id && ($shift['snapshot']['job_id'] ?? null) !== $policy->job_id)) {
                        continue;
                    }
                    $from = CarbonImmutable::parse($shift['starts_at'])->getTimestamp();
                    $until = CarbonImmutable::parse($shift['ends_at'])->getTimestamp();
                    $segments = [[$from, $until]];
                    foreach ($shift['snapshot']['breaks'] ?? [] as $break) {
                        $a = $from + $break['start'] * 60;
                        $b = $from + $break['end'] * 60;
                        $next = [];
                        foreach ($segments as [$x,$y]) {
                            if ($a >= $y || $b <= $x) {
                                $next[] = [$x, $y];
                            } else {
                                if ($x < $a) {
                                    $next[] = [$x, $a];
                                } if ($b < $y) {
                                    $next[] = [$b, $y];
                                }
                            }
                        }
                        $segments = $next;
                    }
                    foreach ($segments as [$a,$b]) {
                        $a = max($start, $a);
                        $b = min($end, $b);
                        if ($a >= $b) {
                            continue;
                        }
                        $events[$a] = ($events[$a] ?? 0) + 1;
                        $events[$b] = ($events[$b] ?? 0) - 1;
                    }
                }
                ksort($events, SORT_NUMERIC);
                $active = 0;
                $minimum = PHP_INT_MAX;
                foreach ($events as $at => $change) {
                    $active += $change;
                    if ($at < $end) {
                        $minimum = min($minimum, $active);
                    }
                }
                $sources[] = ['policy_id' => $policy->id, 'version' => $policy->version, 'work_date' => $date, 'published' => $published->map(fn ($s) => ['id' => $s->id, 'version' => $s->version])->all(), 'minimum_actual' => $minimum];
                if ($minimum < $policy->minimum_staff) {
                    $warnings[] = ['code' => 'minimum_staffing', 'policy_id' => $policy->id, 'work_date' => $date, 'required' => $policy->minimum_staff, 'actual' => $minimum, 'blocking' => $policy->is_blocking];
                }
            }
        }

        return ['warnings' => $warnings, 'sources' => $sources, 'checksum' => hash('sha256', json_encode($sources,JSON_THROW_ON_ERROR))];
    }
}
