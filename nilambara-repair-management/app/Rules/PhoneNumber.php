<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Accept local and international display formats, but count actual digits.
        if (! is_string($value) || strlen($value) > 30
            || ! preg_match('/^\+?[0-9][0-9(). -]*[0-9]$/D', $value)
            || ! preg_match('/^[0-9]{7,15}$/D', preg_replace('/[^0-9]/', '', $value))) {
            $fail('validation.phone_number')->translate();
        }
    }
}
