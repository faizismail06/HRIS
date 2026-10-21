<?php

namespace Tests\Feature;

use App\Domain\Leave\GrantSchedules;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class LeaveGrantSchedulesTest extends AttendanceScenarioCase
{
    private function schedule(string $cadence = 'monthly', array $extra = []): int
    {
        $type = $this->login($this->hr)->postJson('/app/api/leave-types', ['idempotency_key' => (string) Str::uuid(), 'code' => 'ANNUAL', 'name' => 'Synthetic annual leave', 'is_paid' => true, 'deducts_balance' => true, 'allows_half_day' => true, 'requires_evidence' => false, 'notice_days' => 0, 'minimum_tenure_days' => 0])->assertCreated()->json('id');

        return $this->postJson('/app/api/leave-grant-schedules', array_replace(['idempotency_key' => (string) Str::uuid(), 'leave_type_id' => $type, 'name' => 'Synthetic entitlement', 'cadence' => $cadence, 'expiry_rule' => 'calendar_year_end', 'first_grant_on' => '2026-10-19', 'ends_on' => '2028-01-01', 'minutes' => 480, 'minimum_tenure_days' => 0, 'employee_ids' => [$this->worker->employee_id], 'evidence' => 'Synthetic policy approved in isolated test'], $extra))->assertCreated()->json('id');
    }

    private function approve(int $id): User
    {
        $reviewer = $this->user('hr_admin');
        $this->login($reviewer)->postJson('/app/api/leave-grant-schedules/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'action' => 'approve', 'reason' => 'Synthetic entitlement review'])->assertOk();

        return $reviewer;
    }

    public function test_monthly_grants_catch_up_once_and_stop_prevents_future_credits(): void
    {
        $id = $this->schedule();
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_credit_lots', 0);
        $input = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'action' => 'approve', 'reason' => 'Attempt own policy approval'];
        $this->postJson('/app/api/leave-grant-schedules/'.$id.'/decide', $input)->assertForbidden();
        $reviewer = $this->approve($id);
        app(GrantSchedules::class)->dispatch();
        $this->travelTo(Carbon::parse('2026-12-20 12:00', 'Asia/Jakarta'));
        app(GrantSchedules::class)->dispatch();
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_credit_lots', 3);
        $this->assertDatabaseCount('leave_ledger', 3);
        $this->assertDatabaseCount('leave_grant_runs', 3);
        $this->assertSame(1440, (int) DB::table('leave_credit_lots')->sum('minutes'));
        $this->assertSame(['2027-01-01'], DB::table('leave_credit_lots')->distinct()->pluck('valid_until')->all());
        $this->login($reviewer)->postJson('/app/api/leave-grant-schedules/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'action' => 'stop', 'reason' => 'Synthetic policy withdrawn'])->assertOk();
        $this->travelTo(Carbon::parse('2027-02-20 12:00', 'Asia/Jakarta'));
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_credit_lots', 3);
        $this->login($this->worker)->getJson('/app/api/leave-grant-schedules/'.$id)->assertForbidden();
    }

    public function test_annual_eligibility_and_superseded_type_or_revoked_approval_fail_closed(): void
    {
        $id = $this->schedule('annual');
        $reviewer = $this->approve($id);
        $reviewer->grants()->delete();
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_grant_runs', 0);
        $reviewer->grants()->create(['role' => 'hr_admin', 'scope' => 'company']);
        DB::table('leave_types')->update(['is_active' => false]);
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_grant_runs', 0);
        DB::table('leave_types')->update(['is_active' => true]);
        $this->worker->employee->assignments()->update(['valid_to' => '2027-10-01']);
        $this->worker->employee->periods()->update(['ends_on' => '2027-10-01']);
        $this->travelTo(Carbon::parse('2027-10-20 12:00', 'Asia/Jakarta'));
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_credit_lots', 1);
        $this->assertDatabaseHas('leave_grant_runs', ['leave_grant_schedule_id' => $id, 'grant_on' => '2027-10-19', 'outcome' => 'ineligible', 'leave_credit_lot_id' => null]);
    }

    public function test_overlapping_schedules_cannot_duplicate_approved_entitlements(): void
    {
        $first = $this->schedule();
        $this->approve($first);
        // A new version of the type pauses the old schedule, but cannot bypass overlap protection.
        $second = $this->schedule('annual');
        $reviewer = $this->user('hr_admin');
        $this->login($reviewer)->postJson('/app/api/leave-grant-schedules/'.$second.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'action' => 'approve', 'reason' => 'Duplicate policy attempt'])->assertUnprocessable();
        $this->assertDatabaseHas('leave_grant_schedules', ['id' => $second, 'status' => 'draft']);
    }
}
