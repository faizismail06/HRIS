<?php

namespace Tests\Feature;

use App\Domain\Identity\Access;
use App\Domain\Workflow\WorkflowRegistry;
use App\Http\Controllers\WorkflowWorkspaceController;
use App\Models\WorkflowRequest;
use App\Support\WorkspaceOperations;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class OperationWorkspaceTest extends AttendanceScenarioCase
{
    public function test_catalog_routes_resolve_and_all_permitted_forms_render(): void
    {
        foreach (Access::ROLES as $role) {
            if (! $this->worker->grants()->where('role', $role)->exists()) {
                $this->worker->grants()->create(['role' => $role, 'scope' => $role === 'manager' ? 'team' : 'company']);
            }
        }
        $this->login($this->worker->fresh());
        $this->get('/app/modules')->assertOk()->assertSee('Ruang kerja');
        foreach (WorkspaceOperations::all() as $key => $op) {
            $route = Route::getRoutes()->match(Request::create('/app/api'.str_replace('{record}', '1', $op['endpoint']), $op['method']));
            $this->assertNotEmpty($route->getActionName(), $key);
            $this->get('/app/work/'.$key)->assertOk()->assertSee('data-operation-schema', false);
        }
        $this->assertEqualsCanonicalizing(app(WorkflowRegistry::class)->kinds(), array_keys(WorkflowWorkspaceController::KINDS));
    }

    public function test_all_lookup_sources_use_real_schema_and_never_expose_private_rows(): void
    {
        foreach (['hr_admin', 'payroll_officer', 'finance_approver', 'it_asset_officer', 'platform_admin'] as $role) {
            $this->worker->grants()->create(['role' => $role, 'scope' => 'company']);
        }
        $this->login($this->worker->fresh());
        $sources = [];
        $walk = function ($fields) use (&$walk, &$sources) {
            foreach ($fields as $field) {
                if (isset($field['source'])) {
                    $sources[] = $field['source'];
                } if (isset($field['fields'])) {
                    $walk($field['fields']);
                }
            }
        };
        foreach (WorkspaceOperations::all() as $op) {
            $walk($op['fields']);
        }
        foreach (array_unique($sources) as $source) {
            if ($source === 'swap-shifts') {
                continue;
            }
            $response = $this->getJson('/app/options/'.$source)->assertOk();
            foreach ($response->json('data') as $item) {
                $this->assertEqualsCanonicalizing(['value', 'label', 'meta'], array_keys($item));
            }
        }
        $this->login($this->user('employee'));
        $this->getJson('/app/options/accounts')->assertForbidden();
        $this->getJson('/app/options/payroll-batches')->assertForbidden();
        $this->getJson('/app/options/unknown')->assertNotFound();
        $this->get('/app/work/account-access')->assertForbidden();
        $this->get('/app/work/unknown')->assertNotFound();
    }

    public function test_asset_form_issues_accepts_returns_and_rejects_stale_versions(): void
    {
        $it = $this->user('it_asset_officer');
        $this->login($it);
        $this->post('/app/work/asset-create', ['idempotency_key' => (string) Str::uuid(), 'code' => 'DEVICE', 'category' => 'Laptop', 'branch_id' => (string) DB::table('branches')->sole()->id, 'condition' => 'new'])->assertOk()->assertSee('Hasil tersimpan');
        $asset = DB::table('assets')->where('code', 'DEVICE')->sole();
        $this->post('/app/work/asset-issue', ['idempotency_key' => (string) Str::uuid(), 'record_id' => (string) $asset->id, 'expected_version' => '1', 'employee_id' => (string) $this->worker->employee_id, 'evidence' => 'Synthetic signed handover'])->assertOk();
        $custody = DB::table('asset_custodies')->sole();
        $this->login($this->worker);
        $this->post('/app/work/asset-accept', ['idempotency_key' => (string) Str::uuid(), 'record_id' => (string) $custody->id, 'expected_version' => '1'])->assertOk();
        $this->assertNotNull(DB::table('asset_custodies')->value('accepted_at'));
        $this->login($it);
        $this->from('/app/work/asset-return')->post('/app/work/asset-return', ['idempotency_key' => (string) Str::uuid(), 'record_id' => (string) $custody->id, 'expected_version' => '1', 'condition' => 'good', 'evidence' => 'Synthetic inspection'])->assertRedirect()->assertSessionHasErrors('operation');
        $this->post('/app/work/asset-return', ['idempotency_key' => (string) Str::uuid(), 'record_id' => (string) $custody->id, 'expected_version' => '2', 'condition' => 'good', 'evidence' => 'Synthetic inspection'])->assertOk();
        $this->assertNotNull(DB::table('asset_custodies')->value('returned_at'));
    }

    public function test_private_profile_workflow_keeps_sensitive_details_authorized(): void
    {
        $this->login($this->worker);
        $this->post('/app/work/request-bank_change', ['idempotency_key' => (string) Str::uuid(), 'employee_id' => (string) $this->worker->employee_id, 'base_version' => '0', 'data' => ['bank_code' => 'SYNTHETIC', 'account_number' => '12345678', 'account_name' => 'Synthetic Worker'], 'reason' => 'Synthetic bank verification'])->assertRedirect();
        $record = WorkflowRequest::where('kind', 'bank_change')->sole();
        $this->get('/app/requests/'.$record->id)->assertOk()->assertSee('12345678');
        $this->login($this->hr);
        $this->get('/app/requests/'.$record->id)->assertNotFound();
        $this->getJson('/app/profile-version?employee_id='.$this->worker->employee_id.'&category=bank')->assertNotFound();
    }

    public function test_import_flow_uploads_maps_previews_commits_and_links_results(): void
    {
        $this->login($this->hr);
        $file = UploadedFile::fake()->createWithContent('branches.csv', "Code,Name,Timezone\nIMPORT,Synthetic Imported Branch,Asia/Jakarta\n");
        $this->post('/app/work/import-upload', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'branches', 'file' => $file])->assertOk()->assertSee('Petakan kolom impor');
        $id = DB::table('import_batches')->sole()->id;
        $mapping = [['key' => 'code', 'value' => 'Code'], ['key' => 'name', 'value' => 'Name'], ['key' => 'timezone', 'value' => 'Timezone']];
        $this->post('/app/work/import-map', ['idempotency_key' => (string) Str::uuid(), 'record_id' => (string) $id, 'expected_version' => '1', 'mapping' => $mapping])->assertOk();
        $this->post('/app/work/import-preview', ['idempotency_key' => (string) Str::uuid(), 'record_id' => (string) $id, 'expected_version' => '2'])->assertOk();
        $batch = DB::table('import_batches')->sole();
        $this->post('/app/work/import-commit', ['idempotency_key' => (string) Str::uuid(), 'record_id' => (string) $id, 'expected_version' => (string) $batch->version, 'checksum' => $batch->preview_checksum, 'background' => '0'])->assertOk();
        $this->assertDatabaseHas('branches', ['code' => 'IMPORT']);
        $this->get('/app/work/report-attendance?run=1&starts_on=2026-10-01&ends_on=2026-11-01')->assertOk()->assertSee('Rincian');
    }
}
