<?php

namespace App\Domain\Workflow;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Clock;
use App\Domain\Operations\Mutation;
use App\Domain\Operations\Outbox;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function __construct(private WorkflowRegistry $registry, private Clock $clock) {}

    public function define(User $actor, string $key, array $input): array
    {
        $roles = $this->registry->handler((string) ($input['kind'] ?? ''))->approverRoles();
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'], 'kind' => ['required', Rule::in($this->registry->kinds())],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'min_amount_minor' => ['required', 'integer', 'min:0'], 'max_amount_minor' => ['nullable', 'integer', 'gte:min_amount_minor'],
            'priority' => ['required', 'integer', 'between:0,100'], 'steps' => ['required', 'array', 'min:1', 'max:8'],
            'steps.*' => ['required', 'array:role'], 'steps.*.role' => ['required', Rule::in($roles)],
            'deadline_calendar' => ['nullable', 'array:timezone,branch_id,working_days,start_local,end_local,due_minutes,escalate_minutes'],
            'deadline_calendar.timezone' => ['required_with:deadline_calendar', 'timezone'], 'deadline_calendar.branch_id' => ['required_with:deadline_calendar', 'integer', 'exists:branches,id'],
            'deadline_calendar.working_days' => ['required_with:deadline_calendar', 'array', 'min:1', 'max:7'], 'deadline_calendar.working_days.*' => ['integer', 'between:1,7', 'distinct'],
            'deadline_calendar.start_local' => ['required_with:deadline_calendar', 'date_format:H:i'], 'deadline_calendar.end_local' => ['required_with:deadline_calendar', 'date_format:H:i'],
            'deadline_calendar.due_minutes' => ['required_with:deadline_calendar', 'integer', 'between:1,525600'], 'deadline_calendar.escalate_minutes' => ['required_with:deadline_calendar', 'integer', 'gt:deadline_calendar.due_minutes', 'max:525600'],
        ])->validate();
        if (! empty($data['deadline_calendar'])) {
            abort_unless($data['deadline_calendar']['end_local'] > $data['deadline_calendar']['start_local'], 422);
            $data['deadline_calendar']['working_days'] = array_map('intval', $data['deadline_calendar']['working_days']);
        }
        $handler = $this->registry->handler($data['kind']);
        if ($handler instanceof WorkflowRouting) {
            $handler->validateSteps($data['steps']);
        }

        return Mutation::run($actor, 'workflow.define', $key, $data, fn ($u) => abort_unless(Access::companyPermission($u, 'workflow.manage'), 403), function ($u) use ($data) {
            $version = (int) WorkflowDefinition::where('name', $data['name'])->max('version') + 1;
            WorkflowDefinition::where('name', $data['name'])->update(['is_active' => false]);
            $definition = WorkflowDefinition::create($data + ['version' => $version, 'created_by' => $u->id]);
            Audit::record($u->id, 'workflow.defined', 'workflow_definition', $definition->id, ['kind' => $data['kind'], 'version' => $version]);

            return ['id' => $definition->id, 'version' => $version];
        });
    }

    public function draft(User $actor, string $key, string $kind, array $input): array
    {
        $handler = $this->registry->handler($kind);

        return Mutation::run($actor, 'workflow.draft', $key, ['kind' => $kind, 'input' => $input], fn ($u) => $handler->prepare($u, $input), function ($u) use ($handler, $kind, $input) {
            $prepared = $handler->prepare($u, $input);
            $request = WorkflowRequest::create(['public_id' => (string) Str::uuid(), 'kind' => $kind, 'employee_id' => $prepared['employee_id'], 'submitted_by' => $u->id, 'payload' => $prepared['payload'], 'summary' => $prepared['summary'], 'status' => 'draft', 'version' => 1]);
            Audit::record($u->id, 'workflow.drafted', 'workflow_request', $request->id, ['kind' => $kind]);

            return ['id' => $request->id, 'public_id' => $request->public_id, 'version' => 1];
        });
    }

    public function submit(User $actor, int $id, int $version, string $key): array
    {
        return Mutation::run($actor, 'workflow.submit', $key, compact('id', 'version'), fn ($u) => $this->owned($u, $id), function ($u) use ($id, $version) {
            $r = $this->owned($u, $id);
            CoreWrite::version($r->version, $version);
            abort_unless($r->status === 'draft', 409);
            $handler = $this->registry->handler($r->kind);
            $prepared = $handler->prepare($u, $r->payload);
            abort_unless($prepared['employee_id'] === $r->employee_id, 409);
            $r->summary = $prepared['summary'];
            $definition = $this->definition($r);
            if ($handler instanceof WorkflowRouting) {
                $handler->validateSteps($definition->steps);
            }
            $r->fill(['workflow_definition_id' => $definition->id, 'submitted_at' => $this->clock->now(), 'status' => 'in_review', 'version' => $r->version + 1])->save();
            foreach ($definition->steps as $position => $step) {
                $participants = $handler instanceof ParticipantWorkflow ? $handler->participants($r, $step['role']) : null;
                $candidates = User::where('is_active', true)->whereKeyNot($r->submitted_by);
                if ($participants !== null) {
                    $candidates->whereIn('id', $participants);
                }
                $users = $candidates->get()->filter(fn ($candidate) => (! $candidate->employee_id || $candidate->employee_id !== $r->employee_id) && ($participants !== null ? $handler->participantEligible($candidate, $r, $step['role']) : WorkflowAccess::eligible($candidate, $step['role'], $r->employee_id)))->pluck('id')->all();
                $delegation = $participants === null ? app(Delegations::class)->snapshot($r, $step['role'], $users) : [];
                $baseUsers = $users;
                $users = array_values(array_unique(array_merge($users, array_map('intval', array_keys($delegation)))));
                $r->steps()->create(['position' => $position + 1, 'role' => $step['role'], 'base_approver_ids' => $baseUsers, 'approver_ids' => $users, 'delegation_snapshot' => $delegation, 'status' => 'waiting']);
            }
            $handler->submitted($r);
            $this->activate($r, $r->steps()->orderBy('position')->firstOrFail());
            Audit::record($u->id, 'workflow.submitted', 'workflow_request', $id, ['definition_id' => $definition->id, 'version' => $definition->version]);
            Outbox::notify([$r->submitted_by], 'request.submitted', 'workflow_request', $id, 'submitted:'.$id);

            return ['id' => $id, 'status' => $r->status, 'version' => $r->version];
        });
    }

    public function decide(User $actor, int $id, int $version, string $key, string $decision, ?string $reason): array
    {
        Validator::make(compact('decision', 'reason'), ['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']])->validate();

        return Mutation::run($actor, 'workflow.decide', $key, compact('id', 'version', 'decision', 'reason'), function ($u) use ($id) {
            $r = WorkflowRequest::findOrFail($id);
            abort_unless(WorkflowAccess::visible($u, $r), 404);
            abort_if($r->submitted_by === $u->id || ($u->employee_id && $r->employee_id === $u->employee_id), 403);
        }, function ($u) use ($id, $version, $decision, $reason) {
            $r = WorkflowRequest::query()->lockForUpdate()->findOrFail($id);
            CoreWrite::version($r->version, $version);
            abort_unless($r->status === 'in_review', 409);
            $step = $r->steps()->where('status', 'pending')->lockForUpdate()->firstOrFail();
            abort_unless(in_array($u->id, $step->approver_ids, true) && WorkflowAccess::stepEligible($u, $r, $step), 403);
            $delegationId = WorkflowAccess::eligible($u, $step->role, $r->employee_id) ? null : app(Delegations::class)->decisionId($u, $r, $step);
            DB::table('workflow_decisions')->insert(['workflow_step_id' => $step->id, 'actor_id' => $u->id, 'decision' => $decision, 'reason' => $reason ? encrypt($reason) : null, 'workflow_delegation_id' => $delegationId, 'created_at' => $this->clock->now()]);
            $step->update(['status' => $decision === 'approve' ? 'approved' : 'rejected', 'version' => $step->version + 1]);
            $handler = $this->registry->handler($r->kind);
            if ($decision === 'approve' && $handler instanceof StageWorkflow) {
                $handler->stageApproved($r, $step);
            }
            if ($decision === 'reject') {
                $this->registry->handler($r->kind)->released($r);
                $r->steps()->whereIn('status', ['waiting', 'blocked'])->update(['status' => 'cancelled']);
                $r->status = 'rejected';
                $r->finalized_at = $this->clock->now();
            } elseif ($next = $r->steps()->where('status', 'waiting')->orderBy('position')->first()) {
                $this->activate($r, $next);
            } else {
                $this->registry->handler($r->kind)->approved($r);
                $r->status = 'approved';
                $r->finalized_at = $this->clock->now();
            }
            $r->version++;
            $r->save();
            Audit::record($u->id, 'workflow.'.$decision, 'workflow_request', $id, ['step_id' => $step->id, 'reason_provided' => (bool) $reason]);
            Outbox::notify([$r->submitted_by], 'request.'.$r->status, 'workflow_request', $id, 'decision:'.$step->id);

            return ['id' => $id, 'status' => $r->status, 'version' => $r->version];
        });
    }

    public function withdraw(User $actor, int $id, int $version, string $key, string $reason): array
    {
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:1000']])->validate();

        return Mutation::run($actor, 'workflow.withdraw', $key, compact('id', 'version', 'reason'), fn ($u) => $this->owned($u, $id), function ($u) use ($id, $version) {
            $r = $this->owned($u, $id);
            CoreWrite::version($r->version, $version);
            abort_unless(in_array($r->status, ['draft', 'in_review', 'blocked']), 409, 'Pengajuan final memerlukan pembalikan terpisah.');
            if ($r->status !== 'draft') {
                $this->registry->handler($r->kind)->released($r);
            }
            $r->steps()->whereIn('status', ['waiting', 'pending', 'blocked'])->update(['status' => 'cancelled']);
            $r->update(['status' => 'withdrawn', 'finalized_at' => $this->clock->now(), 'version' => $r->version + 1]);
            Audit::record($u->id, 'workflow.withdrawn', 'workflow_request', $id, ['reason_provided' => true]);
            Outbox::notify([$r->submitted_by], 'request.withdrawn', 'workflow_request', $id, 'withdraw:'.$id);

            return ['id' => $id, 'status' => $r->status, 'version' => $r->version];
        });
    }

    public function reassign(User $actor, int $id, int $version, string $key, int $replacement, string $reason): array
    {
        Validator::make(compact('reason', 'replacement'), ['reason' => ['required', 'string', 'max:1000'], 'replacement' => ['required', 'integer', 'exists:users,id']])->validate();

        return Mutation::run($actor, 'workflow.reassign', $key, compact('id', 'version', 'replacement', 'reason'), fn ($u) => abort_unless(Access::companyPermission($u, 'workflow.manage'), 403), function ($u) use ($id, $version, $replacement) {
            $r = WorkflowRequest::query()->lockForUpdate()->findOrFail($id);
            CoreWrite::version($r->version, $version);
            abort_unless(in_array($r->status, ['in_review', 'blocked']), 409);
            abort_unless(in_array($r->kind, ['leave', 'leave_cancel', 'correction', 'overtime', 'swap', 'contract', 'letter', 'career', 'onboarding', 'offboarding']), 403);
            $target = User::findOrFail($replacement);
            abort_if($target->id === $r->submitted_by || ($target->employee_id && $target->employee_id === $r->employee_id), 403);
            $step = $r->steps()->whereIn('status', ['pending', 'blocked'])->lockForUpdate()->firstOrFail();
            if ($step->role === 'counterparty') {
                abort_unless(WorkflowAccess::stepEligible($target, $r, $step), 403);
                $role = 'counterparty';
            } else {
                $role = WorkflowAccess::eligible($target, $step->role, $r->employee_id) ? $step->role : 'hr_admin';
                abort_unless(WorkflowAccess::eligible($target, $role, $r->employee_id), 403);
            }
            $step->update(['role' => $role, 'base_approver_ids' => [$target->id], 'approver_ids' => [$target->id], 'delegation_snapshot' => [], 'version' => $step->version + 1]);
            $this->activate($r, $step);
            $r->version++;
            $r->save();
            Audit::record($u->id, 'workflow.reassigned', 'workflow_request', $id, ['step_id' => $step->id, 'replacement_id' => $target->id, 'reason_provided' => true]);

            return ['id' => $id, 'status' => $r->status, 'version' => $r->version];
        });
    }

    private function owned(User $actor, int $id): WorkflowRequest
    {
        return WorkflowRequest::where('submitted_by', $actor->id)->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function definition(WorkflowRequest $r): WorkflowDefinition
    {
        $placement = $r->employee_id ? Employee::findOrFail($r->employee_id)->assignments()->at(today()->toDateString())->first() : null;
        $amount = (int) ($r->summary['amount_minor'] ?? 0);
        $definitions = WorkflowDefinition::where('kind', $r->kind)->where('is_active', true)->where('min_amount_minor', '<=', $amount)->where(fn ($q) => $q->whereNull('max_amount_minor')->orWhere('max_amount_minor', '>=', $amount))->get()->filter(fn ($d) => (! $d->branch_id || $d->branch_id === $placement?->branch_id) && (! $d->department_id || $d->department_id === $placement?->department_id))->sortByDesc('priority');
        if ($definitions->isEmpty() || $definitions->where('priority', $definitions->first()->priority)->count() !== 1) {
            throw ValidationException::withMessages(['workflow' => 'Tetapkan satu alur persetujuan aktif yang sesuai; aturan tidak ada atau ambigu.']);
        }

        return $definitions->first();
    }

    private function activate(WorkflowRequest $r, WorkflowStep $step): void
    {
        $blocked = empty($step->approver_ids);
        $calendar = WorkflowDefinition::findOrFail($r->workflow_definition_id)->deadline_calendar;
        $due = $calendar ? app(BusinessDeadline::class)->add($this->clock->now(), $calendar['due_minutes'], $calendar) : null;
        $escalate = $calendar ? app(BusinessDeadline::class)->add($this->clock->now(), $calendar['escalate_minutes'], $calendar) : null;
        $step->update(['status' => $blocked ? 'blocked' : 'pending', 'activated_at' => $this->clock->now(), 'due_at' => $due['at'] ?? $this->clock->now()->addHours(24), 'escalate_at' => $escalate['at'] ?? $this->clock->now()->addHours(48), 'deadline_snapshot' => $calendar ? ['calendar' => $calendar, 'due_holidays' => $due['holiday_versions'], 'escalation_holidays' => $escalate['holiday_versions']] : ['mode' => 'elapsed_legacy', 'due_hours' => 24, 'escalation_hours' => 48]]);
        $r->status = $blocked ? 'blocked' : 'in_review';
        $r->save();
        $users = $blocked ? WorkflowAccess::operators($r) : $step->approver_ids;
        Outbox::notify($users, $blocked ? 'request.missing_approver' : 'request.action_required', 'workflow_request', $r->id, 'activate:'.$step->id.':'.$step->version);
    }
}
