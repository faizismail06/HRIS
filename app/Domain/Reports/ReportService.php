<?php

namespace App\Domain\Reports;

use App\Domain\Attendance\AttendanceService;
use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Demo\DemoBoundary;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Leave\LotAccounting;
use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportService
{
    public const KINDS = ['attendance', 'overtime', 'leave_balances', 'contracts', 'lifecycle', 'assets', 'payroll', 'expenses'];

    public function authorize(User $u, string $kind): void
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $allowed = match ($kind) {
            'payroll' => Access::companyPermission($u, 'payroll.prepare') || Access::companyPermission($u, 'payroll.approve'),
            'expenses' => Access::companyPermission($u, 'payroll.approve'),
            'assets' => Access::companyPermission($u, 'assets.manage'),
            'contracts', 'lifecycle' => $u->grants()->whereIn('role', ['hr_admin', 'hr_officer'])->exists(),
            'leave_balances' => $u->grants()->whereIn('role', ['hr_admin', 'hr_officer', 'employee'])->exists(),
            default => $u->grants()->whereIn('role', Access::EMPLOYEE_READERS)->exists(),
        };
        abort_unless($allowed, 403);
    }

    public function filters(array $input): array
    {
        $d = Validator::make($input, ['starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'], 'branch_id' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer'], 'employee_id' => ['nullable', 'integer'], 'employment_status' => ['nullable', 'in:PREBOARDING,ACTIVE,OFFBOARDING,ENDED']])->validate();
        abort_if(CarbonImmutable::parse($d['starts_on'])->diffInDays(CarbonImmutable::parse($d['ends_on'])) > 62, 422, 'Laporan maksimal62hari per batch.');
        foreach (['employee_id', 'branch_id', 'department_id'] as $field) {
            if (isset($d[$field])) {
                $d[$field] = (int) $d[$field];
            }
        }

        return $d;
    }

    public function visible(User $u, int $employee, string $date, array $filters, ?array $roles = null): bool
    {
        $q = EmployeeVisibility::query($u, $date, $roles ?? Access::EMPLOYEE_READERS)->whereKey($employee);
        if (! empty($filters['employee_id']) && $employee !== $filters['employee_id']) {
            return false;
        }
        foreach (['branch_id', 'department_id'] as $field) {
            if (! empty($filters[$field])) {
                $q->whereHas('assignments', fn ($a) => $a->at($date)->where($field, $filters[$field]));
            }
        }
        if (! empty($filters['employment_status']) && Employee::findOrFail($employee)->statusAt($date) !== $filters['employment_status']) {
            return false;
        }

        return $q->exists();
    }

    public function build(User $u, string $kind, array $input): array
    {
        $this->authorize($u, $kind);
        $f = $this->filters($input);
        $rows = [];
        if (in_array($kind, ['attendance', 'overtime'])) {
            $shifts = RosterShift::where('status', 'published')->where('work_date', '>=', $f['starts_on'])->where('work_date', '<', $f['ends_on'])->orderBy('id')->get();
            foreach ($shifts as $shift) {
                if (! $this->visible($u, $shift->employee_id, $shift->work_date, $f)) {
                    continue;
                }
                $employee = Employee::findOrFail($shift->employee_id);
                $period = DB::table('attendance_periods')->where('branch_id', $shift->snapshot['branch_id'])->where('starts_on', '<=', $shift->work_date)->where('ends_on', '>', $shift->work_date)->where('status', 'closed')->first();
                $closed = $period ? DB::table('attendance_snapshots')->where('attendance_period_id', $period->id)->where('revision', $period->revision)->where('roster_shift_id', $shift->id)->first() : null;
                $snapshot = $closed ? json_decode($closed->snapshot, true) : null;
                if ($kind === 'attendance') {
                    $result = $snapshot ? $snapshot['result'] : (array) app(AttendanceService::class)->recalculate($shift);
                    $present = ! empty($result['effective_in']);
                    $rows[] = ['record_id' => $shift->id, 'employee_id' => $employee->id, 'employee_number' => $snapshot['employee_number'] ?? $employee->employee_number, 'employee_name' => $snapshot['employee_name'] ?? $employee->name, 'work_date' => $shift->work_date, 'status' => $result['status'], 'present' => $present, 'late' => $present && $result['late_minutes'] > 0, 'early' => $present && $result['early_minutes'] > 0, 'work_minutes' => $result['work_minutes'], 'late_minutes' => $result['late_minutes'], 'early_minutes' => $result['early_minutes'], 'source' => $closed ? 'closed_snapshot' : 'live_projection', 'snapshot_id' => $closed?->id, 'revision' => $period?->revision, 'source_path' => $closed ? '/app/api/attendance-snapshots/'.$closed->id : '/app/api/attendance/'.$shift->id];
                } else {
                    $overtime = $snapshot ? $snapshot['overtime'] : DB::table('overtime_authorizations')->where('roster_shift_id', $shift->id)->get()->map(fn ($r) => (array) $r)->all();
                    foreach ($overtime as $o) {
                        $rows[] = ['record_id' => $o['id'], 'employee_id' => $employee->id, 'employee_number' => $employee->employee_number, 'work_date' => $shift->work_date, 'status' => $closed ? 'verified' : $o['status'], 'payable_minutes' => $o['payable_minutes'], 'attendance_version' => $o['attendance_version'], 'source' => $closed ? 'closed_snapshot' : 'live_authorization'];
                    }
                }
            }
        } elseif ($kind === 'leave_balances') {
            $date = CarbonImmutable::parse($f['ends_on'])->subDay()->toDateString();
            foreach (DB::table('leave_credit_lots')->where('valid_from', '<=', $date)->where('created_at', '<', $f['ends_on'])->orderBy('id')->get() as $lot) {
                if (! $this->visible($u, $lot->employee_id, $date, $f, ['employee', 'hr_admin', 'hr_officer'])) {
                    continue;
                }
                $ledger = DB::table('leave_ledger')->where('leave_credit_lot_id', $lot->id)->where('created_at', '<', $f['ends_on']);
                $reserved = max(0, -(int) (clone $ledger)->whereIn('kind', ['reservation', 'release'])->sum('minutes'));
                $used = max(0, -(int) (clone $ledger)->whereIn('kind', ['usage', 'reversal'])->sum('minutes'));
                $capacity = LotAccounting::capacity($lot, $f['ends_on']);
                $rows[] = ['record_id' => $lot->id, 'employee_id' => $lot->employee_id, 'work_date' => $date, 'type_code' => $lot->type_code, 'year' => $lot->entitlement_year, 'credited_minutes' => $lot->minutes, 'adjusted_credit_minutes' => $capacity, 'reserved_minutes' => $reserved, 'used_minutes' => $used, 'available_minutes' => $lot->valid_until > $date ? max(0, $capacity - $reserved - $used) : 0];
            }
        } elseif ($kind === 'contracts') {
            $date = CarbonImmutable::parse($f['ends_on'])->subDay()->toDateString();
            foreach (DB::table('employment_contracts')->where('starts_on', '<', $f['ends_on'])->where('ends_on', '>', $f['starts_on'])->orderBy('id')->get() as $c) {
                if (! $this->visible($u, $c->employee_id, $date, $f, ['hr_admin', 'hr_officer'])) {
                    continue;
                }
                $rows[] = ['record_id' => $c->id, 'employee_id' => $c->employee_id, 'work_date' => $date, 'contract_type' => $c->contract_type, 'starts_on' => $c->starts_on, 'ends_on' => $c->ends_on, 'state' => $c->status === 'approved' ? ($c->starts_on > $date ? 'SCHEDULED' : ($c->ends_on <= $date ? 'EXPIRED' : 'ACTIVE')) : strtoupper($c->status)];
            }
        } elseif ($kind === 'lifecycle') {
            foreach (DB::table('lifecycle_cases')->where('effective_on', '>=', $f['starts_on'])->where('effective_on', '<', $f['ends_on'])->orderBy('id')->get() as $c) {
                if (! $this->visible($u, $c->employee_id, $c->last_working_on ?? $c->effective_on, $f, ['hr_admin', 'hr_officer'])) {
                    continue;
                }
                $rows[] = ['record_id' => $c->id, 'employee_id' => $c->employee_id, 'work_date' => $c->effective_on, 'kind' => $c->kind, 'status' => $c->status, 'open_tasks' => DB::table('lifecycle_tasks')->where('lifecycle_case_id', $c->id)->where('status', 'open')->count()];
            }
        } elseif ($kind === 'assets') {
            foreach (DB::table('asset_custodies')->join('assets', 'assets.id', '=', 'asset_custodies.asset_id')->where('issued_at', '<', $f['ends_on'])->where(fn ($q) => $q->whereNull('returned_at')->orWhere('returned_at', '>=', $f['starts_on']))->orderBy('asset_custodies.id')->get(['asset_custodies.*', 'assets.code', 'assets.category', 'assets.branch_id']) as $c) {
                if ((! empty($f['employee_id']) && $f['employee_id'] !== $c->employee_id) || (! empty($f['branch_id']) && $f['branch_id'] !== $c->branch_id)) {
                    continue;
                }
                $rows[] = ['record_id' => $c->id, 'employee_id' => $c->employee_id, 'work_date' => CarbonImmutable::parse($c->issued_at)->toDateString(), 'asset_code' => $c->code, 'category' => $c->category, 'issued_at' => $c->issued_at, 'returned_at' => $c->returned_at];
            }
        } elseif ($kind === 'payroll') {
            $query = DB::table('payroll_rows')->join('payroll_batches', 'payroll_batches.id', '=', 'payroll_rows.payroll_batch_id')->join('attendance_periods', 'attendance_periods.id', '=', 'payroll_batches.attendance_period_id')->where('attendance_periods.starts_on', '<', $f['ends_on'])->where('attendance_periods.ends_on', '>', $f['starts_on'])->orderBy('payroll_rows.id');
            if (! empty($f['branch_id'])) {
                $query->where('attendance_periods.branch_id', $f['branch_id']);
            }
            foreach ($query->get(['payroll_rows.id', 'payroll_rows.employee_id', 'payroll_rows.snapshot', 'payroll_batches.status', 'payroll_batches.kind', 'attendance_periods.starts_on']) as $r) {
                if (! empty($f['employee_id']) && $f['employee_id'] !== $r->employee_id) {
                    continue;
                }
                $s = decrypt($r->snapshot);
                $dates = collect($s['placements'] ?? [])->filter(fn ($p) => $p['work_date'] >= $f['starts_on'] && $p['work_date'] < $f['ends_on'] && (empty($f['department_id']) || $p['department_id'] === $f['department_id']) && (empty($f['employment_status']) || $p['employment_status'] === $f['employment_status']));
                if (! empty($s['placements']) && $dates->isEmpty()) {
                    continue;
                }
                $date = $dates->first()['work_date'] ?? max($f['starts_on'], $s['reference_work_date'] ?? $s['lines'][0]['source'][0]['work_date'] ?? $r->starts_on);
                $rows[] = ['record_id' => $r->id, 'employee_id' => $r->employee_id, 'work_date' => $date, 'employee_number' => $s['employee_number'], 'status' => $r->status, 'batch_kind' => $r->kind, 'gross_minor' => $s['gross_minor'], 'deduction_minor' => $s['deduction_minor'], 'net_minor' => $s['net_minor'], 'paid_minor' => (int) DB::table('payroll_payment_outcomes')->where('payroll_row_id', $r->id)->where('status', 'paid')->sum('amount_minor')];
            }
        } else {
            foreach (DB::table('expense_claims')->where('incurred_on', '>=', $f['starts_on'])->where('incurred_on', '<', $f['ends_on'])->orderBy('id')->get() as $c) {
                if (! empty($f['employee_id']) && $f['employee_id'] !== $c->employee_id) {
                    continue;
                }
                $rows[] = ['record_id' => $c->id, 'employee_id' => $c->employee_id, 'work_date' => $c->incurred_on, 'cost_center_id' => $c->cost_center_id, 'amount_minor' => $c->amount_minor, 'status' => $c->status, 'duplicate_warning' => $c->duplicate_warning];
            }
        }
        abort_if(count($rows) > 5000, 422, 'Hasil melebihi5000baris; persempit filter.');
        if (in_array($kind, ['payroll', 'expenses', 'assets'])) {
            $rows = array_values(array_filter($rows, function ($row) use ($f, $kind) {
                $person = Employee::findOrFail($row['employee_id']);
                if (! empty($f['employment_status']) && $person->statusAt($row['work_date']) !== $f['employment_status']) {
                    return false;
                }
                $q = $person->assignments()->at($row['work_date']);
                if (! empty($f['department_id'])) {
                    if ($kind === 'expenses' && $row['cost_center_id'] !== $f['department_id']) {
                        return false;
                    }
                    $q->where('department_id', $f['department_id']);
                }
                if (! empty($f['branch_id']) && $kind === 'expenses') {
                    $q->where('branch_id', $f['branch_id']);
                }

                return empty($f['department_id']) && (empty($f['branch_id']) || $kind !== 'expenses') ? true : $q->exists();
            }));
        }
        $totals = $kind === 'attendance' ? ['shifts' => count($rows), 'present' => count(array_filter($rows, fn ($r) => $r['present'])), 'late' => count(array_filter($rows, fn ($r) => $r['late'])), 'early' => count(array_filter($rows, fn ($r) => $r['early'])), 'incomplete' => count(array_filter($rows, fn ($r) => $r['status'] === 'INCOMPLETE')), 'absent' => count(array_filter($rows, fn ($r) => $r['status'] === 'ABSENT'))] : ['rows' => count($rows)];
        Audit::record($u->id, 'report.read', 'report', null, ['kind' => $kind, 'starts_on' => $f['starts_on'], 'ends_on' => $f['ends_on'], 'rows' => count($rows)]);

        return ['kind' => $kind, 'filters' => $f, 'definitions' => ['period' => '[starts_on,ends_on)', 'presence' => 'Shift with effective_in from validated clock or approved correction; incomplete/conflict remain flagged independently. Late and early are subsets.', 'closed' => 'Closed attendance and overtime use the frozen revision; open data may change', 'currency' => 'IDR minor units = sen'], 'watermark' => DemoBoundary::enabled() ? 'DEMO — DATA SINTETIS / '.config('hris.demo_scenario_id') : null, 'totals' => $totals, 'data' => $rows];
    }
}
