<?php

namespace App\Http\Requests;

use App\Domain\CoreHR\EmployeeFields;
use App\Domain\Identity\Access;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::companyPermission($this->user(), 'employees.manage');
    }

    public function rules(): array
    {
        return EmployeeFields::identity() + EmployeeFields::assignment() + [
            'employee_number' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', 'unique:employees,employee_number'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after:starts_on'],
        ];
    }
}
