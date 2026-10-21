<?php

namespace App\Domain\Lifecycle;

use App\Domain\Attendance\AttendanceService;
use App\Domain\Identity\Audit;
use App\Domain\Leave\LeaveHandler;
use App\Domain\Operations\Outbox;
use App\Domain\Workflow\WorkflowRegistry;
use App\Models\RosterShift;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;

class ExitReconciliation
{
    /** Called only inside the final authorized offboarding decision transaction. */
    public function apply(WorkflowRequest $exit, ?string $oldEnd): void
    {
        $end = $exit->payload['effective_end_on'];
        $shifts = RosterShift::where('employee_id', $exit->employee_id)->where('work_date', '>=', $end)->when($oldEnd, fn ($q) => $q->where('work_date', '<', $oldEnd))->orderBy('id')->get();
        $ids = $shifts->pluck('id')->all();
        foreach ($shifts as $shift) {
            abort_if(app(AttendanceService::class)->closed($shift), 409, 'Roster setelah exit sudah tertutup; selesaikan penyesuaian periode sebelum keputusan exit.');
        }
        $cancelled = [];
        foreach (WorkflowRequest::whereIn('status', ['draft', 'blocked', 'in_review'])->whereIn('kind', ['leave', 'overtime', 'swap', 'correction', 'career'])->where(fn ($q) => $q->where('employee_id', $exit->employee_id)->orWhere('summary->colleague_employee_id', $exit->employee_id))->orderBy('id')->get() as $request) {
            $p = $request->payload;
            $affected = match ($request->kind) {
                'leave' => $p['ends_on'] >= $end && (! $oldEnd || $p['starts_on'] < $oldEnd),
                'career' => $p['effective_on'] >= $end && (! $oldEnd || $p['effective_on'] < $oldEnd),
                'swap' => in_array($p['source_shift_id'], $ids) || in_array($p['target_shift_id'], $ids),
                default => in_array($p['roster_shift_id'], $ids),
            };
            if (! $affected) {
                continue;
            }
            if ($request->status !== 'draft') {
                if ($request->kind === 'leave') {
                    app(LeaveHandler::class)->released($request, $exit->effectActorId());
                } else {
                    app(WorkflowRegistry::class)->handler($request->kind)->released($request);
                }
            }
            $request->steps()->whereIn('status', ['waiting', 'pending', 'blocked'])->update(['status' => 'cancelled']);
            $request->update(['status' => 'withdrawn', 'version' => $request->version + 1, 'finalized_at' => now()]);
            $cancelled[] = $request->id;
            Audit::record($exit->effectActorId(), 'workflow.withdrawn_by_exit_decision', 'workflow_request', $request->id, ['offboarding_request_id' => $exit->id]);
            Outbox::notify([$request->submitted_by], 'request.withdrawn_by_exit', 'workflow_request', $request->id, 'exit:'.$exit->id.':'.$request->id);
        }
        $segments = DB::table('leave_segments')->where('employee_id', $exit->employee_id)->whereIn('roster_shift_id', $ids)->where('state', 'approved')->get();
        foreach ($segments as $segment) {
            foreach (DB::table('leave_allocations')->where('leave_segment_id', $segment->id)->where('state', 'used')->get() as $a) {
                DB::table('leave_allocations')->where('id', $a->id)->update(['state' => 'reversed']);
                app(LeaveHandler::class)->ledger($exit, $a->leave_credit_lot_id, $a->id, 'reversal', $a->minutes);
            }
            DB::table('leave_segments')->where('id', $segment->id)->update(['state' => 'cancelled']);
        }
        foreach ($segments->groupBy('workflow_request_id') as $requestId => $affected) {
            $original = WorkflowRequest::findOrFail($requestId);
            $original->update(['summary' => $original->summary + ['exit_reconciliation' => ['offboarding_request_id' => $exit->id, 'cancelled_future_segment_ids' => $affected->pluck('id')->all()]], 'version' => $original->version + 1]);
        }
        $overtime = DB::table('overtime_authorizations')->where('employee_id', $exit->employee_id)->whereIn('roster_shift_id', $ids)->whereIn('status', ['pending', 'approved', 'verified'])->pluck('id')->all();
        DB::table('overtime_authorizations')->whereIn('id', $overtime)->update(['status' => 'cancelled', 'payable_minutes' => 0, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        $superseded = $shifts->whereIn('status', ['draft', 'published'])->pluck('id')->all();
        RosterShift::whereIn('id', $superseded)->update(['status' => 'superseded', 'version' => DB::raw('version + 1')]);
        $snapshot = ['effective_end_on' => $end, 'previous_end_on' => $oldEnd, 'withdrawn_request_ids' => $cancelled, 'cancelled_leave_segment_ids' => $segments->pluck('id')->all(), 'cancelled_overtime_ids' => $overtime, 'superseded_shift_ids' => $superseded];
        DB::table('employment_exit_reconciliations')->insert(['workflow_request_id' => $exit->id, 'employee_id' => $exit->employee_id, 'actor_id' => $exit->effectActorId(), 'snapshot' => json_encode($snapshot), 'created_at' => now()]);
        Audit::record($exit->effectActorId(), 'employment.future_sources_reconciled', 'workflow_request', $exit->id, $snapshot);
    }
}
