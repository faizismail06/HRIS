<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\RosterVersion;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class AttendanceWorkspaceTest extends AttendanceScenarioCase
{
    public function test_html_schedule_clocking_replay_and_overnight_departure_use_server_time(): void
    {
        $draft = $this->roster();
        $id = $draft['shift_id'];
        $this->travelTo(Carbon::parse('2026-10-19 22:12:00', 'Asia/Jakarta'));
        $this->login($this->worker)->get('/app/attendance')->assertOk()->assertSee('Synthetic Worker')->assertSee(route('workspace.attendance.show', $id));
        $this->get('/app/attendance/'.$id)->assertOk()->assertSee('Catat masuk')->assertSee('Asia/Jakarta')->assertDontSee('Catat pulang')->assertHeader('Cache-Control', 'no-store, private');
        $input = ['idempotency_key' => (string) Str::uuid(), 'latitude' => -6.2, 'longitude' => 106.8, 'accuracy_m' => 10];
        $this->postJson('/app/attendance/'.$id.'/clock-in', $input)->assertOk()->assertJsonPath('work_date', '2026-10-19');
        $this->postJson('/app/attendance/'.$id.'/clock-in', $input)->assertOk();
        $this->assertDatabaseCount('attendance_events', 1);
        $this->get('/app/attendance/'.$id)->assertOk()->assertSee('Catat pulang')->assertDontSee('Catat masuk')->assertSee('Lokasi perangkat')->assertDontSee('106.8')->assertDontSee('-6.2');
        $this->travelTo(Carbon::parse('2026-10-20 05:50:00', 'Asia/Jakarta'));
        $this->post('/app/attendance/'.$id.'/clock-out', array_replace($input, ['idempotency_key' => (string) Str::uuid()]))->assertRedirect('/app/attendance/'.$id);
        $this->assertDatabaseCount('attendance_events', 2);
        $this->get('/app/attendance/'.$id)->assertOk()->assertSee('398 menit kerja')->assertSee('terlambat 12 menit')->assertSee('pulang awal 10 menit')->assertSee('19 Oct 2026 22:12');
    }

    public function test_geofence_identity_overrides_and_other_employee_requests_fail_closed(): void
    {
        $draft = $this->roster();
        $id = $draft['shift_id'];
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00', 'Asia/Jakarta'));
        $this->login($this->worker)->postJson('/app/attendance/'.$id.'/clock-in', ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonPath('code', 'location_required');
        $this->postJson('/app/attendance/'.$id.'/clock-in', ['idempotency_key' => (string) Str::uuid(), 'latitude' => 0, 'longitude' => 0, 'accuracy_m' => 10])->assertUnprocessable()->assertJsonPath('code', 'outside_geofence');
        $this->postJson('/app/attendance/'.$id.'/clock-in', ['idempotency_key' => (string) Str::uuid(), 'employee_id' => 999, 'occurred_at' => now()->toIso8601String(), 'simulation' => true])->assertUnprocessable()->assertJsonValidationErrors(['employee_id', 'occurred_at', 'simulation']);
        $other = Employee::create(['employee_number' => 'UI-OTHER', 'name' => 'Other Worker']);
        $this->login($this->user('employee', $other->id))->get('/app/attendance?view=team')->assertOk()->assertDontSee('Synthetic Worker');
        $this->get('/app/attendance/'.$id)->assertNotFound();
        $this->postJson('/app/attendance/'.$id.'/clock-in', ['idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $this->login($this->hr)->get('/app/attendance?view=team')->assertOk()->assertSee('Synthetic Worker');
        $this->get('/app/attendance/'.$id)->assertOk()->assertDontSee('data-clock-form');
        $this->postJson('/app/attendance/'.$id.'/clock-in', ['idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_photo_required_clocking_and_remote_without_coordinates_use_persisted_policy(): void
    {
        Storage::fake('local');
        $draft = $this->roster();
        $shift = RosterShift::findOrFail($draft['shift_id']);
        $shift->update(['snapshot' => array_replace_recursive($shift->snapshot, ['location' => ['requires_photo' => true, 'attendance_mode' => 'remote']])]);
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00', 'Asia/Jakarta'));
        $this->login($this->worker)->get('/app/attendance/'.$shift->id)->assertOk()->assertSee('Foto bukti kehadiran')->assertSee('data-requires-location="false"', false);
        $this->postJson('/app/attendance/'.$shift->id.'/clock-in', ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonPath('code', 'photo_required');
        $this->post('/app/attendance/'.$shift->id.'/photo', ['idempotency_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->image('attendance.png')])->assertRedirect('/app/attendance/'.$shift->id);
        $this->post('/app/attendance/'.$shift->id.'/clock-in', ['idempotency_key' => (string) Str::uuid()])->assertRedirect('/app/attendance/'.$shift->id);
        $event = DB::table('attendance_events')->sole();
        $this->assertSame('REMOTE_SELF_REPORTED', $event->provenance);
        $this->assertSame('[]', decrypt($event->coordinates));
        $this->assertNotNull($event->private_attachment_id);
    }

    public function test_roster_draft_review_publish_replay_and_employee_visibility(): void
    {
        $this->login($this->hr)->get('/app/schedules/create')->assertOk()->assertSee('Synthetic Night')->assertSee('Synthetic Worker');
        $input = ['idempotency_key' => (string) Str::uuid(), 'employee_ids' => [$this->worker->employee_id], 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-21', 'working_days' => [2], 'shift_template_id' => $this->template];
        $this->post('/app/schedules', $input)->assertRedirect();
        $record = RosterVersion::latest('id')->firstOrFail();
        $this->post('/app/schedules', $input)->assertRedirect('/app/schedules/'.$record->id);
        $this->assertDatabaseCount('roster_versions', 1);
        $this->get('/app/schedules/'.$record->id)->assertOk()->assertSee('Publikasikan roster')->assertSee('Hari libur')->assertSee('22:00');
        $this->login($this->worker)->get('/app/attendance?starts_on=2026-10-20&ends_on=2026-10-21')->assertOk()->assertDontSee('Synthetic Worker');
        $this->get('/app/schedules/'.$record->id)->assertForbidden();
        $this->post('/app/schedules', $input)->assertForbidden();
        $this->login($this->hr);
        $publish = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $record->checksum];
        $this->post('/app/schedules/'.$record->id.'/publish', $publish)->assertRedirect('/app/schedules/'.$record->id);
        $this->post('/app/schedules/'.$record->id.'/publish', $publish)->assertRedirect('/app/schedules/'.$record->id);
        $this->assertSame('published', $record->fresh()->status);
        $this->assertSame(2, RosterShift::where('roster_version_id', $record->id)->where('status', 'published')->count());
        $this->login($this->worker)->get('/app/attendance?starts_on=2026-10-20&ends_on=2026-10-21')->assertOk()->assertSee('Synthetic Worker')->assertSee('Hari libur');
    }

    public function test_stale_roster_conflicts_and_invalid_filter_do_not_publish_or_expose_rows(): void
    {
        $this->roster('2026-10-20');
        $input = ['idempotency_key' => (string) Str::uuid(), 'employee_ids' => [$this->worker->employee_id], 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20', 'working_days' => [2], 'shift_template_id' => $this->template];
        $this->post('/app/schedules', $input)->assertRedirect();
        $record = RosterVersion::latest('id')->firstOrFail();
        $this->from('/app/schedules/'.$record->id)->post('/app/schedules/'.$record->id.'/publish', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 99, 'checksum' => $record->checksum])->assertSessionHasErrors('roster');
        $this->from('/app/schedules/'.$record->id)->post('/app/schedules/'.$record->id.'/publish', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $record->checksum])->assertSessionHasErrors();
        $this->assertSame('draft', $record->fresh()->status);
        $this->assertSame(1, RosterShift::where('status', 'published')->count());
        $this->get('/app/attendance?starts_on=2026-10-21&ends_on=2026-10-20')->assertSessionHasErrors('ends_on');
        $this->get('/app/attendance?starts_on=2026-01-01&ends_on=2026-12-31')->assertSessionHasErrors('ends_on');
        $input['working_days'] = [];
        $this->post('/app/schedules', $input)->assertSessionHasErrors('confirm_all_off');
    }

    public function test_staffing_warning_must_be_acknowledged_from_review_screen(): void
    {
        $this->postJson('/app/api/staffing-policies', ['idempotency_key' => (string) Str::uuid(), 'code' => 'UI-COVERAGE', 'work_location_id' => $this->location, 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'working_days' => [1, 2, 3, 4, 5, 6, 7], 'start_local' => '22:00', 'end_local' => '23:00', 'end_day_offset' => 0, 'minimum_staff' => 2, 'is_blocking' => false])->assertCreated();
        $this->post('/app/schedules', ['idempotency_key' => (string) Str::uuid(), 'employee_ids' => [$this->worker->employee_id], 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20', 'working_days' => [2], 'shift_template_id' => $this->template])->assertRedirect();
        $record = RosterVersion::latest('id')->firstOrFail();
        $this->get('/app/schedules/'.$record->id)->assertOk()->assertSee('Peringatan staffing')->assertSee('acknowledge_warnings')->assertSee('cakupan minimum 1 dari kebutuhan 2');
        $data = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $record->checksum];
        $this->from('/app/schedules/'.$record->id)->post('/app/schedules/'.$record->id.'/publish', $data)->assertSessionHasErrors('roster');
        $this->assertSame('draft', $record->fresh()->status);
        $this->post('/app/schedules/'.$record->id.'/publish', $data + ['acknowledge_warnings' => 1])->assertRedirect('/app/schedules/'.$record->id);
        $this->assertSame('published', $record->fresh()->status);
    }

    public function test_manager_cannot_disclose_a_mixed_scope_batch_or_publish_after_team_access_changes(): void
    {
        $draft = $this->roster('2026-10-20');
        $managerPerson = Employee::create(['employee_number' => 'UI-MGR', 'name' => 'UI Manager']);
        $manager = $this->user('employee', $managerPerson->id);
        $manager->grants()->create(['role' => 'manager', 'scope' => 'team']);
        $assignment = $this->worker->employee->assignments()->sole();
        $assignment->update(['manager_employee_id' => $managerPerson->id]);
        $this->login($manager)->get('/app/schedules/'.$draft['id'])->assertOk()->assertSee('Synthetic Worker');
        $assignment->update(['manager_employee_id' => null]);
        $this->get('/app/schedules/'.$draft['id'])->assertNotFound();
        $this->get('/app/attendance/'.$draft['shift_id'])->assertNotFound();
        $assignment->update(['manager_employee_id' => $managerPerson->id]);
        // Add an out-of-scope employee to the same existing batch; no partial batch disclosure.
        $other = Employee::create(['employee_number' => 'UI-OUT', 'name' => 'Hidden Outside Employee']);
        $copy = RosterShift::findOrFail($draft['shift_id'])->replicate();
        $copy->employee_id = $other->id;
        $copy->save();
        $this->get('/app/schedules/'.$draft['id'])->assertNotFound()->assertDontSee('Hidden Outside Employee');
        $this->get('/app/schedules')->assertOk()->assertDontSee('Roster #'.$draft['id']);
        $this->post('/app/schedules/'.$draft['id'].'/publish', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'checksum' => $draft['checksum']])->assertNotFound();
    }
}
