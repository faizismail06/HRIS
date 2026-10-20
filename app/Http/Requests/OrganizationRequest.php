<?php

namespace App\Http\Requests;

use App\Domain\CoreHR\OrganizationCatalog;
use App\Domain\Identity\Access;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::companyPermission($this->user(), 'organization.manage');
    }

    public function rules(): array
    {
        $kind = $this->route('kind');
        $type = OrganizationCatalog::type($kind);
        $table = (new ($type['model']))->getTable();
        $rules = [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique($table)->ignore($this->route('record'))],
            'name' => ['required', 'string', 'max:160'],
            'version' => [$this->route('record') ? 'required' : 'nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];

        return $rules + match ($kind) {
            'branches' => ['timezone' => ['required', 'timezone']],
            'departments' => ['parent_id' => ['nullable', 'integer', 'exists:departments,id']],
            'jobs' => ['department_id' => ['required', 'integer', 'exists:departments,id']],
        };
    }
}
