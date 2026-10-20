<?php

namespace App\Domain\Lifecycle;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\CoreHR\EmployeeFields;
use App\Domain\Identity\Access;
use App\Domain\Identity\AccountSecurity;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Operations\Outbox;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LifecycleService
{
    public const ROLES = ['hr_admin', 'hr_officer', 'manager', 'it_asset_officer', 'finance_approver'];

    public function template(User $actor, string $key, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'employees.review'), 403);
        $d = Validator::make($input, ['code' => ['required', 'regex:/^[A-Z0-9_-]{2,30}$/'], 'kind' => ['required', Rule::in(['onboarding', 'offboarding'])], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'job_id' => ['nullable', 'integer', 'exists:job_positions,id'], 'tasks' => ['required', 'array', 'min:1', 'max:40'], 'tasks.*' => ['array:code,label,role,mandatory,due_offset_days,dependencies'], 'tasks.*.code' => ['required', 'regex:/^[a-z0-9_]{2,40}$/', 'distinct'], 'tasks.*.label' => ['required', 'string', 'max:160'], 'tasks.*.role' => ['required', Rule::in(self::ROLES)], 'tasks.*.mandatory' => ['required', 'boolean'], 'tasks.*.due_offset_days' => ['required', 'integer', 'between:-90,90'], 'tasks.*.dependencies' => ['present', 'array', 'max:40'], 'tasks.*.dependencies.*' => ['string']])->validate();
        $seen = [];
        foreach ($d['tasks'] as $task) {
            abort_if(array_diff($task['dependencies'], $seen), 422, 'Dependency harus merujuk task sebelumnya; siklus tidak diizinkan.');
            $seen[] = $task['code'];
        }

        return Mutation::run($actor, 'lifecycle.template', $key, $d, fn ($u) => abort_unless(Access::companyPermission($u, 'employees.review'), 403), function ($u) use ($d) {
            $version = (int) DB::table('lifecycle_templates')->where('code', $d['code'])->max('version') + 1;
            DB::table('lifecycle_templates')->where('code', $d['code'])->update(['is_active' => false]);
            $id = DB::table('lifecycle_templates')->insertGetId(array_replace($d, ['tasks' => json_encode($d['tasks']), 'version' => $version, 'created_at' => now(), 'updated_at' => now()]));
            Audit::record($u->id, 'lifecycle.template_created', 'lifecycle_template', $id, ['version' => $version]);

            return ['id' => $id, 'version' => $version];
        });
    }

    public function onboarding(User $actor, string $key, int $version, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'employees.manage'), 403);
        $d = Validator::make($input, ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'employment_period_id' => ['nullable', 'integer'], 'lifecycle_template_id' => ['required', 'integer'], 'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'owners' => ['required', 'array'], 'owners.*' => ['required', 'integer', 'exists:users,id']] + EmployeeFields::assignment())->validate();

        return CoreWrite::run(fn () => Mutation::run($actor, 'lifecycle.onboarding', $key, compact('version', 'd'), fn ($u) => abort_unless(Access::companyPermission($u, 'employees.manage'), 403), function ($u) use ($d, $version) {
            $employee = Employee::findOrFail($d['employee_id']);
            CoreWrite::version($employee->version, $version);
            EmployeeFields::validatePlacement($d, $d['starts_on'], $employee->id);
            if (! empty($d['employment_period_id'])) {
                $period = $employee->periods()->findOrFail($d['employment_period_id']);
                abort_unless($period->starts_on->toDateString() === $d['starts_on'] && $period->starts_on->toDateString() >= today()->toDateString(), 422, 'Hanya periode baru yang belum mulai dapat masuk onboarding.');
                $assignment = $employee->assignments()->where('employment_period_id', $period->id)->at($d['starts_on'])->firstOrFail();
                foreach (array_keys(EmployeeFields::assignment()) as $field) {
                    abort_unless(($assignment->{$field} ?? null) == ($d[$field] ?? null), 422, 'Penempatan berbeda; gunakan pengajuan perubahan organisasi.');
                }
                $period->update(['activation_state' => 'pending']);
            } else {
                abort_if($employee->periods()->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $d['starts_on']))->exists(), 422, 'Tutup periode sebelumnya sebelum rehire; person lama tetap digunakan.');
                $period = $employee->periods()->create(['starts_on' => $d['starts_on'], 'activation_state' => 'pending']);
                $employee->assignments()->create(array_intersect_key($d, EmployeeFields::assignment()) + ['employment_period_id' => $period->id, 'valid_from' => $d['starts_on']]);
            }
            $case = $this->createCase($employee->id, $period->id, 'onboarding', $d['lifecycle_template_id'], $d['starts_on'], $d['owners']);
            $employee->increment('version');
            Audit::record($u->id, 'lifecycle.onboarding_created', 'lifecycle_case', $case, ['employee_id' => $employee->id, 'period_id' => $period->id]);

            return ['id' => $case, 'version' => 1, 'employment_period_id' => $period->id, 'employee_version' => $version + 1];
        }));
    }

    public function createCase(int $employee, int $period, string $kind, int $template, string $date, array $owners, array $extra = []): int
    {
        $t = DB::table('lifecycle_templates')->where('id', $template)->where('kind', $kind)->where('is_active', true)->firstOrFail();
        $referenceDate = $extra['last_working_on'] ?? $date;
        $assignment = Employee::findOrFail($employee)->assignments()->at($referenceDate)->first();
        if (! $assignment && $kind === 'offboarding') {
            $assignment = Employee::findOrFail($employee)->assignments()->at(CarbonImmutable::parse($date)->subDay()->toDateString())->first();
        }
        abort_unless($assignment && (! $t->branch_id || $t->branch_id === $assignment->branch_id) && (! $t->job_id || $t->job_id === $assignment->job_id), 422, 'Template tidak sesuai lokasi/jabatan.');
        $id = DB::table('lifecycle_cases')->insertGetId($extra + ['employee_id' => $employee, 'employment_period_id' => $period, 'lifecycle_template_id' => $template, 'kind' => $kind, 'effective_on' => $date, 'created_at' => now(), 'updated_at' => now()]);
        $tasks = json_decode($t->tasks, true);
        abort_if(array_diff(array_keys($owners), array_column($tasks, 'code')), 422, 'Owner memuat task tidak dikenal.');
        foreach ($tasks as $task) {
            $owner = User::find($owners[$task['code']] ?? null);
            abort_unless($owner && $this->ownerEligible($owner, $task['role'], $employee, $referenceDate), 422, 'Setiap task memerlukan owner aktif dengan role/scope sesuai.');
            $taskId = DB::table('lifecycle_tasks')->insertGetId(['lifecycle_case_id' => $id, 'code' => $task['code'], 'label' => $task['label'], 'role' => $task['role'], 'owner_id' => $owner->id, 'mandatory' => $task['mandatory'], 'dependencies' => json_encode($task['dependencies']), 'due_on' => CarbonImmutable::parse($date)->addDays($task['due_offset_days'])->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
            Outbox::notify([$owner->id], 'lifecycle.task_assigned', 'lifecycle_task', $taskId, 'task:'.$taskId);
        }

        return $id;
    }

    public function ownerEligible(User $actor, string $role, int $employee, string $date): bool
    {
        if (! AccountSecurity::available($actor)) {
            return false;
        }
        $assignment = Employee::findOrFail($employee)->assignments()->at($date)->first();
        if ($role === 'manager') {
            return $assignment && $assignment->manager_employee_id === $actor->employee_id && $actor->grants()->where('role', 'manager')->where('scope', 'team')->exists();
        }

        return $actor->grants()->where('role', $role)->get()->contains(fn ($g) => $g->scope === 'company' || ($assignment && (($g->scope === 'branch' && $g->branch_id === $assignment->branch_id) || ($g->scope === 'department' && $g->department_id === $assignment->department_id))));
    }

    public function task(User $actor, int $id, string $key, int $version, array $input): array
    {
        $d = Validator::make($input, ['action' => ['required', Rule::in(['complete', 'waive'])], 'evidence' => ['required', 'string', 'max:2000']])->validate();

        return Mutation::run($actor, 'lifecycle.task', $key, compact('id', 'version', 'd'), function ($u) use ($id, $d) {
            $task = DB::table('lifecycle_tasks')->where('id', $id)->firstOrFail();
            $case = DB::table('lifecycle_cases')->where('id', $task->lifecycle_case_id)->firstOrFail();
            abort_unless(($d['action'] === 'waive' && Access::companyPermission($u, 'employees.review')) || ($task->owner_id === $u->id && $this->ownerEligible($u, $task->role, $case->employee_id, $case->last_working_on ?? $case->effective_on)), 403);
        }, function ($u) use ($id, $version, $d) {
            $task = DB::table('lifecycle_tasks')->where('id', $id)->firstOrFail();
            CoreWrite::version($task->version, $version);
            abort_unless($task->status === 'open', 409);
            $dependencies = json_decode($task->dependencies, true);
            abort_if($d['action'] === 'complete' && DB::table('lifecycle_tasks')->where('lifecycle_case_id', $task->lifecycle_case_id)->whereIn('code', $dependencies)->whereNotIn('status', ['completed', 'waived'])->exists(), 422, 'Selesaikan dependency terlebih dahulu.');
            DB::table('lifecycle_tasks')->where('id', $id)->update(['status' => $d['action'] === 'waive' ? 'waived' : 'completed', 'evidence' => encrypt($d['evidence']), 'completed_by' => $u->id, 'completed_at' => now(), 'version' => $version + 1, 'updated_at' => now()]);
            DB::table('lifecycle_cases')->where('id', $task->lifecycle_case_id)->increment('version');
            Audit::record($u->id, 'lifecycle.task_'.$d['action'], 'lifecycle_task', $id, ['evidence_provided' => true]);

            return ['id' => $id, 'version' => $version + 1];
        });
    }
}
