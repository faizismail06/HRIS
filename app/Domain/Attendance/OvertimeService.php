<?php

namespace App\Domain\Attendance;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\RosterShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OvertimeService
{
    public function actual(User $actor, int $id, int $version, string $key, array $input): array
    {
        $data = Validator::make($input, ['actual_start' => ['required', 'date_format:Y-m-d\TH:i:sP'], 'actual_end' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:actual_start']])->validate();

        return Mutation::run($actor, 'overtime.actual', $key, compact('id', 'version', 'data'), fn ($u) => DB::table('overtime_authorizations')->where('employee_id', $u->employee_id ?? 0)->where('id', $id)->firstOrFail(), function ($u) use ($id, $version, $data) {
            $record = DB::table('overtime_authorizations')->where('id', $id)->lockForUpdate()->firstOrFail();
            CoreWrite::version($record->version, $version);
            abort_unless($record->status === 'approved', 409);
            $shift = RosterShift::findOrFail($record->roster_shift_id);
            abort_if(app(AttendanceService::class)->closed($shift), 409);
            abort_unless(CarbonImmutable::parse($data['actual_start']) >= CarbonImmutable::parse($record->planned_start) && CarbonImmutable::parse($data['actual_end']) <= CarbonImmutable::parse($record->planned_end) && CarbonImmutable::parse($data['actual_end']) <= now(), 422, 'Aktual harus telah terjadi dan berada dalam otorisasi.');
            DB::table('overtime_authorizations')->where('id', $id)->update($data + ['version' => $record->version + 1, 'updated_at' => now()->toIso8601String()]);
            Audit::record($u->id, 'overtime.actual_recorded', 'overtime_authorization', $id, ['version' => $record->version + 1]);

            return ['id' => $id, 'version' => $record->version + 1];
        });
    }

    public function verify(User $actor, int $id, int $version, string $key, ?string $exception): array
    {
        Validator::make(['exception' => $exception], ['exception' => ['nullable', 'string', 'max:1000']])->validate();

        return Mutation::run($actor, 'overtime.verify', $key, compact('id', 'version', 'exception'), function ($u) use ($id) {
            $o = DB::table('overtime_authorizations')->where('id', $id)->firstOrFail();
            abort_if($u->employee_id === $o->employee_id, 403);
            abort_unless(Access::companyPermission($u, 'attendance.close') || WorkflowAccess::eligible($u, 'manager', $o->employee_id), 403);
        }, function ($u) use ($id, $version, $exception) {
            $o = DB::table('overtime_authorizations')->where('id', $id)->lockForUpdate()->firstOrFail();
            CoreWrite::version($o->version, $version);
            abort_unless(in_array($o->status, ['approved', 'verified']) && $o->actual_start && $o->actual_end, 409);
            $shift = RosterShift::findOrFail($o->roster_shift_id);
            $attendance = app(AttendanceService::class);
            abort_if($attendance->closed($shift), 409);
            $result = $attendance->recalculate($shift);
            $start = CarbonImmutable::parse($o->actual_start);
            $end = CarbonImmutable::parse($o->actual_end);
            $supported = $result->effective_in && $result->effective_out && CarbonImmutable::parse($result->effective_in) <= $start && CarbonImmutable::parse($result->effective_out) >= $end && $result->status === 'PRESENT';
            if (! $supported) {
                abort_unless($exception && Access::companyPermission($u, 'attendance.close'), 422, 'Aktual harus didukung attendance atau pengecualian HR beralasan.');
            }
            $seconds = $end->getTimestamp() - $start->getTimestamp();
            foreach ($shift->snapshot['breaks'] ?? [] as $b) {
                if ($shift->starts_at) {
                    $seconds -= max(0, min($end->getTimestamp(), $shift->starts_at->addMinutes($b['end'])->getTimestamp()) - max($start->getTimestamp(), $shift->starts_at->addMinutes($b['start'])->getTimestamp()));
                }
            }
            $minutes = (int) floor(max(0, $seconds) / 60);
            DB::table('overtime_authorizations')->where('id', $id)->update(['status' => 'verified', 'payable_minutes' => $minutes, 'verified_by' => $u->id, 'verified_at' => now()->toIso8601String(), 'attendance_version' => $result->version, 'exception_reason' => $exception ? encrypt($exception) : null, 'version' => $o->version + 1, 'updated_at' => now()->toIso8601String()]);
            Audit::record($u->id, 'overtime.actual_verified', 'overtime_authorization', $id, ['payable_minutes' => $minutes, 'attendance_version' => $result->version, 'exception_provided' => (bool) $exception]);

            return ['id' => $id, 'version' => $o->version + 1, 'payable_minutes' => $minutes];
        });
    }
}
