<?php

namespace Tests\Feature;

use App\Support\PrototypeAttendance;
use App\Support\PrototypeRequests;
use Tests\TestCase;

class PrototypeRequestsTest extends TestCase
{
    private array $pages = ['/prototype/requests', '/prototype/requests/CUT-041', '/prototype/leave-balances', '/prototype/team-calendar'];

    public function test_request_pages_require_preview_and_reject_production(): void
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

    public function test_list_combines_scope_and_retains_detail_return_filters(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $filters = ['q' => 'Nadia', 'type' => 'LEAVE', 'status' => 'IN_REVIEW', 'branch' => 'Jakarta', 'employee' => 'EMP-004'];
        $this->get(route('prototype.requests', $filters))->assertOk()->assertSee('CUT-041')->assertDontSee('CUT-040')->assertDontSee('COR-026');
        $this->get(route('prototype.requests.show', array_merge(['requestId' => 'CUT-041'], $filters)))
            ->assertOk()->assertSee(route('prototype.requests', $filters))->assertSee('Lestari Anindya')->assertSee('Menunggu keputusan');
    }

    public function test_balance_reservations_releases_and_half_days_are_distinct(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $nadia = PrototypeRequests::ledger('EMP-004');
        $this->assertSame(24, $nadia['credits']);
        $this->assertSame(6, $nadia['used']);
        $this->assertSame(4, $nadia['reserved']);
        $this->assertSame(14, $nadia['available']);
        $this->assertSame(0, PrototypeRequests::ledger('EMP-007')['reserved']);
        $this->assertSame('9,5', PrototypeRequests::days(PrototypeRequests::ledger('EMP-005')['available']));
        $this->get('/prototype/leave-balances?employee=EMP-004')->assertOk()->assertSee('7 hari tersedia')->assertSee('Reservasi');
        $this->get('/prototype/leave-balances?employee=EMP-007')->assertOk()->assertSee('Pelepasan reservasi');
        $this->get('/prototype/requests/CUT-042')->assertOk()->assertSee('0,5 hari kerja');
        $this->get('/prototype/requests/CUT-041?from=balances&employee=EMP-004')
            ->assertOk()->assertSee(route('prototype.leave-balances', ['employee' => 'EMP-004']));
    }

    public function test_calendar_has_availability_without_request_payload_or_private_reasons(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/team-calendar?week=2026-10-19&employee=EMP-004')
            ->assertOk()->assertSee('Tidak tersedia')->assertSee('Terjadwal')
            ->assertDontSee('CUT-041')->assertDontSee('CUT-040')->assertDontSee('Cakupan tim minimum');
        $this->assertSame('LEAVE', PrototypeAttendance::find('ATT-EMP-004-23')['status']);
        $this->assertSame('SCHEDULED', PrototypeAttendance::find('ATT-EMP-004-21')['status']);
        $this->get('/prototype/attendance/ATT-EMP-004-18')->assertOk()->assertSee('Belum lengkap');
    }

    public function test_rejected_withdrawn_and_overtime_examples_do_not_offer_fake_approval(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/requests/CUT-039')->assertOk()->assertSee('Ditolak')->assertSee('Cakupan tim minimum');
        $this->get('/prototype/requests/SWP-003')->assertOk()->assertSee('Ditarik')->assertSee('Tidak dilanjutkan');
        $this->get('/prototype/requests/OVT-007')->assertOk()->assertSee('tidak otomatis menjadi lembur terbayar');
        $this->get('/prototype/requests/COR-026')->assertOk()->assertSee('Raw event belum berubah')->assertSee('Lihat shift sumber');
        foreach ($this->pages as $page) {
            $this->post($page)->assertStatus(405);
        }
    }

    public function test_unknown_ids_invalid_scope_and_empty_results_are_handled(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/requests/UNKNOWN')->assertNotFound();
        $this->getJson('/prototype/requests?type[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->getJson('/prototype/leave-balances?employee=UNKNOWN')->assertUnprocessable()->assertJsonValidationErrors('employee');
        $this->getJson('/prototype/team-calendar?week=2026-11-02')->assertUnprocessable()->assertJsonValidationErrors('week');
        $this->get('/prototype/requests?q=no-match')->assertOk()->assertSee('Tidak ada pengajuan yang cocok');
        $this->get('/prototype/team-calendar?employee=EMP-004&branch=Surabaya')->assertOk()->assertSee('Tidak ada anggota yang cocok');
    }
}
