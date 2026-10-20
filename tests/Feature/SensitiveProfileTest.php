<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeePrivateProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class SensitiveProfileTest extends AttendanceScenarioCase
{
    private function definition(string $kind, string $role): void
    {
        $this->login($this->hr)->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Synthetic '.$kind, 'kind' => $kind, 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => $role]]])->assertOk();
    }

    private function propose(string $kind, string $category, array $values, int $base = 0): int
    {
        $this->login($this->worker);

        return $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => $kind, 'payload' => ['employee_id' => $this->worker->employee_id, 'category' => $category, 'base_version' => $base, 'data' => $values, 'reason' => 'Synthetic private verification reason']])->assertCreated()->json('id');
    }

    private function submit(int $id): void
    {
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
    }

    private function decide(User $actor, int $id, ?string $key = null)
    {
        $this->login($actor);

        return $this->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => $key ?? (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve']);
    }

    public function test_bank_verification_is_finance_only_encrypted_private_and_retry_safe(): void
    {
        $finance = $this->user('finance_approver');
        $this->definition('bank_change', 'finance_approver');
        $number = '9988776655443322';
        $id = $this->propose('bank_change', 'bank', ['bank_code' => 'TEST', 'account_number' => $number, 'account_name' => 'Synthetic Worker']);
        $this->submit($id);
        $this->getJson('/app/api/requests/'.$id)->assertOk()->assertDontSee($number);
        $this->login($this->hr)->getJson('/app/api/requests/'.$id.'/proposed-profile')->assertNotFound();
        $this->login($finance)->getJson('/app/api/requests/'.$id.'/proposed-profile')->assertOk()->assertJsonPath('data.account_number', $number)->assertHeader('Cache-Control', 'no-store, private');
        $this->decide($this->worker, $id)->assertForbidden();
        $key = (string) Str::uuid();
        $this->decide($finance, $id, $key)->assertOk();
        $this->decide($finance, $id, $key)->assertOk();
        $profile = EmployeePrivateProfile::sole();
        $this->assertSame($number, $profile->data['account_number']);
        $this->assertSame($finance->id, $profile->verified_by);
        $this->assertStringNotContainsString($number, DB::table('employee_private_profiles')->value('data'));
        $this->assertStringNotContainsString($number, json_encode(DB::table('audit_entries')->get()));
        $this->assertDatabaseCount('private_profile_revisions', 1);
        $this->login($this->hr)->getJson('/app/api/employees/'.$this->worker->employee_id.'/private-profile/bank')->assertNotFound();
        $this->login($this->worker)->getJson('/app/api/employees/'.$this->worker->employee_id.'/private-profile/bank')->assertOk()->assertJsonPath('version', 1);
    }

    public function test_profile_version_conflicts_do_not_overwrite_and_general_edits_cannot_move_department(): void
    {
        $this->definition('profile_change', 'hr_admin');
        $one = $this->propose('profile_change', 'general', ['phone' => '0811111111']);
        $this->submit($one);
        $two = $this->propose('profile_change', 'general', ['phone' => '0822222222']);
        $this->submit($two);
        $this->decide($this->hr, $one)->assertOk();
        $this->decide($this->hr, $two)->assertConflict();
        $this->assertSame('0811111111', EmployeePrivateProfile::sole()->data['phone']);
        $this->login($this->worker)->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'profile_change', 'payload' => ['employee_id' => $this->worker->employee_id, 'category' => 'general', 'base_version' => 1, 'reason' => 'Attempt bypass', 'data' => ['department_id' => 1]]])->assertUnprocessable();
    }

    public function test_typed_custom_fields_reject_unknown_and_invalid_values(): void
    {
        $this->login($this->hr)->postJson('/app/api/profile-fields', ['idempotency_key' => (string) Str::uuid(), 'code' => 'uniform_size', 'label' => 'Uniform size', 'type' => 'select', 'options' => ['S', 'M', 'L']])->assertCreated();
        $this->definition('profile_change', 'hr_admin');
        $this->login($this->worker);
        $payload = ['employee_id' => $this->worker->employee_id, 'category' => 'general', 'base_version' => 0, 'reason' => 'Synthetic', 'data' => ['custom' => ['uniform_size' => 'XXL']]];
        $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'profile_change', 'payload' => $payload])->assertUnprocessable();
        $payload['data'] = ['custom' => ['secret_role' => 'admin']];
        $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'profile_change', 'payload' => $payload])->assertUnprocessable();
        $id = $this->propose('profile_change', 'general', ['custom' => ['uniform_size' => 'M']]);
        $this->submit($id);
        $this->decide($this->hr, $id)->assertOk();
    }

    public function test_suspension_is_separate_from_employment_and_last_admin_is_protected(): void
    {
        $admin = $this->user('platform_admin');
        $this->login($admin);
        $input = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $admin->security_version, 'starts_at' => now()->toIso8601String(), 'reason' => 'Synthetic restriction'];
        $this->postJson('/app/api/accounts/'.$admin->id.'/suspensions', $input)->assertUnprocessable();
        $input['expected_version'] = $this->worker->security_version;
        $input['idempotency_key'] = (string) Str::uuid();
        $id = $this->postJson('/app/api/accounts/'.$this->worker->id.'/suspensions', $input)->assertCreated()->json('id');
        $this->assertSame('ACTIVE', $this->worker->employee->statusAt(today()->toDateString()));
        $this->login($this->worker->fresh())->getJson('/app/api/inbox')->assertUnauthorized();
        $this->login($admin)->postJson('/app/api/account-suspensions/'.$id.'/revoke', ['idempotency_key' => (string) Str::uuid(), 'reason' => 'Synthetic restriction ended'])->assertOk();
        $this->login($this->worker->fresh())->getJson('/app/api/inbox')->assertOk();
    }

    public function test_national_identity_is_unique_and_failed_verification_creates_no_revision(): void
    {
        $this->definition('identity_change', 'hr_admin');
        $values = ['national_id' => '1234567890123456', 'legal_name' => 'Synthetic Identity'];
        $first = $this->propose('identity_change', 'identity', $values);
        $this->submit($first);
        $this->decide($this->hr, $first)->assertOk();
        $other = Employee::create(['employee_number' => 'SECOND', 'name' => 'Synthetic Other']);
        $this->worker = $this->user('employee', $other->id);
        $second = $this->propose('identity_change', 'identity', $values);
        $this->submit($second);
        $this->decide($this->hr, $second)->assertUnprocessable();
        $this->assertDatabaseCount('employee_private_profiles', 1);
        $this->assertDatabaseCount('private_profile_revisions', 1);
        $this->assertDatabaseHas('workflow_requests', ['id' => $second, 'status' => 'in_review']);
    }
}
