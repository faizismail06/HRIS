<?php

namespace Tests\Feature;

use App\Domain\Identity\AccountSecurity;
use App\Domain\Lifecycle\EmploymentRevocations;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class LifecycleAssetsTest extends AttendanceScenarioCase
{
    private function template(string $kind, array $tasks): int
    {
        return $this->login($this->hr)->postJson('/app/api/lifecycle-templates', ['idempotency_key' => (string) Str::uuid(), 'code' => strtoupper($kind), 'kind' => $kind, 'tasks' => $tasks])->assertCreated()->json('id');
    }

    private function task(string $code, string $role, array $dependencies = []): array
    {
        return ['code' => $code, 'label' => 'Synthetic '.$code, 'role' => $role, 'mandatory' => true, 'due_offset_days' => 0, 'dependencies' => $dependencies];
    }

    private function define(string $kind): void
    {
        $this->login($this->hr)->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Synthetic '.$kind, 'kind' => $kind, 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
    }

    private function draft(User $actor, string $kind, array $payload): int
    {
        return $this->login($actor)->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => $kind, 'payload' => $payload])->assertCreated()->json('id');
    }

    private function submit(int $id)
    {
        return $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1]);
    }

    private function asset(User $it): int
    {
        return $this->login($it)->postJson('/app/api/assets', ['idempotency_key' => (string) Str::uuid(), 'code' => 'SYNTHETIC-LAPTOP', 'serial' => 'SYNTHETIC-123', 'category' => 'Laptop', 'branch_id' => $this->worker->employee->assignments()->sole()->branch_id, 'condition' => 'good'])->assertCreated()->json('id');
    }

    public function test_rehire_preserves_person_and_old_period_and_activation_requires_owned_dependency_tasks(): void
    {
        $officer = $this->user('hr_officer');
        $employee = $this->worker->employee;
        $assignment = $employee->assignments()->sole();
        $employee->assignments()->update(['valid_to' => '2026-10-01']);
        $employee->periods()->update(['ends_on' => '2026-10-01']);
        $template = $this->template('onboarding', [$this->task('identity_check', 'hr_officer'), $this->task('orientation', 'hr_officer', ['identity_check'])]);
        $input = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'employee_id' => $employee->id, 'lifecycle_template_id' => $template, 'starts_on' => '2026-10-19', 'branch_id' => $assignment->branch_id, 'department_id' => $assignment->department_id, 'job_id' => $assignment->job_id, 'owners' => ['identity_check' => $officer->id, 'orientation' => $officer->id]];
        $case = $this->login($officer)->postJson('/app/api/onboarding-cases', $input)->assertCreated()->json('id');
        $this->assertSame('PREBOARDING', $employee->fresh()->statusAt('2026-10-19'));
        $this->assertFalse(AccountSecurity::available($this->worker->fresh()));
        $this->define('onboarding');
        $draft = $this->draft($officer, 'onboarding', ['lifecycle_case_id' => $case, 'case_version' => 1]);
        $this->submit($draft)->assertUnprocessable();
        $tasks = DB::table('lifecycle_tasks')->where('lifecycle_case_id', $case)->orderBy('id')->get();
        $taskInput = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'action' => 'complete', 'evidence' => 'Synthetic verified evidence'];
        $this->postJson('/app/api/lifecycle-tasks/'.$tasks[1]->id, $taskInput)->assertUnprocessable();
        $this->login($this->hr)->postJson('/app/api/lifecycle-tasks/'.$tasks[0]->id, $taskInput)->assertForbidden();
        $this->login($officer)->postJson('/app/api/lifecycle-tasks/'.$tasks[0]->id, $taskInput)->assertOk();
        $taskInput['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/app/api/lifecycle-tasks/'.$tasks[1]->id, $taskInput)->assertOk();
        $draft = $this->draft($officer, 'onboarding', ['lifecycle_case_id' => $case, 'case_version' => 3]);
        $this->submit($draft)->assertOk();
        $this->login($this->hr)->postJson('/app/api/requests/'.$draft.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();
        $this->assertSame('ACTIVE', $employee->fresh()->statusAt('2026-10-19'));
        $this->assertTrue(AccountSecurity::available($this->worker->fresh()));
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('employment_periods', 2);
        $this->assertDatabaseHas('employment_periods', ['id' => $assignment->employment_period_id, 'ends_on' => '2026-10-01']);
    }

    public function test_exit_revokes_at_effective_end_even_while_asset_task_and_custody_remain_open(): void
    {
        $officer = $this->user('hr_officer');
        $it = $this->user('it_asset_officer');
        $asset = $this->asset($it);
        $this->postJson('/app/api/assets/'.$asset.'/issue', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'employee_id' => $this->worker->employee_id, 'evidence' => 'Synthetic laptop issue'])->assertCreated();
        $template = $this->template('offboarding', [$this->task('asset_clearance', 'it_asset_officer')]);
        $this->define('offboarding');
        $id = $this->draft($officer, 'offboarding', ['employee_id' => $this->worker->employee_id, 'employee_version' => 1, 'employment_period_id' => $this->worker->employee->periods()->sole()->id, 'lifecycle_template_id' => $template, 'effective_end_on' => '2026-10-21', 'last_working_on' => '2026-10-20', 'reason_class' => 'resignation', 'reason' => 'Synthetic private exit reason', 'owners' => ['asset_clearance' => $it->id]]);
        $this->submit($id)->assertOk();
        $this->login($this->hr)->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();
        $this->assertTrue(AccountSecurity::available($this->worker->fresh()));
        $this->assertSame('OFFBOARDING', $this->worker->employee->fresh()->statusAt('2026-10-20'));
        $this->travelTo(Carbon::parse('2026-10-21 00:01:00', 'Asia/Jakarta'));
        $this->assertFalse(AccountSecurity::available($this->worker->fresh()));
        $before = $this->worker->fresh()->security_version;
        app(EmploymentRevocations::class)->reconcile();
        app(EmploymentRevocations::class)->reconcile();
        $this->assertSame($before + 1, $this->worker->fresh()->security_version);
        $this->assertDatabaseHas('lifecycle_tasks', ['code' => 'asset_clearance', 'status' => 'open']);
        $this->assertDatabaseHas('asset_custodies', ['asset_id' => $asset, 'returned_at' => null]);
    }

    public function test_asset_has_one_custodian_and_lost_item_creates_settlement_without_salary_deduction(): void
    {
        $it = $this->user('it_asset_officer');
        $asset = $this->asset($it);
        $input = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'employee_id' => $this->worker->employee_id, 'evidence' => 'Synthetic handover'];
        $custody = $this->postJson('/app/api/assets/'.$asset.'/issue', $input)->assertCreated()->json('id');
        $input['expected_version'] = 2;
        $input['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/app/api/assets/'.$asset.'/issue', $input)->assertConflict();
        $this->login($this->worker)->postJson('/app/api/asset-custodies/'.$custody.'/accept', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($it)->postJson('/app/api/asset-custodies/'.$custody.'/return', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'condition' => 'lost', 'evidence' => 'Synthetic incident evidence'])->assertOk();
        $this->assertDatabaseCount('asset_custodies', 1);
        $this->assertDatabaseCount('asset_settlement_cases', 1);
        $this->assertDatabaseHas('assets', ['id' => $asset, 'status' => 'lost']);
        $settlement = DB::table('asset_settlement_cases')->sole();
        $resolution = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'resolution' => 'no_charge', 'evidence' => 'Synthetic Finance incident assessment'];
        $this->postJson('/app/api/asset-settlements/'.$settlement->id.'/resolve', $resolution)->assertForbidden();
        $finance = $this->user('finance_approver');
        $this->login($finance)->postJson('/app/api/asset-settlements/'.$settlement->id.'/resolve', $resolution)->assertOk();
        $this->assertDatabaseHas('asset_settlement_cases', ['id' => $settlement->id, 'status' => 'resolved', 'resolution' => 'no_charge', 'resolved_by' => $finance->id]);
        $this->assertDatabaseCount('employee_compensations', 0);
        $this->login($this->hr)->getJson('/app/api/assets')->assertForbidden();
    }

    public function test_clearance_reassignment_revokes_old_owner_and_closure_requires_completed_tasks(): void
    {
        $officer = $this->user('hr_officer');
        $oldOwner = $this->user('it_asset_officer');
        $newOwner = $this->user('it_asset_officer');
        $template = $this->template('offboarding', [$this->task('asset_clearance', 'it_asset_officer')]);
        $this->define('offboarding');
        $id = $this->draft($officer, 'offboarding', ['employee_id' => $this->worker->employee_id, 'employee_version' => 1, 'employment_period_id' => $this->worker->employee->periods()->sole()->id, 'lifecycle_template_id' => $template, 'effective_end_on' => '2026-10-21', 'last_working_on' => '2026-10-20', 'reason_class' => 'resignation', 'reason' => 'Synthetic exit', 'owners' => ['asset_clearance' => $oldOwner->id]]);
        $this->submit($id)->assertOk();
        $this->login($this->hr)->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();
        $case = DB::table('lifecycle_cases')->sole();
        $task = DB::table('lifecycle_tasks')->sole();
        $close = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $case->version, 'allow_open_assets' => false, 'reason' => 'Synthetic clearance reviewed'];
        $this->postJson('/app/api/lifecycle-cases/'.$case->id.'/close', $close)->assertUnprocessable();
        $this->postJson('/app/api/lifecycle-tasks/'.$task->id.'/reassign', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'owner_id' => $newOwner->id, 'reason' => 'Synthetic duty handover'])->assertOk();
        $complete = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'action' => 'complete', 'evidence' => 'Synthetic inventory checked'];
        $this->login($oldOwner)->postJson('/app/api/lifecycle-tasks/'.$task->id, $complete)->assertForbidden();
        $this->login($newOwner)->postJson('/app/api/lifecycle-tasks/'.$task->id, $complete)->assertOk();
        $close['expected_version'] = DB::table('lifecycle_cases')->find($case->id)->version;
        $this->login($this->hr)->postJson('/app/api/lifecycle-cases/'.$case->id.'/close', $close)->assertOk()->assertJsonPath('status', 'completed');
        $this->assertDatabaseCount('lifecycle_owner_changes', 1);
        $snapshot = json_decode(DB::table('lifecycle_cases')->find($case->id)->closure_snapshot, true);
        $this->assertSame('completed', $snapshot['tasks'][0]['status']);
        $this->assertTrue(AccountSecurity::available($this->worker->fresh()));
    }
}
