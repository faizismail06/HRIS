<?php

namespace App\Domain\Workflow;

use App\Domain\Identity\AccountSecurity;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Delegations
{
    public function create(User $actor, string $key, array $input): array
    {
        $d = Validator::make($input, ['delegate_id' => ['required', 'integer', 'exists:users,id'], 'role' => ['required', Rule::in(['manager', 'hr_admin', 'hr_officer', 'finance_approver'])], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i:sP'], 'ends_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:starts_at'], 'employee_ids' => ['required', 'array', 'min:1', 'max:200'], 'employee_ids.*' => ['required', 'integer', 'exists:employees,id', 'distinct'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        abort_if($actor->id === $d['delegate_id'], 422);
        abort_if(CarbonImmutable::parse($d['starts_at'])->diffInDays(CarbonImmutable::parse($d['ends_at'])) > 90, 422, 'Delegasi maksimal90hari.');

        return Mutation::run($actor, 'workflow.delegation.create', $key, $d, function ($u) use ($d) {
            foreach ($d['employee_ids'] as $employee) {
                abort_unless(WorkflowAccess::eligible($u, $d['role'], $employee), 403, 'Delegasi hanya boleh dalam scope sendiri.');
            }
            $target = User::findOrFail($d['delegate_id']);
            abort_unless(AccountSecurity::available($target) && $target->two_factor_confirmed_at && $target->grants()->where('role', $d['role'])->exists(), 422, 'Delegate harus aktif, memiliki mandat role terkait dan MFA.');
        }, function ($u) use ($d) {
            $id = DB::table('workflow_delegations')->insertGetId(array_replace($d, ['employee_ids' => json_encode(array_map('intval', $d['employee_ids'])), 'reason' => encrypt($d['reason']), 'delegator_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]));
            Audit::record($u->id, 'workflow.delegated', 'workflow_delegation', $id, ['delegate_id' => $d['delegate_id'], 'role' => $d['role'], 'employee_ids' => $d['employee_ids'], 'ends_at' => $d['ends_at']]);

            return ['id' => $id];
        });
    }

    public function revoke(User $actor, int $id, string $key): array
    {
        return Mutation::run($actor, 'workflow.delegation.revoke', $key, compact('id'), fn ($u) => abort_unless(DB::table('workflow_delegations')->where('id', $id)->where('delegator_id', $u->id)->exists(), 404), function ($u) use ($id) {
            DB::table('workflow_delegations')->where('id', $id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'workflow.delegation_revoked', 'workflow_delegation', $id);

            return ['id' => $id, 'status' => 'revoked'];
        });
    }

    public function snapshot(WorkflowRequest $r, string $role, array $approvers): array
    {
        $delegates = [];
        foreach (DB::table('workflow_delegations')->whereIn('delegator_id', $approvers)->where('role', $role)->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())->orderBy('id')->get() as $delegation) {
            $target = User::find($delegation->delegate_id);
            if ($target && $this->valid($target, $r, $role, $delegation)) {
                $delegates[(string) $target->id][] = $delegation->id;
            }
        }

        ksort($delegates, SORT_NUMERIC);

        return $delegates;
    }

    private function valid(User $actor, WorkflowRequest $r, string $role, object $d): bool
    {
        $delegator = User::find($d->delegator_id);

        return $actor->id === $d->delegate_id && AccountSecurity::available($actor) && $actor->two_factor_confirmed_at && $actor->grants()->where('role', $role)->exists()
            && ! $d->revoked_at && CarbonImmutable::parse($d->starts_at) <= now() && CarbonImmutable::parse($d->ends_at) > now()
            && in_array($r->employee_id, json_decode($d->employee_ids, true), true) && $delegator && $delegator->id !== $r->submitted_by && $delegator->employee_id !== $r->employee_id
            && WorkflowAccess::eligible($delegator, $role, $r->employee_id) && $actor->id !== $r->submitted_by && $actor->employee_id !== $r->employee_id;
    }

    public function decisionId(User $actor, WorkflowRequest $r, WorkflowStep $step): ?int
    {
        foreach (($step->delegation_snapshot ?? [])[(string) $actor->id] ?? [] as $id) {
            $d = DB::table('workflow_delegations')->find($id);
            if ($d && $d->role === $step->role && $this->valid($actor, $r, $step->role, $d)) {
                return $id;
            }
        }

        return null;
    }
}
