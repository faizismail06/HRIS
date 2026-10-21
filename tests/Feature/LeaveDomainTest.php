<?php

namespace Tests\Feature;

use App\Domain\Leave\LotAccounting;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Job;
use App\Models\RosterShift;
use App\Models\RosterVersion;
use App\Models\User;
use App\Models\WorkflowRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class LeaveDomainTest extends TestCase
{
    use DatabaseTransactions;

    private User $hr;

    private User $worker;

    private int $type;

    private int $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        $this->travelTo(Carbon::parse('2026-10-19 08:00:00'));
        Company::create(['name' => 'Synthetic Leave Test']);
        $b = Branch::create(['company_id' => 1, 'code' => 'TEST', 'name' => 'Test Branch']);
        $this->branch = $b->id;
        $d = Department::create(['company_id' => 1, 'code' => 'TEST', 'name' => 'Test Department']);
        $j = Job::create(['department_id' => $d->id, 'code' => 'TEST', 'name' => 'Test Job']);
        $employee = Employee::create(['employee_number' => 'TEST', 'name' => 'Test Person']);
        $period = $employee->periods()->create(['starts_on' => '2020-01-01']);
        $employee->assignments()->create(['employment_period_id' => $period->id, 'branch_id' => $b->id, 'department_id' => $d->id, 'job_id' => $j->id, 'valid_from' => '2020-01-01']);
        $this->hr = $this->user('hr_admin');
        $this->worker = $this->user('employee', $employee->id);
        $this->login($this->hr);
        $this->type = $this->postJson('/app/api/leave-types', ['idempotency_key' => (string) Str::uuid(), 'code' => 'ANNUAL', 'name' => 'Synthetic Leave', 'is_paid' => true, 'deducts_balance' => true, 'allows_half_day' => true, 'requires_evidence' => false, 'notice_days' => 0, 'minimum_tenure_days' => 0])->assertCreated()->json('id');
        foreach (['leave', 'leave_cancel'] as $kind) {
            $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Workflow '.$kind, 'kind' => $kind, 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
        }
    }

    private function user(string $role, ?int $employee = null): User
    {
        $u = User::factory()->create();
        $u->forceFill(['employee_id' => $employee, 'is_active' => true, 'two_factor_secret' => Fortify::currentEncrypter()->encrypt('SYNTHETICLEAVEKEY'), 'two_factor_confirmed_at' => now()])->save();
        $u->grants()->create(['role' => $role, 'scope' => $role === 'employee' ? 'self' : 'company']);

        return $u->fresh();
    }

    private function login(User $u): static
    {
        $this->actingAs($u)->withSession(['auth.security_version' => $u->security_version, 'auth.mfa_user' => $u->id]);
        session()->save();

        return $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function shift(string $date, bool $off = false): RosterShift
    {
        $r = RosterVersion::create(['status' => 'published', 'version' => 1, 'created_by' => $this->hr->id, 'checksum' => str_repeat('a', 64)]);

        return RosterShift::create(['roster_version_id' => $r->id, 'employee_id' => $this->worker->employee_id, 'work_date' => $date, 'starts_at' => $off ? null : $date.'T09:00:00+07:00', 'ends_at' => $off ? null : $date.'T17:00:00+07:00', 'timezone' => 'Asia/Jakarta', 'is_day_off' => $off, 'status' => 'published', 'snapshot' => ['branch_id' => $this->branch, 'department_id' => 1, 'breaks' => [], 'half_day_split_minute' => 240, 'clock_in_before_minutes' => 120, 'clock_out_after_minutes' => 240, 'tolerance_minutes' => 0]]);
    }

    private function credit(int $minutes, int $year = 2026, ?string $until = null): void
    {
        $this->login($this->hr)->postJson('/app/api/leave-credits', ['idempotency_key' => (string) Str::uuid(), 'employee_id' => $this->worker->employee_id, 'type_code' => 'ANNUAL', 'entitlement_year' => $year, 'valid_from' => $year.'-01-01', 'valid_until' => $until ?? ($year + 1).'-01-01', 'minutes' => $minutes, 'source_key' => (string) Str::uuid(), 'source_type' => 'grant', 'reason' => 'Synthetic entitlement approved for testing'])->assertCreated();
    }

    private function request(string $start, string $end, string $segment = 'full'): int
    {
        $this->login($this->worker);

        return $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'leave', 'payload' => ['leave_type_id' => $this->type, 'starts_on' => $start, 'ends_on' => $end, 'segment' => $segment, 'reason' => 'Private absence explanation']])->assertCreated()->json('id');
    }

    private function action(int $id, string $action, int $version, array $extra = [], ?string $key = null)
    {
        return $this->postJson('/app/api/requests/'.$id.'/'.$action, ['idempotency_key' => $key ?? (string) Str::uuid(), 'expected_version' => $version] + $extra);
    }

    public function test_competing_two_day_submissions_with_three_days_available_reserve_only_one(): void
    {
        foreach (['2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23'] as $date) {
            $this->shift($date);
        } $this->credit(1440);
        $first = $this->request('2026-10-20', '2026-10-21');
        $this->action($first, 'submit', 1)->assertOk();
        $second = $this->request('2026-10-22', '2026-10-23');
        $this->action($second, 'submit', 1)->assertUnprocessable();
        $this->assertDatabaseCount('leave_segments', 2);
        $this->assertSame(960, (int) DB::table('leave_allocations')->where('state', 'reserved')->sum('minutes'));
        $this->getJson('/app/api/my-leave-balances')->assertOk()->assertJsonPath('0.available_minutes', 480)->assertJsonPath('0.reserved_minutes', 960);
        $this->assertSame('draft', WorkflowRequest::find($second)->status);
    }

    public function test_approval_converts_reservation_without_second_deduction_and_retry_does_not_duplicate_usage(): void
    {
        $shift = $this->shift('2026-10-20');
        $this->credit(960);
        $id = $this->request('2026-10-20', '2026-10-20');
        $this->action($id, 'submit', 1)->assertOk();
        $this->login($this->hr);
        $key = (string) Str::uuid();
        $first = $this->action($id, 'decide', 2, ['decision' => 'approve'], $key)->assertOk()->json();
        $this->action($id, 'decide', 2, ['decision' => 'approve'], $key)->assertOk()->assertExactJson($first);
        $this->assertSame(1, DB::table('leave_ledger')->where('kind', 'usage')->count());
        $this->login($this->worker)->getJson('/app/api/my-leave-balances')->assertJsonPath('0.available_minutes', 480)->assertJsonPath('0.reserved_minutes', 0)->assertJsonPath('0.used_minutes', 480);
        $this->getJson('/app/api/attendance/'.$shift->id)->assertJsonPath('result.status', 'ON_LEAVE');
        $this->getJson('/app/api/requests/'.$id)->assertDontSee('Private absence explanation');
    }

    public function test_day_off_costs_nothing_and_half_day_uses_defined_shift_segment(): void
    {
        $this->shift('2026-10-24');
        $this->shift('2026-10-25', true);
        $this->shift('2026-10-26', true);
        $this->credit(960);
        $id = $this->request('2026-10-24', '2026-10-26');
        $this->action($id, 'submit', 1)->assertOk();
        $this->assertSame(480, (int) DB::table('leave_allocations')->sum('minutes'));
        $this->action($id, 'withdraw', 2, ['reason' => 'Use a half day instead'])->assertOk();
        $half = $this->request('2026-10-24', '2026-10-24', 'first_half');
        $this->action($half, 'submit', 1)->assertOk();
        $this->assertSame(240, (int) DB::table('leave_allocations')->where('state', 'reserved')->sum('minutes'));
        $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'leave', 'payload' => ['leave_type_id' => $this->type, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-26', 'segment' => 'full', 'reason' => 'Unneeded leave']])->assertUnprocessable();
    }

    public function test_cross_year_leave_uses_separate_entitlement_lots(): void
    {
        $this->shift('2026-12-31');
        $this->shift('2027-01-01');
        $this->credit(480, 2026);
        $this->credit(480, 2027);
        $id = $this->request('2026-12-31', '2027-01-01');
        $this->action($id, 'submit', 1)->assertOk();
        $allocations = DB::table('leave_allocations')->join('leave_credit_lots', 'leave_credit_lots.id', '=', 'leave_allocations.leave_credit_lot_id')->orderBy('entitlement_year')->pluck('leave_allocations.minutes', 'entitlement_year')->all();
        $this->assertSame([2026 => 480, 2027 => 480], $allocations);
    }

    public function test_expiry_keeps_pending_reservations_and_expires_later_releases(): void
    {
        $this->shift('2026-12-31');
        $this->credit(960, 2026);
        $id = $this->request('2026-12-31', '2026-12-31');
        $this->action($id, 'submit', 1)->assertOk();
        $lot = DB::table('leave_credit_lots')->sole();
        $this->login($this->hr)->postJson('/app/api/leave-credits/'.$lot->id.'/changes', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'debit', 'minutes' => 481, 'reason' => 'Cannot take reserved credit'])->assertUnprocessable();
        $this->travelTo(Carbon::parse('2027-01-02 12:00:00'));
        app(LotAccounting::class)->reconcileExpiry();
        $this->assertSame(-480, (int) DB::table('leave_ledger')->where('kind', 'expiry')->sum('minutes'));
        $this->login($this->worker);
        $this->action($id, 'withdraw', 2, ['reason' => 'Synthetic withdrawal after expiry'])->assertOk();
        app(LotAccounting::class)->reconcileExpiry();
        app(LotAccounting::class)->reconcileExpiry();
        $this->assertSame(-960, (int) DB::table('leave_ledger')->where('kind', 'expiry')->sum('minutes'));
        $this->getJson('/app/api/my-leave-balances')->assertJsonPath('0.available_minutes', 0);
    }

    public function test_reject_releases_and_cancel_approved_leave_requires_another_approval(): void
    {
        $this->shift('2026-10-20');
        $this->credit(480);
        $id = $this->request('2026-10-20', '2026-10-20');
        $this->action($id, 'submit', 1)->assertOk();
        $this->login($this->hr);
        $this->action($id, 'decide', 2, ['decision' => 'reject', 'reason' => 'Test rejection'])->assertOk();
        $this->login($this->worker)->getJson('/app/api/my-leave-balances')->assertJsonPath('0.available_minutes', 480);
        $next = $this->request('2026-10-20', '2026-10-20');
        $this->action($next, 'submit', 1)->assertOk();
        $this->login($this->hr);
        $this->action($next, 'decide', 2, ['decision' => 'approve'])->assertOk();
        $this->login($this->worker);
        $cancel = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'leave_cancel', 'payload' => ['original_request_id' => $next, 'reason' => 'Cancel approved leave']])->assertCreated()->json('id');
        $this->action($cancel, 'submit', 1)->assertOk();
        $this->getJson('/app/api/my-leave-balances')->assertJsonPath('0.available_minutes', 0);
        $this->login($this->hr);
        $this->action($cancel, 'decide', 2, ['decision' => 'approve'])->assertOk();
        $this->login($this->worker)->getJson('/app/api/my-leave-balances')->assertJsonPath('0.available_minutes', 480);
        $this->assertSame(1, DB::table('leave_ledger')->where('kind', 'reversal')->count());
    }

    public function test_ordinary_employee_cannot_grant_balance_and_employee_id_payload_cannot_select_another_person(): void
    {
        $this->shift('2026-10-20');
        $this->credit(480);
        $this->login($this->worker);
        $this->postJson('/app/api/leave-credits', ['idempotency_key' => (string) Str::uuid(), 'employee_id' => $this->worker->employee_id, 'type_code' => 'ANNUAL', 'entitlement_year' => 2026, 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'minutes' => 480, 'source_key' => 'attack', 'source_type' => 'grant', 'reason' => 'Unauthorized'])->assertForbidden();
        $id = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'leave', 'payload' => ['employee_id' => 999, 'leave_type_id' => $this->type, 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20', 'segment' => 'full', 'reason' => 'Self-service']])->assertCreated()->json('id');
        $this->assertSame($this->worker->employee_id, WorkflowRequest::find($id)->employee_id);
    }
}
