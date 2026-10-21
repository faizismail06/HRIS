<?php

namespace App\Domain\Attendance;

use App\Domain\Demo\DemoBoundary;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Clock;
use App\Domain\Operations\Mutation;
use App\Models\RosterShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceService
{
    public function __construct(private Clock $clock) {}

    public function clock(User $actor, string $direction, string $key, array $input): array
    {
        $data = Validator::make($input, [
            'roster_shift_id' => ['required', 'integer', 'exists:roster_shifts,id'], 'latitude' => ['nullable', 'required_with:longitude,accuracy_m', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'required_with:latitude,accuracy_m', 'numeric', 'between:-180,180'], 'accuracy_m' => ['nullable', 'required_with:latitude,longitude', 'numeric', 'min:0'],
            'occurred_at' => ['prohibited'], 'employee_id' => ['prohibited'], 'simulation' => ['prohibited'], 'scenario_id' => ['prohibited'],
        ])->validate();
        abort_unless(in_array($direction, ['in', 'out']), 422);

        return Mutation::run($actor, 'attendance.clock.'.$direction, $key, $data, function ($u) use ($data) {
            abort_unless($u->employee_id, 403);
            RosterShift::where('employee_id', $u->employee_id)->whereKey($data['roster_shift_id'])->firstOrFail();
        }, function ($u) use ($data, $direction) {
            $shift = RosterShift::query()->lockForUpdate()->findOrFail($data['roster_shift_id']);
            $now = $this->clock->now();
            $snapshot = $shift->snapshot;
            $reject = function (string $code, string $message) use ($u, $shift, $direction) {
                DB::table('attendance_attempts')->insert(['actor_id' => $u->id, 'roster_shift_id' => $shift->id, 'direction' => $direction, 'rejection_code' => $code, 'created_at' => $this->clock->now()->toIso8601String()]);

                return ['rejected' => true, 'code' => $code, 'message' => $message];
            };
            if ($shift->status !== 'published' || $shift->is_day_off) {
                return $reject('not_scheduled', 'Tidak ada shift kerja yang berlaku. Ajukan koreksi jika jadwal perlu diperbaiki.');
            }
            if ($this->closed($shift)) {
                return $reject('period_closed', 'Periode sudah ditutup. Gunakan pengajuan penyesuaian.');
            }
            if ($direction === 'in' && ($now < $shift->starts_at->subMinutes($snapshot['clock_in_before_minutes']) || $now > $shift->ends_at)) {
                return $reject('outside_clock_window', 'Di luar jendela masuk. Gunakan pengajuan koreksi.');
            }
            if ($direction === 'out' && ($now < $shift->starts_at || $now > $shift->ends_at->addMinutes($snapshot['clock_out_after_minutes']))) {
                return $reject('outside_clock_window', 'Di luar jendela pulang. Gunakan pengajuan koreksi.');
            }
            $existing = DB::table('attendance_events')->where('roster_shift_id', $shift->id)->get()->keyBy('direction');
            if (isset($existing[$direction])) {
                return $reject('already_recorded', 'Waktu ini sudah tercatat. Muat ulang ringkasan kehadiran.');
            }
            if ($direction === 'out' && ! isset($existing['in'])) {
                return $reject('missing_arrival', 'Belum ada waktu masuk. Ajukan koreksi.');
            }
            if ($direction === 'out' && $now <= CarbonImmutable::parse($existing['in']->occurred_at)) {
                return $reject('invalid_time_order', 'Waktu pulang harus sesudah waktu masuk.');
            }
            $location = $snapshot['location'];
            $mode = $location['attendance_mode'] ?? 'onsite';
            if ($mode !== 'remote' && ! isset($data['latitude'], $data['longitude'], $data['accuracy_m'])) {
                return $reject('location_required', 'Kebijakan shift ini memerlukan lokasi perangkat.');
            }
            if ($mode !== 'remote' && (float) $data['accuracy_m'] > $location['max_accuracy_m']) {
                return $reject('poor_accuracy', 'Akurasi lokasi belum memadai. Coba kembali di area terbuka atau ajukan koreksi.');
            }
            $distance = $mode !== 'remote' ? $this->distance((float) $data['latitude'], (float) $data['longitude'], (float) $location['latitude'], (float) $location['longitude']) : null;
            if ($mode === 'onsite' && $distance > $location['radius_m']) {
                return $reject('outside_geofence', 'Lokasi berada di luar radius yang disetujui. Ajukan koreksi atau minta peninjauan lokasi kerja.');
            }
            // Photo-required locations fail closed until evidence has been uploaded and validated for this shift.
            $photo = $this->photo($u, $shift);
            if ($location['requires_photo'] && ! $photo) {
                return $reject('photo_required', 'Unggah foto bukti yang valid untuk shift ini sebelum mencatat waktu.');
            }
            $provenance = match ($mode) {
                'remote' => 'REMOTE_SELF_REPORTED', 'field' => 'FIELD_DEVICE_REPORTED', default => 'DEVICE_REPORTED'
            };
            if (DemoBoundary::enabled()) {
                DemoBoundary::database();
                $provenance = 'SIMULATED';
            }
            $coordinates = $mode === 'remote' ? [] : ['latitude' => (float) $data['latitude'], 'longitude' => (float) $data['longitude'], 'accuracy_m' => (float) $data['accuracy_m'], 'distance_m' => round($distance, 2)];
            $id = DB::table('attendance_events')->insertGetId(['roster_shift_id' => $shift->id, 'employee_id' => $u->employee_id, 'actor_id' => $u->id, 'direction' => $direction, 'occurred_at' => $now->toIso8601String(), 'received_at' => now()->toIso8601String(), 'coordinates' => encrypt(json_encode($coordinates, JSON_THROW_ON_ERROR)), 'private_attachment_id' => $photo?->id, 'provenance' => $provenance, 'scenario_id' => DemoBoundary::enabled() ? config('hris.demo_scenario_id') : null, 'created_at' => now()->toIso8601String()]);
            $result = $this->recalculate($shift);
            Audit::record($u->id, 'attendance.clock_'.$direction, 'attendance_event', $id, ['roster_shift_id' => $shift->id, 'provenance' => $provenance]);

            return ['id' => $id, 'roster_shift_id' => $shift->id, 'work_date' => $shift->work_date, 'status' => $result->status, 'version' => $result->version];
        });
    }

    public function recalculate(RosterShift $shift): object
    {
        return DB::transaction(function () use ($shift) {
            DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();

            return $this->project($shift->fresh());
        });
    }

    private function project(RosterShift $shift): object
    {
        $current = DB::table('attendance_results')->where('roster_shift_id', $shift->id)->first();
        if ($current && $this->closed($shift)) {
            return $current;
        }
        $events = DB::table('attendance_events')->where('roster_shift_id', $shift->id)->get()->keyBy('direction');
        $correction = DB::table('attendance_corrections')->join('workflow_requests', 'workflow_requests.id', '=', 'attendance_corrections.workflow_request_id')->where('roster_shift_id', $shift->id)->where('workflow_requests.status', 'approved')->orderByDesc('attendance_corrections.id')->select('attendance_corrections.*')->first();
        $in = $correction ? CarbonImmutable::parse($correction->effective_in) : (isset($events['in']) ? CarbonImmutable::parse($events['in']->occurred_at) : null);
        $out = $correction ? CarbonImmutable::parse($correction->effective_out) : (isset($events['out']) ? CarbonImmutable::parse($events['out']->occurred_at) : null);
        $minutes = 0;
        $late = 0;
        $early = 0;
        $now = $this->clock->now();
        $leaves = DB::table('leave_segments')->where('roster_shift_id', $shift->id)->where('state', 'approved')->get();
        $required = $shift->is_day_off ? [] : [[$shift->starts_at->getTimestamp(), $shift->ends_at->getTimestamp()]];
        $exclusions = [];
        if (! $shift->is_day_off) {
            foreach ($shift->snapshot['breaks'] as $break) {
                $exclusions[] = [$shift->starts_at->addMinutes($break['start'])->getTimestamp(), $shift->starts_at->addMinutes($break['end'])->getTimestamp()];
            }
            foreach ($leaves as $leave) {
                $exclusions[] = [CarbonImmutable::parse($leave->starts_at)->getTimestamp(), CarbonImmutable::parse($leave->ends_at)->getTimestamp()];
            }
        }
        foreach ($exclusions as [$left,$right]) {
            $remaining = [];
            foreach ($required as [$start,$end]) {
                if ($right <= $start || $left >= $end) {
                    $remaining[] = [$start, $end];

                    continue;
                } if ($left > $start) {
                    $remaining[] = [$start, min($left, $end)];
                } if ($right < $end) {
                    $remaining[] = [max($right, $start), $end];
                }
            }
            $required = $remaining;
        }
        $expectedStart = $required ? $required[0][0] : null;
        $expectedEnd = $required ? $required[count($required) - 1][1] : null;
        if ($shift->is_day_off) {
            $status = 'DAY_OFF';
        } elseif ($in && $out) {
            $status = 'PRESENT';
            $seconds = max(0, $out->getTimestamp() - $in->getTimestamp());
            foreach ($shift->snapshot['breaks'] as $break) {
                $start = $shift->starts_at->addMinutes($break['start']);
                $end = $shift->starts_at->addMinutes($break['end']);
                $seconds -= max(0, min($out->getTimestamp(), $end->getTimestamp()) - max($in->getTimestamp(), $start->getTimestamp()));
            }
            $minutes = (int) floor(max(0, $seconds) / 60);
            if ($expectedStart && $in->getTimestamp() > $expectedStart + 60 * $shift->snapshot['tolerance_minutes']) {
                $late = (int) ceil(($in->getTimestamp() - $expectedStart) / 60);
            }
            $early = $expectedEnd ? (int) ceil(max(0, $expectedEnd - $out->getTimestamp()) / 60) : 0;
        } elseif ($in) {
            $status = $now > $shift->ends_at->addMinutes($shift->snapshot['clock_out_after_minutes']) ? 'INCOMPLETE' : 'IN_PROGRESS';
        } else {
            $status = $now > $shift->ends_at->addMinutes($shift->snapshot['clock_out_after_minutes']) ? 'ABSENT' : 'SCHEDULED';
        }
        if (! $shift->is_day_off && ! $required && ! $in && ! $out && $leaves->isNotEmpty()) {
            $status = 'ON_LEAVE';
        }
        foreach ($leaves as $leave) {
            if ($in && ($out?->getTimestamp() ?? $now->getTimestamp()) > CarbonImmutable::parse($leave->starts_at)->getTimestamp() && $in->getTimestamp() < CarbonImmutable::parse($leave->ends_at)->getTimestamp()) {
                $status = 'LEAVE_CONFLICT';
            }
        }
        $values = ['status' => $status, 'work_minutes' => $minutes, 'late_minutes' => $late, 'early_minutes' => $early, 'effective_in' => $in?->toIso8601String(), 'effective_out' => $out?->toIso8601String(), 'sources' => json_encode(['event_ids' => $events->pluck('id')->values()->all(), 'leave_segment_ids' => $leaves->pluck('id')->values()->all(), 'correction_id' => $correction?->id, 'template_version' => $shift->snapshot['template_version'] ?? null, 'roster_version_id' => $shift->roster_version_id], JSON_THROW_ON_ERROR)];
        $changed = ! $current || collect($values)->contains(function ($value, $key) use ($current) {
            $previous = $current->{$key} ?? null;
            if ($key === 'sources') {
                return json_decode($value, true) !== json_decode($previous ?? '{}', true) && json_decode($value, true) != json_decode($previous ?? '{}', true);
            }
            if (in_array($key, ['effective_in', 'effective_out']) && $value && $previous) {
                return CarbonImmutable::parse($value)->getTimestamp() !== CarbonImmutable::parse($previous)->getTimestamp();
            }

            return (string) $value !== (string) $previous;
        });
        if ($changed) {
            DB::table('attendance_results')->updateOrInsert(['roster_shift_id' => $shift->id], $values + ['version' => $current ? $current->version + 1 : 1, 'created_at' => $current?->created_at ?? $now->toIso8601String(), 'updated_at' => $now->toIso8601String()]);
        }

        return DB::table('attendance_results')->where('roster_shift_id', $shift->id)->firstOrFail();
    }

    public function closed(RosterShift $shift): ?object
    {
        return DB::table('attendance_periods')->where('branch_id', $shift->snapshot['branch_id'])->where('starts_on', '<=', $shift->work_date)->where('ends_on', '>', $shift->work_date)->where('status', 'closed')->first();
    }

    private function photo(User $u, RosterShift $shift): ?object
    {
        return DB::table('private_attachments')->where('uploaded_by', $u->id)->where('subject_type', 'roster_shift')->where('subject_id', $shift->id)->where('mime', 'like', 'image/%')->where('created_at', '>=', $this->clock->now()->subMinutes(15)->toIso8601String())->latest('id')->first();
    }

    private function distance(float $lat, float $lon, float $centerLat, float $centerLon): float
    {
        $a = sin(deg2rad($lat - $centerLat) / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($centerLat)) * sin(deg2rad($lon - $centerLon) / 2) ** 2;

        return 6371000 * 2 * asin(min(1, sqrt($a)));
    }
}
