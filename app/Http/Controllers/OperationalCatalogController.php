<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\Access;
use App\Domain\Identity\AccountSecurity;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationalCatalogController extends Controller
{
    public function index(Request $r, string $kind)
    {
        $u = $r->user();
        $finance = Access::companyPermission($u, 'payroll.prepare') || Access::companyPermission($u, 'payroll.approve');
        $spec = match ($kind) {
            'workflow-definitions' => ['workflow_definitions', 'workflow.manage', ['id', 'name', 'kind', 'version', 'is_active', 'steps', 'priority']],
            'lifecycle-templates' => ['lifecycle_templates', 'employees.manage', ['id', 'code', 'kind', 'version', 'branch_id', 'job_id', 'tasks']],
            'payroll-policies' => ['payroll_policies', 'finance', ['id', 'code', 'version', 'valid_from', 'valid_until', 'status', 'rules', 'official_sources']],
            'bank-export-templates' => ['bank_export_templates', 'finance', ['id', 'code', 'version', 'columns', 'confirmed_at']],
            'document-templates' => ['document_templates', 'letters', ['id', 'code', 'kind', 'title', 'version', 'is_active']],
            'expense-policies' => ['expense_policies', 'expenses', ['id', 'category', 'version', 'valid_from', 'valid_until', 'max_claim_minor', 'max_month_minor', 'minimum_tenure_days', 'job_ids']],
            'work-locations' => ['work_locations', 'schedules', ['id', 'code', 'name', 'branch_id', 'timezone', 'latitude', 'longitude', 'radius_m', 'max_accuracy_m', 'requires_photo', 'attendance_mode', 'version', 'is_active']],
            'shift-templates' => ['shift_templates', 'schedules', ['id', 'code', 'name', 'work_location_id', 'starts_local', 'ends_local', 'end_day_offset', 'unpaid_breaks', 'half_day_split_minute', 'tolerance_minutes', 'version', 'is_active']],
            default => abort(404),
        };
        [$table, $permission, $columns] = $spec;
        $self = $u->employee_id && $u->grants()->where('role', 'employee')->where('scope', 'self')->exists();
        $q = DB::table($table)->orderBy('id');
        if ($permission === 'finance') {
            abort_unless($finance, 403);
        } elseif (in_array($permission, ['letters', 'expenses'])) {
            abort_unless($self || ($permission === 'expenses' ? $finance : Access::companyPermission($u, 'documents.manage')), 403);
            if ($self && ! $finance && ! Access::companyPermission($u, 'documents.manage')) {
                if ($permission === 'letters') {
                    $q->where('is_active', true)->where('kind', '<>', 'contract');
                } else {
                    $q->where('valid_until', '>', today()->toDateString());
                }
            }
        } elseif ($permission === 'schedules') {
            abort_unless($u->grants()->whereIn('role', ['hr_admin', 'hr_officer', 'manager'])->exists(), 403);
            if (! Access::companyPermission($u, 'schedules.manage')) {
                $visible = EmployeeVisibility::query($u, today()->toDateString(), ['hr_admin', 'hr_officer', 'manager'])->select('employees.id');
                $branches = DB::table('assignments')->whereIn('employee_id', $visible)->whereNull('superseded_at')->where('valid_from', '<=', today()->toDateString())->where(fn ($b) => $b->whereNull('valid_to')->orWhere('valid_to', '>', today()->toDateString()))->pluck('branch_id')->merge($u->grants()->whereIn('role', ['hr_admin', 'hr_officer'])->where('scope', 'branch')->pluck('branch_id'))->unique();
                $q->whereIn($kind === 'work-locations' ? 'branch_id' : 'work_location_id', $kind === 'work-locations' ? $branches : DB::table('work_locations')->whereIn('branch_id', $branches)->select('id'));
            }
        } else {
            abort_unless(Access::companyPermission($u, $permission), 403);
        }

        return response()->json($q->paginate(50, $columns))->header('Cache-Control', 'no-store, private');
    }

    public function reviewers(Request $r)
    {
        $d = $r->validate(['employee_id' => ['required', 'integer', 'exists:employees,id'], 'role' => ['required', 'in:'.implode(',', Access::ROLES)]]);
        abort_unless(Access::companyPermission($r->user(), 'employees.review') || ($d['role'] === 'finance_approver' && Access::companyPermission($r->user(), 'payroll.approve')), 403);
        $users = User::where('is_active', true)->whereHas('grants', fn ($q) => $q->where('role', $d['role']))->orderBy('id')->get()->filter(fn ($u) => AccountSecurity::available($u) && WorkflowAccess::eligible($u, $d['role'], (int) $d['employee_id']))->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values();

        return response()->json(['data' => $users])->header('Cache-Control', 'no-store, private');
    }
}
