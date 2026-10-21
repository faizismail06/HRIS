<?php

namespace App\Domain\Payroll;

use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PayrollPolicyService
{
    public function create(User $actor, string $key, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'payroll.prepare'), 403);
        $d = Validator::make($input, ['code' => ['required', 'regex:/^[A-Z0-9_-]{2,30}$/'], 'valid_from' => ['required', 'date_format:Y-m-d'], 'valid_until' => ['required', 'date_format:Y-m-d', 'after:valid_from'], 'official_sources' => ['required', 'array', 'min:1', 'max:20'], 'official_sources.*' => ['required', 'url:https', 'max:2000'], 'rules' => ['required', 'array:rounding,proration_basis,requires_tax_profile,components,variation_warning_percent,applicability_statement'], 'rules.rounding' => ['required', Rule::in(['half_up', 'floor', 'ceiling'])], 'rules.proration_basis' => ['required', Rule::in(['calendar_days'])], 'rules.requires_tax_profile' => ['required', 'boolean'], 'rules.applicability_statement' => ['required', 'string', 'max:2000'], 'rules.variation_warning_percent' => ['required', 'integer', 'between:1,1000'], 'rules.components' => ['present', 'array', 'max:40'], 'rules.components.*' => ['array:code,label,kind,method,basis,amount_minor,numerator,denominator,cap_minor,tax_statuses,brackets,tax_component,subtract_prior_tax'], 'rules.components.*.code' => ['required', 'regex:/^[A-Z][A-Z0-9_]{1,39}$/', 'distinct'], 'rules.components.*.label' => ['required', 'string', 'max:120'], 'rules.components.*.kind' => ['required', Rule::in(['earning', 'deduction'])], 'rules.components.*.method' => ['required', Rule::in(['fixed', 'ratio', 'brackets'])], 'rules.components.*.basis' => ['required', Rule::in(['base', 'gross', 'overtime_minutes', 'late_minutes', 'unpaid_leave_minutes', 'absent_shifts', 'prior_gross', 'prior_tax_paid', 'annual_gross'])], 'rules.components.*.amount_minor' => ['nullable', 'integer', 'between:0,1000000000000000'], 'rules.components.*.numerator' => ['nullable', 'integer', 'between:0,1000000000'], 'rules.components.*.denominator' => ['nullable', 'integer', 'between:1,1000000000'], 'rules.components.*.cap_minor' => ['nullable', 'integer', 'between:0,1000000000000000'], 'rules.components.*.tax_statuses' => ['nullable', 'array', 'max:30'], 'rules.components.*.tax_statuses.*' => ['string', 'max:40'], 'rules.components.*.brackets' => ['nullable', 'array', 'max:30'], 'rules.components.*.brackets.*' => ['array:up_to,numerator,denominator'], 'rules.components.*.brackets.*.up_to' => ['nullable', 'integer', 'between:1,1000000000000000'], 'rules.components.*.brackets.*.numerator' => ['required', 'integer', 'between:0,1000000000'], 'rules.components.*.brackets.*.denominator' => ['required', 'integer', 'between:1,1000000000']])->validate();
        $deductions = false;
        abort_if(count(array_filter($d['rules']['components'], fn ($c) => ! empty($c['subtract_prior_tax']))) > 1, 422, 'Hanya satu komponen rekonsiliasi pajak kumulatif diizinkan.');
        foreach ($d['rules']['components'] as $component) {
            Validator::make($component, ['tax_component' => ['sometimes', 'boolean'], 'subtract_prior_tax' => ['sometimes', 'boolean']])->validate();
            abort_if((! empty($component['tax_component']) && $component['kind'] !== 'deduction') || (! empty($component['subtract_prior_tax']) && empty($component['tax_component'])), 422);
            abort_if($component['kind'] === 'earning' && ($deductions || $component['basis'] === 'gross'), 422, 'Earning harus sebelum deduction dan tidak boleh bergantung gross.');
            $deductions = $deductions || $component['kind'] === 'deduction';
            abort_unless($component['method'] !== 'fixed' || isset($component['amount_minor']), 422);
            abort_unless($component['method'] !== 'ratio' || (isset($component['numerator'], $component['denominator'])), 422);
            if ($component['method'] === 'brackets') {
                $previous = 0;
                $open = false;
                abort_if(empty($component['brackets']), 422);
                foreach ($component['brackets'] as $bracket) {
                    abort_if($open || (isset($bracket['up_to']) && $bracket['up_to'] <= $previous), 422, 'Bracket harus berurutan dan rentang terakhir terbuka.');
                    $open = ! isset($bracket['up_to']);
                    $previous = $bracket['up_to'] ?? $previous;
                }
                abort_unless($open, 422, 'Bracket terakhir harus terbuka.');
            }
        }

        return Mutation::run($actor, 'payroll.policy.create', $key, $d, fn ($u) => abort_unless(Access::companyPermission($u, 'payroll.prepare'), 403), function ($u) use ($d) {
            $version = (int) DB::table('payroll_policies')->where('code', $d['code'])->max('version') + 1;
            $id = DB::table('payroll_policies')->insertGetId(array_replace($d, ['rules' => json_encode($d['rules']), 'official_sources' => json_encode($d['official_sources']), 'version' => $version, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]));
            Audit::record($u->id, 'payroll.policy_drafted', 'payroll_policy', $id, ['version' => $version]);

            return ['id' => $id, 'version' => $version, 'status' => 'draft'];
        });
    }

    public function approve(User $actor, int $id, string $key, string $evidence): array
    {
        Validator::make(compact('evidence'), ['evidence' => ['required', 'string', 'max:4000']])->validate();

        return Mutation::run($actor, 'payroll.policy.approve', $key, compact('id', 'evidence'), fn ($u) => abort_unless(Access::companyPermission($u, 'payroll.approve'), 403), function ($u) use ($id, $evidence) {
            $p = DB::table('payroll_policies')->where('id', $id)->firstOrFail();
            abort_if($p->created_by === $u->id, 403);
            abort_unless($p->status === 'draft', 409);
            // Human payroll-expert attestation is required; a URL alone is never treated as verified law.
            DB::table('payroll_policies')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $u->id, 'review_evidence' => encrypt($evidence), 'approved_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'payroll.policy_expert_approved', 'payroll_policy', $id, ['version' => $p->version, 'evidence_provided' => true]);

            return ['id' => $id, 'status' => 'approved'];
        });
    }
}
