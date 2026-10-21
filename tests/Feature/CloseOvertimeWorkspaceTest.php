<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class CloseOvertimeWorkspaceTest extends AttendanceScenarioCase
{
    private function createPeriod(): int
    {
        $this->login($this->hr)->post('/app/attendance-periods', ['idempotency_key' => (string) Str::uuid(), 'branch_id' => WorkLocation::findOrFail($this->location)->branch_id, 'starts_on' => '2026-10-19', 'last_work_date' => '2026-10-19'])->assertRedirect();

        return DB::table('attendance_periods')->latest('id')->value('id');
    }

    private function input(int $version, array $extra = []): array
    {
        return $extra + ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $version];
    }

    private function authorizeOvertime(): array
    {
        $draft = $this->roster();
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Synthetic UI Overtime', 'kind' => 'overtime', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
        $this->login($this->worker);
        $request = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'overtime', 'payload' => ['roster_shift_id' => $draft['shift_id'], 'planned_start' => '2026-10-19T22:00:00+07:00', 'planned_end' => '2026-10-20T01:00:00+07:00', 'reason' => 'Synthetic overtime reason', 'task' => 'Synthetic operational task']])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$request.'/submit', $this->input(1))->assertOk();
        $this->login($this->hr)->postJson('/app/api/requests/'.$request.'/decide', $this->input(2, ['decision' => 'approve']))->assertOk();

        return $draft + ['overtime_id' => DB::table('overtime_authorizations')->where('workflow_request_id', $request)->value('id')];
    }

    public function test_review_close_reopen_and_revision_history_preserve_frozen_snapshots(): void
    {
        $this->roster('2026-10-19', true);
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
        $id = $this->createPeriod();
        $this->assertDatabaseHas('attendance_periods', ['id' => $id, 'ends_on' => '2026-10-20']);
        $this->get('/app/attendance-periods')->assertOk()->assertSee('2026-10-19 — 2026-10-19');
        $this->get('/app/attendance-periods/'.$id)->assertOk()->assertSee('Jalankan tinjauan');
        $this->post('/app/attendance-periods/'.$id.'/review', $this->input(1))->assertRedirect('/app/attendance-periods/'.$id);
        $this->get('/app/attendance-periods/'.$id)->assertOk()->assertSee('Tidak ada penghambat')->assertSee('Tutup periode dan simpan snapshot');
        $this->get('/app/attendance-periods/'.$id)->assertOk();
        $period = DB::table('attendance_periods')->find($id);
        $this->assertSame(2, $period->version);
        $input = $this->input(2, ['checksum' => $period->review_checksum]);
        $this->post('/app/attendance-periods/'.$id.'/close', $input)->assertRedirect();
        $this->post('/app/attendance-periods/'.$id.'/close', $input)->assertRedirect();
        $this->assertDatabaseCount('attendance_snapshots', 1);
        $sha = DB::table('attendance_snapshots')->value('sha256');
        $this->get('/app/attendance-periods/'.$id)->assertOk()->assertSee('Snapshot revisi 1')->assertSee($sha)->assertSee('Synthetic Worker');
        $this->post('/app/attendance-periods/'.$id.'/reopen', $this->input(3))->assertSessionHasErrors('reason');
        $this->post('/app/attendance-periods/'.$id.'/reopen', $this->input(3, ['reason' => 'Synthetic revision review']))->assertRedirect();
        $audit = DB::table('audit_entries')->where('action', 'attendance_period.reopened')->value('changes');
        $this->assertStringNotContainsString('Synthetic revision review', $audit);
        $this->assertSame('Synthetic revision review', decrypt(json_decode($audit, true)['reason_encrypted']));
        $this->get('/app/attendance-periods/'.$id)->assertOk()->assertSee($sha)->assertSee('Jalankan tinjauan');
        $this->post('/app/attendance-periods/'.$id.'/review', $this->input(4))->assertRedirect();
        $period = DB::table('attendance_periods')->find($id);
        $this->post('/app/attendance-periods/'.$id.'/close', $this->input(5, ['checksum' => $period->review_checksum]))->assertRedirect();
        $this->assertDatabaseCount('attendance_snapshots', 2);
        $this->get('/app/attendance-periods/'.$id.'?revision=1')->assertOk()->assertSee('Snapshot revisi 1')->assertSee($sha);
        DB::table('attendance_periods')->where('id', $id)->update(['payroll_finalized' => true]);
        $this->get('/app/attendance-periods/'.$id)->assertOk()->assertSee('Payroll sudah final')->assertDontSee('Alasan membuka kembali');
        $this->from('/app/attendance-periods/'.$id)->post('/app/attendance-periods/'.$id.'/reopen', $this->input(6, ['reason' => 'Forbidden after final payroll']))->assertSessionHasErrors('period');
    }

    public function test_unresolved_attendance_blocks_close_and_non_hr_cannot_read_period_data(): void
    {
        $draft = $this->roster();
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00'));
        $this->login($this->worker);
        $this->clock($draft['shift_id'], 'in')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $id = $this->createPeriod();
        $this->post('/app/attendance-periods/'.$id.'/review', $this->input(1))->assertRedirect();
        $this->get('/app/attendance-periods/'.$id)->assertOk()->assertSee('Kehadiran belum selesai')->assertSee(route('workspace.attendance.show', $draft['shift_id']))->assertDontSee('Tutup periode dan simpan snapshot');
        $period = DB::table('attendance_periods')->find($id);
        $this->from('/app/attendance-periods/'.$id)->post('/app/attendance-periods/'.$id.'/close', $this->input(2, ['checksum' => $period->review_checksum]))->assertSessionHasErrors('period');
        $this->assertDatabaseCount('attendance_snapshots', 0);
        foreach ([$this->worker, $this->user('hr_officer')] as $actor) {
            $this->login($actor)->get('/app/attendance-periods')->assertForbidden();
            $this->get('/app/attendance-periods/'.$id)->assertForbidden();
            $this->post('/app/attendance-periods/'.$id.'/review', $this->input(2))->assertForbidden();
        }
    }

    public function test_changed_sources_after_review_require_a_new_review_before_close(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
        $id = $this->createPeriod();
        $this->post('/app/attendance-periods/'.$id.'/review', $this->input(1))->assertRedirect();
        $period = DB::table('attendance_periods')->find($id);
        $this->get('/app/attendance-periods/'.$id)->assertOk()->assertSee('Jadwal karyawan belum lengkap');
        $this->roster('2026-10-19', true);
        $this->from('/app/attendance-periods/'.$id)->post('/app/attendance-periods/'.$id.'/close', $this->input(2, ['checksum' => $period->review_checksum]))->assertSessionHasErrors('period');
        $this->assertDatabaseCount('attendance_snapshots', 0);
        $this->post('/app/attendance-periods/'.$id.'/review', $this->input(2))->assertRedirect();
        $this->get('/app/attendance-periods/'.$id)->assertOk()->assertSee('Tidak ada penghambat');
    }

    public function test_two_actual_hours_of_three_planned_hours_are_verified_independently_and_replayed_once(): void
    {
        $draft = $this->authorizeOvertime();
        $id = $draft['overtime_id'];
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00'));
        $this->login($this->worker);
        $this->clock($draft['shift_id'], 'in')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-20 00:00:00'));
        $this->clock($draft['shift_id'], 'out')->assertOk();
        $this->get('/app/overtime')->assertOk()->assertSee('Synthetic Worker');
        $this->get('/app/overtime/'.$id)->assertOk()->assertSee('Isi aktual lembur')->assertSee('Asia/Jakarta')->assertDontSee('Verifikasi aktual lembur');
        $data = $this->input(1, ['actual_start' => '2026-10-19T22:00', 'actual_end' => '2026-10-20T00:00']);
        $this->post('/app/overtime/'.$id.'/actual', $data)->assertRedirect('/app/overtime/'.$id);
        $this->post('/app/overtime/'.$id.'/actual', $data)->assertRedirect('/app/overtime/'.$id);
        $this->assertDatabaseHas('overtime_authorizations', ['id' => $id, 'version' => 2, 'payable_minutes' => 0]);
        $this->post('/app/overtime/'.$id.'/verify', $this->input(2))->assertForbidden();
        $this->login($this->hr)->get('/app/overtime/'.$id)->assertOk()->assertSee('Verifikasi aktual lembur')->assertDontSee('Isi aktual lembur');
        $verify = $this->input(2);
        $this->post('/app/overtime/'.$id.'/verify', $verify)->assertRedirect('/app/overtime/'.$id);
        $this->post('/app/overtime/'.$id.'/verify', $verify)->assertRedirect('/app/overtime/'.$id);
        $this->assertDatabaseHas('overtime_authorizations', ['id' => $id, 'version' => 3, 'status' => 'verified', 'payable_minutes' => 120]);
        $this->assertSame(1, DB::table('audit_entries')->where('action', 'overtime.actual_verified')->count());
        $this->get('/app/overtime/'.$id)->assertOk()->assertSee('120 menit');
    }

    public function test_manager_requires_attendance_support_hr_exception_is_private_and_unrelated_users_are_denied(): void
    {
        $draft = $this->authorizeOvertime();
        $id = $draft['overtime_id'];
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $this->login($this->worker)->post('/app/overtime/'.$id.'/actual', $this->input(1, ['actual_start' => '2026-10-19T22:00', 'actual_end' => '2026-10-20T00:00']))->assertRedirect();
        $managerPerson = Employee::create(['employee_number' => 'OT-MGR', 'name' => 'Synthetic OT Manager']);
        $manager = $this->user('employee', $managerPerson->id);
        $manager->grants()->create(['role' => 'manager', 'scope' => 'team']);
        $assignment = $this->worker->employee->assignments()->sole();
        $assignment->update(['manager_employee_id' => $managerPerson->id]);
        $this->login($manager)->get('/app/overtime/'.$id)->assertOk()->assertSee('Verifikasi aktual lembur')->assertDontSee('exception_reason');
        $this->from('/app/overtime/'.$id)->post('/app/overtime/'.$id.'/verify', $this->input(2))->assertSessionHasErrors('overtime');
        $this->post('/app/overtime/'.$id.'/verify', $this->input(2, ['exception_reason' => 'Unauthorized manager exception']))->assertForbidden();
        $this->login($this->hr)->post('/app/overtime/'.$id.'/verify', $this->input(2, ['exception_reason' => 'Private synthetic HR exception evidence']))->assertRedirect();
        $this->assertStringNotContainsString('Private synthetic HR', DB::table('overtime_authorizations')->where('id', $id)->value('exception_reason'));
        $this->login($this->worker)->get('/app/overtime/'.$id)->assertOk()->assertSee('Pengecualian beralasan dicatat')->assertDontSee('Private synthetic HR exception evidence');
        $assignment->update(['manager_employee_id' => null]);
        $this->login($manager)->get('/app/overtime/'.$id)->assertNotFound();
        $this->get('/app/overtime')->assertOk()->assertDontSee('Synthetic Worker');
        $this->post('/app/overtime/'.$id.'/actual', $this->input(3, ['actual_start' => '2026-10-19T22:00', 'actual_end' => '2026-10-20T00:00']))->assertNotFound();
    }

    public function test_approved_attendance_correction_marks_prior_overtime_verification_stale(): void
    {
        $draft = $this->authorizeOvertime();
        $id = $draft['overtime_id'];
        $requestId = DB::table('overtime_authorizations')->where('id', $id)->value('workflow_request_id');
        $this->login($this->worker)->get('/app/requests/'.$requestId)->assertOk()->assertSee(route('workspace.overtime.show', $id));
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00'));
        $this->clock($draft['shift_id'], 'in')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-20 00:00:00'));
        $this->clock($draft['shift_id'], 'out')->assertOk();
        $this->post('/app/overtime/'.$id.'/actual', $this->input(1, ['actual_start' => '2026-10-19T22:00', 'actual_end' => '2026-10-20T00:00']))->assertRedirect();
        $this->login($this->hr)->post('/app/overtime/'.$id.'/verify', $this->input(2))->assertRedirect();
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Synthetic source correction', 'kind' => 'correction', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $this->login($this->worker);
        $correction = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'correction', 'payload' => ['roster_shift_id' => $draft['shift_id'], 'effective_in' => '2026-10-19T22:05:00+07:00', 'effective_out' => '2026-10-20T00:00:00+07:00', 'reason' => 'Synthetic correction changes source']])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$correction.'/submit', $this->input(1))->assertOk();
        $this->login($this->hr)->postJson('/app/api/requests/'.$correction.'/decide', $this->input(2, ['decision' => 'approve']))->assertOk();
        $this->get('/app/overtime/'.$id)->assertOk()->assertSee('Sumber kehadiran berubah setelah verifikasi')->assertSee('Verifikasi ulang aktual');
        $this->from('/app/overtime/'.$id)->post('/app/overtime/'.$id.'/verify', $this->input(3))->assertSessionHasErrors('overtime');
    }
}
