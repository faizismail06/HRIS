<?php

namespace Tests\Feature;

use App\Support\PrototypeAttendance;
use App\Support\PrototypeReports;
use Tests\TestCase;

class PrototypeReportsTest extends TestCase
{
    private array $pages = ['/prototype/attendance-reports', '/prototype/attendance-periods', '/prototype/attendance-periods/CLOSE-SEP-26'];

    public function test_reports_require_preview_and_reject_production(): void
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

    public function test_report_routes_are_read_only_and_render_with_privacy_headers(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach ($this->pages as $page) {
            $this->get($page)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->post($page)->assertStatus(405);
        }
    }

    public function test_late_and_early_are_overlapping_subsets_not_additional_presence_groups(): void
    {
        $records = collect(PrototypeAttendance::records())->where('person.id', 'EMP-004')->whereBetween('date', ['2026-10-19', '2026-10-25'])->all();
        $summary = PrototypeReports::summary($records);
        $this->assertSame(7, $summary['total']);
        $this->assertSame(1, $summary['counts']['PRESENT']);
        $this->assertSame(1, $summary['late']);
        $this->assertSame(1, $summary['early']);
        $this->assertSame(7, array_sum($summary['counts']));
        $dimas = PrototypeReports::summary([PrototypeAttendance::find('ATT-EMP-007-20')]);
        $this->assertSame(1, $dimas['counts']['IN_PROGRESS']);
        $this->assertSame(0, $dimas['late']);
        $rejected = PrototypeReports::summary([PrototypeAttendance::find('ATT-EMP-007-15')]);
        $this->assertSame(1, $rejected['counts']['ABSENT']);
        $this->assertSame(0, $rejected['counts']['PRESENT']);
    }

    public function test_combined_scope_and_source_detail_keep_report_return_context(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $filters = ['week' => '2026-10-19', 'branch' => 'Jakarta', 'department' => 'operasional', 'employee' => 'EMP-004'];
        $url = route('prototype.attendance.show', ['record' => 'ATT-EMP-004-19', 'from' => 'reports', 'report_week' => '2026-10-19', 'department' => 'operasional', 'branch' => 'Jakarta', 'employee' => 'EMP-004', 'date' => '2026-10-19']);
        $this->get(route('prototype.attendance-reports', $filters))->assertOk()->assertSee('7 baris hari/roster')->assertSee($url)->assertDontSee('ATT-EMP-007-19');
        $this->get($url)->assertOk()->assertSee('Kembali ke laporan')->assertSee(route('prototype.attendance-reports', $filters))->assertSee('20 Okt');
        $this->get('/prototype/attendance?date=2026-10-19&employee=EMP-004')->assertOk()->assertSee(route('prototype.attendance-reports', ['week' => '2026-10-19', 'employee' => 'EMP-004']));
    }

    public function test_review_has_blockers_and_source_changes_invalidate_readiness(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $period = PrototypeReports::period('CLOSE-OCT-W1');
        $this->assertNotSame($period['source_version'], $period['review_version']);
        $this->assertCount(2, array_filter(PrototypeReports::checks($period), fn ($check) => $check['level'] === 'BLOCKER'));
        $this->get('/prototype/attendance-periods/CLOSE-OCT-W1')->assertOk()->assertSee('2 shift belum lengkap')->assertSee('Versi sumber belum lolos tinjauan')->assertSee('Peringatan')->assertSee('ATT-OCT-W1-V1')->assertSee('ATT-OCT-W1-V2');
        $this->get('/prototype/attendance-periods/CLOSE-OCT-26')->assertOk()->assertSee('Shift berjalan atau mendatang')->assertSee('OVT-007')->assertSee('rencana 4 jam')
            ->assertSee(route('prototype.attendance-reports', ['week' => '2026-10-12']))->assertSee(route('prototype.attendance-reports', ['week' => '2026-10-19']));
    }

    public function test_closed_snapshot_includes_former_employee_and_links_final_payroll_without_reopening(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $period = PrototypeReports::period('CLOSE-SEP-26');
        $this->assertSame('CLOSED', $period['status']);
        $this->assertSame(hash('sha256', json_encode($period['snapshot'])), $period['checksum']);
        $this->assertContains('EMP-011', array_column($period['snapshot'], 'employee'));
        $this->get('/prototype/attendance-periods/CLOSE-SEP-26')->assertOk()->assertSee('Intan Kusuma')->assertSee('SRC-SEP-011-28-30')->assertSee($period['checksum'])->assertSee(route('prototype.payroll.show', ['batch' => 'PAY-SEP-26']))->assertSee('tidak menimpa hasil lama');
    }

    public function test_invalid_report_scope_empty_and_unknown_period_are_handled(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/attendance-periods/UNKNOWN')->assertNotFound();
        $this->getJson('/prototype/attendance-reports?week=2026-11-02')->assertUnprocessable()->assertJsonValidationErrors('week');
        $this->getJson('/prototype/attendance-reports?department[]=sdm')->assertUnprocessable()->assertJsonValidationErrors('department');
        $this->getJson('/prototype/attendance/ATT-EMP-004-19?from=reports&report_week=bad')->assertUnprocessable()->assertJsonValidationErrors('report_week');
        $this->get('/prototype/attendance-reports?branch=Bandung&employee=EMP-004')->assertOk()->assertSee('Tidak ada catatan yang cocok')->assertSee('Tidak ada shift sumber sesuai filter');
    }
}
