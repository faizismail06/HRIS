<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OperationalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key', $this->input('idempotency_key'))]);
    }

    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'expected_version' => ['sometimes', 'required', 'integer', 'min:1']];
    }

    public function key(): string
    {
        return $this->validated('idempotency_key');
    }

    public function version(): int
    {
        $this->validate(['expected_version' => ['required', 'integer', 'min:1']]);

        return (int) $this->input('expected_version');
    }
}
