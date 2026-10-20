<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AccountPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > 72 || str_contains($value, "\0")) {
            $fail('Kata sandi maksimal 72 byte dan tidak boleh berisi karakter null.');
        }
    }
}
