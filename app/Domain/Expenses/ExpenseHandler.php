<?php

namespace App\Domain\Expenses;

use App\Domain\Workflow\StageWorkflow;
use App\Domain\Workflow\WorkflowHandler;
use App\Domain\Workflow\WorkflowRouting;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpenseHandler implements StageWorkflow, WorkflowHandler, WorkflowRouting
{
    public function approverRoles(): array
    {
        return ['manager', 'finance_approver'];
    }

    public function validateSteps(array $steps): void
    {
        abort_unless(array_column($steps, 'role') === ['manager', 'finance_approver'], 422, 'Reimbursement memerlukan manager dahulu, kemudian verifikasi Finance.');
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless($actor->employee_id && $actor->grants()->where('role', 'employee')->where('scope', 'self')->exists(), 403);
        $p = Validator::make($input, ['expense_policy_id' => ['required', 'integer', 'exists:expense_policies,id'], 'incurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'amount_minor' => ['required', 'integer', 'between:1,1000000000000000'], 'cost_center_id' => ['required', 'integer'], 'description' => ['required', 'string', 'max:2000']])->validate();
        $policy = DB::table('expense_policies')->where('id', $p['expense_policy_id'])->firstOrFail();
        $employee = Employee::findOrFail($actor->employee_id);
        $assignment = $employee->assignments()->at($p['incurred_on'])->first();
        $period = $employee->periods()->where('starts_on', '<=', $p['incurred_on'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $p['incurred_on']))->first();
        abort_unless($assignment && $period && $period->activation_state === 'active' && $assignment->department_id === $p['cost_center_id'], 422, 'Cost center harus sesuai penempatan saat pengeluaran.');
        abort_unless($policy->valid_from <= $p['incurred_on'] && $policy->valid_until > $p['incurred_on'] && $p['amount_minor'] <= $policy->max_claim_minor, 422, 'Tanggal/nominal tidak memenuhi kebijakan.');
        abort_unless($period->starts_on->diffInDays(CarbonImmutable::parse($p['incurred_on'])) >= $policy->minimum_tenure_days, 422);
        $jobs = json_decode($policy->job_ids, true);
        abort_if($jobs && ! in_array($assignment->job_id, $jobs, true), 422, 'Jabatan tidak memenuhi eligibility.');

        return ['employee_id' => $employee->id, 'payload' => $p, 'summary' => ['amount_minor' => $p['amount_minor'], 'currency' => 'IDR', 'minor_unit' => 'sen', 'incurred_on' => $p['incurred_on'], 'category' => $policy->category, 'cost_center_id' => $p['cost_center_id']]];
    }

    public function submitted(WorkflowRequest $r): void
    {
        $p = $r->payload;
        $hashes = DB::table('private_attachments')->where('subject_type', 'workflow_request')->where('subject_id', $r->id)->pluck('sha256')->all();
        abort_unless($hashes, 422, 'Bukti pengeluaran wajib sebelum submit.');
        $policy = DB::table('expense_policies')->find($p['expense_policy_id']);
        $date = CarbonImmutable::parse($p['incurred_on']);
        $claimed = (int) DB::table('expense_claims')->join('expense_policies', 'expense_policies.id', '=', 'expense_claims.expense_policy_id')->where('employee_id', $r->employee_id)->where('category', $policy->category)->whereNotIn('expense_claims.status', ['rejected', 'withdrawn'])->whereBetween('incurred_on', [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()])->sum('amount_minor');
        abort_if($claimed + $p['amount_minor'] > $policy->max_month_minor, 422, 'Batas kategori bulanan terlampaui.');
        $duplicate = DB::table('expense_claims')->where('employee_id', $r->employee_id)->where('incurred_on', $p['incurred_on'])->where('amount_minor', $p['amount_minor'])->whereNotIn('status', ['rejected', 'withdrawn'])->get()->contains(fn ($c) => count(array_intersect(json_decode($c->evidence_hashes, true), $hashes)) > 0);
        DB::table('expense_claims')->insert(['workflow_request_id' => $r->id, 'employee_id' => $r->employee_id, 'expense_policy_id' => $policy->id, 'incurred_on' => $p['incurred_on'], 'amount_minor' => $p['amount_minor'], 'cost_center_id' => $p['cost_center_id'], 'duplicate_warning' => $duplicate, 'evidence_hashes' => json_encode($hashes), 'created_at' => now(), 'updated_at' => now()]);
        $r->summary = $r->summary + ['duplicate_warning' => $duplicate];
        $r->save();
    }

    public function stageApproved(WorkflowRequest $r, WorkflowStep $step): void
    {
        if ($step->role === 'finance_approver') {
            $decision = DB::table('workflow_decisions')->where('workflow_step_id', $step->id)->firstOrFail();
            abort_unless($decision->reason && trim(decrypt($decision->reason)) !== '', 422, 'Finance wajib mencatat verifikasi bukti, eligibility dan peringatan duplikasi.');
        }
        DB::table('expense_claims')->where('workflow_request_id', $r->id)->update(['status' => $step->role === 'manager' ? 'manager_approved' : 'finance_verified', 'updated_at' => now()]);
    }

    public function approved(WorkflowRequest $r): void
    {
        DB::table('expense_claims')->where('workflow_request_id', $r->id)->update(['status' => 'approved_for_payment', 'updated_at' => now()]);
    }

    public function released(WorkflowRequest $r): void
    {
        DB::table('expense_claims')->where('workflow_request_id', $r->id)->update(['status' => $r->steps()->where('status', 'rejected')->exists() ? 'rejected' : 'withdrawn', 'updated_at' => now()]);
    }
}
