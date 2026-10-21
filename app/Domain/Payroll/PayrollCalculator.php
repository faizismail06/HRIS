<?php

namespace App\Domain\Payroll;

use App\Models\Employee;
use App\Models\EmployeePrivateProfile;
use App\Models\WorkflowRequest;
use Brick\Math\BigRational;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PayrollCalculator
{
    public function collect(object $batch): array
    {
        $period = DB::table('attendance_periods')->where('id', $batch->attendance_period_id)->firstOrFail();
        $policy = DB::table('payroll_policies')->where('id', $batch->payroll_policy_id)->firstOrFail();
        $rules = json_decode($policy->rules, true);
        $blockers = [];
        if ($period->status !== 'closed') {
            $blockers[] = ['code' => 'attendance_not_closed'];
        }
        if ($policy->status !== 'approved') {
            $blockers[] = ['code' => 'policy_not_expert_approved'];
        }
        if ($policy->valid_from > $period->starts_on || $policy->valid_until < $period->ends_on) {
            $blockers[] = ['code' => 'policy_dates_do_not_cover_period'];
        }
        if (substr($period->starts_on, 0, 4) !== CarbonImmutable::parse($period->ends_on)->subDay()->format('Y') && collect($rules['components'])->contains(fn ($c) => in_array($c['basis'], ['prior_gross', 'prior_tax_paid', 'annual_gross']) || ! empty($c['subtract_prior_tax']))) {
            $blockers[] = ['code' => 'split_tax_year_required'];
        }
        if (($batch->kind ?? 'regular') === 'off_cycle') {
            return app(PayrollAdjustments::class)->offCycle($batch, $period, $policy, $blockers);
        }
        $warnings = [];
        $adjustments = DB::table('payroll_adjustments')->where('payroll_batch_id', $batch->id)->orderBy('id')->get();
        if ($adjustments->contains(fn ($a) => $a->status === 'pending')) {
            $blockers[] = ['code' => 'adjustment_pending'];
        }
        $attendance = DB::table('attendance_snapshots')->where('attendance_period_id', $period->id)->where('revision', $period->revision)->orderBy('id')->get();
        $employees = Employee::whereHas('assignments', fn ($q) => $q->whereNull('superseded_at')->where('branch_id', $period->branch_id)->where('valid_from', '<', $period->ends_on)->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $period->starts_on)))->orderBy('id')->get();
        abort_if($employees->count() > 500, 422, 'Batch sinkron dibatasi500karyawan; gunakan periode/cabang lebih kecil atau worker khusus.');
        $start = CarbonImmutable::parse($period->starts_on);
        $end = CarbonImmutable::parse($period->ends_on);
        $days = (int) $start->diffInDays($end);
        $rows = [];
        $sourceEmployees = [];
        foreach ($employees as $employee) {
            $compensations = DB::table('employee_compensations')->where('employee_id', $employee->id)->where('valid_from', '<', $period->ends_on)->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', $period->starts_on))->orderBy('id')->get();
            $assignments = $employee->assignments()->whereNull('superseded_at')->orderBy('id')->get();
            $employment = $employee->periods()->orderBy('id')->get();
            $bank = EmployeePrivateProfile::where('employee_id', $employee->id)->where('category', 'bank')->first();
            $tax = EmployeePrivateProfile::where('employee_id', $employee->id)->where('category', 'tax')->first();
            $base = BigRational::of(0);
            $daily = [];
            $activeDays = 0;
            for ($day = $start; $day < $end; $day = $day->addDay()) {
                $date = $day->toDateString();
                $placed = $assignments->contains(fn ($a) => $a->branch_id === $period->branch_id && $a->valid_from->toDateString() <= $date && (! $a->valid_to || $a->valid_to->toDateString() > $date));
                $employed = $employment->contains(fn ($e) => $e->activation_state === 'active' && $e->starts_on->toDateString() <= $date && (! $e->ends_on || $e->ends_on->toDateString() > $date));
                if (! $placed || ! $employed) {
                    continue;
                }
                $activeDays++;
                $comp = $compensations->first(fn ($c) => $c->valid_from <= $date && (! $c->valid_until || $c->valid_until > $date));
                if (! $comp) {
                    $blockers[] = ['code' => 'compensation_gap', 'employee_id' => $employee->id, 'work_date' => $date];

                    continue;
                }
                $amount = (int) decrypt($comp->monthly_base_minor);
                $base = $base->plus(BigRational::of($amount)->dividedBy($days));
                $daily[] = ['work_date' => $date, 'compensation_id' => $comp->id, 'monthly_base_minor' => $amount, 'denominator_days' => $days];
            }
            if (! $activeDays) {
                continue;
            }
            if (! $bank) {
                $blockers[] = ['code' => 'bank_unverified', 'employee_id' => $employee->id];
            }
            if ($rules['requires_tax_profile'] && ! $tax) {
                $blockers[] = ['code' => 'tax_profile_unverified', 'employee_id' => $employee->id];
            }
            $employeeAttendance = $attendance->filter(fn ($a) => json_decode($a->snapshot, true)['employee_id'] === $employee->id)->map(fn ($a) => json_decode($a->snapshot, true));
            $context = ['base' => IntegerMoney::rounded($base, $rules['rounding']), 'gross' => 0, 'overtime_minutes' => 0, 'late_minutes' => 0, 'unpaid_leave_minutes' => 0, 'absent_shifts' => 0];
            foreach ($employeeAttendance as $shift) {
                $context['overtime_minutes'] += array_sum(array_column($shift['overtime'], 'payable_minutes'));
                $context['late_minutes'] += (int) $shift['result']['late_minutes'];
                $context['absent_shifts'] += $shift['result']['status'] === 'ABSENT' ? 1 : 0;
                // Leave policy is frozen on the workflow payload's type ID; never infer unpaid from absence.
                $sources = is_string($shift['result']['sources']) ? json_decode($shift['result']['sources'], true) : $shift['result']['sources'];
                foreach ($sources['leave_segment_ids'] ?? [] as $leaveId) {
                    $segment = DB::table('leave_segments')->where('id', $leaveId)->first();
                    if (! $segment) {
                        continue;
                    }
                    $request = WorkflowRequest::find($segment->workflow_request_id);
                    $type = $request ? DB::table('leave_types')->find($request->payload['leave_type_id']) : null;
                    if ($type && ! $type->is_paid) {
                        $context['unpaid_leave_minutes'] += $segment->minutes;
                    }
                }
            }
            $context['gross'] = $context['base'];
            $history = app(PayrollHistory::class)->context($employee->id, (int) substr($period->starts_on, 0, 4), $period->starts_on);
            $blockers = array_merge($blockers, $history['blockers']);
            $context['prior_gross'] = $history['prior_gross'];
            $context['prior_tax_paid'] = $history['prior_tax_paid'];
            $context['annual_gross'] = IntegerMoney::sum($context['gross'], $context['prior_gross']);
            $context['tax_paid_minor'] = 0;
            $lines = [['code' => 'BASE', 'kind' => 'earning', 'amount_minor' => $context['base'], 'method' => 'calendar_days', 'source' => $daily, 'rounding' => $rules['rounding']]];
            $deductions = 0;
            foreach ($rules['components'] as $component) {
                if ((! empty($component['subtract_prior_tax']) || $component['basis'] === 'prior_tax_paid') && ! $history['tax_classification_complete']) {
                    $blockers[] = ['code' => 'historical_tax_classification_missing', 'employee_id' => $employee->id];
                }
                if (! empty($component['tax_statuses']) && ! in_array($tax?->data['tax_status'] ?? null, $component['tax_statuses'], true)) {
                    continue;
                }
                $basis = $context[$component['basis']];
                $value = BigRational::of(0);
                if ($component['method'] === 'fixed') {
                    $value = BigRational::of($component['amount_minor']);
                }
                if ($component['method'] === 'ratio') {
                    $value = BigRational::of($basis)->multipliedBy($component['numerator'])->dividedBy($component['denominator']);
                }
                if ($component['method'] === 'brackets') {
                    $lower = 0;
                    foreach ($component['brackets'] as $bracket) {
                        $upper = $bracket['up_to'] ?? $basis;
                        $taxable = max(0, min($basis, $upper) - $lower);
                        $value = $value->plus(BigRational::of($taxable)->multipliedBy($bracket['numerator'])->dividedBy($bracket['denominator']));
                        $lower = $upper;
                        if ($upper >= $basis) {
                            break;
                        }
                    }
                }
                $amount = IntegerMoney::rounded($value, $rules['rounding']);
                if (! empty($component['subtract_prior_tax'])) {
                    $amount = max(0, $amount - $history['prior_tax_paid']);
                }
                if (isset($component['cap_minor'])) {
                    $amount = min($amount, $component['cap_minor']);
                }
                $lines[] = ['code' => $component['code'], 'label' => $component['label'], 'kind' => $component['kind'], 'amount_minor' => $amount, 'basis_value' => $basis, 'rule' => $component, 'rounding' => $rules['rounding']];
                if ($component['kind'] === 'earning') {
                    $context['gross'] = IntegerMoney::sum($context['gross'], $amount);
                    $context['annual_gross'] = IntegerMoney::sum($context['gross'], $context['prior_gross']);
                } else {
                    $deductions = IntegerMoney::sum($deductions, $amount);
                    if (! empty($component['tax_component'])) {
                        $context['tax_paid_minor'] = IntegerMoney::sum($context['tax_paid_minor'], $amount);
                    }
                }
            }
            $net = $context['gross'] - $deductions;
            foreach ($adjustments->where('employee_id', $employee->id)->where('status', 'approved') as $adjustment) {
                $line = PayrollAdjustments::line($adjustment);
                $lines[] = $line;
                $net = IntegerMoney::sum($net, $line['amount_minor']);
            }
            if ($net < 0) {
                $blockers[] = ['code' => 'negative_net', 'employee_id' => $employee->id];
            }
            $reimbursements = DB::table('expense_allocations')->join('expense_claims', 'expense_claims.id', '=', 'expense_allocations.expense_claim_id')->where('payroll_batch_id', $batch->id)->where('expense_claims.employee_id', $employee->id)->orderBy('expense_allocations.id')->get(['expense_allocations.id as allocation_id', 'expense_claims.id as claim_id', 'expense_claims.amount_minor', 'expense_claims.status'])->map(fn ($c) => (array) $c)->all();
            foreach ($reimbursements as $claim) {
                if ($claim['status'] !== 'approved_for_payment') {
                    $blockers[] = ['code' => 'claim_not_payable', 'claim_id' => $claim['claim_id']];
                }
                $lines[] = ['code' => 'CLAIM_'.$claim['claim_id'], 'kind' => 'reimbursement', 'amount_minor' => $claim['amount_minor'], 'allocation_id' => $claim['allocation_id'], 'method' => 'approved_claim'];
                $net = IntegerMoney::sum($net, $claim['amount_minor']);
            }
            $rows[] = ['employee_id' => $employee->id, 'employee_number' => $employee->employee_number, 'employee_name' => $employee->name, 'currency' => 'IDR', 'minor_unit' => 'sen', 'period' => [$period->starts_on, $period->ends_on], 'policy_id' => $policy->id, 'policy_version' => $policy->version, 'attendance_revision' => $period->revision, 'attendance_hashes' => $attendance->filter(fn ($a) => json_decode($a->snapshot, true)['employee_id'] === $employee->id)->pluck('sha256')->all(), 'bank' => $bank?->data, 'bank_version' => $bank?->version, 'tax_version' => $tax?->version, 'lines' => $lines, 'context' => $context, 'gross_minor' => $context['gross'], 'deduction_minor' => $deductions, 'net_minor' => $net];
            $rows[array_key_last($rows)]['reimbursements'] = $reimbursements;
            $previous = DB::table('payroll_final_snapshots')->join('payroll_rows', 'payroll_rows.id', '=', 'payroll_final_snapshots.payroll_row_id')->join('payroll_batches', 'payroll_batches.id', '=', 'payroll_rows.payroll_batch_id')->join('attendance_periods', 'attendance_periods.id', '=', 'payroll_batches.attendance_period_id')->where('payroll_rows.employee_id', $employee->id)->where('payroll_batches.kind', 'regular')->where('attendance_periods.ends_on', '<=', $period->starts_on)->orderByDesc('attendance_periods.ends_on')->orderByDesc('payroll_final_snapshots.id')->first(['payroll_final_snapshots.snapshot', 'payroll_final_snapshots.sha256', 'payroll_rows.id']);
            if ($previous) {
                $oldNet = decrypt($previous->snapshot)['net_minor'];
                if (($oldNet === 0 && $net !== 0) || ($oldNet > 0 && abs($net - $oldNet) * 100 > $oldNet * $rules['variation_warning_percent'])) {
                    $warnings[] = ['code' => 'material_net_variation', 'employee_id' => $employee->id, 'previous_row_id' => $previous->id, 'threshold_percent' => $rules['variation_warning_percent']];
                }
            }
            $sourceEmployees[] = ['id' => $employee->id, 'version' => $employee->version, 'compensation' => $compensations->all(), 'employment' => $employment->toArray(), 'assignments' => $assignments->toArray(), 'bank_version' => $bank?->version, 'tax_version' => $tax?->version, 'reimbursements' => $reimbursements, 'history' => $history];
        }
        if (! $rows) {
            $blockers[] = ['code' => 'no_employees'];
        }
        $included = collect($rows)->flatMap(fn ($row) => array_column($row['reimbursements'], 'allocation_id'))->all();
        foreach (DB::table('expense_allocations')->where('payroll_batch_id', $batch->id)->whereNotIn('id', $included)->pluck('expense_claim_id') as $claimId) {
            $blockers[] = ['code' => 'claim_employee_missing_from_payroll', 'claim_id' => $claimId];
        }
        foreach ($adjustments->where('status', 'approved') as $a) {
            if (! in_array($a->employee_id, array_column($rows, 'employee_id'), true)) {
                $blockers[] = ['code' => 'adjustment_employee_missing', 'adjustment_id' => $a->id];
            }
        }
        $checksum = hash('sha256', json_encode(['period' => $period, 'policy' => $policy, 'attendance' => $attendance->pluck('sha256')->all(), 'employees' => $sourceEmployees, 'adjustments' => $adjustments->all(), 'warnings' => $warnings], JSON_THROW_ON_ERROR));

        return ['rows' => $rows, 'blockers' => $blockers, 'warnings' => $warnings, 'checksum' => $checksum, 'total_minor' => IntegerMoney::sum(0, ...array_column($rows, 'net_minor'))];
    }
}
