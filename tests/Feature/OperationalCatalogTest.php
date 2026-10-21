<?php

namespace Tests\Feature;

use App\Models\Branch;
use Tests\Support\AttendanceScenarioCase;

class OperationalCatalogTest extends AttendanceScenarioCase
{
    public function test_catalogs_follow_domain_roles_and_location_scope_without_arbitrary_table_access(): void
    {
        foreach (['workflow-definitions', 'lifecycle-templates', 'document-templates', 'work-locations', 'shift-templates'] as $kind) {
            $this->getJson('/app/api/catalogs/'.$kind)->assertOk();
        }
        $this->getJson('/app/api/leave-types')->assertOk();
        $this->getJson('/app/api/catalogs/users')->assertNotFound();
        $this->getJson('/app/api/catalogs/bank-export-templates')->assertForbidden();
        $finance = $this->user('finance_approver');
        foreach (['payroll-policies', 'bank-export-templates', 'expense-policies'] as $kind) {
            $this->login($finance)->getJson('/app/api/catalogs/'.$kind)->assertOk();
        }
        $other = Branch::create(['company_id' => 1, 'code' => 'OTHER', 'name' => 'Other synthetic branch']);
        $branchHr = $this->user('hr_admin');
        $branchHr->grants()->update(['scope' => 'branch', 'branch_id' => $other->id]);
        $this->login($branchHr)->getJson('/app/api/catalogs/work-locations')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/app/api/catalogs/shift-templates')->assertOk()->assertJsonPath('total', 0);
        $this->login($this->worker)->getJson('/app/api/catalogs/document-templates')->assertOk();
        $this->getJson('/app/api/catalogs/payroll-policies')->assertForbidden();
        $this->getJson('/app/api/eligible-reviewers?employee_id='.$this->worker->employee_id.'&role=finance_approver')->assertForbidden();
        $this->login($finance)->getJson('/app/api/eligible-reviewers?employee_id='.$this->worker->employee_id.'&role=finance_approver')->assertOk()->assertJsonPath('data.0.id', $finance->id);
    }
}
