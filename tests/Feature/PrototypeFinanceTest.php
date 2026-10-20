<?php

namespace Tests\Feature;

use App\Support\PrototypeFinance;
use Tests\TestCase;

class PrototypeFinanceTest extends TestCase
{
    private array $pages = ['/prototype/payroll', '/prototype/payroll/PAY-SEP-26', '/prototype/payroll/PAY-SEP-26/payslips/EMP-004', '/prototype/reimbursements', '/prototype/reimbursements/EXP-001'];

    public function test_finance_routes_require_preview_and_are_never_enabled_in_production(): void
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

    public function test_finance_pages_are_get_only_with_privacy_headers(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach ($this->pages as $page) {
            $this->get($page)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->post($page)->assertStatus(405);
        }
    }

    public function test_batch_filter_and_payslip_navigation_preserve_context(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $filters = ['q' => 'SEP', 'status' => 'FINALIZED', 'period' => '2026-09'];
        $this->get(route('prototype.payroll', $filters))->assertOk()->assertSee('PAY-SEP-26')->assertDontSee('PAY-OCT-26');
        $this->get(route('prototype.payroll.show', array_merge($filters, ['batch' => 'PAY-SEP-26'])))->assertOk()->assertSee(route('prototype.payroll', $filters))->assertSee(route('prototype.reimbursements'));
        $this->get(route('prototype.payroll.payslip', array_merge($filters, ['batch' => 'PAY-SEP-26', 'employee' => 'EMP-004'])))
            ->assertOk()->assertSee(route('prototype.payroll.show', array_merge($filters, ['batch' => 'PAY-SEP-26'])));
    }

    public function test_final_snapshot_totals_and_payment_outcomes_are_distinct(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $batch = PrototypeFinance::payroll('PAY-SEP-26');
        $this->assertSame(17500000, $batch['total']);
        $this->assertSame(7400000, $batch['paid']);
        $this->assertSame('FINALIZED', $batch['status']);
        $this->get('/prototype/payroll/PAY-SEP-26')->assertOk()->assertSee('Sebagian dibayar')->assertSee('Transfer gagal')->assertSee('Intan Kusuma');
        $this->get('/prototype/payroll/PAY-SEP-26/payslips/EMP-005')->assertOk()->assertSee('Gagal · tidak dihitung terbayar')->assertSee('Rp 0')->assertSee('TRF-DEMO-SEP-002');
        $this->get('/prototype/payroll/PAY-SEP-26/payslips/EMP-004')->assertOk()->assertSee('Rp 6.400.000')->assertSee('COMP-004-SEP')->assertDontSee('COMP-004-OCT');
        $this->assertSame(6000000, $batch['employees'][0]['components'][0][1]);
        $this->assertSame(6500000, PrototypeFinance::payroll('PAY-OCT-26')['employees'][0]['components'][0][1]);
    }

    public function test_unreleased_slips_are_denied_but_working_components_and_blockers_are_visible(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/payroll/PAY-OCT-26/payslips/EMP-004')->assertNotFound();
        $this->get('/prototype/payroll/PAY-ADJ-26/payslips/EMP-011')->assertNotFound();
        $this->get('/prototype/payroll/PAY-SEP-26/payslips/EMP-001')->assertNotFound();
        $this->get('/prototype/payroll/PAY-OCT-26')->assertOk()->assertSee('Nilai bersih negatif')->assertSee('Parameter regulasi belum disahkan')->assertSee('Periode kehadiran belum ditutup')->assertSee('SNAP-OCT-V2')->assertSee('COMP-004-OCT')->assertSee('−Rp 200.000')->assertSee('Belum ada persetujuan versi ini')->assertDontSee('Pratinjau slip </a>', false);
        $future = PrototypeFinance::payroll('PAY-SEP-26');
        $future['release'] = '2026-10-21';
        $this->assertFalse(PrototypeFinance::released($future));
        $this->get('/prototype/payroll/PAY-ADJ-26')->assertOk()->assertSee('ADJ-DEMO-011-NEXT')->assertSee('tidak mengubah batch September');
    }

    public function test_claim_amount_partial_settlements_and_failed_attempts_reconcile(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $claim = PrototypeFinance::claim('EXP-002');
        $this->assertSame(420000, $claim['amount']);
        $this->assertSame(200000, $claim['paid']);
        $this->assertSame(220000, $claim['remaining']);
        $this->get('/prototype/reimbursements/EXP-002')->assertOk()->assertSee('Rp 420.000')->assertSee('Rp 200.000')->assertSee('Rp 220.000')->assertSee('SET-EXP-002B')->assertSee('Gagal · tidak dihitung terbayar');
        $paid = PrototypeFinance::claim('EXP-003');
        $this->assertSame('PAID', $paid['status']);
        $this->assertSame(0, $paid['remaining']);
        foreach ($paid['settlements'] as $settlement) {
            $this->assertNotEmpty($settlement['reference']);
            $this->assertNotEmpty($settlement['date']);
            $this->assertNotEmpty($settlement['actor']);
        }
    }

    public function test_claim_channels_and_duplicate_warning_do_not_imply_double_payment_or_fraud(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/reimbursements/EXP-001')->assertOk()->assertSee(route('prototype.payroll.show', ['batch' => 'PAY-OCT-26']))->assertSee('Tidak boleh dimasukkan lagi');
        $this->assertSame('PAYROLL', PrototypeFinance::claim('EXP-001')['channel']);
        $first = PrototypeFinance::claim('EXP-004');
        $second = PrototypeFinance::claim('EXP-005');
        $this->assertSame([$first['amount'], $first['date'], $first['evidence']], [$second['amount'], $second['date'], $second['evidence']]);
        $filters = ['q' => 'Sinta', 'status' => 'SUBMITTED', 'employee' => 'EMP-008', 'branch' => 'Bandung'];
        $this->get(route('prototype.reimbursements', $filters))->assertOk()->assertSee('EXP-004')->assertDontSee('EXP-005');
        $this->get(route('prototype.reimbursements.show', array_merge($filters, ['claim' => 'EXP-004'])))
            ->assertOk()->assertSee(route('prototype.reimbursements', $filters))
            ->assertSee(route('prototype.reimbursements.show', array_merge($filters, ['claim' => 'EXP-005'])))
            ->assertSee('tidak otomatis menetapkan kecurangan');
        $this->get('/prototype/reimbursements/EXP-005')->assertOk()->assertSee('bukan kewajiban bayar yang disetujui');
        $this->get('/prototype/reimbursements/EXP-006')->assertOk()->assertSee('Belum ditetapkan')->assertSee('belum berarti disetujui');
    }

    public function test_invalid_scope_empty_and_unknown_records_are_handled(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/payroll/UNKNOWN')->assertNotFound();
        $this->get('/prototype/reimbursements/PAY-SEP-26')->assertNotFound();
        $this->getJson('/prototype/payroll?period=2027-01')->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->getJson('/prototype/reimbursements?status=FINALIZED')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/prototype/payroll?q[]=bad')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->get('/prototype/payroll?q=no-match')->assertOk()->assertSee('Tidak ada batch yang cocok');
        $this->get('/prototype/reimbursements?q=no-match')->assertOk()->assertSee('Tidak ada klaim yang cocok');
    }
}
