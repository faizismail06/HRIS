<?php

namespace App\Domain\Attendance;

use App\Domain\Operations\Clock;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\RosterShift;
use App\Models\User;
use App\Models\WorkflowRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CorrectionHandler implements WorkflowHandler
{
    public function __construct(private AttendanceService $attendance, private Clock $clock) {}

    public function approverRoles(): array
    {
        return ['manager', 'hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless($actor->employee_id, 403);
        $data = Validator::make($input, ['roster_shift_id' => ['required', 'integer', 'exists:roster_shifts,id'], 'effective_in' => ['required', 'date_format:Y-m-d\TH:i:sP'], 'effective_out' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:effective_in'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        $shift = RosterShift::where('employee_id', $actor->employee_id)->where('status', 'published')->where('is_day_off', false)->findOrFail($data['roster_shift_id']);
        abort_unless($this->clock->now() <= $shift->ends_at->addDays(7), 422, 'Batas pengajuan koreksi tujuh hari telah lewat. Minta peninjauan HR terpisah.');
        $in = CarbonImmutable::parse($data['effective_in']);
        $out = CarbonImmutable::parse($data['effective_out']);
        abort_unless($out <= $this->clock->now() && $in >= $shift->starts_at->subMinutes($shift->snapshot['clock_in_before_minutes']) && $out <= $shift->ends_at->addMinutes($shift->snapshot['clock_out_after_minutes']), 422, 'Waktu koreksi di luar shift/jendela atau belum terjadi.');
        $result = $this->attendance->recalculate($shift);

        return ['employee_id' => $actor->employee_id, 'payload' => $data, 'summary' => ['roster_shift_id' => $shift->id, 'work_date' => $shift->work_date, 'result_version' => $result->version, 'requires_adjustment' => (bool) $this->attendance->closed($shift)]];
    }

    public function submitted(WorkflowRequest $r): void
    {
        $shift = RosterShift::findOrFail($r->payload['roster_shift_id']);
        abort_if(DB::table('attendance_corrections')->join('workflow_requests', 'workflow_requests.id', '=', 'attendance_corrections.workflow_request_id')->where('roster_shift_id', $shift->id)->whereIn('workflow_requests.status', ['in_review', 'blocked'])->exists(), 422, 'Masih ada koreksi terbuka untuk shift ini.');
        DB::table('attendance_corrections')->insert(['workflow_request_id' => $r->id, 'roster_shift_id' => $shift->id, 'effective_in' => $r->payload['effective_in'], 'effective_out' => $r->payload['effective_out'], 'base_result_version' => $r->summary['result_version'], 'created_at' => $this->clock->now()->toIso8601String()]);
    }

    public function approved(WorkflowRequest $r): void
    {
        $shift = RosterShift::findOrFail($r->payload['roster_shift_id']);
        $record = DB::table('attendance_corrections')->where('workflow_request_id', $r->id)->firstOrFail();
        if ($closed = $this->attendance->closed($shift)) {
            DB::table('attendance_adjustments')->insert(['workflow_request_id' => $r->id, 'attendance_period_id' => $closed->id, 'employee_id' => $r->employee_id, 'payload' => json_encode(['type' => 'correction', 'roster_shift_id' => $shift->id, 'effective_in' => $r->payload['effective_in'], 'effective_out' => $r->payload['effective_out']], JSON_THROW_ON_ERROR), 'created_at' => $this->clock->now()->toIso8601String()]);

            return;
        }
        $current = $this->attendance->recalculate($shift);
        abort_unless($current->version === $record->base_result_version, 409, 'Sumber kehadiran berubah; ajukan koreksi berdasarkan data terbaru.');
        // Temporarily expose the in-transaction final state to the deterministic projection.
        $r->status = 'approved';
        $r->save();
        $this->attendance->recalculate($shift);
    }

    public function released(WorkflowRequest $r): void {}
}
