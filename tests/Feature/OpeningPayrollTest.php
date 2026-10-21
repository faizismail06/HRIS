<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class OpeningPayrollTest extends AttendanceScenarioCase
{
    public function test_import_opening_is_draft_then_independently_verified_and_duplicate_year_is_rejected(): void
    {
        $payroll = $this->user('payroll_officer');
        $finance = $this->user('finance_approver');
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Opening review', 'kind' => 'opening_payroll', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'finance_approver']]])->assertOk();
        $this->get('/app/api/import-templates/opening_payroll')->assertForbidden();
        $csv = $this->login($payroll)->get('/app/api/import-templates/opening_payroll')->assertOk()->getContent();
        $this->assertStringContainsString('gross_minor,tax_paid_minor', $csv);
        $csv .= 'TEST,2026,2026-09-30,SYNTHETIC-OPENING,500000,5000,Synthetic independently verified opening'."\n";
        $batch = $this->post('/app/api/imports', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'opening_payroll', 'file' => UploadedFile::fake()->createWithContent('opening.csv', $csv)], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $headers = str_getcsv(trim(explode("\n", $csv)[0]), ',', '"', '');
        $this->postJson('/app/api/imports/'.$batch.'/map', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'mapping' => array_combine($headers, $headers)])->assertOk();
        $preview = $this->postJson('/app/api/imports/'.$batch.'/preview', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2])->assertOk()->assertJsonPath('error_count', 0)->json();
        $this->postJson('/app/api/imports/'.$batch.'/commit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 3, 'checksum' => $preview['checksum']])->assertOk();
        $this->assertDatabaseCount('payroll_openings', 0);
        $request = DB::table('workflow_requests')->where('kind', 'opening_payroll')->sole();
        $this->login($this->hr)->getJson('/app/api/requests/'.$request->id.'/proposed-opening-payroll')->assertNotFound();
        $this->login($payroll)->postJson('/app/api/requests/'.$request->id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->assertDatabaseHas('payroll_openings', ['employee_id' => $this->worker->employee_id, 'status' => 'pending']);
        $replacement = $this->user('finance_approver');
        $reassign = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'replacement_id' => $replacement->id, 'reason' => 'Synthetic Finance duty handover'];
        $this->login($this->hr)->postJson('/app/api/requests/'.$request->id.'/reassign', $reassign)->assertForbidden();
        $this->login($finance)->postJson('/app/api/requests/'.$request->id.'/reassign', $reassign)->assertOk();
        $finance = $replacement;
        $this->login($finance)->getJson('/app/api/requests/'.$request->id.'/proposed-opening-payroll')->assertOk()->assertJsonPath('gross_minor', 500000);
        $this->postJson('/app/api/requests/'.$request->id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 3, 'decision' => 'approve', 'reason' => 'Synthetic evidence verified'])->assertOk();
        $this->assertDatabaseHas('payroll_openings', ['employee_id' => $this->worker->employee_id, 'status' => 'approved', 'approved_by' => $finance->id]);
        $this->login($payroll)->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'opening_payroll', 'payload' => ['employee_id' => $this->worker->employee_id, 'tax_year' => 2026, 'through_on' => '2026-09-30', 'source_key' => 'DUPLICATE', 'gross_minor' => 1, 'tax_paid_minor' => 0, 'reason' => 'Duplicate attempt']])->assertUnprocessable();
    }
}
