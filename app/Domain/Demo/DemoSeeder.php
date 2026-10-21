<?php

namespace App\Domain\Demo;

use App\Domain\Attendance\ScheduleService;
use App\Domain\Leave\LeaveService;
use App\Domain\Payroll\PayrollPolicyService;
use App\Domain\Workflow\WorkflowService;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class DemoSeeder
{
    public function run(): void
    {
        DemoBoundary::database();
        abort_unless(DemoBoundary::enabled(), 403);
        DB::transaction(function () {
            abort_if(DB::table('users')->exists() || DB::table('employees')->exists() || DB::table('companies')->exists(), 409, 'Demo init hanya untuk database kosong yang sudah ditandai demo.');
            Company::create(['name' => 'DEMO — Synthetic People Company']);
            $branch = Branch::create(['company_id' => 1, 'code' => 'DEMO', 'name' => 'Synthetic Jakarta', 'timezone' => 'Asia/Jakarta']);
            $department = Department::create(['company_id' => 1, 'code' => 'DEMO', 'name' => 'Synthetic Team']);
            $job = Job::create(['department_id' => $department->id, 'code' => 'DEMO', 'name' => 'Synthetic Specialist']);
            $manager = Employee::create(['employee_number' => 'SIM-MANAGER', 'name' => 'Synthetic Manager']);
            $employee = Employee::create(['employee_number' => 'SIM-EMPLOYEE', 'name' => 'Synthetic Employee']);
            foreach ([$manager, $employee] as $person) {
                $period = $person->periods()->create(['starts_on' => today()->subYears(2)->startOfYear()->toDateString()]);
                $person->assignments()->create(['employment_period_id' => $period->id, 'branch_id' => $branch->id, 'department_id' => $department->id, 'job_id' => $job->id, 'manager_employee_id' => $person->id === $employee->id ? $manager->id : null, 'valid_from' => $period->starts_on]);
            }
            $users = [];
            foreach (['employee', 'manager', 'hr_officer', 'hr_admin', 'payroll_officer', 'finance_approver', 'it_asset_officer', 'platform_admin'] as $role) {
                $u = User::create(['name' => 'DEMO '.$role, 'email' => $role.'@synthetic.invalid', 'password' => Str::random(64), 'is_active' => true]);
                $u->forceFill(['is_active' => true, 'employee_id' => match ($role) {
                    'employee' => $employee->id, 'manager' => $manager->id, default => null
                }, 'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now(), 'email_verified_at' => now()])->save();
                $u->grants()->create(['role' => $role, 'scope' => match ($role) {
                    'employee' => 'self', 'manager' => 'team', default => 'company'
                }]);
                DB::table('demo_personas')->insert(['code' => $role, 'user_id' => $u->id, 'scenario_id' => config('hris.demo_scenario_id')]);
                $users[$role] = $u->fresh();
            }
            $hr = $users['hr_admin'];
            foreach (['leave', 'leave_cancel', 'correction', 'overtime', 'letter', 'contract', 'contract_cancel', 'career', 'onboarding', 'offboarding', 'general', 'identity_change', 'bank_change', 'tax_change', 'compensation', 'expense'] as $kind) {
                if ($kind === 'general') {
                    $kind = 'profile_change';
                }
                $roles = $kind === 'expense' ? ['manager', 'finance_approver'] : (in_array($kind, ['bank_change', 'tax_change', 'compensation']) ? ['finance_approver'] : ['hr_admin']);
                app(WorkflowService::class)->define($hr, (string) Str::uuid(), ['name' => 'DEMO '.$kind, 'kind' => $kind, 'min_amount_minor' => 0, 'priority' => 10, 'steps' => array_map(fn ($r) => ['role' => $r], $roles)]);
            }
            $s = app(ScheduleService::class);
            $location = $s->location($hr, (string) Str::uuid(), ['branch_id' => $branch->id, 'code' => 'DEMO', 'name' => 'Synthetic Site', 'timezone' => 'Asia/Jakarta', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_m' => 100, 'max_accuracy_m' => 100, 'requires_photo' => false]);
            $template = $s->template($hr, (string) Str::uuid(), ['code' => 'DEMO', 'name' => 'Synthetic Day', 'work_location_id' => $location['id'], 'starts_local' => '08:00', 'ends_local' => '17:00', 'end_day_offset' => 0, 'unpaid_breaks' => [['start' => 240, 'end' => 300]], 'half_day_split_minute' => 240, 'tolerance_minutes' => 0, 'clock_in_before_minutes' => 120, 'clock_out_after_minutes' => 240]);
            $rows = [];
            for ($i = 0; $i < 7; $i++) {
                $rows[] = ['employee_id' => $employee->id, 'work_date' => today()->addDays($i)->toDateString(), 'shift_template_id' => $template['id'], 'is_day_off' => false];
            }
            $draft = $s->draft($hr, (string) Str::uuid(), ['rows' => $rows]);
            $s->publish($hr, $draft['id'], 1, (string) Str::uuid(), $draft['checksum']);
            $leave = app(LeaveService::class);
            $leave->type($hr, (string) Str::uuid(), ['code' => 'SIMULATED', 'name' => 'DEMO synthetic leave', 'is_paid' => true, 'deducts_balance' => true, 'allows_half_day' => true, 'requires_evidence' => false, 'notice_days' => 0, 'minimum_tenure_days' => 0]);
            $leave->grant($hr, (string) Str::uuid(), ['employee_id' => $employee->id, 'type_code' => 'SIMULATED', 'entitlement_year' => today()->year, 'valid_from' => today()->startOfYear()->toDateString(), 'valid_until' => today()->addYear()->startOfYear()->toDateString(), 'minutes' => 2400, 'source_key' => 'SIMULATED:OPENING', 'source_type' => 'grant', 'reason' => 'DEMO ONLY. Synthetic entitlement, not a production policy.']);
            app(PayrollPolicyService::class)->create($users['payroll_officer'], (string) Str::uuid(), ['code' => 'DEMO_DRAFT_ONLY', 'valid_from' => today()->startOfYear()->toDateString(), 'valid_until' => today()->addYear()->startOfYear()->toDateString(), 'official_sources' => ['https://example.invalid/synthetic-policy'], 'rules' => ['rounding' => 'half_up', 'proration_basis' => 'calendar_days', 'requires_tax_profile' => false, 'applicability_statement' => 'SIMULATED arithmetic example only; not regulatory settings or production approval.', 'variation_warning_percent' => 25, 'components' => []]]);
            DB::table('installation_boundary')->where('id', 1)->update(['scenario_time' => today()->setTime(8, 0)->toIso8601String()]);
        });
    }
}
