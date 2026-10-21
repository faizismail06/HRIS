<?php

namespace Tests\Feature;

use App\Domain\Operations\Outbox;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Job;
use App\Models\LeaveType;
use App\Models\RosterShift;
use App\Models\RosterVersion;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class WorkflowWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    private User $hr;

    private User $worker;

    private RosterShift $shift;

    private LeaveType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 0));
        Company::create(['name' => 'Synthetic UI integration']);
        $b = Branch::create(['company_id' => 1, 'code' => 'UI', 'name' => 'UI Branch']);
        $d = Department::create(['company_id' => 1, 'code' => 'UI', 'name' => 'UI Department']);
        $j = Job::create(['department_id' => $d->id, 'code' => 'UI', 'name' => 'UI Job']);
        $employee = Employee::create(['employee_number' => 'UI-TEST', 'name' => 'Synthetic UI Person']);
        $period = $employee->periods()->create(['starts_on' => '2020-01-01']);
        $employee->assignments()->create(['employment_period_id' => $period->id, 'branch_id' => $b->id, 'department_id' => $d->id, 'job_id' => $j->id, 'valid_from' => '2020-01-01']);
        $this->hr = $this->user('hr_admin');
        $this->worker = $this->user('employee', $employee->id);
        $this->type = LeaveType::create(['code' => 'UI', 'version' => 1, 'approved_by' => $this->hr->id, 'name' => 'Synthetic UI leave', 'is_paid' => true, 'deducts_balance' => false, 'allows_half_day' => false, 'requires_evidence' => true, 'notice_days' => 0, 'minimum_tenure_days' => 0]);
        WorkflowDefinition::create(['name' => 'UI leave', 'kind' => 'leave', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']], 'version' => 1, 'created_by' => $this->hr->id]);
        $version = RosterVersion::create(['status' => 'published', 'version' => 1, 'created_by' => $this->hr->id, 'checksum' => str_repeat('a', 64)]);
        $this->shift = RosterShift::create(['roster_version_id' => $version->id, 'employee_id' => $employee->id, 'work_date' => '2026-10-20', 'starts_at' => '2026-10-20T09:00:00+09:00', 'ends_at' => '2026-10-20T17:00:00+09:00', 'timezone' => 'Asia/Jayapura', 'is_day_off' => false, 'status' => 'published', 'snapshot' => ['branch_id' => $b->id, 'department_id' => $d->id, 'breaks' => [], 'half_day_split_minute' => 240, 'clock_in_before_minutes' => 120, 'clock_out_after_minutes' => 240, 'tolerance_minutes' => 0]]);
    }

    private function user(string $role, ?int $employee = null): User
    {
        $u = User::factory()->create();
        $u->forceFill(['employee_id' => $employee, 'is_active' => true, 'two_factor_secret' => Fortify::currentEncrypter()->encrypt('SYNTHETICUIKEYAAA'), 'two_factor_confirmed_at' => now()])->save();
        $u->grants()->create(['role' => $role, 'scope' => $role === 'employee' ? 'self' : 'company']);

        return $u->fresh();
    }

    private function login(User $u): static
    {
        $this->actingAs($u)->withSession(['auth.security_version' => $u->security_version, 'auth.mfa_user' => $u->id]);
        session()->save();

        return $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function draft(?string $key = null): WorkflowRequest
    {
        $this->login($this->worker)->post('/app/requests', ['idempotency_key' => $key ?? (string) Str::uuid(), 'kind' => 'leave', 'leave_type_id' => $this->type->id, 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20', 'segment' => 'full', 'reason' => 'Private synthetic medical reason'])->assertRedirect();

        return WorkflowRequest::latest('id')->firstOrFail();
    }

    public function test_html_leave_evidence_submit_and_independent_review_use_domain_services(): void
    {
        Storage::fake('local');
        $this->login($this->worker)->get('/app/requests/create')->assertOk()->assertSee('Simpan draf')->assertSee('Synthetic UI leave');
        $key = (string) Str::uuid();
        $record = $this->draft($key);
        $this->draft($key);
        $this->assertDatabaseCount('workflow_requests', 1);
        $this->get('/app/requests/'.$record->id)->assertOk()->assertSee('Private synthetic medical reason')->assertSee('Kirim untuk ditinjau')->assertHeader('Cache-Control', 'no-store, private');
        $this->from('/app/requests/'.$record->id)->post('/app/requests/'.$record->id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertSessionHasErrors('operation');
        $this->assertSame('draft', $record->fresh()->status);
        $this->post('/app/requests/'.$record->id.'/attachments', ['idempotency_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->image('proof.png')])->assertRedirect('/app/requests/'.$record->id);
        $this->post('/app/requests/'.$record->id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertRedirect('/app/requests/'.$record->id);
        $this->assertSame('in_review', $record->fresh()->status);
        $this->post('/app/requests/'.$record->id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertForbidden();
        $this->login($this->hr)->get('/app/requests/'.$record->id)->assertOk()->assertSee('Keputusan Anda')->assertSee('Private synthetic medical reason');
        $decision = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'];
        $this->post('/app/requests/'.$record->id.'/decide', $decision)->assertRedirect('/app/requests/'.$record->id);
        $this->post('/app/requests/'.$record->id.'/decide', $decision)->assertRedirect('/app/requests/'.$record->id);
        $this->assertSame('approved', $record->fresh()->status);
        $this->assertDatabaseCount('workflow_decisions', 1);
        $this->assertDatabaseHas('leave_segments', ['workflow_request_id' => $record->id, 'state' => 'approved']);
    }

    public function test_list_and_detail_hide_other_users_and_stale_mutations_can_be_recovered(): void
    {
        $record = $this->draft();
        $this->get('/app/requests?status=draft')->assertOk()->assertSee('Cuti #'.$record->id);
        $this->from('/app/requests/'.$record->id)->post('/app/requests/'.$record->id.'/withdraw', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 99, 'withdrawal_reason' => 'Synthetic withdrawal'])->assertRedirect('/app/requests/'.$record->id)->assertSessionHasErrors('operation');
        $this->assertSame('draft', $record->fresh()->status);
        $outsider = $this->user('employee');
        $this->login($outsider)->get('/app/requests')->assertOk()->assertDontSee('Cuti #'.$record->id);
        $this->get('/app/requests/'.$record->id)->assertNotFound();
        $this->post('/app/requests/'.$record->id.'/withdraw', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'withdrawal_reason' => 'Unauthorized'])->assertNotFound();
        $this->login($this->worker)->post('/app/requests/'.$record->id.'/withdraw', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'withdrawal_reason' => 'Synthetic withdrawal'])->assertRedirect();
        $this->assertSame('withdrawn', $record->fresh()->status);
    }

    public function test_overtime_form_uses_selected_shift_timezone_and_rejects_tampered_kind(): void
    {
        $this->login($this->worker)->get('/app/requests/create?kind=overtime')->assertOk()->assertSee('Asia/Jayapura')->assertSee('planned_start');
        $this->post('/app/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'overtime', 'roster_shift_id' => $this->shift->id, 'planned_start' => '2026-10-20T18:00', 'planned_end' => '2026-10-20T20:00', 'task' => 'Synthetic task', 'reason' => 'Synthetic overtime'])->assertRedirect();
        $record = WorkflowRequest::latest('id')->firstOrFail();
        $this->assertSame('2026-10-20T18:00:00+09:00', $record->payload['planned_start']);
        $this->assertSame('2026-10-20T20:00:00+09:00', $record->payload['planned_end']);
        $this->post('/app/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'compensation'])->assertSessionHasErrors('kind');
        $this->get('/app/requests/create?kind=compensation')->assertSessionHasErrors('kind');
        $this->assertDatabaseCount('workflow_requests', 1);
    }

    public function test_manager_can_review_leave_without_medical_reason_or_evidence_metadata(): void
    {
        Storage::fake('local');
        $record = $this->draft();
        $this->post('/app/requests/'.$record->id.'/attachments', ['idempotency_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->image('proof.png')])->assertRedirect();
        $managerPerson = Employee::create(['employee_number' => 'UI-MGR', 'name' => 'Synthetic Manager']);
        $manager = $this->user('employee', $managerPerson->id);
        $manager->grants()->create(['role' => 'manager', 'scope' => 'team']);
        Employee::findOrFail($this->worker->employee_id)->assignments()->update(['manager_employee_id' => $managerPerson->id]);
        $record->update(['status' => 'in_review', 'version' => 2]);
        $record->steps()->create(['position' => 1, 'role' => 'manager', 'base_approver_ids' => [$manager->id], 'approver_ids' => [$manager->id], 'status' => 'pending']);
        $this->login($manager)->get('/app/requests/'.$record->id)->assertOk()->assertSee('Keputusan Anda')->assertDontSee('Private synthetic medical reason')->assertDontSee('Unduh bukti #');
        $attachment = DB::table('private_attachments')->value('id');
        $this->get('/app/api/attachments/'.$attachment.'/download')->assertNotFound();
        $this->login($this->worker)->get('/app/requests/'.$record->id)->assertOk()->assertSee('Private synthetic medical reason')->assertSee('Unduh bukti #');
    }

    public function test_notification_read_archive_and_search_use_live_visibility(): void
    {
        $record = $this->draft();
        Outbox::notify([$this->worker->id], 'request.submitted', 'workflow_request', $record->id, 'ui-test-'.$record->id);
        $message = DB::table('inbox_messages')->where('user_id', $this->worker->id)->firstOrFail();
        $this->get('/app/notifications')->assertOk()->assertSee('Pengajuan telah dikirim')->assertSee('Buka pengajuan #'.$record->id)->assertDontSee('Private synthetic medical reason');
        $this->post('/app/notifications/'.$message->id, ['action' => 'read'])->assertRedirect('/app/notifications');
        $this->assertNotNull(DB::table('inbox_messages')->where('id', $message->id)->value('read_at'));
        $this->get('/app/search')->assertOk()->assertSee('Minimal dua karakter');
        $this->get('/app/search?q=leave')->assertOk()->assertSee(route('workspace.requests.show', $record->id))->assertDontSee('Private synthetic medical reason');
        $this->get('/app/search?q=l')->assertSessionHasErrors('q');
        $this->login($this->hr)->post('/app/notifications/'.$message->id, ['action' => 'archive'])->assertNotFound();
        $outsider = $this->user('employee');
        $this->login($outsider)->get('/app/search?q=leave')->assertOk()->assertDontSee(route('workspace.requests.show', $record->id))->assertSee('Belum ditemukan hasil');
        $this->get('/app/notifications')->assertOk()->assertDontSee('Buka pengajuan #'.$record->id);
        $this->login($this->worker)->post('/app/notifications/'.$message->id, ['action' => 'archive'])->assertRedirect('/app/notifications');
        $this->get('/app/notifications')->assertOk()->assertDontSee('Buka pengajuan #'.$record->id);
    }

    public function test_remaining_forms_and_invalid_status_are_rendered_or_validated(): void
    {
        $this->login($this->worker)->get('/app/requests/create?kind=correction')->assertOk()->assertSee('effective_in')->assertSee('effective_out')->assertSee('Asia/Jayapura');
        $this->get('/app/requests/create?kind=leave_cancel')->assertOk()->assertSee('Belum ada cuti disetujui')->assertSee('original_request_id');
        $this->get('/app/requests?status=unknown')->assertSessionHasErrors('status');
        $this->get('/app/search?before_id=bad')->assertOk();
        $this->get('/app/notifications?before_id=bad')->assertSessionHasErrors('before_id');
    }
}
