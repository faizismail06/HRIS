<?php

namespace Tests\Feature;

use App\Domain\Leave\GrantSchedules;
use App\Domain\Leave\LotAccounting;
use App\Models\LeaveType;
use App\Models\WorkflowRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class LeaveWorkspaceTest extends AttendanceScenarioCase
{
    private function type(): int
    {
        $this->login($this->hr)->post('/app/leave-management/types', ['idempotency_key' => (string) Str::uuid(), 'code' => 'UI-ANNUAL', 'name' => 'Synthetic UI Annual', 'is_paid' => 1, 'deducts_balance' => 1, 'allows_half_day' => 1, 'requires_evidence' => 0, 'notice_days' => 0, 'minimum_tenure_days' => 0])->assertRedirect('/app/leave-management/types');

        return LeaveType::where('code', 'UI-ANNUAL')->where('is_active', true)->sole()->id;
    }

    private function credit(): array
    {
        $this->type();
        $data = ['idempotency_key' => (string) Str::uuid(), 'employee_id' => $this->worker->employee_id, 'type_code' => 'UI-ANNUAL', 'entitlement_year' => 2026, 'valid_from' => '2026-01-01', 'last_valid_on' => '2026-12-31', 'minutes' => 960, 'source_type' => 'grant', 'source_key' => 'synthetic-ui-credit', 'reason' => 'Synthetic approved credit evidence'];
        $this->post('/app/leave-management/credits', $data)->assertRedirect();

        return ['id' => DB::table('leave_credit_lots')->value('id'), 'input' => $data];
    }

    private function schedule(): int
    {
        $type = $this->type();
        $this->post('/app/leave-management/schedules', ['idempotency_key' => (string) Str::uuid(), 'leave_type_id' => $type, 'name' => 'Synthetic UI monthly policy', 'cadence' => 'monthly', 'expiry_rule' => 'calendar_year_end', 'first_grant_on' => '2026-10-19', 'last_schedule_on' => '2027-12-31', 'minutes' => 480, 'minimum_tenure_days' => 0, 'employee_ids' => [$this->worker->employee_id], 'evidence' => 'Private synthetic policy evidence'])->assertRedirect();

        return DB::table('leave_grant_schedules')->latest('id')->value('id');
    }

    public function test_types_manual_credit_replay_and_own_balance_follow_real_ledger(): void
    {
        $this->get('/app/leave-management/types')->assertOk()->assertSee('Simpan versi jenis cuti');
        $credit = $this->credit();
        $this->get('/app/leave-management/credits/create')->assertOk()->assertSee('Synthetic Worker')->assertSee('Synthetic UI Annual');
        $this->post('/app/leave-management/credits', $credit['input'])->assertRedirect('/app/leave-management/credits/'.$credit['id']);
        $this->assertDatabaseCount('leave_credit_lots', 1);
        $this->assertDatabaseCount('leave_ledger', 1);
        $this->assertDatabaseHas('leave_credit_lots', ['id' => $credit['id'], 'valid_until' => '2027-01-01']);
        $this->get('/app/leave-management')->assertOk()->assertSee('Synthetic Worker');
        $this->get('/app/leave-management/credits/'.$credit['id'])->assertOk()->assertSee('0 / 0 / 960')->assertDontSee('Synthetic approved credit evidence');
        $this->login($this->worker)->get('/app/leave-balances')->assertOk()->assertSee('Tersedia 960 menit')->assertSee('2026-12-31');
        $this->getJson('/app/api/my-leave-balances')->assertOk()->assertJsonPath('0.available_minutes', 960);
        $this->get('/app/leave-management')->assertForbidden();
        $this->get('/app/leave-management/credits/'.$credit['id'])->assertForbidden();
        $this->post('/app/leave-management/credits', $credit['input'])->assertForbidden();
        $this->login($this->user('employee'))->get('/app/leave-balances')->assertForbidden();
    }

    public function test_reserved_credit_is_protected_from_debit_and_withdraw_releases_it(): void
    {
        $credit = $this->credit();
        $this->roster('2026-10-20');
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'UI leave approval', 'kind' => 'leave', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
        $this->login($this->worker)->post('/app/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'leave', 'leave_type_id' => LeaveType::where('code', 'UI-ANNUAL')->value('id'), 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20', 'segment' => 'full', 'reason' => 'Synthetic private leave'])->assertRedirect();
        $request = WorkflowRequest::latest('id')->firstOrFail();
        $this->post('/app/requests/'.$request->id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertRedirect();
        $this->get('/app/leave-balances')->assertOk()->assertSee('Tersedia 540 menit')->assertSee('dipesan 420');
        $this->login($this->hr);
        $path = '/app/leave-management/credits/'.$credit['id'].'/changes';
        $this->from('/app/leave-management/credits/'.$credit['id'])->post($path, ['idempotency_key' => (string) Str::uuid(), 'kind' => 'debit', 'minutes' => 541, 'reason' => 'Invalid excess debit'])->assertSessionHasErrors('operation');
        $data = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'debit', 'minutes' => 240, 'reason' => 'Synthetic HR correction'];
        $this->post($path, $data)->assertRedirect();
        $this->post($path, $data)->assertRedirect();
        $this->assertDatabaseCount('leave_lot_changes', 1);
        $this->login($this->worker)->get('/app/leave-balances')->assertOk()->assertSee('Tersedia 300 menit');
        $this->post('/app/requests/'.$request->id.'/withdraw', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'withdrawal_reason' => 'Synthetic cancellation before approval'])->assertRedirect();
        $this->get('/app/leave-balances')->assertOk()->assertSee('Tersedia 720 menit')->assertSee('dipesan 0');
    }

    public function test_expired_carry_over_is_source_linked_once_and_self_adjustments_are_denied(): void
    {
        $credit = $this->credit();
        $this->worker->grants()->create(['role' => 'hr_admin', 'scope' => 'company']);
        $this->login($this->worker)->get('/app/leave-management/credits/'.$credit['id'])->assertOk()->assertSee('Perubahan hak Anda sendiri')->assertDontSee('Catat perubahan hak');
        $data = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'debit', 'minutes' => 1, 'reason' => 'Forbidden self adjustment'];
        $this->post('/app/leave-management/credits/'.$credit['id'].'/changes', $data)->assertForbidden();
        $newCredit = $credit['input'];
        $newCredit['idempotency_key'] = (string) Str::uuid();
        $newCredit['source_key'] = 'forbidden-self-grant';
        $this->post('/app/leave-management/credits', $newCredit)->assertForbidden();
        $this->travelTo(Carbon::parse('2027-01-02 12:00:00'));
        app(LotAccounting::class)->reconcileExpiry();
        $this->login($this->hr);
        $carry = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'carry_out', 'minutes' => 480, 'reason' => 'Synthetic source carry-over policy', 'valid_from' => '2027-01-01', 'last_valid_on' => '2027-03-31'];
        $this->post('/app/leave-management/credits/'.$credit['id'].'/changes', $carry)->assertRedirect();
        $target = DB::table('leave_credit_lots')->where('source_key', 'carry:'.$credit['id'])->value('id');
        $this->post('/app/leave-management/credits/'.$credit['id'].'/changes', $carry)->assertRedirect('/app/leave-management/credits/'.$target);
        $this->assertDatabaseCount('leave_credit_lots', 2);
        $this->assertDatabaseHas('leave_credit_lots', ['id' => $target, 'valid_until' => '2027-04-01', 'minutes' => 480]);
        $carry['idempotency_key'] = (string) Str::uuid();
        $this->from('/app/leave-management/credits/'.$credit['id'])->post('/app/leave-management/credits/'.$credit['id'].'/changes', $carry)->assertSessionHasErrors('operation');
        $this->login($this->worker)->get('/app/leave-balances')->assertOk()->assertSee('Kedaluwarsa')->assertSee('Tersedia 480 menit');
    }

    public function test_schedule_requires_independent_non_recipient_approval_then_dispatches_once_and_stops(): void
    {
        $id = $this->schedule();
        $this->get('/app/leave-management/schedules')->assertOk()->assertSee('Synthetic UI monthly policy');
        $this->get('/app/leave-management/schedules/'.$id)->assertOk()->assertSee('Menunggu HR Admin lain');
        $approve = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'action' => 'approve', 'reason' => 'Synthetic independent policy review'];
        $this->post('/app/leave-management/schedules/'.$id.'/decide', $approve)->assertForbidden();
        $this->worker->grants()->create(['role' => 'hr_admin', 'scope' => 'company']);
        $this->login($this->worker)->post('/app/leave-management/schedules/'.$id.'/decide', $approve)->assertForbidden();
        $reviewer = $this->user('hr_admin');
        $this->login($reviewer)->get('/app/leave-management/schedules/'.$id)->assertOk()->assertSee('Sahkan jadwal hak cuti')->assertSee('Private synthetic policy evidence');
        $this->post('/app/leave-management/schedules/'.$id.'/decide', $approve)->assertRedirect();
        $this->post('/app/leave-management/schedules/'.$id.'/decide', $approve)->assertRedirect();
        $this->assertDatabaseCount('leave_credit_lots', 0);
        app(GrantSchedules::class)->dispatch();
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_credit_lots', 1);
        $this->assertDatabaseHas('leave_credit_lots', ['valid_until' => '2027-01-01', 'minutes' => 480]);
        $this->get('/app/leave-management/schedules/'.$id)->assertOk()->assertSee('Hak diberikan');
        $this->travelTo(Carbon::parse('2026-12-20 12:00:00'));
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_credit_lots', 3);
        $this->post('/app/leave-management/schedules/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'action' => 'stop', 'reason' => 'Synthetic policy stopped'])->assertRedirect();
        $this->travelTo(Carbon::parse('2027-01-20 12:00:00'));
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_credit_lots', 3);
        $this->assertDatabaseCount('leave_grant_runs', 3);
    }

    public function test_schedule_rejects_missing_expiry_rule_and_inactive_type_pause_is_visible(): void
    {
        $id = $this->schedule();
        $reviewer = $this->user('hr_admin');
        $this->login($reviewer)->post('/app/leave-management/schedules/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'action' => 'approve', 'reason' => 'Synthetic policy review'])->assertRedirect();
        $this->type();
        $this->login($reviewer)->get('/app/leave-management/schedules/'.$id)->assertOk()->assertSee('Pemberian baru ditunda');
        app(GrantSchedules::class)->dispatch();
        $this->assertDatabaseCount('leave_credit_lots', 0);
        $this->post('/app/leave-management/schedules', ['idempotency_key' => (string) Str::uuid(), 'leave_type_id' => LeaveType::where('is_active', true)->value('id'), 'name' => 'Missing rule', 'cadence' => 'annual', 'first_grant_on' => '2026-10-19', 'last_schedule_on' => '2027-12-31', 'minutes' => 480, 'minimum_tenure_days' => 0, 'employee_ids' => [$this->worker->employee_id], 'evidence' => 'Synthetic policy'])->assertSessionHasErrors('expiry_rule');
        $this->login($this->user('hr_officer'))->get('/app/leave-management/types')->assertForbidden();
        $this->get('/app/leave-management/schedules/'.$id)->assertForbidden();
    }
}
