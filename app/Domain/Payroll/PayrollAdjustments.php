<?php

namespace App\Domain\Payroll;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\Employee;
use App\Models\EmployeePrivateProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayrollAdjustments
{
    public function create(User $actor, string $key, array $input): array
    {
        $d = Validator::make($input, ['source_key' => ['required', 'string', 'max:100'], 'source_payroll_row_id' => ['required', 'integer'], 'attendance_adjustment_id' => ['nullable', 'integer'], 'payroll_batch_id' => ['required', 'integer'], 'amount_minor' => ['required', 'integer', 'between:-1000000000000000,1000000000000000', 'not_in:0'], 'reason' => ['required', 'string', 'max:4000']])->validate();

        return CoreWrite::run(fn () => Mutation::run($actor, 'payroll.adjustment.create', $key, $d, fn ($u) => PayrollService::authorize($u), function ($u) use ($d) {
            $source = DB::table('payroll_rows')->where('id', $d['source_payroll_row_id'])->firstOrFail();
            $old = DB::table('payroll_batches')->where('id', $source->payroll_batch_id)->where('status', 'finalized')->firstOrFail();
            $target = DB::table('payroll_batches')->where('id', $d['payroll_batch_id'])->where('status', 'draft')->firstOrFail();
            $oldPeriod = DB::table('attendance_periods')->find($old->attendance_period_id);
            $newPeriod = DB::table('attendance_periods')->find($target->attendance_period_id);
            abort_unless($oldPeriod->branch_id === $newPeriod->branch_id, 422);
            abort_unless($target->kind === 'off_cycle' ? $oldPeriod->id === $newPeriod->id : $newPeriod->starts_on >= $oldPeriod->ends_on, 422, 'Koreksi masuk periode berikutnya atau off-cycle untuk periode sumber.');
            if (! empty($d['attendance_adjustment_id'])) {
                $a = DB::table('attendance_adjustments')->where('id', $d['attendance_adjustment_id'])->firstOrFail();
                abort_unless($a->employee_id === $source->employee_id && $a->attendance_period_id === $oldPeriod->id, 422);
            }
            $id = DB::table('payroll_adjustments')->insertGetId(array_replace($d, ['employee_id' => $source->employee_id, 'amount_minor' => encrypt((int) $d['amount_minor']), 'reason' => encrypt($d['reason']), 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]));
            Audit::record($u->id, 'payroll.adjustment_proposed', 'payroll_adjustment', $id, ['source_row_id' => $source->id, 'target_batch_id' => $target->id]);

            return ['id' => $id, 'version' => 1, 'status' => 'pending'];
        }));
    }

    public function decide(User $actor, int $id, int $version, string $key, array $input): array
    {
        $d = Validator::make($input, ['decision' => ['required', 'in:approve,reject,cancel'], 'evidence' => ['required', 'string', 'max:4000']])->validate();

        return Mutation::run($actor, 'payroll.adjustment.decide', $key, compact('id', 'version', 'd'), fn ($u) => PayrollService::authorize($u, true), function ($u) use ($id, $version, $d) {
            $a = DB::table('payroll_adjustments')->where('id', $id)->firstOrFail();
            CoreWrite::version($a->version, $version);
            abort_unless(DB::table('payroll_batches')->where('id', $a->payroll_batch_id)->where('status', 'draft')->exists(), 409, 'Kembalikan batch ke draft sebelum keputusan adjustment.');
            abort_if($u->id === $a->created_by || $u->employee_id === $a->employee_id, 403);
            abort_unless($d['decision'] === 'cancel' ? in_array($a->status, ['pending', 'approved']) : $a->status === 'pending', 409);
            $status = ['approve' => 'approved', 'reject' => 'rejected', 'cancel' => 'cancelled'][$d['decision']];
            DB::table('payroll_adjustments')->where('id', $id)->update(['status' => $status, 'version' => $version + 1, 'approved_by' => $u->id, 'approved_at' => now(), 'review_evidence' => encrypt($d['evidence']), 'updated_at' => now()]);
            Audit::record($u->id, 'payroll.adjustment_'.$status, 'payroll_adjustment', $id, ['evidence_provided' => true]);

            return ['id' => $id, 'version' => $version + 1, 'status' => $status];
        });
    }

    public function offCycle(object $batch, object $period, object $policy, array $blockers): array
    {
        $rows = [];
        foreach (DB::table('payroll_adjustments')->where('payroll_batch_id', $batch->id)->where('status', 'approved')->get()->groupBy('employee_id') as $employeeId => $adjustments) {
            $person = Employee::findOrFail($employeeId);
            $bank = EmployeePrivateProfile::where('employee_id', $employeeId)->where('category', 'bank')->first();
            if (! $bank) {
                $blockers[] = ['code' => 'bank_unverified', 'employee_id' => $employeeId];
            }
            $lines = $adjustments->map(fn ($a) => self::line($a))->all();
            $source = decrypt(DB::table('payroll_rows')->where('id', $adjustments->first()->source_payroll_row_id)->value('snapshot'));
            $net = IntegerMoney::sum(0, ...array_column($lines, 'amount_minor'));
            if ($net < 0) {
                $blockers[] = ['code' => 'negative_net', 'employee_id' => $employeeId];
            }
            $rows[] = ['employee_id' => $person->id, 'employee_number' => $person->employee_number, 'employee_name' => $person->name, 'currency' => 'IDR', 'minor_unit' => 'sen', 'period' => [$period->starts_on, $period->ends_on], 'policy_id' => $policy->id, 'policy_version' => $policy->version, 'attendance_revision' => $period->revision, 'bank' => $bank?->data, 'bank_version' => $bank?->version, 'lines' => $lines, 'context' => ['base' => 0], 'gross_minor' => 0, 'deduction_minor' => 0, 'net_minor' => $net, 'reimbursements' => [], 'kind' => 'off_cycle'];
            $rows[array_key_last($rows)]['placements'] = $source['placements'] ?? [];
            $rows[array_key_last($rows)]['reference_work_date'] = $source['reference_work_date'] ?? $source['lines'][0]['source'][0]['work_date'] ?? $period->starts_on;
        }
        if (! $rows) {
            $blockers[] = ['code' => 'no_approved_adjustments'];
        }
        if (DB::table('payroll_adjustments')->where('payroll_batch_id', $batch->id)->where('status', 'pending')->exists()) {
            $blockers[] = ['code' => 'adjustment_pending'];
        }

        return ['rows' => $rows, 'blockers' => $blockers, 'warnings' => [], 'checksum' => hash('sha256', json_encode([$period, $policy, $rows, $blockers])), 'total_minor' => IntegerMoney::sum(0, ...array_column($rows, 'net_minor'))];
    }

    public static function line(object $a): array
    {
        return ['code' => 'ADJUSTMENT_'.$a->id, 'kind' => 'net_adjustment', 'amount_minor' => (int) decrypt($a->amount_minor), 'method' => 'finance_verified_net_correction', 'source_payroll_row_id' => $a->source_payroll_row_id, 'attendance_adjustment_id' => $a->attendance_adjustment_id, 'adjustment_id' => $a->id, 'version' => $a->version, 'approved_by' => $a->approved_by];
    }
}
