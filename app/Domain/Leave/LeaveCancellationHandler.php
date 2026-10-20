<?php

namespace App\Domain\Leave;

use App\Domain\Attendance\AttendanceService;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\RosterShift;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeaveCancellationHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['manager', 'hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless($actor->employee_id, 403);
        $data = Validator::make($input, ['original_request_id' => ['required', 'integer', 'exists:workflow_requests,id'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        $original = WorkflowRequest::where('employee_id', $actor->employee_id)->where('kind', 'leave')->where('status', 'approved')->findOrFail($data['original_request_id']);
        abort_unless(DB::table('leave_segments')->where('workflow_request_id', $original->id)->where('state', 'approved')->exists(), 409);

        return ['employee_id' => $actor->employee_id, 'payload' => $data, 'summary' => ['original_request_id' => $original->id, 'starts_on' => $original->summary['starts_on'], 'ends_on' => $original->summary['ends_on']]];
    }

    public function submitted(WorkflowRequest $r): void
    {
        abort_if(DB::table('leave_cancellations')->where('original_request_id', $r->payload['original_request_id'])->whereIn('status', ['pending', 'approved'])->exists(), 422, 'Pembatalan sedang ditinjau atau sudah disetujui.');
        DB::table('leave_cancellations')->insert(['workflow_request_id' => $r->id, 'original_request_id' => $r->payload['original_request_id'], 'created_at' => now()->toIso8601String()]);
    }

    public function approved(WorkflowRequest $r): void
    {
        $periods = [];
        foreach (DB::table('leave_segments')->where('workflow_request_id', $r->payload['original_request_id'])->where('state', 'approved')->get() as $segment) {
            foreach (DB::table('leave_allocations')->where('leave_segment_id', $segment->id)->where('state', 'used')->get() as $allocation) {
                DB::table('leave_allocations')->where('id', $allocation->id)->update(['state' => 'reversed']);
                app(LeaveHandler::class)->ledger($r, $allocation->leave_credit_lot_id, $allocation->id, 'reversal', $allocation->minutes);
            }
            DB::table('leave_segments')->where('id', $segment->id)->update(['state' => 'cancelled']);
            $shift = RosterShift::findOrFail($segment->roster_shift_id);
            $attendance = app(AttendanceService::class);
            if ($closed = $attendance->closed($shift)) {
                $periods[$closed->id][] = $segment->id;
            } else {
                $attendance->recalculate($shift);
            }
        }
        foreach ($periods as $period => $ids) {
            DB::table('attendance_adjustments')->insert(['workflow_request_id' => $r->id, 'attendance_period_id' => $period, 'employee_id' => $r->employee_id, 'payload' => json_encode(['type' => 'leave_cancellation', 'original_request_id' => $r->payload['original_request_id'], 'segment_ids' => $ids], JSON_THROW_ON_ERROR), 'created_at' => now()->toIso8601String()]);
        }
        DB::table('leave_cancellations')->where('workflow_request_id', $r->id)->update(['status' => 'approved']);
    }

    public function released(WorkflowRequest $r): void
    {
        DB::table('leave_cancellations')->where('workflow_request_id', $r->id)->update(['status' => 'rejected']);
    }
}
