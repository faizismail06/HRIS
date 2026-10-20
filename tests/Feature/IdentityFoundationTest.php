<?php

namespace Tests\Feature;

use App\Domain\Identity\AccountAccess;
use App\Domain\Identity\Audit;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentPeriod;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IdentityFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'TestingOnlyPassword123';

    protected function setUp(): void
    {
        parent::setUp();
        // Never transact against a developer/production database accidentally.
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        Company::create(['name' => 'Synthetic Test Company']);
    }

    private function account(string $role = 'hr_admin', string $scope = 'company', array $targets = []): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $user->forceFill(['is_active' => true, 'two_factor_secret' => Fortify::currentEncrypter()->encrypt((new Google2FA)->generateSecretKey()), 'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['testing-recovery-code'])), 'two_factor_confirmed_at' => now()])->save();
        $user->grants()->create(array_merge(['role' => $role, 'scope' => $scope], $targets));

        return $user->fresh();
    }

    private function signedIn(User $user, bool $mfa = true): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.mfa_user' => $mfa ? $user->id : null, 'auth.password_confirmed_at' => time()]);
    }

    private function organization(string $code): array
    {
        $branch = Branch::create(['company_id' => 1, 'code' => $code, 'name' => 'Branch '.$code]);
        $department = Department::create(['company_id' => 1, 'code' => $code, 'name' => 'Department '.$code]);
        $job = Job::create(['department_id' => $department->id, 'code' => $code, 'name' => 'Job '.$code]);

        return ['branch_id' => $branch->id, 'department_id' => $department->id, 'job_id' => $job->id];
    }

    private function employee(string $number, array $organization, ?int $manager = null): Employee
    {
        $employee = Employee::create(['employee_number' => $number, 'name' => 'Person '.$number]);
        $period = EmploymentPeriod::create(['employee_id' => $employee->id, 'starts_on' => '2020-01-01']);
        $employee->assignments()->create($organization + ['employment_period_id' => $period->id, 'valid_from' => '2020-01-01', 'manager_employee_id' => $manager]);

        return $employee;
    }

    public function test_login_is_real_registration_is_closed_and_anonymous_data_is_blocked(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke ruang kerja')->assertDontSee('Pratinjau statis')->assertHeader('X-Frame-Options', 'DENY');
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
        $this->get('/app/employees')->assertRedirect('/login');
        $this->get('/forgot-password')->assertOk();
    }

    public function test_password_only_login_can_enroll_but_cannot_read_operational_data(): void
    {
        $user = $this->account();
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => self::PASSWORD])->assertRedirect('/app');
        $this->get('/app/employees')->assertRedirect('/account/security');
        $this->post('/user/confirm-password', ['password' => self::PASSWORD])->assertRedirect();
        $this->get('/account/security')->assertOk()->assertSee('Siapkan autentikator');
        $this->from('/account/security')->post('/user/two-factor-authentication')->assertRedirect('/account/security');
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->get('/account/security')->assertOk()->assertSee('Pindai dan konfirmasi')->assertSee('Masukkan kunci secara manual')->assertHeader('Cache-Control', 'no-store, private');
        $code = (new Google2FA)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($user->two_factor_secret));
        $this->from('/account/security')->post('/user/confirmed-two-factor-authentication', ['code' => $code])->assertRedirect('/account/security');
        $this->get('/app')->assertOk();
        $this->get('/account/security')->assertOk()->assertSee('Autentikator aktif');
    }

    public function test_totp_challenge_and_recovery_codes_gate_login_and_recovery_is_single_use(): void
    {
        $user = $this->account();
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertOk();
        $code = (new Google2FA)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($user->two_factor_secret));
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/app');
        $this->get('/app')->assertOk();
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/two-factor-challenge');
        $this->post('/two-factor-challenge', ['recovery_code' => 'testing-recovery-code'])->assertRedirect('/app');
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->postJson('/two-factor-challenge', ['recovery_code' => 'testing-recovery-code'])->assertUnprocessable();
        $this->assertGuest();
        $audit = json_encode(DB::table('audit_entries')->get());
        $this->assertStringNotContainsString('testing-recovery-code', $audit);
        $this->assertStringNotContainsString(self::PASSWORD, $audit);
        $this->assertStringNotContainsString($code, $audit);
    }

    public function test_inactive_accounts_and_revoked_pending_challenges_cannot_authenticate(): void
    {
        $user = $this->account();
        $user->forceFill(['is_active' => false])->save();
        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertUnprocessable();
        $user->forceFill(['is_active' => true])->save();
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $user->increment('security_version');
        $this->postJson('/two-factor-challenge', ['recovery_code' => 'testing-recovery-code'])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_active_session_is_invalidated_after_access_changes(): void
    {
        $admin = $this->account('platform_admin');
        $target = $this->account();
        $this->signedIn($target)->get('/app')->assertOk();
        app(AccountAccess::class)->replace($admin, $target, [], false);
        $this->getJson('/app')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = $this->account();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertTooManyRequests();
    }

    public function test_password_recovery_is_uniform_and_reset_revokes_prior_sessions(): void
    {
        Notification::fake();
        $user = $this->account();
        $known = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->json();
        $unknown = $this->postJson('/forgot-password', ['email' => 'unknown@example.test'])->assertOk()->json();
        $this->assertSame($known, $unknown);
        $token = Password::broker()->createToken($user);
        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))->assertOk();
        $this->postJson('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'NewTestingPassword123', 'password_confirmation' => 'NewTestingPassword123'])->assertOk();
        $this->assertTrue(Hash::check('NewTestingPassword123', $user->fresh()->password));
        $this->assertGreaterThan($user->security_version, $user->fresh()->security_version);
        $this->signedIn($user)->getJson('/app')->assertUnauthorized();
    }

    public function test_branch_scope_blocks_direct_urls_and_search_does_not_widen_access(): void
    {
        $a = $this->organization('A');
        $b = $this->organization('B');
        $allowed = $this->employee('ALLOWED', $a);
        $hidden = $this->employee('HIDDEN', $b);
        $user = $this->account('hr_officer', 'branch', ['branch_id' => $a['branch_id']]);
        $this->signedIn($user)->get('/app/employees')->assertOk()->assertSee($allowed->name)->assertDontSee($hidden->name);
        $this->get('/app/employees/'.$allowed->id)->assertOk();
        $this->get('/app/employees/'.$hidden->id)->assertNotFound();
        $this->get('/app/employees?q=HIDDEN')->assertOk()->assertDontSee($hidden->name);
        $this->get('/app/employees?as_of=2999-01-01')->assertRedirect()->assertSessionHasErrors('as_of');
    }

    public function test_self_team_department_company_and_platform_scopes_are_distinct(): void
    {
        $a = $this->organization('A');
        $b = $this->organization('B');
        $manager = $this->employee('MANAGER', $a);
        $report = $this->employee('REPORT', $a, $manager->id);
        $other = $this->employee('OTHER', $b);
        $user = $this->account('employee', 'self');
        $user->forceFill(['employee_id' => $manager->id])->save();
        $this->signedIn($user)->get('/app/employees')->assertOk()->assertSee($manager->name)->assertDontSee($report->name)->assertDontSee($other->name);
        $user->grants()->delete();
        $user->grants()->create(['role' => 'manager', 'scope' => 'team']);
        $this->get('/app/employees')->assertOk()->assertSee($report->name)->assertDontSee($other->name);
        $dept = $this->account('auditor', 'department', ['department_id' => $a['department_id']]);
        $this->signedIn($dept)->get('/app/employees')->assertOk()->assertSee($report->name)->assertDontSee($other->name);
        $company = $this->account();
        $this->signedIn($company)->get('/app/employees')->assertOk()->assertSee($other->name);
        foreach (['platform_admin', 'payroll_officer', 'it_asset_officer', 'finance_approver'] as $role) {
            $this->signedIn($this->account($role))->get('/app/employees')->assertForbidden();
        }
    }

    public function test_as_of_scope_uses_historical_assignment_and_excludes_future_changes(): void
    {
        $a = $this->organization('A');
        $b = $this->organization('B');
        $employee = $this->employee('TRANSFER', $a);
        $old = $employee->assignments()->first();
        $old->update(['valid_to' => today()->toDateString()]);
        $employee->assignments()->create($b + ['employment_period_id' => $old->employment_period_id, 'valid_from' => today()->toDateString()]);
        $user = $this->account('hr_admin', 'branch', ['branch_id' => $a['branch_id']]);
        $this->signedIn($user)->get('/app/employees/'.$employee->id)->assertNotFound();
        $this->get('/app/employees/'.$employee->id.'?as_of='.today()->subDay()->toDateString())->assertOk()->assertSee('Branch A')->assertDontSee('Branch B');
    }

    public function test_database_rejects_overlapping_history_and_cycles(): void
    {
        $a = $this->organization('A');
        $first = $this->employee('FIRST', $a);
        $second = $this->employee('SECOND', $a, $first->id);
        $this->rejectsDatabase(fn () => $first->periods()->create(['starts_on' => '2021-01-01']));
        $this->rejectsDatabase(fn () => $first->assignments()->create($a + ['employment_period_id' => $first->periods()->first()->id, 'valid_from' => '2021-01-01']));
        $this->rejectsDatabase(fn () => $first->assignments()->first()->update(['manager_employee_id' => $second->id]));
        $child = Department::create(['company_id' => 1, 'code' => 'CHILD', 'name' => 'Child', 'parent_id' => $a['department_id']]);
        $this->rejectsDatabase(fn () => Department::find($a['department_id'])->update(['parent_id' => $child->id]));
        $this->rejectsDatabase(fn () => $first->periods()->first()->update(['ends_on' => '2022-01-01']));
    }

    public function test_audit_is_append_only_and_last_platform_admin_cannot_be_removed(): void
    {
        $admin = $this->account('platform_admin');
        Audit::record($admin->id, 'testing.audit', 'user', $admin->id);
        $this->rejectsDatabase(fn () => DB::table('audit_entries')->update(['action' => 'tampered']));
        $this->rejectsDatabase(fn () => DB::table('audit_entries')->delete());
        $this->rejectsDatabase(fn () => DB::statement('TRUNCATE audit_entries'));
        $this->expectException(ValidationException::class);
        app(AccountAccess::class)->replace($admin, $admin, [], false);
    }

    public function test_provisioning_creates_an_enrollment_required_account_without_default_credentials(): void
    {
        $this->artisan('hris:provision-account', ['role' => 'hr_admin'])
            ->expectsQuestion('Nama lengkap', 'Provisioned Test Person')
            ->expectsQuestion('Email kerja', 'provisioned@example.test')
            ->expectsQuestion('Kata sandi', self::PASSWORD)
            ->expectsQuestion('Ulangi kata sandi', self::PASSWORD)
            ->assertSuccessful();
        $user = User::where('email', 'provisioned@example.test')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->two_factor_secret);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertSame('hr_admin', $user->grants()->sole()->role);
        $this->assertDatabaseHas('audit_entries', ['action' => 'account.provisioned_via_console', 'subject_id' => (string) $user->id]);
    }

    public function test_first_platform_admin_bootstrap_is_single_use(): void
    {
        Company::query()->delete();
        $this->artisan('hris:provision-account', ['role' => 'platform_admin', '--bootstrap' => true, '--company' => 'Bootstrap Test Company'])
            ->expectsQuestion('Nama lengkap', 'Bootstrap Test Admin')
            ->expectsQuestion('Email kerja', 'bootstrap@example.test')
            ->expectsQuestion('Kata sandi', self::PASSWORD)
            ->expectsQuestion('Ulangi kata sandi', self::PASSWORD)
            ->assertSuccessful();
        $this->assertSame(1, Company::sole()->id);
        $this->artisan('hris:provision-account', ['role' => 'platform_admin', '--bootstrap' => true, '--company' => 'Duplicate'])->assertFailed();
        $this->assertSame(1, User::where('email', 'bootstrap@example.test')->count());
    }

    public function test_ended_employment_and_missing_session_stamp_revoke_access(): void
    {
        $organization = $this->organization('A');
        $employee = $this->employee('ENDED', $organization);
        $user = $this->account('employee', 'self');
        $user->forceFill(['employee_id' => $employee->id])->save();
        $this->signedIn($user)->get('/app')->assertOk();
        $employee->assignments()->update(['valid_to' => today()->toDateString()]);
        $employee->periods()->update(['ends_on' => today()->toDateString()]);
        $this->getJson('/app')->assertUnauthorized();
        $this->assertGuest();
        $other = $this->account();
        $this->actingAs($other)->withSession(['auth.security_version' => null])->getJson('/app')->assertUnauthorized();
    }

    public function test_reset_rejects_weak_passwords_and_does_not_remove_mfa(): void
    {
        $user = $this->account();
        $secret = $user->two_factor_secret;
        $token = Password::broker()->createToken($user);
        $this->postJson('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'weak', 'password_confirmation' => 'weak'])->assertUnprocessable();
        foreach ([str_repeat('Abc123', 13), 'TestPassword123'."\0".'Invalid'] as $invalid) {
            $this->postJson('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => $invalid, 'password_confirmation' => $invalid])->assertUnprocessable();
        }
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
    }

    public function test_challenge_has_attempt_limit_and_disabled_mfa_locks_data_again(): void
    {
        $user = $this->account();
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/two-factor-challenge', ['recovery_code' => 'invalid'])->assertUnprocessable();
        }
        $this->postJson('/two-factor-challenge', ['recovery_code' => 'testing-recovery-code'])->assertTooManyRequests();
        Auth::guard('web')->logout();
        $this->signedIn($user)->from('/account/security')->delete('/user/two-factor-authentication')->assertRedirect('/account/security');
        $this->get('/app')->assertRedirect('/account/security');
        $this->get('/account/security')->assertOk()->assertSee('Siapkan autentikator');
    }

    public function test_mutation_policies_are_default_deny_and_non_admin_cannot_change_grants(): void
    {
        $user = $this->account();
        $employee = $this->employee('A', $this->organization('A'));
        $this->assertFalse(Gate::forUser($user)->allows('create', Employee::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $employee));
        $this->post('/app/employees', [])->assertMethodNotAllowed();
        $this->expectException(HttpException::class);
        app(AccountAccess::class)->replace($user, $user, [], false);
    }

    private function rejectsDatabase(callable $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Database should reject this invariant violation.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->getCode(), ['23514', '23P01', 'P0001']);
        }
    }
}
