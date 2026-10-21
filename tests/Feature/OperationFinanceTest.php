<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class OperationFinanceTest extends AttendanceScenarioCase
{
    private function write(string $operation, array $data = [])
    {
        return $this->post('/app/work/'.$operation, ['idempotency_key' => (string) Str::uuid()] + $data);
    }

    private function workflow(User $owner, User $finance, string $kind, array $payload): void
    {
        $this->login($owner);
        $this->write('request-'.$kind, $payload)->assertRedirect();
        $r = WorkflowRequest::where('kind', $kind)->latest('id')->firstOrFail();
        $this->get('/app/requests/'.$r->id)->assertOk();
        $this->post('/app/requests/'.$r->id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->login($finance);
        $this->get('/app/requests/'.$r->id)->assertOk()->assertSee('Keputusan Anda');
        $this->post('/app/requests/'.$r->id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => '2', 'decision' => 'approve', 'reason' => 'Synthetic independent review'])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_payroll_ui_runs_draft_to_private_slip_with_independent_approvals_and_exact_money(): void
    {
        $payroll = $this->user('payroll_officer');
        $finance = $this->user('finance_approver');
        foreach (['compensation', 'bank_change'] as $kind) {
            $this->login($this->hr);
            $this->write('workflow-definition', ['name' => 'Synthetic '.$kind, 'kind' => $kind, 'min_amount_minor' => '0', 'priority' => '10', 'steps' => [['role' => 'finance_approver']]])->assertOk();
        }
        $this->workflow($payroll, $finance, 'compensation', ['employee_id' => (string) $this->worker->employee_id, 'valid_from' => '2026-10-17', 'monthly_base_minor' => '100.01', 'reason' => 'Synthetic arithmetic fixture']);
        $this->assertSame(10001, decrypt(DB::table('employee_compensations')->value('monthly_base_minor')));
        $this->workflow($this->worker, $finance, 'bank_change', ['employee_id' => (string) $this->worker->employee_id, 'base_version' => '0', 'data' => ['bank_code' => 'TEST', 'account_number' => '001122334455', 'account_name' => 'Synthetic Worker'], 'reason' => 'Synthetic verification']);
        $this->roster('2026-10-17', true);
        $this->login($this->hr);
        $period = $this->postJson('/app/api/attendance-periods', ['idempotency_key' => (string) Str::uuid(), 'branch_id' => DB::table('branches')->sole()->id, 'starts_on' => '2026-10-17', 'ends_on' => '2026-10-18'])->assertCreated()->json('id');
        $review = $this->postJson('/app/api/attendance-periods/'.$period.'/review', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk()->json();
        $this->postJson('/app/api/attendance-periods/'.$period.'/close', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'checksum' => $review['checksum']])->assertOk();
        $this->login($payroll);
        $this->write('payroll-policy', ['code' => 'UI_SYNTHETIC', 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'official_sources' => 'https://example.org/synthetic-fixture', 'rules' => ['rounding' => 'half_up', 'proration_basis' => 'calendar_days', 'requires_tax_profile' => '0', 'applicability_statement' => 'SYNTHETIC TEST ONLY. No statutory policy or real payment.', 'variation_warning_percent' => '25', 'components' => []]])->assertOk();
        $policy = DB::table('payroll_policies')->sole();
        $this->assertSame('draft', $policy->status);
        $this->login($finance);
        $this->write('payroll-policy-approve', ['record_id' => (string) $policy->id, 'evidence' => 'Synthetic fixture review only'])->assertOk();
        $this->login($payroll);
        $this->write('payroll-create', ['attendance_period_id' => (string) $period, 'payroll_policy_id' => (string) $policy->id, 'kind' => 'regular'])->assertOk();
        $batch = DB::table('payroll_batches')->sole();
        $this->write('payroll-calculate', ['record_id' => (string) $batch->id, 'expected_version' => '1'])->assertOk();
        $this->get('/app/work/payroll-detail?run=1&record_id='.$batch->id)->assertOk()->assertSee('Rp');
        $version = DB::table('payroll_batches')->value('version');
        $this->write('payroll-review', ['record_id' => (string) $batch->id, 'expected_version' => (string) $version, 'acknowledge_warnings' => '1', 'reason' => 'Synthetic review'])->assertOk();
        $this->login($finance);
        $this->write('payroll-approve', ['record_id' => (string) $batch->id, 'expected_version' => (string) DB::table('payroll_batches')->value('version')])->assertOk();
        $this->write('payroll-finalize', ['record_id' => (string) $batch->id, 'expected_version' => (string) DB::table('payroll_batches')->value('version'), 'release_at' => '2026-10-19T11:00', 'form_timezone' => 'Asia/Jakarta'])->assertOk();
        $this->assertDatabaseHas('payroll_batches', ['id' => $batch->id, 'status' => 'finalized']);
        $row = DB::table('payroll_rows')->sole();
        $this->login($this->worker);
        $this->get('/app/work/payslip?run=1&record_id='.$row->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->login($this->hr);
        $this->get('/app/work/payroll-detail?run=1&record_id='.$batch->id)->assertForbidden();
    }
}
