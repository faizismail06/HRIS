<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\Job;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class CoreHrWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        Company::create(['name' => 'Synthetic Workflow Test']);
    }

    private function actor(string $role = 'hr_admin', string $scope = 'company', array $targets = []): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true, 'two_factor_secret' => Fortify::currentEncrypter()->encrypt('TESTINGSECRETKEYA'), 'two_factor_confirmed_at' => now()])->save();
        $user->grants()->create(['role' => $role, 'scope' => $scope] + $targets);

        return $user->fresh();
    }

    private function signedIn(User $user): static
    {
        $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.mfa_user' => $user->id]);
        session()->save();

        return $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function organization(string $code): array
    {
        $branch = Branch::create(['company_id' => 1, 'code' => $code, 'name' => 'Branch '.$code]);
        $department = Department::create(['company_id' => 1, 'code' => $code, 'name' => 'Department '.$code]);
        $job = Job::create(['department_id' => $department->id, 'code' => $code, 'name' => 'Job '.$code]);

        return ['branch_id' => $branch->id, 'department_id' => $department->id, 'job_id' => $job->id, 'manager_employee_id' => null];
    }

    private function employee(string $code, array $organization): Employee
    {
        $employee = Employee::create(['employee_number' => $code, 'name' => 'Person '.$code]);
        $period = $employee->periods()->create(['starts_on' => '2026-01-01']);
        $employee->assignments()->create($organization + ['employment_period_id' => $period->id, 'valid_from' => '2026-01-01']);

        return $employee->fresh();
    }

    private function propose(Employee $employee, array $values): EmployeeChangeRequest
    {
        $this->postJson('/app/employees/'.$employee->id.'/changes', $values + ['base_version' => $employee->version, 'reason' => 'Synthetic work change'])->assertRedirect();

        return EmployeeChangeRequest::latest('id')->firstOrFail();
    }

    private function review(EmployeeChangeRequest $change, string $decision = 'approve')
    {
        return $this->postJson('/app/employee-changes/'.$change->id.'/review', ['decision' => $decision, 'version' => $change->version, 'review_note' => 'Reviewed synthetic work change']);
    }

    public function test_organization_forms_store_update_archive_and_reject_stale_versions(): void
    {
        $this->signedIn($this->actor())->get('/app/organization')->assertOk()->assertSee('Belum ada cabang');
        $this->get('/app/organization/branches/create')->assertOk();
        $this->post('/app/organization/branches', ['code' => 'JKT', 'name' => 'Jakarta', 'timezone' => 'Asia/Jakarta', 'is_active' => 1])->assertRedirect('/app/organization/branches');
        $branch = Branch::sole();
        $this->get('/app/organization/branches/'.$branch->id.'/edit')->assertOk()->assertSee('Jakarta');
        $this->putJson('/app/organization/branches/'.$branch->id, ['version' => 1, 'code' => 'JKT', 'name' => 'Jakarta HQ', 'timezone' => 'Asia/Jakarta', 'is_active' => 1])->assertRedirect();
        $this->putJson('/app/organization/branches/'.$branch->id, ['version' => 1, 'code' => 'JKT', 'name' => 'Stale', 'timezone' => 'Asia/Jakarta', 'is_active' => 1])->assertConflict();
        $this->assertSame('Jakarta HQ', $branch->fresh()->name);
        $this->putJson('/app/organization/branches/'.$branch->id, ['version' => 2, 'code' => 'JKT', 'name' => 'Jakarta HQ', 'timezone' => 'Asia/Jakarta', 'is_active' => 0])->assertRedirect();
        $this->assertFalse($branch->fresh()->is_active);
        $this->assertDatabaseCount('audit_entries', 3);
    }

    public function test_department_cycle_and_archiving_active_children_are_rejected_atomically(): void
    {
        $this->signedIn($this->actor());
        $this->postJson('/app/organization/departments', ['code' => 'P', 'name' => 'Parent', 'is_active' => 1])->assertRedirect();
        $parent = Department::sole();
        $this->postJson('/app/organization/departments', ['code' => 'C', 'name' => 'Child', 'parent_id' => $parent->id, 'is_active' => 1])->assertRedirect();
        $child = Department::where('code', 'C')->firstOrFail();
        $this->putJson('/app/organization/departments/'.$parent->id, ['version' => 1, 'code' => 'P', 'name' => 'Parent', 'parent_id' => $child->id, 'is_active' => 1])->assertUnprocessable();
        $this->putJson('/app/organization/departments/'.$parent->id, ['version' => 1, 'code' => 'P', 'name' => 'Parent', 'is_active' => 0])->assertUnprocessable();
        $this->assertNull($parent->fresh()->parent_id);
        $this->assertTrue($parent->fresh()->is_active);
        $this->assertSame(1, $parent->fresh()->version);
        $this->get('/app/organization/departments')->assertOk()->assertSee('Child');
        $this->get('/app/organization/jobs/create')->assertOk();
        $this->postJson('/app/organization/jobs', ['code' => 'ROLE', 'name' => 'Role', 'department_id' => $child->id, 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('job_positions', ['code' => 'ROLE', 'department_id' => $child->id]);
    }

    public function test_current_and_future_master_references_prevent_archive_but_history_is_preserved(): void
    {
        $organization = $this->organization('A');
        $employee = $this->employee('A', $organization);
        $this->signedIn($this->actor());
        $body = ['code' => 'A', 'name' => 'Branch A', 'timezone' => 'Asia/Jakarta', 'version' => 1, 'is_active' => 0];
        $this->putJson('/app/organization/branches/'.$organization['branch_id'], $body)->assertUnprocessable();
        $employee->assignments()->update(['valid_to' => '2026-10-01']);
        $employee->periods()->update(['ends_on' => '2026-10-01']);
        $this->putJson('/app/organization/branches/'.$organization['branch_id'], $body)->assertRedirect();
        $this->assertSame(1, $employee->assignments()->count());
        $this->assertFalse(Branch::find($organization['branch_id'])->is_active);
        $this->delete('/app/organization/branches/'.$organization['branch_id'])->assertMethodNotAllowed();
    }

    public function test_role_and_scope_limits_apply_to_forms_and_direct_mutation_requests(): void
    {
        $organization = $this->organization('A');
        $employee = $this->employee('A', $organization);
        foreach ([$this->actor('platform_admin'), $this->actor('auditor'), $this->actor('hr_admin', 'branch', ['branch_id' => $organization['branch_id']]), $this->actor('employee', 'self')] as $actor) {
            $this->signedIn($actor)->get('/app/organization')->assertForbidden();
            $this->postJson('/app/organization/branches', [])->assertForbidden();
            $this->get('/app/employees/create')->assertForbidden();
            $this->postJson('/app/employees', [])->assertForbidden();
            $this->get('/app/employees/'.$employee->id.'/change')->assertForbidden();
            $this->get('/app/employee-changes')->assertForbidden();
            $this->get('/app/employee-changes/123')->assertForbidden();
        }
        $this->signedIn($this->actor('hr_officer'))->get('/app/organization')->assertForbidden();
        $this->get('/app/employees/create')->assertOk();
    }

    public function test_employee_creation_is_atomic_whitelisted_and_initial_assignment_is_visible(): void
    {
        $organization = $this->organization('A');
        $this->signedIn($this->actor('hr_officer'))->get('/app/employees/create')->assertOk();
        $this->postJson('/app/employees', $organization + ['employee_number' => 'NEW', 'name' => 'New Person', 'work_email' => 'work@example.test', 'starts_on' => '2026-10-20', 'salary' => 999, 'version' => 99])->assertRedirect();
        $employee = Employee::sole();
        $this->assertSame(1, $employee->version);
        $this->assertSame('ACTIVE', $employee->statusAt('2026-10-20'));
        $this->assertSame(1, $employee->periods()->count());
        $this->assertSame(1, $employee->assignments()->count());
        $this->get('/app/employees/'.$employee->id)->assertOk()->assertSee('New Person')->assertSee('Branch A')->assertSee('seterusnya');
        $this->get('/app/employees')->assertOk()->assertSee('Tambah karyawan');
        $this->assertStringNotContainsString('salary', json_encode(DB::table('audit_entries')->get()));
    }

    public function test_invalid_initial_placement_or_inactive_master_creates_no_partial_employee(): void
    {
        $a = $this->organization('A');
        $b = $this->organization('B');
        $this->signedIn($this->actor());
        $data = $a + ['employee_number' => 'NEW', 'name' => 'New Person', 'starts_on' => '2026-10-20'];
        $data['job_id'] = $b['job_id'];
        $this->postJson('/app/employees', $data)->assertUnprocessable()->assertJsonValidationErrors('job_id');
        $data['job_id'] = $a['job_id'];
        Branch::find($a['branch_id'])->update(['is_active' => false]);
        $this->postJson('/app/employees', $data)->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('employment_periods', 0);
        $this->assertDatabaseCount('assignments', 0);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_identity_change_requires_another_admin_and_retains_original_comparison_after_approval(): void
    {
        $employee = $this->employee('A', $this->organization('A'));
        $proposer = $this->actor('hr_admin');
        $reviewer = $this->actor('hr_admin');
        $this->signedIn($proposer)->get('/app/employees/'.$employee->id.'/change')->assertOk();
        $change = $this->propose($employee, ['kind' => 'identity', 'name' => 'Updated Person', 'work_email' => 'updated@example.test', 'employee_number' => 'HACK']);
        $this->assertSame('Person A', $employee->fresh()->name);
        $this->review($change)->assertForbidden();
        $this->get('/app/employee-changes/'.$change->id)->assertOk()->assertSee('Menunggu HR Admin lain');
        $this->signedIn($reviewer);
        $this->review($change)->assertRedirect();
        $this->assertSame('Updated Person', $employee->fresh()->name);
        $this->assertSame('A', $employee->fresh()->employee_number);
        $this->assertSame(2, $employee->fresh()->version);
        $this->assertSame('Person A', $change->fresh()->before_payload['name']);
        $this->get('/app/employee-changes/'.$change->id)->assertOk()->assertSee('Person A')->assertSee('Updated Person');
        $this->review($change)->assertConflict();
    }

    public function test_future_assignment_changes_today_only_on_the_effective_date_without_scheduler(): void
    {
        $a = $this->organization('A');
        $b = $this->organization('B');
        $employee = $this->employee('A', $a);
        $this->signedIn($this->actor('hr_officer'));
        $change = $this->propose($employee, $b + ['kind' => 'assignment', 'effective_on' => '2026-11-01']);
        $this->assertSame($a['branch_id'], $employee->assignments()->at('2026-11-01')->sole()->branch_id);
        $this->signedIn($this->actor());
        $this->review($change)->assertRedirect();
        $this->assertSame($a['branch_id'], $employee->assignments()->at('2026-10-20')->sole()->branch_id);
        $this->assertSame($b['branch_id'], $employee->assignments()->at('2026-11-01')->sole()->branch_id);
        $this->assertSame(2, $employee->assignments()->count());
        $this->assertSame('2026-11-01', $employee->assignments()->orderBy('valid_from')->first()->valid_to->toDateString());
        $reader = $this->actor('hr_officer', 'branch', ['branch_id' => $b['branch_id']]);
        $this->signedIn($reader)->get('/app/employees/'.$employee->id)->assertNotFound();
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00'));
        $this->get('/app/employees/'.$employee->id)->assertOk()->assertSee('Branch B')->assertDontSee('Branch A');
    }

    public function test_stale_employee_requests_and_decisions_cannot_overwrite_another_approval(): void
    {
        $employee = $this->employee('A', $this->organization('A'));
        $this->signedIn($this->actor('hr_officer'));
        $first = $this->propose($employee, ['kind' => 'identity', 'name' => 'First']);
        $second = $this->propose($employee, ['kind' => 'identity', 'name' => 'Second']);
        $this->signedIn($this->actor());
        $this->review($first)->assertRedirect();
        $this->review($second)->assertConflict();
        $this->assertSame('First', $employee->fresh()->name);
        $this->assertSame('pending', $second->fresh()->status);
        $this->review($second, 'reject')->assertRedirect();
        $this->postJson('/app/employees/'.$employee->id.'/changes', ['kind' => 'identity', 'name' => 'Stale proposal', 'base_version' => 1, 'reason' => 'Test'])->assertConflict();
    }

    public function test_reviewer_cannot_approve_changes_to_their_own_employee_record(): void
    {
        $employee = $this->employee('A', $this->organization('A'));
        $this->signedIn($this->actor('hr_officer'));
        $change = $this->propose($employee, ['kind' => 'identity', 'name' => 'Changed']);
        $reviewer = $this->actor();
        $reviewer->forceFill(['employee_id' => $employee->id])->save();
        $this->signedIn($reviewer);
        $this->review($change)->assertForbidden();
        $this->assertSame('Person A', $employee->fresh()->name);
        $this->assertSame('pending', $change->fresh()->status);
    }

    public function test_approval_rechecks_master_state_and_rejection_does_not_mutate_employee(): void
    {
        $a = $this->organization('A');
        $b = $this->organization('B');
        $employee = $this->employee('A', $a);
        $this->signedIn($this->actor('hr_officer'));
        $change = $this->propose($employee, $b + ['kind' => 'assignment', 'effective_on' => '2026-11-01']);
        Branch::find($b['branch_id'])->update(['is_active' => false]);
        $this->signedIn($this->actor());
        $this->review($change)->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->assertSame(1, $employee->assignments()->count());
        $this->review($change, 'reject')->assertRedirect();
        $this->assertSame(1, $employee->fresh()->version);
        $this->assertSame('rejected', $change->fresh()->status);
    }

    public function test_manager_cycle_failure_rolls_back_the_entire_approval(): void
    {
        $a = $this->organization('A');
        $first = $this->employee('FIRST', $a);
        $a['manager_employee_id'] = $first->id;
        $second = $this->employee('SECOND', $a);
        $a['manager_employee_id'] = $second->id;
        $this->signedIn($this->actor('hr_officer'));
        $change = $this->propose($first, $a + ['kind' => 'assignment', 'effective_on' => '2026-11-01']);
        $this->signedIn($this->actor());
        $this->review($change)->assertUnprocessable();
        $this->assertNull($first->assignments()->sole()->valid_to);
        $this->assertSame(1, $first->fresh()->version);
        $this->assertSame('pending', $change->fresh()->status);
        $this->assertDatabaseMissing('audit_entries', ['action' => 'employee.change_approved']);
    }

    public function test_approval_after_effective_date_requires_a_new_request(): void
    {
        $a = $this->organization('A');
        $employee = $this->employee('A', $a);
        $this->signedIn($this->actor('hr_officer'));
        $change = $this->propose($employee, $a + ['kind' => 'assignment', 'effective_on' => '2026-11-01']);
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00'));
        $this->signedIn($this->actor());
        $this->review($change)->assertUnprocessable()->assertJsonValidationErrors('effective_on');
        $this->assertSame('pending', $change->fresh()->status);
        $this->assertSame(1, $employee->assignments()->count());
    }

    public function test_assignment_and_review_forms_render_and_stale_html_has_recovery_guidance(): void
    {
        $organization = $this->organization('A');
        $employee = $this->employee('A', $organization);
        $this->signedIn($this->actor('hr_officer'));
        $this->get('/app/employees/'.$employee->id.'/change?kind=assignment')->assertOk()->assertSee('Mulai berlaku')->assertSee('Branch A');
        $change = $this->propose($employee, ['kind' => 'identity', 'name' => 'Updated']);
        $this->signedIn($this->actor());
        $this->get('/app/employee-changes')->assertOk()->assertSee('Person A');
        $this->get('/app/employee-changes/'.$change->id)->assertOk()->assertSee('Catatan tinjauan')->assertSee('Simpan keputusan');
        $this->post('/app/employees/'.$employee->id.'/changes', ['kind' => 'identity', 'name' => 'Stale', 'base_version' => 999, 'reason' => 'Test'])->assertConflict()->assertSee('Data sudah berubah')->assertSee('Kembali ke beranda')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_archived_job_preserves_its_archived_department_and_cannot_be_reactivated_under_it(): void
    {
        $organization = $this->organization('A');
        $job = Job::find($organization['job_id']);
        $department = Department::find($organization['department_id']);
        $job->update(['is_active' => false]);
        $department->update(['is_active' => false]);
        $this->signedIn($this->actor())->get('/app/organization/jobs/'.$job->id.'/edit')->assertOk()->assertSee('Department A (diarsipkan)');
        $body = ['version' => 1, 'code' => 'A', 'name' => 'Archived title corrected', 'department_id' => $department->id, 'is_active' => 0];
        $this->putJson('/app/organization/jobs/'.$job->id, $body)->assertRedirect();
        $this->assertSame($department->id, $job->fresh()->department_id);
        $body['version'] = 2;
        $body['is_active'] = 1;
        $this->putJson('/app/organization/jobs/'.$job->id, $body)->assertUnprocessable();
        $this->assertFalse($job->fresh()->is_active);
    }

    public function test_omitted_optional_email_in_a_name_change_preserves_existing_email(): void
    {
        $employee = $this->employee('A', $this->organization('A'));
        $employee->update(['work_email' => 'existing@example.test']);
        $this->signedIn($this->actor('hr_officer'));
        $change = $this->propose($employee, ['kind' => 'identity', 'name' => 'Renamed']);
        $this->assertSame('existing@example.test', $change->payload['work_email']);
        $this->signedIn($this->actor());
        $this->review($change)->assertRedirect();
        $this->assertSame('existing@example.test', $employee->fresh()->work_email);
    }

    public function test_invalid_controls_are_associated_with_errors_and_review_decision_is_retained(): void
    {
        $this->signedIn($this->actor());
        $this->from('/app/organization/branches/create')->post('/app/organization/branches', ['code' => 'invalid code', 'name' => '', 'timezone' => 'Invalid/Zone', 'is_active' => 1])->assertRedirect('/app/organization/branches/create')->assertSessionHasErrors(['code', 'name', 'timezone']);
        $this->withCookie(config('session.cookie'), session()->getId());
        $response = $this->get('/app/organization/branches/create')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//a[@href="#code"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="code" and @aria-invalid="true" and contains(@aria-describedby,"code-error")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="timezone" and @aria-invalid="true" and @aria-describedby="timezone-error"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="code-error"]')->length);
        $employee = $this->employee('A', $this->organization('A'));
        $change = $this->propose($employee, ['kind' => 'identity', 'name' => 'Renamed']);
        $this->signedIn($this->actor());
        $this->from('/app/employee-changes/'.$change->id)->post('/app/employee-changes/'.$change->id.'/review', ['version' => 1, 'decision' => 'approve', 'review_note' => ''])->assertRedirect()->assertSessionHasErrors('review_note');
        $this->withCookie(config('session.cookie'), session()->getId());
        $response = $this->get('/app/employee-changes/'.$change->id)->assertOk();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@id="decision"]/option[@value="approve" and @selected]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="review_note" and @aria-invalid="true" and @aria-describedby="review_note-error"]')->length);
    }
}
