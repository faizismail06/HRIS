<?php

namespace Tests\Feature;

use App\Domain\Documents\ContractReminders;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class DocumentsTest extends AttendanceScenarioCase
{
    public function test_draft_preview_is_private_text_and_does_not_allocate_a_number_or_issue_a_pdf(): void
    {
        $template = $this->template('employment_certificate');
        $this->definition('letter');
        $this->login($this->worker);
        $request = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'letter', 'payload' => $this->base($template) + ['letter_kind' => 'employment_certificate']])->assertCreated()->json('id');
        $this->getJson('/app/api/requests/'.$request.'/document-preview')->assertOk()->assertJsonPath('draft', true)->assertJsonPath('number', null)->assertSee('Synthetic Worker');
        $this->assertDatabaseCount('document_sequences', 0);
        $this->assertDatabaseCount('issued_documents', 0);
        $outsider = $this->user('employee');
        $this->login($outsider)->getJson('/app/api/requests/'.$request.'/document-preview')->assertNotFound();
    }

    private function template(string $kind): int
    {
        $this->login($this->hr);

        return $this->postJson('/app/api/document-templates', ['idempotency_key' => (string) Str::uuid(), 'code' => strtoupper($kind), 'kind' => $kind, 'title' => 'Synthetic document', 'body' => '{{employee_name}} / {{employee_number}}\n{{period_start}} - {{period_end}}\n{{job_name}}\n{{terms}}'])->assertCreated()->json('id');
    }

    private function definition(string $kind): void
    {
        $this->login($this->hr)->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Synthetic '.$kind, 'kind' => $kind, 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
    }

    private function requestDocument(User $requester, string $kind, array $input): int
    {
        $this->login($requester);
        $id = $this->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => $kind, 'payload' => $input])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();

        return $id;
    }

    private function approve(int $id, ?string $key = null)
    {
        return $this->login($this->hr)->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => $key ?? (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve']);
    }

    private function base(int $template): array
    {
        return ['employee_id' => $this->worker->employee_id, 'employee_version' => 1, 'employment_period_id' => $this->worker->employee->periods()->sole()->id, 'document_template_id' => $template, 'reason' => 'Synthetic request'];
    }

    public function test_issued_letter_is_a_private_immutable_pdf_snapshot_with_retry_safe_number(): void
    {
        $template = $this->template('employment_certificate');
        $this->definition('letter');
        $id = $this->requestDocument($this->worker, 'letter', $this->base($template) + ['letter_kind' => 'employment_certificate']);
        $key = (string) Str::uuid();
        $this->approve($id, $key)->assertOk();
        $this->approve($id, $key)->assertOk();
        $doc = DB::table('issued_documents')->sole();
        $this->assertSame('LTR/2026/000001', $doc->number);
        $this->assertSame('Synthetic Worker', json_decode($doc->snapshot, true)['values']['employee_name']);
        DB::table('employees')->where('id', $this->worker->employee_id)->update(['name' => 'Changed later']);
        $download = $this->login($this->worker)->get('/app/api/documents/'.$doc->id.'/download')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $download->getContent());
        $this->assertSame($doc->sha256, hash('sha256', $download->getContent()));
        $this->login($this->user('employee', Employee::create(['name' => 'Other', 'employee_number' => 'OTHER'])->id))->get('/app/api/documents/'.$doc->id.'/download')->assertNotFound();
        $this->assertDatabaseCount('issued_documents', 1);
        $this->assertStringNotContainsString('%PDF-', $doc->pdf_content);
    }

    public function test_service_letters_require_completed_period_and_templates_reject_unknown_tokens(): void
    {
        $template = $this->template('service_letter');
        $this->definition('letter');
        $this->login($this->worker)->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'letter', 'payload' => $this->base($template) + ['letter_kind' => 'service_letter']])->assertUnprocessable();
        $this->login($this->hr)->postJson('/app/api/document-templates', ['idempotency_key' => (string) Str::uuid(), 'code' => 'INVALID', 'kind' => 'service_letter', 'title' => 'No secrets', 'body' => '{{bank_number}} {{php:system}}'])->assertUnprocessable();
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_future_contract_does_not_replace_current_and_overlapping_primary_is_rejected(): void
    {
        $template = $this->template('contract');
        $this->definition('contract');
        $officer = $this->user('hr_officer');
        $base = $this->base($template) + ['contract_type' => 'Synthetic fixed term', 'renewal_sequence' => 0, 'terms' => 'Synthetic private terms'];
        $current = $this->requestDocument($officer, 'contract', $base + ['starts_on' => '2026-01-01', 'ends_on' => '2026-11-01']);
        $this->approve($current)->assertOk();
        $prior = DB::table('employment_contracts')->where('workflow_request_id', $current)->sole()->id;
        $future = $this->requestDocument($officer, 'contract', array_replace($base, ['renews_id' => $prior, 'renewal_sequence' => 1, 'starts_on' => '2026-11-01', 'ends_on' => '2027-11-01']));
        $this->approve($future)->assertUnprocessable();
        $task = DB::table('contract_review_tasks')->where('employment_contract_id', $prior)->sole();
        $this->login($this->hr)->postJson('/app/api/contract-review-tasks/'.$task->id.'/recommend', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'decision' => 'hr_review', 'reason' => 'Synthetic exception: no manager configured; HR reviewed renewal.'])->assertOk();
        $this->approve($future)->assertOk();
        $this->login($this->worker)->getJson('/app/api/employees/'.$this->worker->employee_id.'/contracts')->assertOk()->assertJsonPath('data.0.effective_status', 'SCHEDULED')->assertJsonPath('data.1.effective_status', 'ACTIVE');
        $overlap = $this->requestDocument($officer, 'contract', $base + ['starts_on' => '2026-10-01', 'ends_on' => '2027-01-01']);
        $this->approve($overlap)->assertUnprocessable();
        $this->assertDatabaseCount('issued_documents', 2);
        app(ContractReminders::class)->dispatch();
        app(ContractReminders::class)->dispatch();
        $this->assertDatabaseCount('contract_review_tasks', 1);
        $this->assertDatabaseCount('outbox_events', DB::table('outbox_events')->distinct()->count('dedup_key'));
        $this->assertSame('ACTIVE', $this->worker->employee->statusAt(today()->toDateString()));
    }

    public function test_future_contract_cancellation_keeps_document_and_does_not_end_employment(): void
    {
        $template = $this->template('contract');
        $this->definition('contract');
        $this->definition('contract_cancel');
        $officer = $this->user('hr_officer');
        $id = $this->requestDocument($officer, 'contract', $this->base($template) + ['contract_type' => 'Synthetic future term', 'renewal_sequence' => 0, 'starts_on' => '2026-11-01', 'ends_on' => '2027-11-01', 'terms' => 'Synthetic terms']);
        $this->approve($id)->assertOk();
        $contract = DB::table('employment_contracts')->sole();
        $cancel = $this->requestDocument($officer, 'contract_cancel', ['contract_id' => $contract->id, 'contract_version' => 2, 'reason' => 'Synthetic scheduled cancellation']);
        $this->approve($cancel)->assertOk();
        $this->assertDatabaseHas('employment_contracts', ['id' => $contract->id, 'status' => 'cancelled', 'version' => 3]);
        $this->assertDatabaseCount('issued_documents', 1);
        $this->assertSame('ACTIVE', $this->worker->employee->statusAt(today()->toDateString()));
        $this->login($this->worker)->get('/app/api/documents/'.$contract->issued_document_id.'/download')->assertOk();
    }
}
