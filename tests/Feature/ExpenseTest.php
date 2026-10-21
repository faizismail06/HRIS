<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class ExpenseTest extends AttendanceScenarioCase
{
    private User $finance;

    private User $manager;

    private int $policy;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $boss = Employee::create(['employee_number' => 'BOSS', 'name' => 'Synthetic Boss']);
        $this->manager = $this->user('employee', $boss->id);
        $this->manager->grants()->create(['role' => 'manager', 'scope' => 'team']);
        $this->worker->employee->assignments()->update(['manager_employee_id' => $boss->id]);
        $this->finance = $this->user('finance_approver');
        $this->policy = $this->login($this->finance)->postJson('/app/api/expense-policies', ['idempotency_key' => (string) Str::uuid(), 'category' => 'SYNTHETIC_MEAL', 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'max_claim_minor' => 50000, 'max_month_minor' => 100000, 'minimum_tenure_days' => 0, 'job_ids' => []])->assertCreated()->json('id');
        $this->login($this->hr)->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Synthetic Expense', 'kind' => 'expense', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'manager'], ['role' => 'finance_approver']]])->assertOk();
    }

    private function submitClaim(): int
    {
        $this->login($this->worker);
        $id = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'expense', 'payload' => ['expense_policy_id' => $this->policy, 'incurred_on' => '2026-10-18', 'amount_minor' => 12345, 'cost_center_id' => $this->worker->employee->assignments()->sole()->department_id, 'description' => 'Synthetic expense']])->assertCreated()->json('id');
        $file = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\nSYNTHETIC_RECEIPT\n%%EOF");
        $this->post('/app/api/requests/'.$id.'/attachments', ['idempotency_key' => (string) Str::uuid(), 'file' => $file], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();

        return $id;
    }

    private function decision(User $actor, int $id, int $version, ?string $reason = null)
    {
        return $this->login($actor)->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $version, 'decision' => 'approve', 'reason' => $reason]);
    }

    public function test_claim_requires_manager_then_finance_evidence_and_duplicate_receipt_warns(): void
    {
        $id = $this->submitClaim();
        $this->decision($this->finance, $id, 2, 'Verified')->assertForbidden();
        $this->decision($this->manager, $id, 2)->assertOk();
        $this->assertDatabaseHas('expense_claims', ['workflow_request_id' => $id, 'status' => 'manager_approved']);
        $this->decision($this->finance, $id, 3)->assertUnprocessable();
        $this->decision($this->finance, $id, 3, 'Synthetic receipt/eligibility/duplicate check verified')->assertOk();
        $this->assertDatabaseHas('expense_claims', ['workflow_request_id' => $id, 'status' => 'approved_for_payment']);
        $this->getJson('/app/api/reports/expenses?starts_on=2026-10-18&ends_on=2026-10-20')->assertOk()->assertJsonPath('data.0.amount_minor', 12345);
        $second = $this->submitClaim();
        $this->assertDatabaseHas('expense_claims', ['workflow_request_id' => $second, 'duplicate_warning' => true]);
        $claim = DB::table('expense_claims')->where('workflow_request_id', $id)->sole()->id;
        $this->login($this->hr)->getJson('/app/api/expense-claims/'.$claim)->assertNotFound();
    }

    public function test_claim_has_one_settlement_channel_and_partial_payments_do_not_change_claimed_amount(): void
    {
        $request = $this->submitClaim();
        $this->decision($this->manager, $request, 2)->assertOk();
        $this->decision($this->finance, $request, 3, 'Synthetic verification')->assertOk();
        $claim = DB::table('expense_claims')->sole()->id;
        $allocation = $this->postJson('/app/api/expense-claims/'.$claim.'/allocate', ['idempotency_key' => (string) Str::uuid(), 'channel' => 'reimbursement'])->assertCreated()->json('id');
        $this->postJson('/app/api/expense-claims/'.$claim.'/allocate', ['idempotency_key' => (string) Str::uuid(), 'channel' => 'reimbursement'])->assertConflict();
        $input = ['idempotency_key' => (string) Str::uuid(), 'external_event_id' => 'SYNTHETIC_CLAIM_TRANSFER_1', 'amount_minor' => 10000, 'reference' => 'SYNTHETIC_PART_1', 'paid_on' => '2026-10-19'];
        $this->postJson('/app/api/expense-allocations/'.$allocation.'/settle', $input)->assertOk()->assertJsonPath('status', 'PARTIAL');
        $input['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/app/api/expense-allocations/'.$allocation.'/settle', $input)->assertOk();
        $input = array_replace($input, ['idempotency_key' => (string) Str::uuid(), 'external_event_id' => 'SYNTHETIC_CLAIM_TRANSFER_2', 'reference' => 'SYNTHETIC_PART_2', 'amount_minor' => 3000]);
        $this->postJson('/app/api/expense-allocations/'.$allocation.'/settle', $input)->assertUnprocessable();
        $input['amount_minor'] = 2345;
        $this->postJson('/app/api/expense-allocations/'.$allocation.'/settle', $input)->assertOk()->assertJsonPath('status', 'PAID');
        $this->assertDatabaseHas('expense_claims', ['id' => $claim, 'status' => 'paid', 'amount_minor' => 12345]);
        $this->assertDatabaseCount('expense_settlements', 2);
    }

    public function test_reimbursement_retry_exports_only_failed_unpaid_items_and_prevents_free_settlement_bypass(): void
    {
        $requests = [$this->submitClaim(), $this->submitClaim()];
        $allocations = [];
        foreach ($requests as $request) {
            $this->decision($this->manager, $request, 2)->assertOk();
            $this->decision($this->finance, $request, 3, 'Synthetic receipt verified')->assertOk();
            $claim = DB::table('expense_claims')->where('workflow_request_id', $request)->sole()->id;
            $allocations[] = $this->postJson('/app/api/expense-claims/'.$claim.'/allocate', ['idempotency_key' => (string) Str::uuid(), 'channel' => 'reimbursement'])->assertCreated()->json('id');
        }
        $this->login($this->hr)->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Bank verification', 'kind' => 'bank_change', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'finance_approver']]])->assertOk();
        $bank = $this->login($this->worker)->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'bank_change', 'payload' => ['employee_id' => $this->worker->employee_id, 'category' => 'bank', 'base_version' => 0, 'reason' => 'Synthetic bank evidence', 'data' => ['bank_code' => 'TEST', 'account_number' => '00001111', 'account_name' => 'Synthetic Worker']]])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$bank.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->decision($this->finance, $bank, 2, 'Synthetic bank verified')->assertOk();
        $payroll = $this->user('payroll_officer');
        $template = $this->login($payroll)->postJson('/app/api/bank-export-templates', ['idempotency_key' => (string) Str::uuid(), 'code' => 'SYNTHETIC_REIMBURSEMENT', 'columns' => [['label' => 'Batch', 'field' => 'batch_id'], ['label' => 'Payment', 'field' => 'payment_id'], ['label' => 'Account', 'field' => 'account_number'], ['label' => 'Amount', 'field' => 'amount_idr']]])->assertCreated()->json('id');
        $this->login($this->finance)->postJson('/app/api/bank-export-templates/'.$template.'/confirm', ['idempotency_key' => (string) Str::uuid()])->assertOk();
        $batch = $this->postJson('/app/api/reimbursement-batches', ['idempotency_key' => (string) Str::uuid(), 'allocation_ids' => $allocations, 'reason' => 'Synthetic reimbursement run'])->assertCreated()->json('id');
        $review = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'action' => 'approve', 'evidence' => 'Synthetic independent payment review'];
        $this->postJson('/app/api/reimbursement-batches/'.$batch.'/decide', $review)->assertForbidden();
        $other = $this->user('finance_approver');
        $this->login($other)->postJson('/app/api/reimbursement-batches/'.$batch.'/decide', $review)->assertOk();
        $exportInput = ['idempotency_key' => (string) Str::uuid(), 'template_id' => $template, 'reason' => 'Synthetic instruction'];
        $instruction = $this->postJson('/app/api/reimbursement-batches/'.$batch.'/bank-export', $exportInput)->assertCreated()->assertJsonPath('item_count', 2)->json('id');
        $this->postJson('/app/api/reimbursement-batches/'.$batch.'/bank-export', $exportInput)->assertCreated()->assertJsonPath('id', $instruction);
        $pendingRetry = array_replace($exportInput, ['idempotency_key' => (string) Str::uuid()]);
        $this->postJson('/app/api/reimbursement-batches/'.$batch.'/bank-export', $pendingRetry)->assertConflict();
        $this->postJson('/app/api/expense-allocations/'.$allocations[0].'/settle', ['idempotency_key' => (string) Str::uuid(), 'external_event_id' => 'BYPASS', 'amount_minor' => 1, 'reference' => 'BYPASS', 'paid_on' => '2026-10-19'])->assertConflict();
        $items = DB::table('expense_instruction_items')->where('expense_bank_instruction_id', $instruction)->orderBy('id')->get();
        foreach ($items as $i => $item) {
            $outcome = ['idempotency_key' => (string) Str::uuid(), 'external_event_id' => 'SYNTHETIC_RESULT_'.$i, 'status' => $i === 0 ? 'paid' : 'failed', 'amount_minor' => $i === 0 ? 12345 : 0, 'reference' => 'SYNTHETIC_REF_'.$i, 'paid_on' => '2026-10-19'];
            $this->postJson('/app/api/reimbursement-instruction-items/'.$item->id.'/outcomes', $outcome)->assertOk();
            $outcome['idempotency_key'] = (string) Str::uuid();
            $this->postJson('/app/api/reimbursement-instruction-items/'.$item->id.'/outcomes', $outcome)->assertOk();
        }
        $exportInput['idempotency_key'] = (string) Str::uuid();
        $retry = $this->postJson('/app/api/reimbursement-batches/'.$batch.'/bank-export', $exportInput)->assertCreated()->assertJsonPath('item_count', 1)->json('id');
        $csv = $this->post('/app/api/reimbursement-instructions/'.$retry.'/download', ['reason' => 'Synthetic retry verification'])->assertOk()->getContent();
        $this->assertCount(2, explode("\n", trim($csv)));
        $this->assertSame(12345, (int) DB::table('expense_settlements')->sum('amount_minor'));
        $this->assertDatabaseCount('expense_instruction_outcomes', 2);
        $this->login($this->hr)->getJson('/app/api/reimbursement-batches/'.$batch)->assertForbidden();
    }
}
