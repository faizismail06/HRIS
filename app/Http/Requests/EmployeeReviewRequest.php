<?php

namespace App\Http\Requests;

use App\Domain\Identity\Access;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::companyPermission($this->user(), 'employees.review');
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approve', 'reject'])], 'version' => ['required', 'integer', 'min:1'], 'review_note' => ['required', 'string', 'max:1000']];
    }
}
