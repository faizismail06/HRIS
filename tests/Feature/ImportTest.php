<?php

namespace Tests\Feature;

use App\Domain\Imports\ImportService;
use App\Jobs\CommitImport;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\AttendanceScenarioCase;

class ImportTest extends AttendanceScenarioCase
{
    private function upload(string $csv): int
    {
        return $this->login($this->hr)->post('/app/api/imports', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'employees', 'file' => UploadedFile::fake()->createWithContent('synthetic.csv', $csv)], ['Accept' => 'application/json'])->assertCreated()->json('id');
    }

    private function csv(bool $duplicate = false): string
    {
        $id = $duplicate ? '1234567890123456' : '1234567890123457';

        return "employee_number,name,starts_on,branch_code,department_code,job_code,national_id,legal_name\nIMPORT-1,Synthetic Import One,2026-10-19,TEST,TEST,TEST,1234567890123456,Synthetic Import One\nIMPORT-2,Synthetic Import Two,2026-10-19,TEST,TEST,TEST,$id,Synthetic Import Two\n";
    }

    private function preview(int $id)
    {
        $mapping = array_combine(explode(',', trim(strtok($this->csv(), "\n"))), explode(',', trim(strtok($this->csv(), "\n"))));
        $this->postJson('/app/api/imports/'.$id.'/map', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'mapping' => $mapping])->assertOk();

        return $this->postJson('/app/api/imports/'.$id.'/preview', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2]);
    }

    public function test_duplicate_identity_invalidates_entire_batch_without_creating_any_profiles(): void
    {
        $id = $this->upload($this->csv(true));
        $preview = $this->preview($id)->assertOk()->assertJsonPath('status', 'invalid')->assertJsonPath('errors.0.row', 3)->json();
        $this->postJson('/app/api/imports/'.$id.'/commit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 3, 'checksum' => $preview['checksum']])->assertConflict();
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('workflow_requests', 0);
    }

    public function test_valid_batch_is_atomic_retry_safe_and_identity_remains_pending_independent_verification(): void
    {
        $csv = $this->csv();
        $id = $this->upload($csv);
        $preview = $this->preview($id)->assertOk()->assertJsonPath('status', 'validated')->json();
        $key = (string) Str::uuid();
        $input = ['idempotency_key' => $key, 'expected_version' => 3, 'checksum' => $preview['checksum']];
        $this->postJson('/app/api/imports/'.$id.'/commit', $input)->assertOk()->assertJsonPath('created_count', 2);
        $this->postJson('/app/api/imports/'.$id.'/commit', $input)->assertOk();
        $this->assertDatabaseCount('employees', 3);
        $this->assertDatabaseCount('workflow_requests', 2);
        $this->assertDatabaseCount('employee_private_profiles', 0);
        $this->assertDatabaseHas('workflow_requests', ['kind' => 'identity_change', 'status' => 'draft']);
        $this->assertSame($id, $this->upload($csv));
        $this->getJson('/app/api/imports/'.$id)->assertOk()->assertDontSee('1234567890123456')->assertJsonPath('status', 'committed');
        $this->assertStringNotContainsString('1234567890123456', DB::table('import_batches')->value('rows'));
        $this->login($this->worker)->getJson('/app/api/imports/'.$id)->assertNotFound();
    }

    public function test_commit_revalidates_after_preview_and_creates_no_partial_rows_on_conflict(): void
    {
        $id = $this->upload($this->csv());
        $preview = $this->preview($id)->assertOk()->json();
        Employee::create(['employee_number' => 'IMPORT-2', 'name' => 'Concurrent synthetic collision']);
        $this->postJson('/app/api/imports/'.$id.'/commit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 3, 'checksum' => $preview['checksum']])->assertConflict()->assertJsonPath('status', 'invalid');
        $this->assertDatabaseMissing('employees', ['employee_number' => 'IMPORT-1']);
        $this->assertDatabaseCount('workflow_requests', 0);
    }

    public function test_xlsx_formulas_are_rejected_and_queued_import_rechecks_revoked_permissions(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['employee_number', 'name'], ['FORMULA', '=1+1']]);
        $path = tempnam(sys_get_temp_dir(), 'hris_xlsx_test_');
        try {
            (new Xlsx($book))->save($path);
            $this->login($this->hr)->post('/app/api/imports', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'employees', 'file' => new UploadedFile($path, 'synthetic.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertUnprocessable();
        } finally {
            $book->disconnectWorksheets();
            if (is_file($path)) {
                unlink($path);
            }
        }
        $id = $this->upload($this->csv());
        $preview = $this->preview($id)->assertOk()->json();
        Queue::fake([CommitImport::class]);
        $this->postJson('/app/api/imports/'.$id.'/commit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 3, 'checksum' => $preview['checksum'], 'background' => true])->assertAccepted();
        $this->hr->grants()->delete();
        try {
            (new CommitImport($this->hr->id, $this->hr->security_version, $id, 4, (string) Str::uuid(), $preview['checksum']))->handle(app(ImportService::class));
            $this->fail('Revoked actor must not commit a queued import.');
        } catch (\RuntimeException $error) {
            $this->assertStringNotContainsString('1234567890123456', $error->getMessage());
        }
        $this->assertDatabaseHas('import_batches', ['id' => $id, 'status' => 'failed']);
        $this->assertDatabaseMissing('employees', ['employee_number' => 'IMPORT-1']);
    }

    private function importKind(string $kind, string $csv): int
    {
        $id = $this->post('/app/api/imports', ['idempotency_key' => (string) Str::uuid(), 'kind' => $kind, 'file' => UploadedFile::fake()->createWithContent('synthetic.csv', $csv)], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $headers = explode(',', strtok($csv, "\n"));
        $this->postJson('/app/api/imports/'.$id.'/map', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'mapping' => array_combine($headers, $headers)])->assertOk();
        $preview = $this->postJson('/app/api/imports/'.$id.'/preview', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2])->assertOk()->assertJsonPath('status', 'validated')->json();
        $this->postJson('/app/api/imports/'.$id.'/commit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 3, 'checksum' => $preview['checksum']])->assertOk();

        return $id;
    }

    public function test_master_roster_leave_and_compensation_imports_use_domain_rules(): void
    {
        $this->login($this->hr);
        $this->importKind('branches', "code,name,timezone\nIMPORT_SITE,Synthetic Site,Asia/Makassar\n");
        $this->assertDatabaseHas('branches', ['code' => 'IMPORT_SITE', 'timezone' => 'Asia/Makassar']);
        $this->importKind('departments', "code,name\nIMPORT_DEPT,Synthetic Department\n");
        $this->importKind('jobs', "code,name,department_code\nIMPORT_JOB,Synthetic Job,IMPORT_DEPT\n");
        $this->importKind('rosters', "employee_number,work_date,shift_code,is_day_off\nTEST,2026-10-19,NIGHT,0\n");
        $this->assertDatabaseHas('roster_versions', ['status' => 'draft']);
        $this->postJson('/app/api/leave-types', ['idempotency_key' => (string) Str::uuid(), 'code' => 'SYNTHETIC', 'name' => 'Synthetic Leave', 'is_paid' => true, 'deducts_balance' => true, 'allows_half_day' => true, 'requires_evidence' => false, 'notice_days' => 0, 'minimum_tenure_days' => 0])->assertCreated();
        $this->importKind('leave_credits', "employee_number,type_code,entitlement_year,valid_from,valid_until,minutes,source_key,reason\nTEST,SYNTHETIC,2026,2026-01-01,2027-01-01,480,SYNTHETIC_IMPORT_CREDIT,Synthetic opening balance\n");
        $this->assertDatabaseHas('leave_credit_lots', ['source_key' => 'SYNTHETIC_IMPORT_CREDIT', 'minutes' => 480]);
        $payroll = $this->user('payroll_officer');
        $this->login($payroll);
        $this->importKind('compensation', "employee_number,valid_from,monthly_base_minor,reason\nTEST,2026-10-19,1000000,Synthetic opening compensation\n");
        $this->assertDatabaseHas('workflow_requests', ['kind' => 'compensation', 'status' => 'draft']);
        $this->assertDatabaseCount('employee_compensations', 0);
    }
}
