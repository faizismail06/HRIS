<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\WorkLocation;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class SwapCalendarTest extends AttendanceScenarioCase
{
    public function test_swap_requires_colleague_consent_then_hr_and_preserves_old_rosters(): void
    {
        $person = Employee::create(['employee_number' => 'COLLEAGUE', 'name' => 'Synthetic Colleague']);
        $period = $person->periods()->create(['starts_on' => '2020-01-01']);
        $placement = Employee::find($this->worker->employee_id)->assignments()->first();
        $person->assignments()->create(['employment_period_id' => $period->id, 'branch_id' => $placement->branch_id, 'department_id' => $placement->department_id, 'job_id' => $placement->job_id, 'valid_from' => '2020-01-01']);
        $colleague = $this->user('employee', $person->id);
        $this->login($this->hr);
        $rows = [];
        foreach ([[$this->worker->employee_id, '2026-10-22', false], [$this->worker->employee_id, '2026-10-23', true], [$person->id, '2026-10-22', true], [$person->id, '2026-10-23', false]] as [$employee,$date,$off]) {
            $rows[] = ['employee_id' => $employee, 'work_date' => $date, 'is_day_off' => $off, 'shift_template_id' => $off ? null : $this->template];
        }
        $draft = $this->postJson('/app/api/rosters', ['idempotency_key' => (string) Str::uuid(), 'rows' => $rows])->assertCreated()->json();
        $this->postJson('/app/api/rosters/'.$draft['id'].'/publish', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $draft['checksum']])->assertOk();
        $definition = ['idempotency_key' => (string) Str::uuid(), 'name' => 'Swap', 'kind' => 'swap', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]];
        $this->postJson('/app/api/workflow-definitions', $definition)->assertUnprocessable();
        $definition['idempotency_key'] = (string) Str::uuid();
        $definition['steps'] = [['role' => 'counterparty'], ['role' => 'hr_admin']];
        $this->postJson('/app/api/workflow-definitions', $definition)->assertOk();
        $source = RosterShift::where('employee_id', $this->worker->employee_id)->where('is_day_off', false)->sole();
        $target = RosterShift::where('employee_id', $person->id)->where('is_day_off', false)->sole();
        $this->login($this->worker);
        $id = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'swap', 'payload' => ['source_shift_id' => $source->id, 'target_shift_id' => $target->id, 'reason' => 'Synthetic swap']])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($this->hr);
        $this->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertForbidden();
        $this->login($colleague);
        $this->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk()->assertJsonPath('status', 'in_review');
        $this->assertSame('published', $source->fresh()->status);
        $this->login($this->hr);
        $key = (string) Str::uuid();
        $body = ['idempotency_key' => $key, 'expected_version' => 3, 'decision' => 'approve'];
        $this->postJson('/app/api/requests/'.$id.'/decide', $body)->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson('/app/api/requests/'.$id.'/decide', $body)->assertOk();
        $this->assertSame('superseded', $source->fresh()->status);
        $this->assertSame('superseded', $target->fresh()->status);
        $this->assertSame(4, RosterShift::where('status', 'published')->count());
        $this->assertSame(8, RosterShift::count());
        $this->assertSame('2026-10-23', RosterShift::where('employee_id', $this->worker->employee_id)->where('status', 'published')->where('is_day_off', false)->sole()->work_date);
    }

    public function test_recurring_pattern_uses_explicit_working_days_and_calendar_does_not_reveal_private_leave_type(): void
    {
        $this->login($this->hr);
        $this->postJson('/app/api/work-calendar-dates', ['idempotency_key' => (string) Str::uuid(), 'branch_id' => WorkLocation::find($this->location)->branch_id, 'work_date' => '2026-10-24', 'label' => 'Synthetic company date', 'is_holiday' => true])->assertCreated();
        $preview = $this->postJson('/app/api/rosters/preview', ['pattern' => ['employee_ids' => [$this->worker->employee_id], 'starts_on' => '2026-10-24', 'ends_on' => '2026-10-26', 'working_days' => [6, 7], 'shift_template_id' => $this->template]])->assertOk()->json();
        $this->assertFalse($preview['rows'][0]['is_day_off']);
        $this->assertFalse($preview['rows'][1]['is_day_off']);
        $this->assertTrue($preview['rows'][2]['is_day_off']);
        $draft = $this->roster('2026-10-24');
        $this->getJson('/app/api/team-calendar?starts_on=2026-10-24&ends_on=2026-10-24')->assertOk()->assertJsonPath('0.availability', 'scheduled')->assertDontSee('leave_type_id')->assertDontSee('reason');
        $this->assertDatabaseCount('work_calendar_dates', 1);
    }
}
