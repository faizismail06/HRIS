<?php

namespace App\Support;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Documents\DocumentService;
use App\Domain\Identity\Access;
use App\Domain\Imports\ImportService;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Only reference labels and optimistic versions leave this boundary. */
class WorkspaceOptions
{
    public function page(Request $r, string $source): array
    {
        $u = $r->user();
        $r->validate(['q' => ['nullable', 'string', 'max:120'], 'after' => ['nullable', 'integer', 'min:0'], 'employee_id' => ['nullable', 'integer'], 'source_shift_id' => ['nullable', 'integer']]);
        $finance = WorkspaceOperations::allowed($u, 'finance');
        $hr = WorkspaceOperations::allowed($u, 'hr');
        $it = WorkspaceOperations::allowed($u, 'it');
        $platform = WorkspaceOperations::allowed($u, 'platform');
        $date = today()->toDateString();
        $visible = fn (int $id, ?string $on = null) => EmployeeVisibility::query($u, $on ?? $date)->whereKey($id)->exists();
        $person = fn (int $id) => ($hr || $finance || $it) || $visible($id);
        $allow = fn ($row) => true;
        $meta = fn ($row) => isset($row->version) ? ['version' => $row->version] : [];
        $columns = ['id'];
        switch ($source) {
            case 'people':
                $q = Employee::query();
                if (! $hr && ! $finance && ! $it) {
                    $q = EmployeeVisibility::query($u, $date);
                }
                $columns = ['id', 'name', 'employee_number', 'version'];
                break;
            case 'branches': case 'departments': case 'jobs':
                $table = $source === 'jobs' ? 'job_positions' : $source;
                $q = DB::table($table);
                $columns = ['id', 'code', 'name', 'version'];
                if (! $hr && ! $finance && ! $it && ! $platform) {
                    $ids = EmployeeVisibility::query($u, $date)->select('employees.id');
                    $q->whereIn('id', DB::table('assignments')->whereIn('employee_id', $ids)->whereNull('superseded_at')->where('valid_from', '<=', $date)->where(fn ($a) => $a->whereNull('valid_to')->orWhere('valid_to', '>', $date))->select($source === 'jobs' ? 'job_id' : ($source === 'branches' ? 'branch_id' : 'department_id')));
                }
                break;
            case 'employment-periods':
                $q = DB::table('employment_periods');
                $columns = ['id', 'employee_id', 'starts_on', 'ends_on'];
                $allow = fn ($row) => $person($row->employee_id);
                break;
            case 'accounts':
                abort_unless($platform, 403);
                $q = DB::table('users');
                $columns = ['id', 'name', 'email', 'security_version'];
                $meta = fn ($row) => ['version' => $row->security_version];
                break;
            case 'reviewers':
                abort_unless($hr || $finance || $platform || WorkspaceOperations::allowed($u, 'delegator'), 403);
                $q = DB::table('users')->where('is_active', true)->whereNotNull('two_factor_confirmed_at')->whereIn('id', DB::table('role_grants')->whereIn('role', ['hr_admin', 'hr_officer', 'manager', 'finance_approver', 'it_asset_officer'])->select('user_id'));
                $columns = ['id', 'name'];
                break;
            case 'requests':
                $q = WorkflowRequest::query();
                $columns = ['id', 'kind', 'status', 'employee_id', 'submitted_by', 'version', 'payload'];
                $allow = fn ($row) => WorkflowAccess::visible($u, $row);
                break;
            case 'my-shifts': case 'swap-shifts':
                abort_unless(WorkspaceOperations::allowed($u, 'self'), 403);
                $q = RosterShift::where('status', 'published')->where('is_day_off', false)->where('work_date', '>', $date);
                if ($source === 'my-shifts') {
                    $q->where('employee_id', $u->employee_id);
                } else {
                    $shift = RosterShift::where('employee_id', $u->employee_id)->where('status', 'published')->where('is_day_off', false)->findOrFail($r->integer('source_shift_id'));
                    $q->where('employee_id', '<>', $u->employee_id)->where('snapshot->branch_id', $shift->snapshot['branch_id'])->where('snapshot->department_id', $shift->snapshot['department_id']);
                }
                $columns = ['id', 'employee_id', 'work_date', 'starts_at', 'ends_at', 'version'];
                break;
            case 'cases':
                $q = DB::table('lifecycle_cases');
                $columns = ['id', 'employee_id', 'kind', 'status', 'effective_on', 'last_working_on', 'version'];
                $allow = fn ($row) => EmployeeVisibility::query($u, $row->last_working_on ?? $row->effective_on, ['hr_admin', 'hr_officer'])->whereKey($row->employee_id)->exists() || $u->employee_id === $row->employee_id || DB::table('lifecycle_tasks')->where('lifecycle_case_id', $row->id)->where('owner_id', $u->id)->exists();
                break;
            case 'tasks':
                $q = DB::table('lifecycle_tasks');
                $columns = ['id', 'lifecycle_case_id', 'label', 'code', 'status', 'owner_id', 'version'];
                $allow = function ($row) use ($u) {
                    $case = DB::table('lifecycle_cases')->where('id', $row->lifecycle_case_id)->first();

                    return $row->owner_id === $u->id || ($case && EmployeeVisibility::query($u, $case->last_working_on ?? $case->effective_on, ['hr_admin', 'hr_officer'])->whereKey($case->employee_id)->exists());
                };
                break;
            case 'contracts': case 'documents': case 'contract-reviews':
                $table = ['contracts' => 'employment_contracts', 'documents' => 'issued_documents', 'contract-reviews' => 'contract_review_tasks'][$source];
                $q = DB::table($table);
                $columns = match ($source) {
                    'contracts' => ['id', 'employee_id', 'contract_type', 'starts_on', 'ends_on', 'status', 'version'],
                    'documents' => ['id', 'employee_id', 'number', 'kind'],
                    default => ['id', 'employment_contract_id', 'status', 'version'],
                };
                $allow = function ($row) use ($u, $source) {
                    $employee = $source === 'contract-reviews' ? DB::table('employment_contracts')->where('id', $row->employment_contract_id)->value('employee_id') : $row->employee_id;

                    return $employee && (DocumentService::visible($u, (int) $employee) || ($source === 'contract-reviews' && WorkflowAccess::eligible($u, 'manager', (int) $employee)));
                };
                break;
            case 'assets': case 'custodies':
                abort_unless($it || ($source === 'custodies' && WorkspaceOperations::allowed($u, 'self')), 403);
                $q = DB::table($source === 'assets' ? 'assets' : 'asset_custodies');
                $columns = $source === 'assets' ? ['id', 'code', 'category', 'serial', 'version'] : ['id', 'asset_id', 'employee_id', 'issued_at', 'accepted_at', 'returned_at', 'version'];
                if (! $it) {
                    $q->where('employee_id', $u->employee_id);
                }
                break;
            case 'settlements': case 'allocations': case 'reimbursements': case 'instructions': case 'instruction-items': case 'bank-exports':
                abort_unless(WorkspaceOperations::allowed($u, 'approver'), 403);
                [$table, $columns] = match ($source) {
                    'settlements' => ['asset_settlement_cases', ['id', 'asset_custody_id', 'classification', 'status', 'version']],
                    'allocations' => ['expense_allocations', ['id', 'expense_claim_id', 'channel']],
                    'reimbursements' => ['expense_batches', ['id', 'status', 'version']],
                    'instructions' => ['expense_bank_instructions', ['id', 'public_id', 'expense_batch_id']],
                    'instruction-items' => ['expense_instruction_items', ['id', 'expense_bank_instruction_id', 'expense_batch_item_id']],
                    default => ['bank_exports', ['id', 'public_id', 'payroll_batch_id']],
                };
                $q = DB::table($table);
                break;
            case 'compensations': case 'payroll-policies': case 'payroll-batches': case 'payroll-rows': case 'bank-templates': case 'adjustments': case 'attendance-adjustments':
                abort_unless($finance, 403);
                [$table, $columns] = match ($source) {
                    'compensations' => ['employee_compensations', ['id', 'employee_id', 'valid_from']],
                    'payroll-policies' => ['payroll_policies', ['id', 'code', 'status', 'version', 'valid_from', 'valid_until']],
                    'payroll-batches' => ['payroll_batches', ['id', 'public_id', 'kind', 'status', 'version']],
                    'payroll-rows' => ['payroll_rows', ['id', 'employee_id', 'payroll_batch_id']],
                    'bank-templates' => ['bank_export_templates', ['id', 'code', 'version', 'confirmed_at']],
                    'adjustments' => ['payroll_adjustments', ['id', 'source_key', 'status', 'version']],
                    default => ['attendance_adjustments', ['id', 'employee_id', 'attendance_period_id']],
                };
                $q = DB::table($table);
                break;
            case 'payslips':
                $q = DB::table('payroll_rows')->whereIn('payroll_batch_id', DB::table('payroll_batches')->where('status', 'finalized')->where('release_at', '<=', now())->select('id'));
                $columns = ['id', 'employee_id', 'payroll_batch_id'];
                if (! $finance) {
                    abort_unless(WorkspaceOperations::allowed($u, 'self'), 403);
                    $q->where('employee_id', $u->employee_id);
                }
                break;
            case 'claims':
                $q = DB::table('expense_claims');
                $columns = ['id', 'employee_id', 'workflow_request_id', 'incurred_on', 'status'];
                $allow = fn ($row) => WorkspaceOperations::allowed($u, 'approver') || (($wr = WorkflowRequest::find($row->workflow_request_id)) && WorkflowAccess::visible($u, $wr));
                break;
            case 'imports':
                $q = DB::table('import_batches')->where('created_by', $u->id);
                $columns = ['id', 'kind', 'status', 'version', 'preview_checksum', 'headers'];
                $allow = fn ($row) => self::importAllowed($u, $row->kind);
                $meta = fn ($row) => ['version' => $row->version, 'checksum' => $row->preview_checksum, 'headers' => json_decode($row->headers, true), 'fields' => ImportService::FIELDS[$row->kind]];
                break;
            case 'exports':
                $q = DB::table('report_exports')->where('created_by', $u->id)->where('security_version', $u->security_version);
                $columns = ['id', 'kind', 'status', 'created_at'];
                break;
            case 'delegations':
                $q = DB::table('workflow_delegations')->where('delegator_id', $u->id);
                $columns = ['id', 'role', 'delegate_id', 'starts_at', 'ends_at'];
                break;
            case 'suspensions': case 'deliveries':
                abort_unless($platform, 403);
                $q = DB::table($source === 'suspensions' ? 'account_suspensions' : 'notification_deliveries');
                $columns = $source === 'suspensions' ? ['id', 'user_id', 'starts_at', 'ends_at', 'revoked_at'] : ['id', 'status', 'channel', 'created_at'];
                break;
            case 'document-templates': case 'lifecycle-templates': case 'expense-policies': case 'work-locations': case 'attendance-periods': case 'leave-types':
                [$table, $columns] = match ($source) {
                    'document-templates' => ['document_templates', ['id', 'code', 'title', 'kind', 'version']],
                    'lifecycle-templates' => ['lifecycle_templates', ['id', 'code', 'kind', 'version']],
                    'expense-policies' => ['expense_policies', ['id', 'category', 'version', 'valid_from', 'valid_until']],
                    'work-locations' => ['work_locations', ['id', 'code', 'name', 'version']],
                    'attendance-periods' => ['attendance_periods', ['id', 'branch_id', 'starts_on', 'ends_on', 'status', 'version']],
                    default => ['leave_types', ['id', 'code', 'name', 'version']],
                };
                abort_unless($hr || $finance || ($source === 'leave-types' && $u->employee_id) || (in_array($source, ['document-templates', 'expense-policies']) && WorkspaceOperations::allowed($u, 'self')), 403);
                $q = DB::table($table);
                if ($source === 'lifecycle-templates') {
                    $columns[] = 'tasks';
                    $q->where('is_active', true);
                    $meta = fn ($row) => ['version' => $row->version, 'tasks' => array_map(fn ($task) => array_intersect_key($task, array_flip(['code', 'label', 'role', 'mandatory', 'dependencies'])), json_decode($row->tasks, true))];
                }
                if ($source === 'document-templates') {
                    $q->where('is_active', true);
                    if (! $hr) {
                        $q->where('kind', '<>', 'contract');
                    }
                }
                break;
            default: abort(404);
        }
        if ($r->filled('employee_id') && in_array('employee_id', $columns)) {
            $q->where('employee_id', $r->integer('employee_id'));
        }
        $q->where('id', '>', $r->integer('after'))->orderBy('id');
        $searchable = array_intersect($columns, ['name', 'code', 'employee_number', 'email', 'public_id', 'label', 'source_key', 'category', 'number']);
        if ($r->filled('q')) {
            $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $r->string('q')->toString());
            $q->where(function ($query) use ($term, $searchable, $r) {
                $query->whereRaw('1=0');
                if (ctype_digit($r->string('q')->toString())) {
                    $query->orWhere('id', $r->integer('q'));
                }
                foreach ($searchable as $column) {
                    $query->orWhere($column, 'ilike', '%'.$term.'%');
                }
            });
        }
        $rows = $q->limit(201)->get($columns);
        $results = [];
        $cursor = null;
        foreach ($rows->take(200) as $row) {
            $cursor = $row->id;
            if (! $allow($row)) {
                continue;
            }
            $label = [];
            foreach (['name', 'employee_number', 'code', 'title', 'label', 'number', 'public_id', 'source_key', 'category', 'kind', 'status', 'work_date', 'starts_on', 'ends_on', 'valid_from', 'effective_on', 'incurred_on', 'role', 'email'] as $field) {
                if (isset($row->$field) && $row->$field !== '') {
                    $label[] = $row->$field;
                }
            }
            if (isset($row->employee_id) && $source !== 'people') {
                $e = Employee::find($row->employee_id);
                if ($e) {
                    array_unshift($label, $e->name.' · '.$e->employee_number);
                }
            }
            $results[] = ['value' => $row->id, 'label' => implode(' · ', $label).' (#'.$row->id.')', 'meta' => $meta($row)];
            if (count($results) >= 50) {
                break;
            }
        }

        return ['data' => $results, 'next' => ($rows->count() > 200 || count($results) === 50) ? $cursor : null];
    }

    public static function importAllowed(User $u, string $kind): bool
    {
        return Access::companyPermission($u, match ($kind) {
            'employees' => 'employees.manage', 'branches', 'departments', 'jobs' => 'organization.manage', 'rosters' => 'schedules.manage', 'leave_credits' => 'leave.manage', 'compensation', 'opening_payroll' => 'payroll.prepare', default => '__none',
        });
    }
}
