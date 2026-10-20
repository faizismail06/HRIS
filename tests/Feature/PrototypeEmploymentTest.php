<?php

namespace Tests\Feature;

use App\Support\PrototypeEmployment;
use App\Support\PrototypePeople;
use Tests\TestCase;

class PrototypeEmploymentTest extends TestCase
{
    private array $pages = ['/prototype/contracts', '/prototype/contracts/CTR-001', '/prototype/letters', '/prototype/letters/LTR-001', '/prototype/career-changes', '/prototype/career-changes/CAR-001'];

    public function test_administration_requires_preview_and_rejects_production(): void
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

    public function test_lists_and_details_are_read_only_with_preview_headers(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach ($this->pages as $page) {
            $this->get($page)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->post($page)->assertStatus(405);
        }
    }

    public function test_filters_and_return_context_survive_without_cross_kind_status_errors(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $filters = ['q' => 'Nadia', 'status' => 'SCHEDULED', 'branch' => 'Jakarta', 'employee' => 'EMP-004'];
        $this->get(route('prototype.contracts', $filters))->assertOk()->assertSee('CTR-002')->assertDontSee('CTR-001');
        $target = route('prototype.career-changes.show', ['record' => 'CAR-001', 'branch' => 'Jakarta', 'employee' => 'EMP-004']);
        $this->get(route('prototype.contracts.show', array_merge(['record' => 'CTR-002'], $filters)))
            ->assertOk()->assertSee(route('prototype.contracts', $filters))->assertSee($target)
            ->assertSee(route('prototype.letters', ['branch' => 'Jakarta', 'employee' => 'EMP-004']));
        $this->get($target)->assertOk();
        $this->get('/prototype/contracts/CTR-001?status=ACTIVE')
            ->assertOk()->assertSee(route('prototype.contracts.show', ['record' => 'CTR-002', 'q' => '', 'status' => 'ACTIVE', 'branch' => '', 'employee' => '']));
    }

    public function test_scheduled_contract_and_career_do_not_replace_current_assignment(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/contracts/CTR-002')->assertOk()->assertSee('1 November 2026')->assertSee('Supervisor Operasional')->assertSee('tidak mengubah kontrak atau jabatan hari ini');
        $this->get('/prototype/career-changes/CAR-001')->assertOk()->assertSee('Koordinator Operasional')->assertSee('Supervisor Operasional')->assertSee('profil hari ini tetap berlaku');
        $this->assertSame('Koordinator Operasional', PrototypePeople::find('EMP-004')['job']);
        $this->get('/prototype/career-changes/CAR-002')->assertOk()->assertSee('Bandung')->assertSee('Surabaya')->assertSee('Sesudah · usulan');
        $this->assertSame('Bandung', PrototypePeople::find('EMP-008')['branch']);
        $this->get('/prototype/career-changes/CAR-003')->assertOk()->assertSee('Bima Prasetya')->assertSee('Ratna Putri')->assertSee('Sudah efektif');
        $this->assertSame('EMP-001', PrototypePeople::find('EMP-005')['manager']);
    }

    public function test_contract_reminder_dates_are_preview_only_and_archives_remain_separate(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/contracts/CTR-001')->assertOk()
            ->assertSee('1 September 2026')->assertSee('1 Oktober 2026')->assertSee('17 Oktober 2026')->assertSee('24 Oktober 2026')
            ->assertSee('Tanggal contoh terlewati')->assertSee('Mendatang')->assertSee('tidak mengirim pengingat');
        $this->get('/prototype/contracts/CTR-003')->assertOk()->assertSee('Arsip kontrak 2025')->assertSee('Digantikan')->assertDontSee('60 hari sebelum berakhir');
        $this->get('/prototype/contracts/CTR-006')->assertOk()->assertSee('Usulan mulai berlaku')->assertSee('Belum ada nomor final');
        $this->assertSame('ACTIVE', PrototypeEmployment::find('contracts', 'CTR-001')['status']);
    }

    public function test_letter_snapshots_keep_originals_reissues_and_completed_employment_periods(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $original = $this->get('/prototype/letters/LTR-001')->assertOk()->assertSee('SKK/DEMO/2026/001')->assertSee('Penerbitan ulang terkait');
        $original->assertSee('<dd>Operasi</dd>', false)->assertSee('CONTOH · DATA SINTETIS');
        $this->get('/prototype/letters/LTR-004')->assertOk()->assertSee('SKK/DEMO/2026/002')->assertSee('<dd>Operasional</dd>', false)->assertSee('Dokumen asli yang dipertahankan');
        $this->get('/prototype/letters/LTR-002')->assertOk()->assertSee('Intan Kusuma')->assertSee('4 Oktober 2021 – 30 September 2026');
        $this->get('/prototype/letters/LTR-003')->assertOk()->assertSee('BELUM DITERBITKAN')->assertSee('Belum ada nomor final')->assertSee('Draf disiapkan');
        $this->assertSame('Operasi', PrototypeEmployment::find('letters', 'LTR-001')['snapshot']['department']);
        $this->assertSame('Koordinator Operasional', PrototypeEmployment::find('letters', 'LTR-004')['snapshot']['job']);
    }

    public function test_invalid_scope_unknown_ids_and_empty_results_are_handled(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/contracts/LTR-001')->assertNotFound();
        $this->get('/prototype/career-changes/UNKNOWN')->assertNotFound();
        $this->getJson('/prototype/letters?status=ACTIVE')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/prototype/contracts?q[]=bad')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/prototype/career-changes?employee=UNKNOWN')->assertUnprocessable()->assertJsonValidationErrors('employee');
        $this->get('/prototype/contracts?q=no-match')->assertOk()->assertSee('Tidak ada catatan yang cocok');
    }
}
