<?php

namespace App\Domain\Payroll;

use Illuminate\Support\Facades\DB;

class PayrollHistory
{
    public function context(int $employeeId, int $year, string $before): array
    {
        $gross = 0;
        $tax = 0;
        $sources = [];
        $blockers = [];
        $opening = DB::table('payroll_openings')->where('employee_id', $employeeId)->where('tax_year', $year)->whereIn('status', ['pending', 'approved'])->first();
        if ($opening) {
            if ($opening->status !== 'approved' || $opening->through_on >= $before) {
                $blockers[] = ['code' => $opening->status === 'pending' ? 'opening_payroll_pending' : 'opening_payroll_overlap', 'employee_id' => $employeeId];
            } else {
                $data = decrypt($opening->snapshot);
                $gross = $data['gross_minor'];
                $tax = $data['tax_paid_minor'];
            }
            $sources[] = ['opening_id' => $opening->id, 'status' => $opening->status, 'through_on' => $opening->through_on, 'hash' => hash('sha256', $opening->snapshot)];
        }
        $complete = true;
        foreach (DB::table('payroll_final_snapshots')->join('payroll_rows', 'payroll_rows.id', '=', 'payroll_final_snapshots.payroll_row_id')->join('payroll_batches', 'payroll_batches.id', '=', 'payroll_rows.payroll_batch_id')->join('attendance_periods', 'attendance_periods.id', '=', 'payroll_batches.attendance_period_id')->where('employee_id', $employeeId)->where('payroll_batches.kind', 'regular')->where('attendance_periods.starts_on', '>=', $year.'-01-01')->where('attendance_periods.ends_on', '<=', $before)->orderBy('payroll_final_snapshots.id')->get(['payroll_final_snapshots.snapshot', 'payroll_final_snapshots.sha256', 'payroll_rows.id', 'attendance_periods.starts_on']) as $row) {
            if ($opening && $row->starts_on <= $opening->through_on) {
                $blockers[] = ['code' => 'opening_final_payroll_overlap', 'employee_id' => $employeeId];

                continue;
            }
            $s = decrypt($row->snapshot);
            $gross = IntegerMoney::sum($gross, $s['gross_minor']);
            $tax = IntegerMoney::sum($tax, $s['context']['tax_paid_minor'] ?? 0);
            $complete = $complete && array_key_exists('tax_paid_minor', $s['context']);
            $sources[] = ['row_id' => $row->id, 'sha256' => $row->sha256];
        }

        return ['prior_gross' => $gross, 'prior_tax_paid' => $tax, 'tax_classification_complete' => $complete, 'sources' => $sources, 'blockers' => $blockers];
    }
}
