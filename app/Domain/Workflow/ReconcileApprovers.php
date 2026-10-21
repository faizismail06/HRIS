<?php

namespace App\Domain\Workflow;

use App\Domain\Identity\Audit;
use App\Domain\Operations\Outbox;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

class ReconcileApprovers
{
    public function run(): void
    {
        WorkflowStep::whereIn('status', ['pending', 'blocked'])->orderBy('id')->chunkById(100, function ($steps) {
            foreach ($steps as $source) {
                DB::transaction(function () use ($source) {
                    DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
                    $step = WorkflowStep::whereKey($source->id)->lockForUpdate()->firstOrFail();
                    $r = WorkflowRequest::findOrFail($step->workflow_request_id);
                    if (! in_array($step->status, ['pending', 'blocked']) || ! in_array($r->status, ['in_review', 'blocked'])) {
                        return;
                    }
                    $delegations = $step->role === 'counterparty' ? [] : app(Delegations::class)->snapshot($r, $step->role, $step->base_approver_ids);
                    $ids = array_values(array_unique(array_merge($step->base_approver_ids, array_map('intval', array_keys($delegations)))));
                    sort($ids);
                    $oldIds = $step->approver_ids;
                    sort($oldIds);
                    $beforeDelegations = $step->delegation_snapshot;
                    $step->approver_ids = $ids;
                    $step->delegation_snapshot = $delegations;
                    $available = User::whereIn('id', $ids)->get()->contains(fn ($u) => $u->id !== $r->submitted_by && (! $u->employee_id || $u->employee_id !== $r->employee_id) && WorkflowAccess::stepEligible($u, $r, $step));
                    $status = $available ? 'pending' : 'blocked';
                    if ($ids === $oldIds && $beforeDelegations == $delegations && $status === $step->status) {
                        return;
                    }
                    $step->status = $status;
                    $step->version++;
                    $step->save();
                    $r->status = $available ? 'in_review' : 'blocked';
                    $r->version++;
                    $r->save();
                    Audit::record(null, 'workflow.approvers_reconciled', 'workflow_request', $r->id, ['step_id' => $step->id, 'before_ids' => $oldIds, 'after_ids' => $ids, 'delegation_ids' => $delegations, 'status' => $status]);
                    Outbox::notify($available ? $ids : WorkflowAccess::operators($r), $available ? 'request.action_required' : 'request.missing_approver', 'workflow_request', $r->id, 'reconcile:'.$step->id.':'.$step->version);
                });
            }
        });
    }
}
