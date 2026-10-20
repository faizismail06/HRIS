<?php

namespace App\Domain\Attendance;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CloseService
{
    public function __construct(private AttendanceService $attendance) {}

    public function create(User $actor, string $key, array $input): array
    {
        $data = Validator::make($input, ['branch_id' => ['required', 'integer', 'exists:branches,id'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on']])->validate();
        abort_unless(CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on'])) <= 62, 422, 'Satu periode maksimal62hari.');

        return CoreWrite::run(fn () => Mutation::run($actor, 'attendance-period.create', $key, $data, fn ($u) => $this->authorize($u), function ($u) use ($data) {
            $id = DB::table('attendance_periods')->insertGetId($data + ['created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);
            Audit::record($u->id, 'attendance_period.created', 'attendance_period', $id, $data);

            return ['id' => $id, 'version' => 1];
        }));
    }

    public function review(User $actor, int $id, int $version, string $key): array
    {
        return Mutation::run($actor, 'attendance-period.review', $key, compact('id', 'version'), fn ($u) => $this->authorize($u), function ($u) use ($id, $version) {
            $p = DB::table('attendance_periods')->where('id', $id)->lockForUpdate()->firstOrFail();
            CoreWrite::version($p->version, $version);
            abort_unless(in_array($p->status, ['open', 'review']), 409);
            $review = $this->collect($p);
            DB::table('attendance_periods')->where('id', $id)->update(['status' => 'review', 'review_checksum' => $review['checksum'], 'version' => $p->version + 1, 'updated_at' => now()->toIso8601String()]);
            Audit::record($u->id, 'attendance_period.reviewed', 'attendance_period', $id, ['checksum' => $review['checksum'], 'blockers' => count($review['blockers'])]);

            return ['id' => $id, 'version' => $p->version + 1, 'checksum' => $review['checksum'], 'blockers' => $review['blockers'], 'rows' => count($review['rows'])];
        });
    }

    public function close(User $actor, int $id, int $version, string $key, string $checksum): array
    {
        return Mutation::run($actor, 'attendance-period.close', $key, compact('id', 'version', 'checksum'), fn ($u) => $this->authorize($u), function ($u) use ($id, $version, $checksum) {
            $p = DB::table('attendance_periods')->where('id', $id)->lockForUpdate()->firstOrFail();
            CoreWrite::version($p->version, $version);
            abort_unless($p->status === 'review', 409);
            $review = $this->collect($p);
            abort_unless(hash_equals($review['checksum'], $checksum) && hash_equals($p->review_checksum, $checksum), 409, 'Sumber berubah sejak tinjauan. Tinjau ulang sebelum menutup.');
            abort_unless(empty($review['blockers']), 422, 'Selesaikan penghambat sebelum menutup periode.');
            $revision = $p->revision + 1;
            foreach ($review['rows'] as $row) {
                $json = json_encode($row, JSON_THROW_ON_ERROR);
                DB::table('attendance_snapshots')->insert(['attendance_period_id' => $id, 'revision' => $revision, 'roster_shift_id' => $row['roster_shift_id'], 'snapshot' => $json, 'sha256' => hash('sha256', $json), 'closed_by' => $u->id, 'created_at' => now()->toIso8601String()]);
            }
            DB::table('attendance_periods')->where('id', $id)->update(['status' => 'closed', 'revision' => $revision, 'version' => $p->version + 1, 'updated_at' => now()->toIso8601String()]);
            Audit::record($u->id, 'attendance_period.closed', 'attendance_period', $id, ['revision' => $revision, 'checksum' => $checksum]);

            return ['id' => $id, 'status' => 'closed', 'revision' => $revision, 'version' => $p->version + 1];
        });
    }

    public function reopen(User $actor, int $id, int $version, string $key, string $reason): array
    {
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:1000']])->validate();

        return Mutation::run($actor, 'attendance-period.reopen', $key, compact('id', 'version', 'reason'), fn ($u) => $this->authorize($u), function ($u) use ($id, $version) {
            $p = DB::table('attendance_periods')->where('id', $id)->lockForUpdate()->firstOrFail();
            CoreWrite::version($p->version, $version);
            abort_unless($p->status === 'closed' && ! $p->payroll_finalized, 409, 'Payroll final memerlukan penyesuaian terpisah.');
            DB::table('attendance_periods')->where('id', $id)->update(['status' => 'open', 'review_checksum' => null, 'version' => $p->version + 1, 'updated_at' => now()->toIso8601String()]);
            Audit::record($u->id, 'attendance_period.reopened', 'attendance_period', $id, ['prior_revision' => $p->revision, 'reason_provided' => true]);

            return ['id' => $id, 'status' => 'open', 'version' => $p->version + 1];
        });
    }

    private function collect(object $period): array
    {
        $blockers = [];
        $rows = [];
        $start = CarbonImmutable::parse($period->starts_on);
        $end = CarbonImmutable::parse($period->ends_on);
        if ($end > today()) {
            $blockers[] = ['code' => 'period_not_ended'];
        }
        $shifts = RosterShift::where('status', 'published')->where('work_date', '>=', $period->starts_on)->where('work_date', '<', $period->ends_on)->orderBy('id')->get()->filter(fn ($s) => $s->snapshot['branch_id'] === $period->branch_id);
        $indexed = [];
        foreach ($shifts as $shift) {
            $indexed[$shift->employee_id.'|'.$shift->work_date] = true;
            $result = $this->attendance->recalculate($shift);
            if (in_array($result->status, ['INCOMPLETE', 'IN_PROGRESS', 'SCHEDULED', 'LEAVE_CONFLICT'])) {
                $blockers[] = ['code' => 'attendance_unresolved', 'roster_shift_id' => $shift->id, 'status' => $result->status];
            }
            if (DB::table('leave_segments')->where('roster_shift_id', $shift->id)->where('state', 'reserved')->exists()) {
                $blockers[] = ['code' => 'leave_pending', 'roster_shift_id' => $shift->id];
            }
            if (DB::table('attendance_corrections')->join('workflow_requests', 'workflow_requests.id', '=', 'attendance_corrections.workflow_request_id')->where('roster_shift_id', $shift->id)->whereIn('workflow_requests.status', ['in_review', 'blocked'])->exists()) {
                $blockers[] = ['code' => 'correction_pending', 'roster_shift_id' => $shift->id];
            }
            $overtime = DB::table('overtime_authorizations')->where('roster_shift_id', $shift->id)->whereIn('status', ['pending', 'approved', 'verified'])->orderBy('id')->get();
            foreach ($overtime as $o) {
                if ($o->status !== 'verified' || $o->attendance_version !== $result->version) {
                    $blockers[] = ['code' => 'overtime_unverified_or_stale', 'overtime_id' => $o->id];
                }
            }
            $employee = Employee::findOrFail($shift->employee_id);
            $rows[] = ['roster_shift_id' => $shift->id, 'employee_id' => $shift->employee_id, 'employee_number' => $employee->employee_number, 'employee_name' => $employee->name, 'employee_version' => $employee->version, 'work_date' => $shift->work_date, 'timezone' => $shift->timezone, 'shift_snapshot' => $shift->snapshot, 'result' => json_decode(json_encode($result), true), 'overtime' => $overtime->map(fn ($o) => ['id' => $o->id, 'version' => $o->version, 'payable_minutes' => $o->payable_minutes, 'verified_by' => $o->verified_by, 'attendance_version' => $o->attendance_version])->all()];
        }
        $employees = Employee::whereHas('assignments', fn ($q) => $q->where('branch_id', $period->branch_id)->where('valid_from', '<', $period->ends_on)->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $period->starts_on)))->get();
        foreach ($employees as $employee) {
            for ($date = $start; $date < $end; $date = $date->addDay()) {
                $day = $date->toDateString();
                $assigned = $employee->assignments()->at($day)->where('branch_id', $period->branch_id)->exists();
                if ($assigned && ! isset($indexed[$employee->id.'|'.$day])) {
                    $blockers[] = ['code' => 'schedule_gap', 'employee_id' => $employee->id, 'work_date' => $day];
                }
            }
        }

        return ['rows' => $rows, 'blockers' => $blockers, 'checksum' => hash('sha256', json_encode(['rows' => $rows, 'blockers' => $blockers], JSON_THROW_ON_ERROR))];
    }

    private function authorize(User $u): void
    {
        abort_unless(Access::companyPermission($u, 'attendance.close'), 403);
    }
}
