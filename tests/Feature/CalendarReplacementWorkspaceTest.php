<?php

namespace Tests\Feature;

use App\Models\RosterShift;
use App\Models\RosterVersion;
use App\Models\WorkflowRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class CalendarReplacementWorkspaceTest extends AttendanceScenarioCase
{
    public function test_replacement_remains_draft_until_publish_and_keeps_prior_shift_history(): void
    {
        $original = $this->roster('2026-10-20');
        $shift = RosterShift::findOrFail($original['shift_id']);
        $this->get('/app/schedules/replace/'.$shift->id)->assertOk()->assertSee('Synthetic Worker');
        $input = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $shift->version, 'is_day_off' => 1, 'replacement_reason' => 'Synthetic approved roster change'];
        $this->post('/app/schedules/replace/'.$shift->id, $input)->assertRedirect();
        $replacement = RosterVersion::latest('id')->firstOrFail();
        $this->post('/app/schedules/replace/'.$shift->id, $input)->assertRedirect('/app/schedules/'.$replacement->id);
        $this->assertSame('published', $shift->fresh()->status);
        $this->get('/app/schedules/'.$replacement->id)->assertOk()->assertSee('Synthetic approved roster change');
        $this->post('/app/schedules/'.$replacement->id.'/publish', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $replacement->checksum])->assertRedirect();
        $this->assertSame('superseded', $shift->fresh()->status);
        $this->assertSame(1, RosterShift::where('status', 'published')->where('employee_id', $this->worker->employee_id)->where('work_date', '2026-10-20')->count());
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $this->worker->id]);
    }

    public function test_replacement_rejects_stale_versions_started_shifts_and_unrelated_actors(): void
    {
        $original = $this->roster();
        $shift = RosterShift::findOrFail($original['shift_id']);
        $input = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 99, 'is_day_off' => 1, 'replacement_reason' => 'Synthetic invalid change'];
        $this->from('/app/schedules/replace/'.$shift->id)->post('/app/schedules/replace/'.$shift->id, $input)->assertSessionHasErrors('roster');
        $this->travelTo(Carbon::parse('2026-10-19 23:00:00'));
        $input['expected_version'] = $shift->version;
        $this->post('/app/schedules/replace/'.$shift->id, $input)->assertSessionHasErrors('roster');
        $this->login($this->worker)->get('/app/schedules/replace/'.$shift->id)->assertForbidden();
        $this->post('/app/schedules/replace/'.$shift->id, $input)->assertForbidden();
        $this->assertDatabaseCount('roster_versions', 1);
    }

    public function test_calendar_conceals_leave_reason_and_filters_scope_before_rendering(): void
    {
        $original = $this->roster('2026-10-20');
        $shift = RosterShift::findOrFail($original['shift_id']);
        $request = WorkflowRequest::create(['public_id' => (string) Str::uuid(), 'kind' => 'leave', 'employee_id' => $this->worker->employee_id, 'submitted_by' => $this->worker->id, 'payload' => ['reason' => 'Private synthetic medical condition'], 'summary' => [], 'status' => 'approved', 'version' => 1]);
        DB::table('leave_segments')->insert(['workflow_request_id' => $request->id, 'employee_id' => $this->worker->employee_id, 'roster_shift_id' => $shift->id, 'work_date' => $shift->work_date, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at, 'minutes' => 420, 'state' => 'approved', 'created_at' => now()]);
        $this->login($this->worker)->get('/app/team-calendar?starts_on=2026-10-20&ends_on=2026-10-20')->assertOk()->assertSee('Synthetic Worker')->assertSee('Tidak tersedia')->assertDontSee('Private synthetic medical condition');
        $this->login($this->user('employee'))->get('/app/team-calendar?starts_on=2026-10-20&ends_on=2026-10-20')->assertOk()->assertDontSee('Synthetic Worker');
        $this->get('/app/team-calendar?starts_on=2026-01-01&ends_on=2026-12-31')->assertSessionHasErrors('ends_on');
    }

    public function test_calendar_date_versions_show_latest_without_rewriting_roster_and_require_company_hr(): void
    {
        $branch = $this->worker->employee->assignments()->sole()->branch_id;
        $data = ['idempotency_key' => (string) Str::uuid(), 'branch_id' => $branch, 'work_date' => '2026-10-20', 'label' => 'Synthetic holiday', 'is_holiday' => 1];
        $this->get('/app/work-calendar')->assertOk();
        $this->post('/app/work-calendar', $data)->assertRedirect('/app/work-calendar');
        $this->post('/app/work-calendar', $data)->assertRedirect();
        $this->assertDatabaseCount('work_calendar_dates', 1);
        $data['idempotency_key'] = (string) Str::uuid();
        $data['label'] = 'Synthetic working day';
        $data['is_holiday'] = 0;
        $this->post('/app/work-calendar', $data)->assertRedirect();
        $this->get('/app/work-calendar')->assertOk()->assertSee('Synthetic working day')->assertDontSee('Synthetic holiday');
        $this->assertDatabaseCount('work_calendar_dates', 2);
        $this->assertDatabaseCount('roster_shifts', 0);
        $this->login($this->worker)->get('/app/work-calendar')->assertForbidden();
        $this->post('/app/work-calendar', $data)->assertForbidden();
    }
}
