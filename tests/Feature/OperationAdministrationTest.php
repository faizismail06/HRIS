<?php

namespace Tests\Feature;

use App\Domain\Reports\ReportExports;
use App\Jobs\BuildReportExport;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class OperationAdministrationTest extends AttendanceScenarioCase
{
    public function test_auditor_read_access_requires_separate_export_authorization_and_revocation_invalidates_files(): void
    {
        Queue::fake([BuildReportExport::class]);
        $auditor = $this->user('auditor');
        $platform = $this->user('platform_admin');
        $filters = ['starts_on' => '2026-10-01', 'ends_on' => '2026-11-01', 'format' => 'csv', 'reason' => 'Synthetic audit mandate'];
        $this->login($auditor);
        $this->get('/app/work/report-attendance?run=1&starts_on=2026-10-01&ends_on=2026-11-01')->assertOk();
        $this->write('export-attendance', $filters)->assertForbidden();
        $this->login($platform);
        $access = ['record_id' => (string) $auditor->id, 'expected_version' => (string) $auditor->security_version, 'is_active' => '1', 'auditor_export_enabled' => '1', 'grants' => [['role' => 'auditor', 'scope' => 'company']]];
        $this->write('account-access', $access)->assertOk();
        $this->login($auditor->fresh());
        $this->write('export-attendance', $filters)->assertOk();
        $id = DB::table('report_exports')->sole()->id;
        app(ReportExports::class)->build($id);
        $this->get('/app/work/export-download?run=1&record_id='.$id)->assertOk();
        $this->login($platform);
        $access['expected_version'] = (string) $auditor->fresh()->security_version;
        $access['auditor_export_enabled'] = '0';
        $this->write('account-access', $access)->assertOk();
        $this->login($auditor->fresh());
        $this->get('/app/work/export-download?run=1&record_id='.$id)->assertForbidden();
        $this->write('export-attendance', $filters)->assertForbidden();
    }

    private function write(string $key, array $input = [])
    {
        return $this->post('/app/work/'.$key, ['idempotency_key' => (string) Str::uuid()] + $input);
    }

    public function test_letter_template_draft_preview_approval_and_pdf_download_are_connected(): void
    {
        $this->login($this->hr);
        $this->write('document-template', ['code' => 'UI_LETTER', 'kind' => 'employment_certificate', 'title' => 'Synthetic certificate', 'body' => 'Synthetic {{employee_name}} / {{employee_number}}'])->assertOk();
        $template = DB::table('document_templates')->sole();
        $this->write('workflow-definition', ['name' => 'Synthetic letter', 'kind' => 'letter', 'min_amount_minor' => '0', 'priority' => '10', 'steps' => [['role' => 'hr_admin']]])->assertOk();
        $this->login($this->worker);
        $this->write('request-letter', ['employee_id' => (string) $this->worker->employee_id, 'employee_version' => '1', 'employment_period_id' => (string) DB::table('employment_periods')->sole()->id, 'document_template_id' => (string) $template->id, 'letter_kind' => 'employment_certificate', 'reason' => 'Synthetic verification'])->assertRedirect()->assertSessionHasNoErrors();
        $request = WorkflowRequest::where('kind', 'letter')->sole();
        $this->get('/app/requests/'.$request->id)->assertOk()->assertSee('Pratinjau dokumen');
        $this->get('/app/work/document-preview?run=1&record_id='.$request->id)->assertOk()->assertSee('Synthetic Worker');
        $this->assertDatabaseCount('issued_documents', 0);
        $this->post('/app/requests/'.$request->id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->login($this->hr);
        $this->post('/app/requests/'.$request->id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => '2', 'decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $doc = DB::table('issued_documents')->sole();
        $this->login($this->worker);
        $this->get('/app/work/document-download?run=1&record_id='.$doc->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_checklist_preferences_accounts_and_audit_have_real_validation_and_permissions(): void
    {
        $this->login($this->hr);
        $this->write('lifecycle-template', ['code' => 'UI_JOIN', 'kind' => 'onboarding', 'tasks' => [['code' => 'induction', 'label' => 'Synthetic induction', 'role' => 'hr_admin', 'mandatory' => '1', 'due_offset_days' => '0', 'dependencies' => '']]])->assertOk();
        $this->assertSame(true, json_decode(DB::table('lifecycle_templates')->value('tasks'), true)[0]['mandatory']);
        $this->getJson('/app/options/lifecycle-templates')->assertOk()->assertJsonPath('data.0.meta.tasks.0.code', 'induction');
        $this->write('notification-update', ['email_enabled' => '0', 'in_app_enabled' => '1', 'timezone' => 'Asia/Jakarta', 'delivery_mode' => 'daily', 'digest_time' => '09:00'])->assertOk();
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $this->hr->id, 'email_enabled' => false]);
        $this->write('profile-field', ['code' => 'uniform_size', 'label' => 'Ukuran seragam', 'type' => 'select', 'options' => "S\nM\nL"])->assertOk();
        $this->login($this->user('platform_admin'));
        $this->get('/app/work/account-detail?run=1&record_id='.$this->worker->id)->assertOk()->assertDontSee('two_factor_secret')->assertDontSee('password');
        $this->get('/app/audit')->assertForbidden();
        $this->login($this->user('auditor'));
        $this->get('/app/audit')->assertOk()->assertSee('Jejak audit');
        $this->get('/app/work/account-detail?run=1&record_id='.$this->worker->id)->assertForbidden();
    }
}
