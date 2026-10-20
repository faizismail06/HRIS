<?php

namespace App\Http\Requests;

use App\Domain\CoreHR\EmployeeFields;
use App\Domain\Identity\Access;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::companyPermission($this->user(), 'employees.manage');
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['identity', 'assignment'])],
            'base_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ] + match ($this->input('kind')) {
            'identity' => EmployeeFields::identity((int) $this->route('employee')),
            'assignment' => EmployeeFields::assignment() + ['effective_on' => ['required', 'date_format:Y-m-d', 'after:today']],
            default => [],
        };
    }
}
