<?php

namespace App\Domain\Expenses;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Payroll\PayrollService;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ExpenseService
{
    public function policy(User $actor, string $key, array $input): array
    {
        PayrollService::authorize($actor, true);
        $d = Validator::make($input, ['category' => ['required', 'regex:/^[A-Z0-9_-]{2,40}$/'], 'valid_from' => ['required', 'date_format:Y-m-d'], 'valid_until' => ['required', 'date_format:Y-m-d', 'after:valid_from'], 'max_claim_minor' => ['required', 'integer', 'between:1,1000000000000000'], 'max_month_minor' => ['required', 'integer', 'gte:max_claim_minor', 'max:1000000000000000'], 'minimum_tenure_days' => ['required', 'integer', 'between:0,36500'], 'job_ids' => ['present', 'array', 'max:100'], 'job_ids.*' => ['integer', 'exists:job_positions,id', 'distinct']])->validate();

        return Mutation::run($actor, 'expense.policy', $key, $d, fn ($u) => PayrollService::authorize($u, true), function ($u) use ($d) {
            $version = (int) DB::table('expense_policies')->where('category', $d['category'])->max('version') + 1;
            $id = DB::table('expense_policies')->insertGetId(array_replace($d, ['job_ids' => json_encode($d['job_ids']), 'version' => $version, 'approved_by' => $u->id, 'created_at' => now()]));
            Audit::record($u->id, 'expense.policy_versioned', 'expense_policy', $id, ['version' => $version]);

            return ['id' => $id, 'version' => $version];
        });
    }

