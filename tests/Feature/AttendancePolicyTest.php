<?php

namespace Tests\Feature;

use App\Models\RosterShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class AttendancePolicyTest extends AttendanceScenarioCase
{
    private function mode(string $mode): void
    {
        $branch = $this->worker->employee->assignments()->sole()->branch_id;
        $location = $this->login($this->hr)->postJson('/app/api/work-locations', ['idempotency_key' => (string) Str::uuid(), 'branch_id' => $branch, 'code' => strtoupper($mode), 'name' => 'Synthetic '.$mode, 'timezone' => 'Asia/Jakarta', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_m' => 100, 'max_accuracy_m' => 100, 'requires_photo' => false, 'attendance_mode' => $mode])->assertCreated()->json('id');
        $this->template = $this->postJson('/app/api/shift-templates', ['idempotency_key' => (string) Str::uuid(), 'code' => strtoupper($mode), 'name' => 'Synthetic policy shift', 'work_location_id' => $location, 'starts_local' => '22:00', 'ends_local' => '06:00', 'end_day_offset' => 1, 'unpaid_breaks' => [], 'tolerance_minutes' => 0, 'clock_in_before_minutes' => 60, 'clock_out_after_minutes' => 120])->assertCreated()->json('id');
    }

    public function test_remote_omits_gps_field_requires_accuracy_and_employee_cannot_choose_policy(): void
    {
        $onsiteTemplate = $this->template;
        $this->mode('remote');
        $remote = $this->roster('2026-10-19');
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00', 'Asia/Jakarta'));
        $this->login($this->worker)->postJson('/app/api/attendance/clock-in', ['idempotency_key' => (string) Str::uuid(), 'roster_shift_id' => $remote['shift_id']])->assertOk();
        $event = DB::table('attendance_events')->sole();
        $this->assertSame('REMOTE_SELF_REPORTED', $event->provenance);
        $this->assertSame('[]', decrypt($event->coordinates));
        $this->mode('field');
        $field = $this->roster('2026-10-20');
        $this->travelTo(Carbon::parse('2026-10-20 22:00:00', 'Asia/Jakarta'));
        $this->login($this->worker);
        $this->clock($field['shift_id'], 'in', ['latitude' => 0, 'longitude' => 0, 'accuracy_m' => 1000])->assertUnprocessable()->assertJsonPath('code', 'poor_accuracy');
        $this->clock($field['shift_id'], 'in', ['latitude' => 0, 'longitude' => 0, 'accuracy_m' => 10])->assertOk();
        $this->assertDatabaseHas('attendance_events', ['roster_shift_id' => $field['shift_id'], 'provenance' => 'FIELD_DEVICE_REPORTED']);
        $this->template = $onsiteTemplate;
        $onsite = $this->roster('2026-10-21');
        $this->travelTo(Carbon::parse('2026-10-21 22:00:00', 'Asia/Jakarta'));
        $this->login($this->worker);
        $this->clock($onsite['shift_id'], 'in', ['latitude' => 0, 'longitude' => 0, 'attendance_mode' => 'remote'])->assertUnprocessable()->assertJsonPath('code', 'outside_geofence');
    }

    public function test_future_roster_replacement_retains_old_record_and_stale_version_cannot_replace_again(): void
    {
        $old = $this->roster('2026-10-20');
        $input = ['idempotency_key' => (string) Str::uuid(), 'rows' => [['employee_id' => $this->worker->employee_id, 'work_date' => '2026-10-20', 'is_day_off' => true]], 'replacements' => [['id' => $old['shift_id'], 'version' => 2]], 'replacement_reason' => 'Synthetic approved operational change'];
        $draft = $this->postJson('/app/api/rosters', $input)->assertCreated()->assertJsonCount(0, 'conflicts')->json();
        $this->postJson('/app/api/rosters/'.$draft['id'].'/publish', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $draft['checksum']])->assertOk();
        $this->assertDatabaseHas('roster_shifts', ['id' => $old['shift_id'], 'status' => 'superseded', 'version' => 3]);
        $this->assertSame(1, RosterShift::where('employee_id', $this->worker->employee_id)->where('status', 'published')->where('is_day_off', true)->count());
        $input['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/app/api/rosters', $input)->assertNotFound();
        $this->assertDatabaseHas('audit_entries', ['action' => 'roster.replaced']);
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $this->worker->id]);
    }

    public function test_staffing_warning_requires_acknowledgement_and_blocking_minimum_counts_breaks(): void
    {
        $this->login($this->hr);
        $policy = ['idempotency_key' => (string) Str::uuid(), 'code' => 'SYNTHETIC_COVERAGE', 'work_location_id' => $this->location, 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'working_days' => [1, 2, 3, 4, 5, 6, 7], 'start_local' => '22:00', 'end_local' => '23:00', 'end_day_offset' => 0, 'minimum_staff' => 2, 'is_blocking' => false];
        $this->postJson('/app/api/staffing-policies', $policy)->assertCreated();
        $input = ['idempotency_key' => (string) Str::uuid(), 'rows' => [['employee_id' => $this->worker->employee_id, 'work_date' => '2026-10-20', 'is_day_off' => false, 'shift_template_id' => $this->template]]];
        $draft = $this->postJson('/app/api/rosters', $input)->assertCreated()->assertJsonPath('warnings.0.actual', 1)->json();
        $publish = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $draft['checksum']];
        $this->postJson('/app/api/rosters/'.$draft['id'].'/publish', $publish)->assertUnprocessable();
        $publish['acknowledge_warnings'] = true;
        $this->postJson('/app/api/rosters/'.$draft['id'].'/publish', $publish)->assertOk();
        $policy = array_replace($policy, ['idempotency_key' => (string) Str::uuid(), 'code' => 'SYNTHETIC_BREAK', 'start_local' => '02:00', 'end_local' => '03:00', 'end_day_offset' => 0, 'minimum_staff' => 1, 'is_blocking' => true]);
        $this->postJson('/app/api/staffing-policies', $policy)->assertCreated();
        $input['idempotency_key'] = (string) Str::uuid();
        $input['rows'][0]['work_date'] = '2026-10-21';
        $draft = $this->postJson('/app/api/rosters', $input)->assertCreated()->json();
        $blocking = collect($draft['warnings'])->firstWhere('blocking', true);
        $this->assertSame(0, $blocking['actual']);
        $this->postJson('/app/api/rosters/'.$draft['id'].'/publish', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $draft['checksum'], 'acknowledge_warnings' => true])->assertUnprocessable();
    }
}
