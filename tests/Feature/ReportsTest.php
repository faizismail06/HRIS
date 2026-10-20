<?php

namespace Tests\Feature;

use App\Domain\Reports\ReportExports;
use App\Jobs\BuildReportExport;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\AttendanceScenarioCase;

class ReportsTest extends AttendanceScenarioCase
{
    private function report(string $kind = 'attendance', array $filters = [])
    {
        return $this->getJson('/app/api/reports/'.$kind.'?'.http_build_query($filters + ['starts_on' => '2026-10-18', 'ends_on' => '2026-10-20']));
    }

    public function test_presence_late_early_and_incomplete_are_independent_and_reports_are_scoped(): void
    {
        $one = $this->roster('2026-10-18');
        $two = $this->roster('2026-10-19');
        $this->login($this->worker);
        $this->travelTo(Carbon::parse('2026-10-18 22:10:00', 'Asia/Jakarta'));
        $this->clock($one['shift_id'], 'in')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-19 05:50:00', 'Asia/Jakarta'));
        $this->clock($one['shift_id'], 'out')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-19 22:00:00', 'Asia/Jakarta'));
        $this->clock($two['shift_id'], 'in')->assertOk();
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jakarta'));
        $this->report()->assertOk()->assertJsonPath('totals.shifts', 2)->assertJsonPath('totals.present', 2)->assertJsonPath('totals.late', 1)->assertJsonPath('totals.early', 1)->assertJsonPath('totals.incomplete', 1);
        $other = Employee::create(['employee_number' => 'OTHER-REPORT', 'name' => 'Synthetic Other']);
        $this->login($this->user('employee', $other->id));
        $this->report()->assertOk()->assertJsonCount(0, 'data');
        $this->report('payroll')->assertForbidden();
        $this->report('expenses')->assertForbidden();
    }

    public function test_closed_report_and_drill_down_use_snapshot_even_after_projection_or_name_changes(): void
    {
        $shift = $this->roster('2026-10-18', true);
        $this->login($this->hr);
        $branch = $this->worker->employee->assignments()->sole()->branch_id;
        $period = $this->postJson('/app/api/attendance-periods', ['idempotency_key' => (string) Str::uuid(), 'branch_id' => $branch, 'starts_on' => '2026-10-18', 'ends_on' => '2026-10-19'])->assertCreated()->json('id');
        $review = $this->postJson('/app/api/attendance-periods/'.$period.'/review', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk()->json();
        $this->postJson('/app/api/attendance-periods/'.$period.'/close', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'checksum' => $review['checksum']])->assertOk();
        DB::table('attendance_results')->where('roster_shift_id', $shift['shift_id'])->update(['status' => 'ABSENT']);
        $this->worker->employee->update(['name' => 'Later changed name']);
        $report = $this->report()->assertOk()->assertJsonPath('data.0.status', 'DAY_OFF')->assertJsonPath('data.0.employee_name', 'Synthetic Worker')->assertJsonPath('data.0.source', 'closed_snapshot')->json();
        $this->getJson($report['data'][0]['source_path'])->assertOk()->assertJsonPath('snapshot.result.status', 'DAY_OFF');
    }

    public function test_csv_and_xlsx_exports_are_private_expiring_formula_safe_and_recheck_team_scope(): void
    {
        Queue::fake([BuildReportExport::class]);
        $this->worker->employee->update(['name' => '=SYNTHETIC_FORMULA()']);
        $this->roster('2026-10-18', true);
        $boss = Employee::create(['employee_number' => 'EXPORT-BOSS', 'name' => 'Synthetic Manager']);
        $manager = $this->user('employee', $boss->id);
        $manager->grants()->create(['role' => 'manager', 'scope' => 'team']);
        $this->worker->employee->assignments()->update(['manager_employee_id' => $boss->id]);
        $this->login($manager);
        foreach (['csv', 'xlsx'] as $format) {
            $id = $this->postJson('/app/api/reports/attendance/exports', ['idempotency_key' => (string) Str::uuid(), 'format' => $format, 'reason' => 'Synthetic team report', 'starts_on' => '2026-10-18', 'ends_on' => '2026-10-20'])->assertAccepted()->json('id');
            app(ReportExports::class)->build($id);
            $bytes = $this->get('/app/api/report-exports/'.$id.'/download')->assertOk()->getContent();
            if ($format === 'csv') {
                $this->assertStringContainsString("'=SYNTHETIC_FORMULA()", $bytes);
            } else {
                $this->assertStringStartsWith('PK', $bytes);
            }
        }
        $this->worker->employee->assignments()->update(['manager_employee_id' => null]);
        $this->get('/app/api/report-exports/'.$id.'/download')->assertForbidden();
        $this->login($this->hr)->get('/app/api/report-exports/'.$id.'/download')->assertNotFound();
        $this->login($manager);
        $this->travel(25)->hours();
        $this->get('/app/api/report-exports/'.$id.'/download')->assertNotFound();
    }

    public function test_search_filters_each_result_by_domain_scope_and_treats_wildcards_literally(): void
    {
        Employee::create(['employee_number' => 'PRIVATE-OTHER', 'name' => 'Synthetic Other Person']);
        $this->login($this->worker)->getJson('/app/api/search?q=Synthetic')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', 'TEST');
        $this->getJson('/app/api/search?q=PRIVATE')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/app/api/search?q=%25_')->assertOk()->assertJsonCount(0, 'data');
        $this->login($this->user('platform_admin'))->getJson('/app/api/search?q=Synthetic')->assertOk()->assertJsonCount(0, 'data');
    }
}
