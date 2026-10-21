<?php

namespace Tests\Feature;

use App\Domain\Leave\LotAccounting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class LeaveLotAccountingTest extends AttendanceScenarioCase
{
    private function credit(): int
    {
        $this->postJson('/app/api/leave-types', ['idempotency_key' => (string) Str::uuid(), 'code' => 'ANNUAL', 'name' => 'Synthetic annual', 'is_paid' => true, 'deducts_balance' => true, 'allows_half_day' => true, 'requires_evidence' => false, 'notice_days' => 0, 'minimum_tenure_days' => 0])->assertCreated();

        return $this->postJson('/app/api/leave-credits', ['idempotency_key' => (string) Str::uuid(), 'employee_id' => $this->worker->employee_id, 'type_code' => 'ANNUAL', 'entitlement_year' => 2026, 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'minutes' => 960, 'source_key' => 'synthetic:opening', 'source_type' => 'grant', 'reason' => 'Synthetic approved entitlement'])->assertCreated()->json('id');
    }

    public function test_adjustment_is_bounded_and_carry_over_reconciles_expiry_without_duplicate_credit(): void
    {
        $lot = $this->credit();
        $this->postJson('/app/api/leave-credits/'.$lot.'/changes', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'debit', 'minutes' => 961, 'reason' => 'Invalid excess'])->assertUnprocessable();
        $debit = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'debit', 'minutes' => 240, 'reason' => 'Synthetic correction'];
        $this->postJson('/app/api/leave-credits/'.$lot.'/changes', $debit)->assertCreated();
        $this->postJson('/app/api/leave-credits/'.$lot.'/changes', $debit)->assertCreated();
        $this->login($this->worker)->getJson('/app/api/my-leave-balances')->assertOk()->assertJsonPath('0.available_minutes', 720);
        $this->postJson('/app/api/leave-credits/'.$lot.'/changes', $debit)->assertForbidden();
        $this->travelTo(Carbon::parse('2027-01-02 12:00', 'Asia/Jakarta'));
        app(LotAccounting::class)->reconcileExpiry();
        app(LotAccounting::class)->reconcileExpiry();
        $this->assertSame(-720, (int) DB::table('leave_ledger')->where('kind', 'expiry')->sum('minutes'));
        $carry = ['idempotency_key' => (string) Str::uuid(), 'kind' => 'carry_out', 'minutes' => 480, 'reason' => 'Synthetic HR carry approval', 'valid_from' => '2027-01-01', 'valid_until' => '2027-04-01'];
        $target = $this->login($this->hr)->postJson('/app/api/leave-credits/'.$lot.'/changes', $carry)->assertCreated()->json('target_lot_id');
        $this->postJson('/app/api/leave-credits/'.$lot.'/changes', $carry)->assertCreated()->assertJsonPath('target_lot_id', $target);
        $carry['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/app/api/leave-credits/'.$lot.'/changes', $carry)->assertUnprocessable();
        app(LotAccounting::class)->reconcileExpiry();
        $this->assertSame(-240, (int) DB::table('leave_ledger')->whereIn('kind', ['expiry', 'expiry_reversal'])->sum('minutes'));
        $this->assertDatabaseCount('leave_credit_lots', 2);
        $this->assertDatabaseCount('leave_lot_changes', 2);
        $this->login($this->worker)->getJson('/app/api/my-leave-balances')->assertOk()->assertJsonPath('0.available_minutes', 0)->assertJsonPath('1.available_minutes', 480);
    }
}
