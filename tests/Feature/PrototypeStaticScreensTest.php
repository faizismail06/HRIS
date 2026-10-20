<?php

namespace Tests\Feature;

use App\Http\Controllers\PrototypePortalController;
use App\Support\PrototypeScreens;
use Tests\TestCase;

class PrototypeStaticScreensTest extends TestCase
{
    private array $pages = ['/prototype/screens', '/prototype/screens/leave', '/prototype/screens/login', '/prototype/portals/employee', '/prototype/states/forbidden'];

    public function test_all_screen_families_require_preview_and_reject_production(): void
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

    public function test_catalog_links_and_read_only_routes_have_privacy_headers(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach ($this->pages as $page) {
            $this->get($page)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->post($page)->assertStatus(405);
        }
        $this->get('/prototype/screens')->assertOk()->assertSee('Formulir per modul')->assertSee(route('prototype.screen', ['screen' => 'demo']))->assertSee(route('prototype.state', ['state' => 'permission-denied']));
    }

    public function test_every_form_renders_ready_and_invalid_without_accepting_data(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach (PrototypeScreens::forms() as $key => $spec) {
            $this->get(route('prototype.screen', ['screen' => $key]))->assertOk()->assertSee($spec['title'])->assertSee('<fieldset disabled', false)->assertSee('belum aktif');
            $this->get(route('prototype.screen', ['screen' => $key, 'state' => 'invalid']))->assertOk()->assertSee('Input contoh lainnya dipertahankan')->assertSee('aria-invalid="true"', false);
        }
    }

    public function test_role_homes_render_and_employee_uses_own_snapshot_and_next_shift(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach (PrototypePortalController::PERSONAS as $key => $label) {
            $this->get(route('prototype.portal', ['persona' => $key]))->assertOk()->assertSee('bukan pergantian akun atau izin');
        }
        $this->get('/prototype/portals/employee')->assertOk()->assertSee('Hari ini libur')->assertSee('21 Oktober 2026')->assertSee('7 hari')->assertSee('2 hari')->assertSee('Koordinator Operasional')->assertSee(route('prototype.payroll.payslip', ['batch' => 'PAY-SEP-26', 'employee' => 'EMP-004']));
        $this->get('/prototype/portals/manager')->assertOk()->assertSee('OVT-007')->assertSee('Raka Adinata')->assertDontSee('Intan Kusuma')->assertDontSee('CUT-042');
        $this->get('/prototype/portals/finance')->assertOk()->assertSee('Transfer gagal')->assertSee('Sebagian dibayar');
        $this->get('/prototype/portals/it')->assertOk()->assertSee('Maya')->assertSee('Intan')->assertDontSee('Rp 7.300.000');
    }

    public function test_auth_screens_have_no_hr_navigation_and_no_credential_actions(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/screens/login')->assertOk()->assertSee('jangan masukkan kredensial')->assertSee('type="password"', false)->assertDontSee('Navigasi ruang kerja')->assertDontSee('Simpan draf');
        $this->get('/prototype/screens/mfa')->assertOk()->assertSee('Verifikasi · belum aktif');
        $this->get('/prototype/screens/recovery')->assertOk()->assertSee('tidak mengungkap apakah akun ada');
    }

    public function test_all_states_render_with_safe_recovery_links(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach (PrototypeScreens::states() as $key => $spec) {
            $this->get(route('prototype.state', ['state' => $key]))->assertOk()->assertSee($spec['title'])->assertSee(route('prototype.screen', ['screen' => $spec['screen']]));
        }
        $this->get('/prototype/states/forbidden')->assertOk()->assertDontSee('Nadia Pramesti')->assertDontSee('Rp 6.400.000');
        $this->get('/prototype/states/expired')->assertOk()->assertDontSee('Nadia Pramesti');
        $this->get('/prototype/states/stale')->assertOk()->assertSee('V1')->assertSee('V2');
        $this->get('/prototype/states/queued')->assertOk()->assertSee('JOB-DEMO-001')->assertSee('bukan tanda ekspor berhasil');
        $this->get('/prototype/states/permission-denied')->assertOk()->assertSee('Tidak ada izin perangkat yang diminta');
        $this->get('/prototype/states/offline')->assertOk()->assertSee('ATT-EMP-004-18')->assertSee('19 Oktober 2026, 06.02')->assertDontSee('21 Oktober 2026');
        $this->get('/prototype/states/error')->assertOk()->assertSee('21 Oktober 2026')->assertSee('22 Oktober 2026')->assertDontSee('ATT-EMP-004-18');
        $this->get('/prototype/screens/claim-settlement')->assertOk()->assertSee('type="number"', false)->assertSee('step="1"', false)->assertSee('value="220000"', false);
    }

    public function test_unknown_screens_personas_states_and_invalid_variant_are_rejected(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/screens/UNKNOWN')->assertNotFound();
        $this->get('/prototype/portals/UNKNOWN')->assertNotFound();
        $this->get('/prototype/states/UNKNOWN')->assertNotFound();
        $this->getJson('/prototype/screens/leave?state[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('state');
        $this->getJson('/prototype/screens/leave?state=real-submit')->assertUnprocessable()->assertJsonValidationErrors('state');
    }
}