    public function allocate(User $actor, int $claimId, string $key, array $input): array
    {
        $d = Validator::make($input, ['channel' => ['required', Rule::in(['payroll', 'reimbursement'])], 'payroll_batch_id' => ['required_if:channel,payroll', 'nullable', 'integer']])->validate();

        return CoreWrite::run(fn () => Mutation::run($actor, 'expense.allocate', $key, compact('claimId', 'd'), fn ($u) => PayrollService::authorize($u, true), function ($u) use ($claimId, $d) {
            $claim = DB::table('expense_claims')->where('id', $claimId)->firstOrFail();
            abort_unless($claim->status === 'approved_for_payment', 409);
            abort_if(DB::table('expense_allocations')->where('expense_claim_id', $claimId)->exists(), 409, 'Claim sudah memilih kanal settlement.');
            if ($d['channel'] === 'payroll') {
                $batch = DB::table('payroll_batches')->where('id', $d['payroll_batch_id'])->firstOrFail();
                abort_unless($batch->status === 'draft', 409, 'Tambahkan claim sebelum kalkulasi atau setelah batch dikembalikan ke draft.');
                $period = DB::table('attendance_periods')->where('id', $batch->attendance_period_id)->firstOrFail();
                abort_unless(Employee::findOrFail($claim->employee_id)->assignments()->where('branch_id', $period->branch_id)->where('valid_from', '<', $period->ends_on)->whereNull('superseded_at')->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $period->starts_on))->exists(), 422, 'Claim di luar scope payroll batch.');
            } else {
                abort_if(! empty($d['payroll_batch_id']), 422);
            }
            $id = DB::table('expense_allocations')->insertGetId(['expense_claim_id' => $claimId, 'channel' => $d['channel'], 'payroll_batch_id' => $d['payroll_batch_id'] ?? null, 'allocated_by' => $u->id, 'created_at' => now()]);
            Audit::record($u->id, 'expense.channel_allocated', 'expense_allocation', $id, ['channel' => $d['channel'], 'claim_id' => $claimId]);

            return ['id' => $id, 'channel' => $d['channel']];
        }));
    }

    public function settle(User $actor, int $allocationId, string $key, array $input): array
    {
        PayrollService::authorize($actor, true);
        $d = Validator::make($input, ['external_event_id' => ['required', 'string', 'max:120'], 'amount_minor' => ['required', 'integer', 'between:1,1000000000000000'], 'reference' => ['required', 'string', 'max:120'], 'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']])->validate();

        return CoreWrite::run(fn () => Mutation::run($actor, 'expense.settle', $key, compact('allocationId', 'd'), fn ($u) => PayrollService::authorize($u, true), function ($u) use ($allocationId, $d) {
            $allocation = DB::table('expense_allocations')->where('id', $allocationId)->firstOrFail();
            abort_unless($allocation->channel === 'reimbursement', 409, 'Claim payroll diselesaikan lewat outcome payroll, bukan kanal terpisah.');
            $existing = DB::table('expense_settlements')->where('external_event_id', $d['external_event_id'])->first();
            if ($existing) {
                abort_unless($existing->expense_allocation_id === $allocationId, 409);
                foreach ($d as $field => $value) {
                    abort_unless($existing->{$field} == $value, 409);
                }

                return ['id' => $existing->id];
            }
            $claim = DB::table('expense_claims')->where('id', $allocation->expense_claim_id)->firstOrFail();
            $paid = (int) DB::table('expense_settlements')->where('expense_allocation_id', $allocationId)->sum('amount_minor');
            abort_if($paid + $d['amount_minor'] > $claim->amount_minor, 422, 'Settlement melebihi sisa claim.');
            $id = $this->record($allocationId, $u->id, $d);
            Audit::record($u->id, 'expense.payment_recorded', 'expense_settlement', $id, ['claim_id' => $claim->id]);

            return ['id' => $id, 'status' => $paid + $d['amount_minor'] === $claim->amount_minor ? 'PAID' : 'PARTIAL'];
        }));
    }

    private function record(int $allocation, int $actor, array $data): int
    {
        $id = DB::table('expense_settlements')->insertGetId($data + ['expense_allocation_id' => $allocation, 'paid_by' => $actor, 'created_at' => now()]);
        $a = DB::table('expense_allocations')->where('id', $allocation)->firstOrFail();
        $claim = DB::table('expense_claims')->where('id', $a->expense_claim_id)->firstOrFail();
        $paid = (int) DB::table('expense_settlements')->where('expense_allocation_id', $allocation)->sum('amount_minor');
        if ($paid === $claim->amount_minor) {
            DB::table('expense_allocations')->where('id', $allocation)->update(['status' => 'settled']);
            DB::table('expense_claims')->where('id', $claim->id)->update(['status' => 'paid', 'updated_at' => now()]);
        }

        return $id;
    }

    public function payrollSettlement(int $outcomeId, array $snapshot, int $totalPaid, User $actor): void
    {
        $claims = $snapshot['reimbursements'] ?? [];
        $salary = $snapshot['net_minor'] - array_sum(array_column($claims, 'amount_minor'));
        $covered = max(0, $totalPaid - $salary);
        $outcome = DB::table('payroll_payment_outcomes')->where('id', $outcomeId)->firstOrFail();
        foreach ($claims as $claim) {
            $target = min($covered, $claim['amount_minor']);
            $covered -= $target;
            $paid = (int) DB::table('expense_settlements')->where('expense_allocation_id', $claim['allocation_id'])->sum('amount_minor');
            $amount = $target - $paid;
            if ($amount <= 0) {
                continue;
            }
            $id = $this->record($claim['allocation_id'], $actor->id, ['external_event_id' => 'payroll:'.$outcomeId.':'.$claim['allocation_id'], 'payroll_payment_outcome_id' => $outcomeId, 'amount_minor' => $amount, 'reference' => $outcome->reference, 'paid_on' => $outcome->paid_on]);
            Audit::record($actor->id, 'expense.payroll_settled', 'expense_settlement', $id, ['payroll_outcome_id' => $outcomeId]);
        }
    }

    public function detail(User $actor, int $id): array
    {
        $claim = DB::table('expense_claims')->where('id', $id)->firstOrFail();
        $workflow = WorkflowRequest::findOrFail($claim->workflow_request_id);
        abort_unless(WorkflowAccess::visible($actor, $workflow), 404);

        return ['id' => $id, 'employee_id' => $claim->employee_id, 'amount_minor' => $claim->amount_minor, 'currency' => 'IDR', 'status' => $claim->status, 'incurred_on' => $claim->incurred_on, 'description' => $workflow->payload['description'], 'duplicate_warning' => $claim->duplicate_warning, 'allocation' => DB::table('expense_allocations')->where('expense_claim_id', $id)->first(['id', 'channel', 'status']), 'paid_minor' => DB::table('expense_settlements')->join('expense_allocations', 'expense_allocations.id', '=', 'expense_settlements.expense_allocation_id')->where('expense_claim_id', $id)->sum('amount_minor')];
    }
}
