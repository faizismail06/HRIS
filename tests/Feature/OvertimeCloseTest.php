<?php

namespace Tests\Feature;

use App\Models\WorkLocation;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class OvertimeCloseTest extends AttendanceScenarioCase
{
    private function period(string $start = '2026-10-19', string $end = '2026-10-20'): int
    {
        $this->login($this->hr);
        $branch = WorkLocation::find($this->location)->branch_id;

        return $this->postJson('/app/api/attendance-periods', ['idempotency_key' => (string) Str::uuid(), 'branch_id' => $branch, 'starts_on' => $start, 'ends_on' => $end])->assertCreated()->json('id');
    }

    public function test_close_blocks_incomplete_then_correction_resolves_and_snapshot_is_immutable(): void
    {
        $draft = $this->roster();
        $this->login($this->worker);
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00'));
        $this->clock($draft['shift_id'], 'in')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $period = $this->period();
        $review = $this->postJson('/app/api/attendance-periods/'.$period.'/review', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk()->json();
        $this->assertNotEmpty($review['blockers']);
        $this->postJson('/app/api/attendance-periods/'.$period.'/close', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'checksum' => $review['checksum']])->assertUnprocessable();
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Correction', 'kind' => 'correction', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
        $this->login($this->worker);
        $id = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'correction', 'payload' => ['roster_shift_id' => $draft['shift_id'], 'effective_in' => '2026-10-19T22:00:00+07:00', 'effective_out' => '2026-10-20T06:00:00+07:00', 'reason' => 'Test checkout correction']])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($this->hr);
        $this->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();
        $review = $this->postJson('/app/api/attendance-periods/'.$period.'/review', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2])->assertOk()->assertJsonCount(0, 'blockers')->json();
        $key = (string) Str::uuid();
        $input = ['idempotency_key' => $key, 'expected_version' => 3, 'checksum' => $review['checksum']];
        $this->postJson('/app/api/attendance-periods/'.$period.'/close', $input)->assertOk()->assertJsonPath('revision', 1);
        $this->postJson('/app/api/attendance-periods/'.$period.'/close', $input)->assertOk();
        $this->assertDatabaseCount('attendance_snapshots', 1);
        try {
            DB::transaction(fn () => DB::table('attendance_snapshots')->update(['sha256' => str_repeat('0', 64)]));
            $this->fail('Snapshot must be immutable.');
        } catch (QueryException $e) {
            $this->assertSame('P0001', $e->getCode());
        }
        DB::table('attendance_periods')->where('id', $period)->update(['payroll_finalized' => true]);
        $this->postJson('/app/api/attendance-periods/'.$period.'/reopen', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 4, 'reason' => 'Test reopen'])->assertConflict();
    }

    public function test_source_change_after_review_invalidates_close_and_roster_gap_is_reported(): void
    {
        $this->roster();
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
        $period = $this->period('2026-10-19', '2026-10-21');
        $review = $this->postJson('/app/api/attendance-periods/'.$period.'/review', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk()->json();
        $this->assertContains('schedule_gap', array_column($review['blockers'], 'code'));
        $this->roster('2026-10-20', true);
        $this->postJson('/app/api/attendance-periods/'.$period.'/close', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'checksum' => $review['checksum']])->assertConflict();
        $this->assertDatabaseCount('attendance_snapshots', 0);
    }

    public function test_planned_three_hours_and_two_verified_actual_hours_only_produce_two_hours(): void
    {
        $draft = $this->roster();
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Overtime', 'kind' => 'overtime', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
        $this->login($this->worker);
        $id = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'overtime', 'payload' => ['roster_shift_id' => $draft['shift_id'], 'planned_start' => '2026-10-19T22:00:00+07:00', 'planned_end' => '2026-10-20T01:00:00+07:00', 'reason' => 'Test additional task', 'task' => 'Synthetic operational work']])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($this->hr);
        $this->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();
        $overtime = DB::table('overtime_authorizations')->where('workflow_request_id', $id)->first();
        $this->assertSame(0, $overtime->payable_minutes);
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00'));
        $this->login($this->worker);
        $this->clock($draft['shift_id'], 'in')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-20 00:00:00'));
        $this->clock($draft['shift_id'], 'out')->assertOk();
        $this->postJson('/app/api/overtime/'.$overtime->id.'/actual', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'actual_start' => '2026-10-19T22:00:00+07:00', 'actual_end' => '2026-10-20T00:00:00+07:00'])->assertOk();
        $this->postJson('/app/api/overtime/'.$overtime->id.'/verify', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2])->assertForbidden();
        $this->login($this->hr);
        $this->postJson('/app/api/overtime/'.$overtime->id.'/verify', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2])->assertOk()->assertJsonPath('payable_minutes', 120);
    }
}
