<?php

namespace Tests\Feature;

use App\Support\PrototypeAdministration;
use App\Support\PrototypePeople;
use Tests\TestCase;

class PrototypeAdministrationTest extends TestCase
{
    private array $pages = ['/prototype/settings', '/prototype/audit', '/prototype/imports', '/prototype/imports/IMP-001'];

    public function test_administration_requires_preview_and_is_denied_in_production(): void
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

    public function test_pages_are_get_only_and_have_preview_headers(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach ($this->pages as $page) {
            $this->get($page)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->post($page)->assertStatus(405);
        }
    }

    public function test_settings_describe_target_scope_without_claiming_real_authentication(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->assertCount(9, PrototypeAdministration::roles());
        $this->get('/prototype/settings')->assertOk()->assertSee('Default deny pada server')->assertSee('belum diterapkan')->assertSee('Persona HR bukan login')->assertSee('Tidak otomatis mendapat data HR/payroll')->assertSee('MFA untuk peran sensitif')->assertDontSee('DB_PASSWORD');
    }

    public function test_audit_combines_filters_and_discloses_changes_without_sensitive_payload(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $filters = ['q' => 'Nadia', 'module' => 'ACCESS', 'result' => 'DENIED'];
        $this->get(route('prototype.audit', $filters))->assertOk()->assertSee('AUD-005')->assertDontSee('AUD-006')->assertSee('Tetap dalam tinjauan')->assertSee('CORR-DEMO-005')->assertSee(route('prototype.requests.show', ['requestId' => 'CUT-041']));
        $this->get('/prototype/audit?module=EXPORT')->assertOk()->assertSee('Tidak ada ekspor')->assertSee('Payload sensitif tidak dimuat');
    }

    public function test_invalid_import_shows_row_column_reason_remedy_and_no_partial_application(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $batch = PrototypeAdministration::import('IMP-001');
        $this->assertSame(2, $batch['invalid_rows']);
        $this->assertSame(0, $batch['applied']);
        $this->assertCount(3, $batch['rows']);
        $this->get('/prototype/imports/IMP-001')->assertOk()->assertSee('Duplikat nomor pada baris 2')->assertSee('Gunakan business key unik')->assertSee('Cabang tidak ada dalam master contoh')->assertSee('=2+3')->assertSee('formula tidak dieksekusi')->assertSee('Tetap tidak diterapkan karena batch bermasalah')->assertSee('seluruh batch ditolak')->assertSee('penulisan nyata oleh UI: 0');
        $this->assertCount(12, PrototypePeople::employees());
    }

    public function test_import_filters_return_scope_and_duplicate_hash_preserve_original_batch(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $filters = ['q' => 'saldo', 'status' => 'DUPLICATE', 'type' => 'BALANCE'];
        $this->get(route('prototype.imports', $filters))->assertOk()->assertSee('IMP-004')->assertDontSee('IMP-003');
        $this->get(route('prototype.imports.show', array_merge($filters, ['batch' => 'IMP-004'])))
            ->assertOk()->assertSee(route('prototype.imports', $filters))->assertSee(route('prototype.imports.show', array_merge($filters, ['batch' => 'IMP-003'])));
        $this->assertSame(PrototypeAdministration::import('IMP-003')['hash'], PrototypeAdministration::import('IMP-004')['hash']);
        $this->assertSame(0, PrototypeAdministration::import('IMP-004')['applied']);
        $this->get('/prototype/imports/IMP-002')->assertOk()->assertSee('2026-11-03 06:00 WIB')->assertSee('belum diterapkan atau dipublikasikan');
        $this->get('/prototype/imports/IMP-003')->assertOk()->assertSee('Tidak ada saldo yang ditulis');
    }

    public function test_empty_invalid_queries_and_unknown_batch_have_recovery(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/imports/UNKNOWN')->assertNotFound();
        $this->getJson('/prototype/audit?module=UNKNOWN')->assertUnprocessable()->assertJsonValidationErrors('module');
        $this->getJson('/prototype/imports?status=ACTIVE')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/prototype/imports?q[]=bad')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->get('/prototype/imports?q=no-match')->assertOk()->assertSee('Tidak ada batch yang cocok');
        $this->get('/prototype/audit?q=no-match')->assertOk()->assertSee('Tidak ada peristiwa yang cocok');
    }
}
