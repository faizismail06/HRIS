<?php

namespace Tests\Feature;

use App\Domain\Operations\Outbox;
use App\Domain\Workflow\WorkflowRegistry;
use App\Jobs\DeliverOutbox;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Tests\Support\TestWorkflowHandler;
use Tests\TestCase;

class WorkflowOperationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        Company::create(['name' => 'Synthetic Workflow Engine Test']);
        app(WorkflowRegistry::class)->register('test', TestWorkflowHandler::class);
    }

    private function user(string $role = 'hr_admin', bool $employee = false): User
    {
        $user = User::factory()->create();
        $person = $employee ? Employee::create(['employee_number' => 'TEST-'.$user->id, 'name' => 'Synthetic Person']) : null;
        $user->forceFill(['is_active' => true, 'employee_id' => $person?->id, 'two_factor_secret' => Fortify::currentEncrypter()->encrypt('SYNTHETICTESTKEYA'), 'two_factor_confirmed_at' => now()])->save();
        $user->grants()->create(['role' => $role, 'scope' => $role === 'employee' ? 'self' : 'company']);

        return $user->fresh();
    }

    private function login(User $u): static
    {
        $this->actingAs($u)->withSession(['auth.security_version' => $u->security_version, 'auth.mfa_user' => $u->id]);
        session()->save();

        return $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function define(array $roles = ['hr_admin']): void
    {
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Test workflow', 'kind' => 'test', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => array_map(fn ($role) => ['role' => $role], $roles)])->assertOk();
    }

    private function draft(): int
    {
        return $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'test', 'payload' => ['reason' => 'Private synthetic explanation']])->assertCreated()->json('id');
    }

    private function action(int $id, string $action, int $version, array $extra = [])
    {
        return $this->postJson('/app/api/requests/'.$id.'/'.$action, ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $version] + $extra);
    }

    public function test_stages_are_ordered_effect_is_exactly_once_and_retry_is_bound_to_inputs(): void
    {
        $admin = $this->user();
        $owner = $this->user('employee', true);
        $this->login($admin);
        $this->define(['hr_admin', 'hr_admin']);
        $this->login($owner);
        $id = $this->draft();
        $this->action($id, 'submit', 1)->assertOk()->assertJsonPath('version', 2);
        $this->action($id, 'decide', 2, ['decision' => 'approve'])->assertForbidden();
        $this->login($admin);
        $this->action($id, 'decide', 2, ['decision' => 'approve'])->assertOk()->assertJsonPath('status', 'in_review');
        $this->assertDatabaseCount('workflow_decisions', 1);
        $this->assertDatabaseMissing('audit_entries', ['action' => 'test.effect']);
        $key = (string) Str::uuid();
        $input = ['idempotency_key' => $key, 'expected_version' => 3, 'decision' => 'approve'];
        $first = $this->postJson('/app/api/requests/'.$id.'/decide', $input)->assertOk()->assertJsonPath('status', 'approved')->json();
        $this->postJson('/app/api/requests/'.$id.'/decide', $input)->assertOk()->assertExactJson($first);
        $this->postJson('/app/api/requests/'.$id.'/decide', array_replace($input, ['decision' => 'reject', 'reason' => 'Different']))->assertConflict();
        $this->assertSame(1, DB::table('audit_entries')->where('action', 'test.effect')->count());
        $this->assertDatabaseCount('workflow_decisions', 2);
        $this->getJson('/app/api/requests/'.$id)->assertOk()->assertDontSee('Private synthetic explanation');
        $this->login($owner);
        $this->action($id, 'withdraw', 4, ['reason' => 'Too late'])->assertConflict();
    }

    public function test_missing_approver_blocks_and_explicit_hr_assignment_is_audited(): void
    {
        $admin = $this->user();
        $owner = $this->user('employee', true);
        $this->login($admin);
        $this->define(['manager']);
        $this->login($owner);
        $id = $this->draft();
        $this->action($id, 'submit', 1)->assertOk()->assertJsonPath('status', 'blocked');
        $this->assertDatabaseMissing('audit_entries', ['action' => 'test.effect']);
        $this->login($admin);
        // Test fixture kind is not an HR domain, so the HR override must fail closed.
        $this->action($id, 'reassign', 2, ['replacement_id' => $admin->id, 'reason' => 'Synthetic exception'])->assertForbidden();
        $this->assertSame('blocked', WorkflowRequest::find($id)->status);
    }

    public function test_wrong_user_ids_and_disabled_actor_cannot_use_cached_mutations_or_inbox(): void
    {
        $admin = $this->user();
        $owner = $this->user('employee', true);
        $other = $this->user('employee', true);
        $this->login($admin);
        $this->define();
        $this->login($owner);
        $id = $this->draft();
        $this->action($id, 'submit', 1)->assertOk();
        $message = DB::table('inbox_messages')->where('user_id', $owner->id)->firstOrFail();
        $this->login($other);
        $this->getJson('/app/api/requests/'.$id)->assertNotFound();
        $this->action($id, 'submit', 2)->assertNotFound();
        $this->patchJson('/app/api/inbox/'.$message->id, ['action' => 'read'])->assertNotFound();
        $this->getJson('/app/api/requests')->assertOk()->assertJsonCount(0, 'data');
        $other->forceFill(['is_active' => false])->save();
        $this->getJson('/app/api/inbox')->assertUnauthorized();
    }

    public function test_rejection_and_withdrawal_release_once_and_never_run_final_effect(): void
    {
        $admin = $this->user();
        $owner = $this->user('employee', true);
        $this->login($admin);
        $this->define();
        $this->login($owner);
        $id = $this->draft();
        $this->action($id, 'submit', 1)->assertOk();
        $this->login($admin);
        $this->action($id, 'decide', 2, ['decision' => 'reject'])->assertUnprocessable();
        $this->action($id, 'decide', 2, ['decision' => 'reject', 'reason' => 'Synthetic rejection'])->assertOk();
        $this->login($owner);
        $id2 = $this->draft();
        $this->action($id2, 'submit', 1)->assertOk();
        $this->action($id2, 'withdraw', 2, ['reason' => 'No longer needed'])->assertOk();
        $this->action($id2, 'withdraw', 2, ['reason' => 'Retry with different key'])->assertConflict();
        $this->assertSame(2, DB::table('audit_entries')->where('action', 'test.released')->count());
        $this->assertDatabaseMissing('audit_entries', ['action' => 'test.effect']);
        $this->assertStringNotContainsString('Synthetic rejection', json_encode(DB::table('audit_entries')->get()));
    }

    public function test_outbox_dedup_inbox_read_and_email_payload_exclude_private_request_details(): void
    {
        Mail::fake();
        $admin = $this->user();
        $owner = $this->user('employee', true);
        $this->login($admin);
        $this->define();
        $this->login($owner);
        $id = $this->draft();
        DB::transaction(function () use ($owner, $id) {
            Outbox::notify([$owner->id], 'request.submitted', 'workflow_request', $id, 'test:dedup');
            Outbox::notify([$owner->id], 'request.submitted', 'workflow_request', $id, 'test:dedup');
        });
        $this->assertDatabaseCount('inbox_messages', 1);
        $this->assertDatabaseCount('notification_deliveries', 1);
        $message = DB::table('inbox_messages')->first();
        $this->getJson('/app/api/inbox')->assertOk()->assertDontSee('Private synthetic explanation');
        $this->patchJson('/app/api/inbox/'.$message->id, ['action' => 'read'])->assertOk();
        $this->assertNotNull(DB::table('inbox_messages')->first()->read_at);
        $job = new DeliverOutbox(DB::table('notification_deliveries')->first()->id);
        $job->handle();
        $job->handle();
        $this->assertSame('sent', DB::table('notification_deliveries')->first()->status);
    }

    public function test_attachment_is_private_parent_scoped_and_rejects_invalid_image(): void
    {
        Storage::fake('local');
        $owner = $this->user('employee', true);
        $other = $this->user('employee', true);
        $this->login($owner);
        $id = $this->draft();
        $this->post('/app/api/requests/'.$id.'/attachments', ['idempotency_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('fake.jpg', 'invalid image')], ['Accept' => 'application/json'])->assertUnprocessable();
        $response = $this->post('/app/api/requests/'.$id.'/attachments', ['idempotency_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->image('photo.png', 32, 32)], ['Accept' => 'application/json'])->assertCreated();
        $attachment = $response->json('id');
        $this->get('/app/api/attachments/'.$attachment.'/download')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->login($other)->getJson('/app/api/attachments/'.$attachment.'/download')->assertNotFound();
        $this->assertDatabaseCount('private_attachments', 1);
    }

    public function test_dated_suspension_blocks_runtime_without_changing_employment(): void
    {
        $owner = $this->user('employee', true);
        $admin = $this->user();
        DB::table('account_suspensions')->insert(['user_id' => $owner->id, 'created_by' => $admin->id, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(), 'reason' => encrypt('Test suspension'), 'created_at' => now(), 'updated_at' => now()]);
        $this->login($owner)->getJson('/app/api/inbox')->assertUnauthorized();
        $this->assertTrue($owner->fresh()->is_active);
    }
}
