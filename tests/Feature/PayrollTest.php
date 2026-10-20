<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class PayrollTest extends AttendanceScenarioCase
{
    private User $payroll;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payroll = $this->user('payroll_officer');
        $this->finance = $this->user('finance_approver');
        foreach (['compensation', 'bank_change'] as $kind) {
            $this->login($this->hr)->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Synthetic '.$kind, 'kind' => $kind, 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'finance_approver']]])->assertOk();
        }
    }

    private function approveRequest(User $owner, string $kind, array $payload): int
    {
        $this->login($owner);
        $id = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => $kind, 'payload' => $payload])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($this->finance)->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();

        return $id;
    }

    private function bank(int $base = 0): void
    {
        $this->approveRequest($this->worker, 'bank_change', ['employee_id' => $this->worker->employee_id, 'category' => 'bank', 'base_version' => $base, 'reason' => 'Synthetic bank verification', 'data' => ['bank_code' => 'TEST', 'account_number' => '001122334455', 'account_name' => 'Synthetic Worker']]);
    }

    private function compensation(string $date, int $amount, ?int $base = null): void
    {
        $this->approveRequest($this->payroll, 'compensation', ['employee_id' => $this->worker->employee_id, 'valid_from' => $date, 'monthly_base_minor' => $amount, 'base_compensation_id' => $base, 'reason' => 'Synthetic compensation test']);
    }

    private function readyBatch(bool $approvePolicy = true): int
    {
        $this->roster('2026-10-17', true);
        $this->roster('2026-10-18', true);
        $branch = $this->worker->employee->assignments()->sole()->branch_id;
        $this->login($this->hr);
        $period = $this->postJson('/app/api/attendance-periods', ['idempotency_key' => (string) Str::uuid(), 'branch_id' => $branch, 'starts_on' => '2026-10-17', 'ends_on' => '2026-10-19'])->assertCreated()->json('id');
        $review = $this->postJson('/app/api/attendance-periods/'.$period.'/review', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk()->json();
        $this->postJson('/app/api/attendance-periods/'.$period.'/close', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'checksum' => $review['checksum']])->assertOk();
        $this->compensation('2026-10-17', 10001);
        $this->compensation('2026-10-18', 10003, DB::table('employee_compensations')->sole()->id);
        $this->bank();
        $this->login($this->payroll);
        $policy = $this->postJson('/app/api/payroll-policies', ['idempotency_key' => (string) Str::uuid(), 'code' => 'SYNTHETIC_ONLY', 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'official_sources' => ['https://www.pajak.go.id/'], 'rules' => ['rounding' => 'half_up', 'proration_basis' => 'calendar_days', 'requires_tax_profile' => false, 'applicability_statement' => 'SYNTHETIC arithmetic fixture only. These are not Indonesian statutory rates.', 'variation_warning_percent' => 25, 'components' => [['code' => 'SYNTHETIC_EARNING', 'label' => 'Synthetic earning', 'kind' => 'earning', 'method' => 'fixed', 'basis' => 'base', 'amount_minor' => 100], ['code' => 'SYNTHETIC_DEDUCTION', 'label' => 'Synthetic deduction', 'kind' => 'deduction', 'method' => 'ratio', 'basis' => 'base', 'numerator' => 1, 'denominator' => 3]]]])->assertCreated()->json('id');
        if ($approvePolicy) {
            $this->login($this->finance)->postJson('/app/api/payroll-policies/'.$policy.'/approve', ['idempotency_key' => (string) Str::uuid(), 'evidence' => 'Synthetic testing fixture, no real regulatory approval or payment.'])->assertOk();
        }

        return $this->login($this->payroll)->postJson('/app/api/payroll-batches', ['idempotency_key' => (string) Str::uuid(), 'attendance_period_id' => $period, 'payroll_policy_id' => $policy])->assertCreated()->json('id');
    }

    private function transition(int $id, string $action, int $version, array $extra = [])
    {
        return $this->postJson('/app/api/payroll-batches/'.$id.'/'.$action, $extra + ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $version]);
    }

    private function finalize(int $batch): int
    {
        $this->login($this->payroll);
        $this->transition($batch, 'calculate', 1)->assertOk();
        $this->transition($batch, 'review', 2)->assertOk();
        $this->login($this->finance);
        $this->transition($batch, 'approve', 3)->assertOk();
        $this->transition($batch, 'finalize', 4, ['release_at' => now()->addHour()->toIso8601String()])->assertOk();

        return DB::table('payroll_rows')->where('payroll_batch_id', $batch)->sole()->id;
    }

    public function test_exact_integer_proration_and_final_snapshot_payslip_access_and_payment_are_separate(): void
    {
        $batch = $this->readyBatch();
        $row = $this->finalize($batch);
        $snapshot = decrypt(DB::table('payroll_final_snapshots')->sole()->snapshot);
        $this->assertSame(10002, $snapshot['context']['base']);
        $this->assertSame(3334, $snapshot['deduction_minor']);
        $this->assertSame(6768, $snapshot['net_minor']);
        $this->login($this->worker)->get('/app/api/payslips/'.$row.'/download')->assertNotFound();
        $this->travel(2)->hours();
        $pdf = $this->login($this->worker)->get('/app/api/payslips/'.$row.'/download')->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->login($this->hr)->get('/app/api/payslips/'.$row.'/download')->assertNotFound();
        $this->getJson('/app/api/payroll-batches/'.$batch)->assertForbidden();
        $this->assertDatabaseCount('payroll_payment_outcomes', 0);
        $this->compensation('2026-11-01', 99999, DB::table('employee_compensations')->orderByDesc('valid_from')->first()->id);
        $this->login($this->worker)->get('/app/api/payslips/'.$row.'/download')->assertOk()->assertContent($pdf);
        $this->login($this->payroll);
        $this->transition($batch, 'calculate', 5)->assertConflict();
    }

    public function test_unsigned_policy_blocks_review_and_source_changes_invalidate_approval(): void
    {
        $batch = $this->readyBatch(false);
        $this->transition($batch, 'calculate', 1)->assertOk()->assertJsonFragment(['code' => 'policy_not_expert_approved']);
        $this->transition($batch, 'review', 2)->assertUnprocessable();
        $policy = DB::table('payroll_policies')->sole()->id;
        $this->login($this->finance)->postJson('/app/api/payroll-policies/'.$policy.'/approve', ['idempotency_key' => (string) Str::uuid(), 'evidence' => 'Synthetic test only'])->assertOk();
        $this->login($this->payroll);
        $this->transition($batch, 'calculate', 2)->assertOk();
        $this->transition($batch, 'review', 3)->assertOk();
        $this->bank(1);
        $this->login($this->finance);
        $this->transition($batch, 'approve', 4)->assertConflict()->assertJsonPath('code', 'source_changed_recalculate');
        $this->assertDatabaseHas('payroll_batches', ['id' => $batch, 'status' => 'draft', 'version' => 5, 'approved_by' => null]);
    }

    public function test_bank_export_requires_confirmation_does_not_pay_and_outcomes_cannot_duplicate_or_overpay(): void
    {
        $batch = $this->readyBatch();
        $row = $this->finalize($batch);
        $template = $this->login($this->payroll)->postJson('/app/api/bank-export-templates', ['idempotency_key' => (string) Str::uuid(), 'code' => 'SYNTHETIC_CSV', 'columns' => [['label' => 'Batch', 'field' => 'batch_id'], ['label' => 'Account', 'field' => 'account_number'], ['label' => 'Amount', 'field' => 'amount_idr']]])->assertCreated()->json('id');
        $exportInput = ['idempotency_key' => (string) Str::uuid(), 'template_id' => $template, 'reason' => 'Synthetic reconciliation test'];
        $this->login($this->finance)->postJson('/app/api/payroll-batches/'.$batch.'/bank-export', $exportInput)->assertNotFound();
        $this->postJson('/app/api/bank-export-templates/'.$template.'/confirm', ['idempotency_key' => (string) Str::uuid()])->assertOk();
        $export = $this->postJson('/app/api/payroll-batches/'.$batch.'/bank-export', $exportInput)->assertCreated()->json('id');
        $this->postJson('/app/api/payroll-batches/'.$batch.'/bank-export', $exportInput)->assertCreated()->assertJsonPath('id', $export);
        $csv = $this->post('/app/api/bank-exports/'.$export.'/download', ['reason' => 'Synthetic download'])->assertOk()->getContent();
        $this->assertStringContainsString('001122334455,67.68', $csv);
        $this->assertDatabaseCount('payroll_payment_outcomes', 0);
        $input = ['idempotency_key' => (string) Str::uuid(), 'external_event_id' => 'SYNTHETIC_TRANSFER_1', 'status' => 'paid', 'amount_minor' => 3000, 'reference' => 'SYNTHETIC_REF_1', 'paid_on' => '2026-10-19'];
        $this->postJson('/app/api/payroll-rows/'.$row.'/payment-outcomes', $input)->assertOk()->assertJsonPath('payment_status', 'PARTIAL');
        $input['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/app/api/payroll-rows/'.$row.'/payment-outcomes', $input)->assertOk();
        $this->assertDatabaseCount('payroll_payment_outcomes', 1);
        $input = array_replace($input, ['idempotency_key' => (string) Str::uuid(), 'external_event_id' => 'SYNTHETIC_TRANSFER_2', 'reference' => 'SYNTHETIC_REF_2', 'amount_minor' => 4000]);
        $this->postJson('/app/api/payroll-rows/'.$row.'/payment-outcomes', $input)->assertUnprocessable();
        $input['amount_minor'] = 3768;
        $this->postJson('/app/api/payroll-rows/'.$row.'/payment-outcomes', $input)->assertOk()->assertJsonPath('payment_status', 'PAID');
        $this->assertDatabaseCount('payroll_payment_outcomes', 2);
    }

    public function test_claim_allocated_to_payroll_cannot_also_be_paid_separately(): void
    {
        Storage::fake('local');
        $batch = $this->readyBatch();
        $boss = Employee::create(['employee_number' => 'EXPENSE-BOSS', 'name' => 'Synthetic Manager']);
        $manager = $this->user('employee', $boss->id);
        $manager->grants()->create(['role' => 'manager', 'scope' => 'team']);
        $this->worker->employee->assignments()->update(['manager_employee_id' => $boss->id]);
        $this->login($this->hr)->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Synthetic payroll expense', 'kind' => 'expense', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'manager'], ['role' => 'finance_approver']]])->assertOk();
        $policy = $this->login($this->finance)->postJson('/app/api/expense-policies', ['idempotency_key' => (string) Str::uuid(), 'category' => 'SYNTHETIC_CLAIM', 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'max_claim_minor' => 100000, 'max_month_minor' => 100000, 'minimum_tenure_days' => 0, 'job_ids' => []])->assertCreated()->json('id');
        $request = $this->login($this->worker)->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'expense', 'payload' => ['expense_policy_id' => $policy, 'incurred_on' => '2026-10-18', 'amount_minor' => 1000, 'cost_center_id' => $this->worker->employee->assignments()->sole()->department_id, 'description' => 'Synthetic claim via payroll']])->assertCreated()->json('id');
        $file = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\nSYNTHETIC\n%%EOF");
        $this->post('/app/api/requests/'.$request.'/attachments', ['idempotency_key' => (string) Str::uuid(), 'file' => $file], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/app/api/requests/'.$request.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($manager)->postJson('/app/api/requests/'.$request.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();
        $this->login($this->finance)->postJson('/app/api/requests/'.$request.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 3, 'decision' => 'approve', 'reason' => 'Synthetic verification'])->assertOk();
        $claim = DB::table('expense_claims')->sole()->id;
        $allocation = $this->postJson('/app/api/expense-claims/'.$claim.'/allocate', ['idempotency_key' => (string) Str::uuid(), 'channel' => 'payroll', 'payroll_batch_id' => $batch])->assertCreated()->json('id');
        $this->postJson('/app/api/expense-allocations/'.$allocation.'/settle', ['idempotency_key' => (string) Str::uuid(), 'external_event_id' => 'FORBIDDEN_OTHER_CHANNEL', 'amount_minor' => 1000, 'reference' => 'FORBIDDEN', 'paid_on' => '2026-10-19'])->assertConflict();
        $row = $this->finalize($batch);
        $this->assertSame(7768, decrypt(DB::table('payroll_final_snapshots')->sole()->snapshot)['net_minor']);
        foreach ([6768, 500, 500] as $i => $amount) {
            $this->postJson('/app/api/payroll-rows/'.$row.'/payment-outcomes', ['idempotency_key' => (string) Str::uuid(), 'external_event_id' => 'SYNTHETIC_PAY_'.$i, 'status' => 'paid', 'amount_minor' => $amount, 'reference' => 'SYNTHETIC_PAY_REF_'.$i, 'paid_on' => '2026-10-19'])->assertOk();
            $this->assertDatabaseHas('expense_claims', ['id' => $claim, 'status' => $i === 2 ? 'paid' : 'approved_for_payment']);
        }
        $this->assertDatabaseCount('expense_settlements', 2);
        $this->assertSame(1000, (int) DB::table('expense_settlements')->sum('amount_minor'));
    }
}
