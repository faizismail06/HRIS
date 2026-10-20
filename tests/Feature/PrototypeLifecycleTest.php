<?php

namespace Tests\Feature;

use App\Support\PrototypeLifecycle;
use App\Support\PrototypePeople;
use Tests\TestCase;

class PrototypeLifecycleTest extends TestCase
{
    private array $pages = ['/prototype/lifecycle', '/prototype/lifecycle/LIF-001', '/prototype/assets', '/prototype/assets/AST-001'];

    public function test_lifecycle_and_assets_require_preview_and_reject_production(): void
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

    public function test_routes_render_with_privacy_headers_and_reject_mutation(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach ($this->pages as $page) {
            $this->get($page)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->post($page)->assertStatus(405);
        }
    }

    public function test_filters_preserve_context_and_drop_kind_specific_status_on_navigation(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $filters = ['q' => 'Maya', 'status' => 'PREPARING', 'branch' => 'Jakarta', 'employee' => 'EMP-006'];
        $this->get(route('prototype.lifecycle', $filters))->assertOk()->assertSee('LIF-001')->assertDontSee('LIF-002');
        $this->get(route('prototype.lifecycle.show', array_merge($filters, ['record' => 'LIF-001'])))
            ->assertOk()->assertSee(route('prototype.lifecycle', $filters))
            ->assertSee(route('prototype.assets', ['branch' => 'Jakarta', 'employee' => 'EMP-006']))
            ->assertSee(route('prototype.assets.show', ['record' => 'AST-003', 'employee' => 'EMP-006']));
        $this->get('/prototype/assets?q=DEMO-BDG-002&status=AWAITING_RETURN&branch=Bandung&employee=EMP-011')
            ->assertOk()->assertSee('AST-002')->assertDontSee('AST-005');
    }

    public function test_onboarding_exposes_unprepared_account_owner_dependency_and_mandatory_tasks(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/lifecycle/LIF-001')->assertOk()->assertSee('Petugas IT contoh')->assertSee('Setelah ON-01')->assertSee('Akun belum siap')->assertSee('Wajib')->assertSee('Opsional')->assertSee('0/4 selesai');
        $this->assertSame('PREBOARDING', PrototypePeople::find('EMP-006')['status']);
        $this->assertSame([], PrototypeLifecycle::asset('AST-003')['custody']);
        $this->get('/prototype/assets/AST-003')->assertOk()->assertSee('Belum ada serah terima')->assertSee('Calon penerima');
    }

    public function test_open_asset_does_not_imply_access_remains_active_after_exit(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/lifecycle/LIF-002')->assertOk()->assertSee('30 Oktober 2026')->assertSee('31 Oktober 2026')->assertSee('2026-11-01 00:00 WIB')->assertSee('Masih aktif sampai jadwal akhir');
        $this->get('/prototype/lifecycle/LIF-003')->assertOk()->assertSee('Dicabut dalam skenario')->assertSee('Lewat tenggat')->assertSee('CASE-AST-002')->assertSee('konfirmasi manual');
        $this->assertSame('ENDED', PrototypePeople::find('EMP-011')['status']);
        $this->assertNull(PrototypeLifecycle::asset('AST-002')['custody'][0]['until']);
        $this->get('/prototype/assets/AST-002')->assertOk()->assertSee('Tidak ada potongan gaji otomatis')->assertSee('Belum dinyatakan hilang');
    }

    public function test_custody_has_one_active_holder_and_consumables_have_no_return_obligation(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach (PrototypeLifecycle::assets() as $asset) {
            $this->assertLessThanOrEqual(1, count(array_filter($asset['custody'], fn ($custody) => $custody['until'] === null)));
        }
        $this->get('/prototype/assets/AST-004')->assertOk()->assertSee('Lestari Anindya · Sudah kembali')->assertSee('Fajar Nugraha · Pemegang aktif');
        $this->get('/prototype/assets/AST-005')->assertOk()->assertSee('CASE-AST-005')->assertSee('Belum ada tanggungan finansial');
        $this->get('/prototype/assets/ITM-001')->assertOk()->assertSee('Tidak · dicatat sebagai barang yang diserahkan')->assertSee('tidak masuk riwayat peminjaman');
        $this->assertFalse(PrototypeLifecycle::asset('ITM-001')['returnable']);
        $this->assertSame([], PrototypeLifecycle::asset('ITM-001')['custody']);
    }

    public function test_rehire_keeps_previous_clearance_separate_and_invalid_inputs_are_handled(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/lifecycle/LIF-004')->assertOk()->assertSee('PER-010-2024')->assertSee('PER-010-2022')->assertSee('CLR-DEMO-010-2023')->assertSee('3/3 selesai');
        $this->get('/prototype/lifecycle/AST-001')->assertNotFound();
        $this->get('/prototype/assets/UNKNOWN')->assertNotFound();
        $this->getJson('/prototype/lifecycle?status=IN_USE')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/prototype/assets?q[]=bad')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/prototype/assets?employee=UNKNOWN')->assertUnprocessable()->assertJsonValidationErrors('employee');
        $this->get('/prototype/assets?q=no-match')->assertOk()->assertSee('Tidak ada catatan yang cocok');
    }
}
