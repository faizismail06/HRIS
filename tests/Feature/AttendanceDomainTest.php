<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RosterShift;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class AttendanceDomainTest extends AttendanceScenarioCase
{
    public function test_night_shift_next_day_departure_preserves_work_date_and_both_late_and_early_flags(): void
    {
        $draft = $this->roster();
        $this->login($this->worker);
        $this->travelTo(Carbon::parse('2026-10-19 22:12:00', 'Asia/Jakarta'));
        $key = (string) Str::uuid();
        $first = $this->clock($draft['shift_id'], 'in', [], $key)->assertOk()->json();
        $this->clock($draft['shift_id'], 'in', [], $key)->assertOk()->assertExactJson($first);
        $this->travelTo(Carbon::parse('2026-10-20 05:50:00', 'Asia/Jakarta'));
        $this->clock($draft['shift_id'], 'out')->assertOk()->assertJsonPath('work_date', '2026-10-19')->assertJsonPath('status', 'PRESENT');
        $result = $this->getJson('/app/api/attendance/'.$draft['shift_id'])->assertOk()->json('result');
        $this->assertSame(398, $result['work_minutes']);
        $this->assertSame(12, $result['late_minutes']);
        $this->assertSame(10, $result['early_minutes']);
        $this->assertDatabaseCount('attendance_events', 2);
        $version = $result['version'];
        $this->getJson('/app/api/attendance/'.$draft['shift_id'])->assertJsonPath('result.version', $version);
        $this->assertStringNotContainsString('latitude', json_encode(DB::table('audit_entries')->get()));
    }

    public function test_geofence_accuracy_and_identity_or_simulation_overrides_are_rejected_without_presence(): void
    {
        $draft = $this->roster();
        $this->login($this->worker);
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00'));
        $this->clock($draft['shift_id'], 'in', ['accuracy_m' => 101])->assertUnprocessable()->assertJsonPath('code', 'poor_accuracy');
        $this->clock($draft['shift_id'], 'in', ['latitude' => -6.3])->assertUnprocessable()->assertJsonPath('code', 'outside_geofence');
        $this->clock($draft['shift_id'], 'in', ['simulation' => true])->assertUnprocessable();
        $this->clock($draft['shift_id'], 'in', ['employee_id' => 999])->assertUnprocessable();
        $this->clock($draft['shift_id'], 'in', ['occurred_at' => '2026-10-19T22:00:00+07:00'])->assertUnprocessable();
        $this->assertDatabaseCount('attendance_events', 0);
        $this->assertDatabaseCount('attendance_attempts', 2);
        $this->assertDatabaseCount('attendance_results', 0);
    }

    public function test_duplicate_roster_cannot_publish_and_a_template_edit_does_not_change_history(): void
    {
        $first = $this->roster();
        $this->postJson('/app/api/rosters/preview', ['rows' => [['employee_id' => $this->worker->employee_id, 'work_date' => '2026-10-19', 'shift_template_id' => $this->template, 'is_day_off' => false]]])->assertOk()->assertJsonCount(1, 'conflicts');
        $duplicate = $this->postJson('/app/api/rosters', ['idempotency_key' => (string) Str::uuid(), 'rows' => [['employee_id' => $this->worker->employee_id, 'work_date' => '2026-10-19', 'shift_template_id' => $this->template, 'is_day_off' => false]]])->assertCreated()->json();
        $this->postJson('/app/api/rosters/'.$duplicate['id'].'/publish', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'checksum' => $duplicate['checksum']])->assertUnprocessable();
        $this->assertSame(1, RosterShift::where('status', 'published')->count());
        DB::table('shift_templates')->where('id', $this->template)->update(['tolerance_minutes' => 50]);
        $this->assertSame(0, RosterShift::find($first['shift_id'])->snapshot['tolerance_minutes']);
    }

    public function test_missing_checkout_absent_and_day_off_are_distinct_and_no_early_absence_is_inferred(): void
    {
        $first = $this->roster();
        $this->roster('2026-10-20', true);
        $third = $this->roster('2026-10-21');
        $this->login($this->worker);
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00'));
        $this->clock($first['shift_id'], 'in')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-20 11:00:00'));
        $this->getJson('/app/api/attendance/'.$first['shift_id'])->assertJsonPath('result.status', 'INCOMPLETE');
        $this->getJson('/app/api/attendance/'.$third['shift_id'])->assertJsonPath('result.status', 'SCHEDULED');
        $this->getJson('/app/api/my-schedule?starts_on=2026-10-19&ends_on=2026-10-21')->assertOk()->assertSee('DAY_OFF');
        $this->travelTo(Carbon::parse('2026-10-22 11:00:00'));
        $this->getJson('/app/api/attendance/'.$third['shift_id'])->assertJsonPath('result.status', 'ABSENT');
    }

    public function test_wrong_employee_clock_and_private_photo_access_are_blocked(): void
    {
        Storage::fake('local');
        $draft = $this->roster();
        DB::table('roster_shifts')->where('id', $draft['shift_id'])->update(['snapshot' => json_encode(array_replace_recursive(RosterShift::find($draft['shift_id'])->snapshot, ['location' => ['requires_photo' => true]]))]);
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00'));
        $this->login($this->worker);
        $this->clock($draft['shift_id'], 'in')->assertUnprocessable()->assertJsonPath('code', 'photo_required');
        $this->post('/app/api/attendance/'.$draft['shift_id'].'/photo', ['idempotency_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('bad.png', 'corrupt')], ['Accept' => 'application/json'])->assertUnprocessable();
        $photo = $this->post('/app/api/attendance/'.$draft['shift_id'].'/photo', ['idempotency_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->image('proof.png', 20, 20)], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->clock($draft['shift_id'], 'in')->assertOk();
        $otherPerson = Employee::create(['employee_number' => 'OTHER', 'name' => 'Other']);
        $other = $this->user('employee', $otherPerson->id);
        $this->login($other);
        $this->clock($draft['shift_id'], 'out')->assertNotFound();
        $this->getJson('/app/api/attendance/'.$draft['shift_id'])->assertNotFound();
        $this->getJson('/app/api/attachments/'.$photo.'/download')->assertNotFound();
        $this->login($this->worker)->get('/app/api/attachments/'.$photo.'/download')->assertOk();
    }

    public function test_raw_events_cannot_be_edited_and_approved_correction_changes_only_projection(): void
    {
        $draft = $this->roster();
        $this->login($this->worker);
        $this->travelTo(Carbon::parse('2026-10-19 22:10:00'));
        $this->clock($draft['shift_id'], 'in')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $this->login($this->hr);
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Correction', 'kind' => 'correction', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
        $this->login($this->worker);
        $id = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'correction', 'payload' => ['roster_shift_id' => $draft['shift_id'], 'effective_in' => '2026-10-19T22:00:00+07:00', 'effective_out' => '2026-10-20T06:00:00+07:00', 'reason' => 'Synthetic missing checkout']])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($this->hr);
        $this->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();
        $this->getJson('/app/api/attendance/'.$draft['shift_id'])->assertJsonPath('result.status', 'PRESENT')->assertJsonPath('result.work_minutes', 420);
        $this->assertDatabaseCount('attendance_events', 1);
        try {
            DB::transaction(fn () => DB::table('attendance_events')->update(['direction' => 'out']));
            $this->fail('Raw attendance must be immutable.');
        } catch (QueryException $error) {
            $this->assertSame('P0001', $error->getCode());
        }
    }
}
