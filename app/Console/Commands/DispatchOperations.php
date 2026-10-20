<?php

namespace App\Console\Commands;

use App\Domain\Documents\ContractReminders;
use App\Domain\Lifecycle\EmploymentRevocations;
use App\Domain\Operations\Outbox;
use App\Domain\Workflow\WorkflowAccess;
use App\Jobs\DeliverOutbox;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchOperations extends Command
{
    protected $signature = 'hris:dispatch-operations';

    protected $description = 'Reconcile outbox deliveries and workflow reminders without automatic approval';

    public function handle(): int
    {
        app(ContractReminders::class)->dispatch();
        app(EmploymentRevocations::class)->reconcile();
        foreach (WorkflowStep::whereIn('status', ['pending', 'blocked'])->where('due_at', '<=', now())->get() as $step) {
            DB::transaction(function () use ($step) {
                DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
                $fresh = WorkflowStep::query()->lockForUpdate()->find($step->id);
                if (! $fresh || ! in_array($fresh->status, ['pending', 'blocked'])) {
                    return;
                }
                Outbox::notify($fresh->approver_ids, 'request.reminder', 'workflow_request', $fresh->workflow_request_id, 'reminder:'.$fresh->id);
                if ($fresh->escalate_at <= now()) {
                    $hr = WorkflowAccess::operators(WorkflowRequest::findOrFail($fresh->workflow_request_id));
                    Outbox::notify($hr, 'request.escalated', 'workflow_request', $fresh->workflow_request_id, 'escalation:'.$fresh->id);
                }
            });
        }
        DB::table('notification_deliveries')->whereIn('status', ['pending', 'failed'])->where('attempts', '<', 5)->where('available_at', '<=', now())->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', now()))->orderBy('id')->limit(100)->pluck('id')->each(fn ($id) => DeliverOutbox::dispatch($id));
        $this->info('Operations reconciled. No workflow was automatically approved.');

        return self::SUCCESS;
    }
}
