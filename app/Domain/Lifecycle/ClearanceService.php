<?php

namespace App\Domain\Lifecycle;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Operations\Outbox;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClearanceService
{
    public function reassign(User $actor, int $taskId, int $version, string $key, array $input): array
    {
        $d = Validator::make($input, ['owner_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'max:2000']])->validate();

        return Mutation::run($actor, 'lifecycle.task.reassign', $key, compact('taskId', 'version', 'd'), fn ($u) => abort_unless(Access::companyPermission($u, 'employees.review'), 403), function ($u) use ($taskId, $version, $d) {
            $task = DB::table('lifecycle_tasks')->where('id', $taskId)->firstOrFail();
            CoreWrite::version($task->version, $version);
            abort_unless($task->status === 'open', 409);
            $case = DB::table('lifecycle_cases')->find($task->lifecycle_case_id);
            abort_if($case->closed_at || $case->status === 'cancelled', 409);
            $owner = User::findOrFail($d['owner_id']);
            abort_unless(app(LifecycleService::class)->ownerEligible($owner, $task->role, $case->employee_id, $case->last_working_on ?? $case->effective_on), 422, 'Pemilik baru tidak memiliki role/scope task ini.');
            DB::table('lifecycle_owner_changes')->insert(['lifecycle_task_id' => $taskId, 'before_owner_id' => $task->owner_id, 'after_owner_id' => $owner->id, 'actor_id' => $u->id, 'reason' => encrypt($d['reason']), 'created_at' => now()]);
            DB::table('lifecycle_tasks')->where('id', $taskId)->update(['owner_id' => $owner->id, 'version' => $version + 1, 'updated_at' => now()]);
            DB::table('lifecycle_cases')->where('id', $case->id)->increment('version');
            Audit::record($u->id, 'lifecycle.task_reassigned', 'lifecycle_task', $taskId, ['before_owner_id' => $task->owner_id, 'after_owner_id' => $owner->id, 'reason_provided' => true]);
            Outbox::notify([$owner->id], 'lifecycle.task_assigned', 'lifecycle_task', $taskId, 'task-owner:'.$taskId.':'.($version + 1));

            return ['id' => $taskId, 'version' => $version + 1];
        });
    }

    public function close(User $actor, int $id, int $version, string $key, array $input): array
    {
        $d = Validator::make($input, ['reason' => ['required', 'string', 'max:2000'], 'allow_open_assets' => ['required', 'boolean']])->validate();

        return Mutation::run($actor, 'lifecycle.clearance.close', $key, compact('id', 'version', 'd'), fn ($u) => abort_unless(Access::companyPermission($u, 'employees.review'), 403), function ($u) use ($id, $version, $d) {
            $case = DB::table('lifecycle_cases')->where('id', $id)->firstOrFail();
            CoreWrite::version($case->version, $version);
            abort_unless($case->kind === 'offboarding' && $case->status === 'approved', 409);
            $tasks = DB::table('lifecycle_tasks')->where('lifecycle_case_id', $id)->orderBy('id')->get(['id', 'owner_id', 'status', 'mandatory', 'version']);
            abort_if($tasks->contains(fn ($t) => $t->mandatory && ! in_array($t->status, ['completed', 'waived'])), 422, 'Task wajib harus selesai atau dikecualikan HR secara eksplisit.');
            $custodies = DB::table('asset_custodies')->where('employee_id', $case->employee_id)->where('issued_at', '<', $case->effective_on)->whereNull('returned_at')->pluck('id')->all();
            $settlements = DB::table('asset_settlement_cases')->join('asset_custodies', 'asset_custodies.id', '=', 'asset_settlement_cases.asset_custody_id')->where('asset_custodies.employee_id', $case->employee_id)->where('asset_custodies.issued_at', '<', $case->effective_on)->where('asset_settlement_cases.status', 'open')->pluck('asset_settlement_cases.id')->all();
            abort_if(($custodies || $settlements) && ! $d['allow_open_assets'], 422, 'Aset/settlement masih terbuka. Selesaikan atau catat pengecualian closure beralasan.');
            $status = ($custodies || $settlements) ? 'closed_with_exception' : 'completed';
            DB::table('lifecycle_cases')->where('id', $id)->update(['status' => $status, 'version' => $version + 1, 'closure_reason' => encrypt($d['reason']), 'closure_snapshot' => json_encode(['tasks' => $tasks->all(), 'open_custody_ids' => $custodies, 'open_settlement_ids' => $settlements]), 'closed_by' => $u->id, 'closed_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'lifecycle.clearance_closed', 'lifecycle_case', $id, ['status' => $status, 'open_asset_count' => count($custodies), 'open_settlement_count' => count($settlements), 'reason_provided' => true]);

            return ['id' => $id, 'status' => $status, 'version' => $version + 1];
        });
    }

    public function reminders(): void
    {
        DB::transaction(function () {
            DB::table('companies')->where('id', 1)->lockForUpdate()->first();
            foreach (DB::table('lifecycle_tasks')->where('status', 'open')->where('due_on', '<=', today()->toDateString())->orderBy('id')->get() as $t) {
                $c = DB::table('lifecycle_cases')->find($t->lifecycle_case_id);
                if (! $c || $c->closed_at || $c->status === 'cancelled') {
                    continue;
                }
                Outbox::notify([$t->owner_id], 'lifecycle.task_due', 'lifecycle_task', $t->id, 'task-due:'.$t->id.':'.$t->owner_id.':'.$t->due_on);
                if ($t->due_on < today()->subDays(1)->toDateString()) {
                    $hr = User::where('is_active', true)->get()->filter(fn ($u) => WorkflowAccess::eligible($u, 'hr_admin', $c->employee_id))->pluck('id')->all();
                    // Escalation uses a case reference visible to HR, without exit reason or task evidence.
                    Outbox::notify($hr, 'lifecycle.task_overdue', 'lifecycle_case', $c->id, 'task-overdue:'.$t->id.':'.$t->owner_id);
                }
            }
        });
    }
}
