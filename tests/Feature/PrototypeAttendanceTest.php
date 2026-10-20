<?php

namespace Tests\Feature;

use App\Support\PrototypeAttendance;
use Tests\TestCase;

class PrototypeAttendanceTest extends TestCase
{
    private array $pages = ['/prototype/attendance', '/prototype/schedules', '/prototype/attendance/ATT-EMP-004-19'];

    public function test_attendance_pages_are_preview_gated_even_with_cached_production_routes(): void
    {
        config(['hris.ui_preview_enabled' => false]);
        foreach ($this->pages as $page) {
            $this->get($page)->assertNotFound();
        }
        config(['hris.ui_preview_enabled' => true]);
        $this->app['env'] = 'production';
        foreach ($this->pages as $page) {
            $this->get($page)->assertNotFound();
        }
    }

    public function test_overnight_example_keeps_one_work_date_and_both_presence_attributes(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $item = PrototypeAttendance::find('ATT-EMP-004-19');
        $this->assertSame('2026-10-19', $item['date']);
        $this->assertSame('2026-10-20', $item['out']->toDateString());
        $this->assertSame('PRESENT', $item['status']);
        $this->assertSame(398, $item['minutes']);

        $this->get('/prototype/attendance?date=2026-10-19&employee=EMP-004')
            ->assertOk()->assertSee('Terlambat 12 mnt')->assertSee('Pulang awal 10 mnt')->assertSee('Hadir')
            ->assertSee('20 Okt, 05.50')->assertDontSee('Arif Wicaksono</span>', false);
        $this->get('/prototype/attendance/ATT-EMP-004-19')
            ->assertOk()->assertSee('6 jam 38 menit')->assertSee('SIMULATED')->assertSee('Hari berikutnya');
    }

    public function test_missing_checkout_day_off_leave_and_absence_remain_distinct(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/attendance/ATT-EMP-004-18')->assertOk()->assertSee('Belum lengkap')->assertSee('Jam pulang belum tercatat');
        $this->get('/prototype/attendance/ATT-EMP-007-19')->assertOk()->assertSee('Libur terjadwal')->assertSee('hari libur bukan tidak hadir');
        $this->get('/prototype/attendance/ATT-EMP-010-19')->assertOk()->assertSee('Cuti disetujui');
        $this->get('/prototype/attendance/ATT-EMP-007-16')->assertOk()->assertSee('Tidak hadir');
        $this->get('/prototype/attendance/ATT-EMP-007-20')->assertOk()->assertSee('Sedang bekerja')->assertSee('bukan status belum lengkap');
        $this->get('/prototype/attendance/ATT-EMP-009-21')->assertOk()->assertSee('Libur terjadwal');
        $this->get('/prototype/attendance/ATT-EMP-009-22')->assertOk()->assertSee('Belum mulai');
    }

    public function test_rejected_location_attempt_does_not_create_presence(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $record = PrototypeAttendance::find('ATT-EMP-007-15');
        $this->assertSame('ABSENT', $record['status']);
        $this->assertNull($record['in']);
        $this->assertNull($record['minutes']);
        $this->get('/prototype/attendance/ATT-EMP-007-15')
            ->assertOk()->assertSee('Percobaan clock-in ditolak')->assertSee('180 m')->assertSee('Tidak dihitung hadir.');
    }

    public function test_week_navigation_retains_employee_branch_and_origin_on_detail(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $query = ['week' => '2026-10-12', 'branch' => 'Jakarta', 'employee' => 'EMP-004'];
        $this->get(route('prototype.schedules', $query))->assertOk()->assertSee('Hari berikutnya')->assertSee('Minggu berikutnya')
            ->assertSee(route('prototype.attendance', ['date' => '2026-10-12', 'branch' => 'Jakarta', 'employee' => 'EMP-004']));
        $this->get(route('prototype.attendance.show', array_merge(['record' => 'ATT-EMP-004-18', 'from' => 'schedules'], $query)))
            ->assertOk()->assertSee(route('prototype.schedules', $query))->assertSee('Kembali ke jadwal');
        $day = ['date' => '2026-10-18', 'branch' => 'Jakarta', 'employee' => 'EMP-004', 'status' => 'INCOMPLETE'];
        $this->get(route('prototype.attendance', $day))->assertOk()->assertSee(route('prototype.schedules', $query));
        $this->get(route('prototype.attendance.show', array_merge(['record' => 'ATT-EMP-004-18'], $day)))
            ->assertOk()->assertSee(route('prototype.attendance', $day));
    }

    public function test_invalid_inputs_unknown_records_and_empty_filters_have_clear_results(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->getJson('/prototype/attendance?date=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson('/prototype/schedules?week[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('week');
        $this->getJson('/prototype/attendance?status=arbitrary')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->get('/prototype/attendance/UNKNOWN')->assertNotFound();
        $this->get('/prototype/attendance?date=2026-10-19&status=INCOMPLETE')->assertOk()->assertSee('Tidak ada hasil kehadiran yang cocok');
        $this->get('/prototype/schedules?employee=EMP-004&branch=Surabaya')->assertOk()->assertSee('Tidak ada jadwal untuk kombinasi filter ini');
    }

    public function test_preview_has_no_clocking_or_mutation_endpoint(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach ($this->pages as $page) {
            $this->post($page)->assertStatus(405);
        }
        $this->post('/attendance/clock-in')->assertNotFound();
    }
}
