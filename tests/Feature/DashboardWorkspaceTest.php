<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class DashboardWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        Company::create(['name' => 'Synthetic Workspace Company']);
    }

    private function login(string $role, ?int $employee = null): void
    {
        $user = User::factory()->create(['name' => 'Synthetic Workspace User']);
        $user->forceFill(['is_active' => true, 'employee_id' => $employee, 'two_factor_secret' => Fortify::currentEncrypter()->encrypt('TESTINGSECRETKEYA'), 'two_factor_confirmed_at' => now()])->save();
        $user->grants()->create(['role' => $role, 'scope' => $role === 'employee' ? 'self' : 'company']);
        $user->refresh();
        $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.mfa_user' => $user->id]);
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_empty_hr_dashboard_has_real_zero_counts_and_setup_actions(): void
    {
        $this->login('hr_admin');
        $this->get('/app')->assertOk()
            ->assertViewHas('employeeCount', 0)
            ->assertViewHas('pendingChanges', 0)
            ->assertViewHas('organization', ['branches' => 0, 'departments' => 0, 'jobs' => 0])
            ->assertSee('Siapkan ruang tim')->assertSee('Direktori Anda masih kosong')
            ->assertSee(route('workspace.employees.create'))->assertSee(route('workspace.organization'))
            ->assertDontSee('Akun Anda telah melewati verifikasi dua langkah.')
            ->assertDontSee('/prototype/', false);
    }

    public function test_employee_summary_and_recent_people_do_not_expose_other_employees(): void
    {
        $self = Employee::create(['employee_number' => 'SELF', 'name' => 'Visible Self']);
        Employee::create(['employee_number' => 'OTHER', 'name' => 'Hidden Other Employee']);
        $this->login('employee', $self->id);
        $this->get('/app')->assertOk()->assertViewHas('employeeCount', 1)
            ->assertViewHas('pendingChanges', null)->assertViewHas('organization', null)
            ->assertSee('Visible Self')->assertDontSee('Hidden Other Employee')
            ->assertDontSee('Siapkan ruang tim')->assertDontSee('Tambah karyawan');
    }

    public function test_platform_admin_cannot_see_hr_counts_or_directory_records(): void
    {
        Employee::create(['employee_number' => 'OTHER', 'name' => 'Hidden HR Employee']);
        $this->login('platform_admin');
        $this->get('/app')->assertOk()->assertViewHas('employeeCount', null)
            ->assertViewHas('organization', null)->assertDontSee('Hidden HR Employee')
            ->assertDontSee('Karyawan terbaru')->assertDontSee('Tambah karyawan');
    }
}
