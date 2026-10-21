<?php

namespace App\Domain\Leave;

use App\Domain\Attendance\AttendanceService;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\RosterShift;
use App\Models\User;
use App\Models\WorkflowRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LeaveHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['manager', 'hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless($actor->employee_id, 403);

        return $this->prepareEmployee($actor->employee_id, $input);
    }

    protected function prepareEmployee(int $employeeId, array $input, bool $noticeException = false): array
    {
        $data = Validator::make($input, ['leave_type_id' => ['required', 'integer', 'exists:leave_types,id'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'segment' => ['required', Rule::in(['full', 'first_half', 'second_half'])], 'reason' => ['required', 'string', 'max:1000']])->validate();
        $type = LeaveType::where('is_active', true)->findOrFail($data['leave_type_id']);
        $person = Employee::findOrFail($employeeId);
        abort_unless(CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on'])) <= 90, 422);
        abort_unless($data['starts_on'] >= ($noticeException ? today()->subDays(7) : today()->addDays($type->notice_days))->toDateString(), 422, 'Tanggal cuti tidak memenuhi batas pemberitahuan atau jendela pengecualian HR.');
        $period = $person->periods()->where('activation_state', 'active')->where('starts_on', '<=', $data['starts_on'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $data['ends_on']))->first();
        abort_unless($period && CarbonImmutable::parse($period->starts_on)->diffInDays(CarbonImmutable::parse($data['starts_on'])) >= $type->minimum_tenure_days, 422, 'Masa kerja tidak memenuhi kebijakan.');
        abort_unless($data['segment'] === 'full' || ($type->allows_half_day && $data['starts_on'] === $data['ends_on']), 422);
        $segments = $this->segments($person->id, $data, $type);
        abort_unless(count($segments) > 0, 422, 'Rentang tidak memiliki waktu kerja terjadwal; tidak memerlukan cuti.');

        return ['employee_id' => $person->id, 'payload' => $data, 'summary' => ['starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'minutes' => array_sum(array_column($segments, 'minutes')), 'shift_ids' => array_column($segments, 'roster_shift_id')]];
    }

    public function submitted(WorkflowRequest $r): void
    {
        $type = LeaveType::where('is_active', true)->findOrFail($r->payload['leave_type_id']);
        if ($type->requires_evidence) {
            abort_unless(DB::table('private_attachments')->where('subject_type', 'workflow_request')->where('subject_id', $r->id)->where('classification', 'medical')->exists(), 422, 'Bukti wajib diunggah sebelum pengajuan dikirim.');
        }
        foreach ($this->segments($r->employee_id, $r->payload, $type) as $segment) {
            $overlap = DB::table('leave_segments')->where('employee_id', $r->employee_id)->whereIn('state', ['reserved', 'approved'])->where('starts_at', '<', $segment['ends_at'])->where('ends_at', '>', $segment['starts_at'])->exists();
            abort_if($overlap, 422, 'Rentang cuti bertumpuk.');
            $id = DB::table('leave_segments')->insertGetId($segment + ['employee_id' => $r->employee_id, 'workflow_request_id' => $r->id, 'state' => 'reserved', 'created_at' => now()->toIso8601String()]);
            if (! $type->deducts_balance) {
                continue;
            }
            $remaining = $segment['minutes'];
            $year = (int) substr($segment['work_date'], 0, 4);
            $lots = DB::table('leave_credit_lots')->where('employee_id', $r->employee_id)->where('type_code', $type->code)->where('entitlement_year', $year)->where('valid_from', '<=', $segment['work_date'])->where('valid_until', '>', $segment['work_date'])->orderBy('valid_until')->orderBy('id')->lockForUpdate()->get();
            foreach ($lots as $lot) {
                $allocated = (int) DB::table('leave_allocations')->where('leave_credit_lot_id', $lot->id)->whereIn('state', ['reserved', 'used'])->sum('minutes');
                $take = min($remaining, max(0, LotAccounting::capacity($lot) - $allocated));
                if (! $take) {
                    continue;
                }
                $allocation = DB::table('leave_allocations')->insertGetId(['leave_segment_id' => $id, 'leave_credit_lot_id' => $lot->id, 'minutes' => $take, 'state' => 'reserved', 'created_at' => now()->toIso8601String()]);
                $this->ledger($r, $lot->id, $allocation, 'reservation', -$take);
                $remaining -= $take;
                if (! $remaining) {
                    break;
                }
            }
            abort_unless($remaining === 0, 422, 'Saldo cuti pada periode hak terkait tidak mencukupi.');
        }
    }

    public function approved(WorkflowRequest $r): void
    {
        $person = Employee::findOrFail($r->employee_id);
        abort_unless($person->periods()->where('activation_state', 'active')->where('starts_on', '<=', $r->payload['starts_on'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $r->payload['ends_on']))->exists(), 422, 'Periode kerja berubah dan tidak lagi mencakup cuti.');
        $adjustments = [];
        $segments = DB::table('leave_segments')->where('workflow_request_id', $r->id)->where('state', 'reserved')->get();
        abort_unless($segments->isNotEmpty(), 409);
        foreach ($segments as $segment) {
            $shift = RosterShift::where('status', 'published')->findOrFail($segment->roster_shift_id);
            $events = DB::table('attendance_events')->where('roster_shift_id', $shift->id)->get()->keyBy('direction');
            if (isset($events['in'])) {
                $in = CarbonImmutable::parse($events['in']->occurred_at)->getTimestamp();
                $out = isset($events['out']) ? CarbonImmutable::parse($events['out']->occurred_at)->getTimestamp() : PHP_INT_MAX;
                abort_if($out > CarbonImmutable::parse($segment->starts_at)->getTimestamp() && $in < CarbonImmutable::parse($segment->ends_at)->getTimestamp(), 422, 'Ada bukti kehadiran pada rentang cuti. HR harus menyelesaikan konflik.');
            }
            foreach (DB::table('leave_allocations')->where('leave_segment_id', $segment->id)->where('state', 'reserved')->get() as $allocation) {
                $lot = DB::table('leave_credit_lots')->find($allocation->leave_credit_lot_id);
                abort_unless($lot->valid_from <= $segment->work_date && $lot->valid_until > $segment->work_date, 422);
                DB::table('leave_allocations')->where('id', $allocation->id)->update(['state' => 'used']);
                $this->ledger($r, $lot->id, $allocation->id, 'release', $allocation->minutes);
                $this->ledger($r, $lot->id, $allocation->id, 'usage', -$allocation->minutes);
            }
            DB::table('leave_segments')->where('id', $segment->id)->update(['state' => 'approved']);
            if ($closed = app(AttendanceService::class)->closed($shift)) {
                $adjustments[$closed->id][] = $segment->id;
            }
            app(AttendanceService::class)->recalculate($shift);
        }
        foreach ($adjustments as $period => $ids) {
            DB::table('attendance_adjustments')->insert(['workflow_request_id' => $r->id, 'attendance_period_id' => $period, 'employee_id' => $r->employee_id, 'payload' => json_encode(['type' => 'leave_approved', 'segment_ids' => $ids], JSON_THROW_ON_ERROR), 'created_at' => now()->toIso8601String()]);
        }
    }

    public function released(WorkflowRequest $r, ?int $actorId = null): void
    {
        foreach (DB::table('leave_segments')->where('workflow_request_id', $r->id)->where('state', 'reserved')->get() as $segment) {
            foreach (DB::table('leave_allocations')->where('leave_segment_id', $segment->id)->where('state', 'reserved')->get() as $a) {
                DB::table('leave_allocations')->where('id', $a->id)->update(['state' => 'released']);
                $this->ledger($r, $a->leave_credit_lot_id, $a->id, 'release', $a->minutes, $actorId);
            }
            DB::table('leave_segments')->where('id', $segment->id)->update(['state' => 'released']);
        }
    }

    private function segments(int $employee, array $data, LeaveType $type): array
    {
        $shifts = RosterShift::where('employee_id', $employee)->where('status', 'published')->whereBetween('work_date', [$data['starts_on'], $data['ends_on']])->orderBy('work_date')->get();
        $expected = (int) CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on'])) + 1;
        abort_unless($shifts->count() === $expected, 422, 'Roster seluruh tanggal harus tersedia, termasuk hari libur.');
        $result = [];
        foreach ($shifts as $shift) {
            if ($shift->is_day_off) {
                continue;
            }
            $start = $shift->starts_at;
            $end = $shift->ends_at;
            if ($data['segment'] !== 'full') {
                $split = $shift->snapshot['half_day_split_minute'] ?? null;
                abort_unless($split, 422, 'Segmen setengah hari belum dikonfigurasi pada shift.');
                if ($data['segment'] === 'first_half') {
                    $end = $shift->starts_at->addMinutes($split);
                } else {
                    $start = $shift->starts_at->addMinutes($split);
                }
            }
            $seconds = $end->getTimestamp() - $start->getTimestamp();
            foreach ($shift->snapshot['breaks'] as $b) {
                $seconds -= max(0, min($end->getTimestamp(), $shift->starts_at->addMinutes($b['end'])->getTimestamp()) - max($start->getTimestamp(), $shift->starts_at->addMinutes($b['start'])->getTimestamp()));
            }
            $minutes = (int) floor($seconds / 60);
            if ($minutes > 0) {
                $result[] = ['roster_shift_id' => $shift->id, 'work_date' => $shift->work_date, 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'minutes' => $minutes];
            }
        }

        return $result;
    }

    public function ledger(WorkflowRequest $r, int $lot, int $allocation, string $kind, int $minutes, ?int $actorId = null): void
    {
        DB::table('leave_ledger')->insert(['employee_id' => $r->employee_id, 'leave_credit_lot_id' => $lot, 'leave_allocation_id' => $allocation, 'kind' => $kind, 'minutes' => $minutes, 'business_key' => $kind.':'.$allocation, 'actor_id' => $actorId ?? $r->effectActorId(), 'created_at' => now()->toIso8601String()]);
    }
}
