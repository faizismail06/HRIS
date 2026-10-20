<?php

namespace App\Domain\CoreHR;

use App\Domain\Identity\Access;
use App\Domain\Identity\AccountSecurity;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\EmployeePrivateProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SensitiveProfiles
{
    public const CATEGORIES = ['general', 'identity', 'bank', 'tax'];

    public static function allowed(User $actor, int $employee, string $category): bool
    {
        if (! AccountSecurity::available($actor) || ! in_array($category, self::CATEGORIES, true)) {
            return false;
        }
        if ($actor->employee_id === $employee && $actor->grants()->where('role', 'employee')->where('scope', 'self')->exists()) {
            return true;
        }
        $roles = in_array($category, ['bank', 'tax'], true) ? ['payroll_officer', 'finance_approver'] : ['hr_admin', 'hr_officer'];

        return collect($roles)->contains(fn ($role) => WorkflowAccess::eligible($actor, $role, $employee));
    }

    public function read(User $actor, int $employee, string $category): array
    {
        abort_unless(self::allowed($actor, $employee, $category), 404);
        $p = EmployeePrivateProfile::where('employee_id', $employee)->where('category', $category)->first();
        Audit::record($actor->id, 'profile.private_read', 'employee', $employee, ['category' => $category]);

        return ['employee_id' => $employee, 'category' => $category, 'version' => $p?->version ?? 0, 'data' => $p?->data ?? [], 'verified_at' => $p?->verified_at];
    }

    public function fields(User $actor, string $key, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'employees.review'), 403);
        $data = Validator::make($input, ['code' => ['required', 'regex:/^[a-z][a-z0-9_]{1,39}$/'], 'label' => ['required', 'string', 'max:120'], 'type' => ['required', Rule::in(['text', 'integer', 'date', 'boolean', 'select'])], 'options' => ['required_if:type,select', 'nullable', 'array', 'min:1', 'max:30'], 'options.*' => ['string', 'max:80', 'distinct']])->validate();

        return Mutation::run($actor, 'profile.field.create', $key, $data, fn ($u) => abort_unless(Access::companyPermission($u, 'employees.review'), 403), function ($u) use ($data) {
            abort_if(DB::table('profile_field_definitions')->where('code', $data['code'])->exists(), 422, 'Kode custom field sudah digunakan.');
            $id = DB::table('profile_field_definitions')->insertGetId(array_replace($data, ['options' => isset($data['options']) ? json_encode($data['options']) : null, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]));
            Audit::record($u->id, 'profile.field_defined', 'profile_field_definition', $id, ['code' => $data['code'], 'type' => $data['type']]);

            return ['id' => $id];
        });
    }

    public function validate(string $category, array $input): array
    {
        $rules = match ($category) {
            'general' => ['birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000'], 'emergency_contact' => ['nullable', 'array:name,relationship,phone'], 'emergency_contact.name' => ['required_with:emergency_contact', 'string', 'max:120'], 'emergency_contact.relationship' => ['required_with:emergency_contact', 'string', 'max:80'], 'emergency_contact.phone' => ['required_with:emergency_contact', 'string', 'max:40'], 'education' => ['nullable', 'array', 'max:20'], 'education.*' => ['array:institution,qualification,completed_year'], 'education.*.institution' => ['required', 'string', 'max:160'], 'education.*.qualification' => ['required', 'string', 'max:160'], 'education.*.completed_year' => ['nullable', 'integer', 'between:1900,2200'], 'custom' => ['nullable', 'array', 'max:30']],
            'identity' => ['national_id' => ['required', 'regex:/^[0-9]{16}$/'], 'legal_name' => ['required', 'string', 'max:160']],
            'bank' => ['bank_code' => ['required', 'regex:/^[A-Za-z0-9_-]{2,20}$/'], 'account_number' => ['required', 'regex:/^[0-9]{4,34}$/'], 'account_name' => ['required', 'string', 'max:160']],
            'tax' => ['tax_number' => ['nullable', 'string', 'max:40'], 'tax_status' => ['required', 'string', 'max:40'], 'bpjs_health_number' => ['nullable', 'string', 'max:40'], 'bpjs_employment_number' => ['nullable', 'string', 'max:40']],
            default => abort(422),
        };
        abort_if(array_diff(array_keys($input), array_keys(array_filter($rules, fn ($key) => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY))), 422, 'Field profil tidak diizinkan; perubahan organisasi memakai workflow terpisah.');
        $data = Validator::make($input, $rules)->validate();
        foreach ($data['custom'] ?? [] as $code => $value) {
            $field = DB::table('profile_field_definitions')->where('code', $code)->where('is_active', true)->first();
            abort_unless($field, 422, 'Custom field tidak terdaftar atau tidak aktif.');
            $rule = match ($field->type) {
                'text' => ['string', 'max:500'], 'integer' => ['integer'], 'boolean' => ['boolean'], 'date' => ['date_format:Y-m-d'], 'select' => [Rule::in(json_decode($field->options, true))],
            };
            Validator::make(['value' => $value], ['value' => $rule])->validate();
        }

        return $data;
    }

    public static function nationalHash(string $id): string
    {
        return hash_hmac('sha256', $id, config('app.key'));
    }
}
