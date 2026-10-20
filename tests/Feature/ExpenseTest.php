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
}
