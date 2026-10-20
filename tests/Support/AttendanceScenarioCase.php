<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Job;
use App\Models\RosterShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

abstract class AttendanceScenarioCase extends TestCase
{
    use DatabaseTransactions;

    protected User $hr;

    protected User $worker;

    protected int $template;

    protected int $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        $this->travelTo(Carbon::parse('2026-10-19 12:00:00', 'Asia/Jakarta'));
        Company::create(['name' => 'Synthetic Attendance Test']);
        $branch = Branch::create(['company_id' => 1, 'code' => 'TEST', 'name' => 'Test Branch']);
        $department = Department::create(['company_id' => 1, 'code' => 'TEST', 'name' => 'Test Department']);
        $job = Job::create(['department_id' => $department->id, 'code' => 'TEST', 'name' => 'Test Job']);
        $person = Employee::create(['employee_number' => 'TEST', 'name' => 'Synthetic Worker']);
        $period = $person->periods()->create(['starts_on' => '2020-01-01']);
        $person->assignments()->create(['employment_period_id' => $period->id, 'branch_id' => $branch->id, 'department_id' => $department->id, 'job_id' => $job->id, 'valid_from' => '2020-01-01']);
        $this->hr = $this->user('hr_admin');
        $this->worker = $this->user('employee', $person->id);
        $this->login($this->hr);
        $this->location = $this->postJson('/app/api/work-locations', ['idempotency_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'code' => 'HQ', 'name' => 'Synthetic Site', 'timezone' => 'Asia/Jakarta', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_m' => 100, 'max_accuracy_m' => 100, 'requires_photo' => false])->assertCreated()->json('id');
        $this->template = $this->postJson('/app/api/shift-templates', ['idempotency_key' => (string) Str::uuid(), 'code' => 'NIGHT', 'name' => 'Synthetic Night', 'work_location_id' => $this->location, 'starts_local' => '22:00', 'ends_local' => '06:00', 'end_day_offset' => 1, 'unpaid_breaks' => [['start' => 240, 'end' => 300]], 'half_day_split_minute' => 240, 'tolerance_minutes' => 0, 'clock_in_before_minutes' => 120, 'clock_out_after_minutes' => 240])->assertCreated()->json('id');
    }

    protected function user(string $role, ?int $employee = null): User
    {
        $u = User::factory()->create();
        $u->forceFill(['employee_id' => $employee, 'is_active' => true, 'two_factor_secret' => Fortify::currentEncrypter()->encrypt('TESTATTENDANCEKEY'), 'two_factor_confirmed_at' => now()])->save();
        $u->grants()->create(['role' => $role, 'scope' => $role === 'employee' ? 'self' : 'company']);

        return $u->fresh();
    }

    protected function login(User $u): static
    {
        $this->actingAs($u)->withSession(['auth.security_version' => $u->security_version, 'auth.mfa_user' => $u->id]);
        session()->save();

        return $this->withCookie(config('session.cookie'), session()->getId());
    }

    protected function roster(string $date = '2026-10-19', bool $off = false): array
    {
        $this->login($this->hr);
        $draft = $this->postJson('/app/api/rosters', ['idempotency_key' => (string) Str::uuid(), 'rows' => [['employee_id' => $this->worker->employee_id, 'work_date' => $date, 'shift_template_id' => $off ? null : $this->template, 'is_day_off' => $off]]])->assertCreated()->json();
        $this->postJson('/app/api/rosters/'.$draft['id'].'/publish', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $draft['checksum']])->assertOk();

        return $draft + ['shift_id' => RosterShift::where('roster_version_id', $draft['id'])->sole()->id];
    }

    protected function clock(int $shift, string $direction, array $extra = [], ?string $key = null)
    {
        return $this->postJson('/app/api/attendance/clock-'.$direction, array_replace(['idempotency_key' => $key ?? (string) Str::uuid(), 'roster_shift_id' => $shift, 'latitude' => -6.2, 'longitude' => 106.8, 'accuracy_m' => 10], $extra));
    }
}
