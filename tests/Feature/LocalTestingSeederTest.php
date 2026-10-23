<?php

namespace Tests\Feature;

use App\Domain\Workflow\WorkflowService;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkflowRequest;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\JobSeeder;
use Database\Seeders\LeaveBalanceSeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\LocalTestingSeeder;
use Database\Seeders\RosterSeeder;
use Database\Seeders\ShiftTemplateSeeder;
use Database\Seeders\StaffUserSeeder;
use Database\Seeders\WorkflowSeeder;
use Database\Seeders\WorkLocationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class LocalTestingSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(7, 0));
    }

    public function test_full_seed_is_linked_and_rerun_preserves_records_passwords_and_ledger(): void
    {
        $this->seed(LocalTestingSeeder::class);
        $tables = ['companies' => 1, 'branches' => 2, 'departments' => 2, 'job_positions' => 4, 'employees' => 6, 'employment_periods' => 6, 'assignments' => 6, 'users' => 11, 'leave_types' => 3, 'work_locations' => 2, 'shift_templates' => 2, 'roster_shifts' => 84, 'leave_credit_lots' => 6, 'leave_ledger' => 6, 'workflow_definitions' => 6];
        foreach ($tables as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertDatabaseCount('workflow_requests', 0);
        $this->assertSame(84, DB::table('roster_shifts')->where('status', 'published')->count());
        $user = User::where('email', 'employee.sample@hris.test')->firstOrFail();
        $this->assertSame('LOCAL-EMP-001', $user->employee->employee_number);
        $this->assertDatabaseHas('role_grants', ['user_id' => $user->id, 'role' => 'employee', 'scope' => 'self']);
        $password = $user->password;
        Branch::where('code', 'LOCAL-JKT')->update(['name' => 'Operator edited branch']);
        $audits = DB::table('audit_entries')->count();
        $this->seed(LocalTestingSeeder::class);
        foreach ($tables as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame($audits, DB::table('audit_entries')->count());
        $this->assertSame('Operator edited branch', Branch::where('code', 'LOCAL-JKT')->value('name'));
    }

    public function test_seeded_dropdowns_and_leave_approval_use_real_domain_data(): void
    {
        $this->seed(LocalTestingSeeder::class);
        $hr = User::where('email', 'hr.admin@hris.test')->firstOrFail();
        $reviewer = User::where('email', 'hr.reviewer@hris.test')->firstOrFail();
        $hr->forceFill(['two_factor_secret' => Fortify::currentEncrypter()->encrypt('TESTINGSECRETKEYA'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($hr)->withSession(['auth.security_version' => $hr->security_version, 'auth.mfa_user' => $hr->id]);
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->getJson('/app/options/people')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/app/options/leave-types')->assertOk()->assertJsonCount(3, 'data');

        $employee = Employee::where('employee_number', 'LOCAL-EMP-001')->firstOrFail();
        $service = app(WorkflowService::class);
        $request = $service->draft($hr, (string) Str::uuid(), 'leave_exception', ['employee_id' => $employee->id, 'leave_type_id' => LeaveType::where('code', 'LOCAL-ANNUAL')->value('id'), 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-26', 'segment' => 'full', 'reason' => 'Synthetic test leave', 'exception_reason' => 'Synthetic HR notice exception']);
        $service->submit($hr, $request['id'], 1, (string) Str::uuid());
        $this->assertSame('in_review', WorkflowRequest::findOrFail($request['id'])->status);
        $service->decide($reviewer, $request['id'], 2, (string) Str::uuid(), 'approve', 'Synthetic independent review');
        $this->assertSame('approved', WorkflowRequest::findOrFail($request['id'])->status);
        $this->assertSame(480, (int) DB::table('leave_allocations')->where('state', 'used')->sum('minutes'));
    }

    public function test_individual_seeder_resolves_its_own_prerequisites(): void
    {
        $this->seed(LeaveBalanceSeeder::class);
        $this->assertDatabaseCount('employees', 6);
        $this->assertDatabaseCount('leave_credit_lots', 6);
        $this->assertDatabaseCount('leave_ledger', 6);
        $this->assertDatabaseCount('roster_shifts', 0);
    }

    public function test_all_sample_seeders_reject_non_local_and_demo_before_writing(): void
    {
        $classes = [CompanySeeder::class, BranchSeeder::class, DepartmentSeeder::class, JobSeeder::class, EmployeeSeeder::class, StaffUserSeeder::class, LeaveTypeSeeder::class, LeaveBalanceSeeder::class, WorkLocationSeeder::class, ShiftTemplateSeeder::class, RosterSeeder::class, WorkflowSeeder::class, LocalTestingSeeder::class];
        foreach (['production', 'staging', 'demo'] as $environment) {
            $this->app['env'] = $environment;
            foreach ($classes as $class) {
                try {
                    app($class)->run();
                    $this->fail('Seeder should reject '.$environment);
                } catch (\RuntimeException $exception) {
                    $this->assertStringContainsString('lokal non-demo', $exception->getMessage());
                }
            }
        }
        $this->app['env'] = 'local';
        config(['hris.demo_features_enabled' => true]);
        try {
            app(LocalTestingSeeder::class)->run();
            $this->fail('Seeder should reject demo flag.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('lokal non-demo', $exception->getMessage());
        }
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('users', 0);
    }
}
