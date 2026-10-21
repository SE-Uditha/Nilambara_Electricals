<?php

namespace App\Rules;

use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DecimalValue implements ValidationRule
{
    public function __construct(private int $precision = 19, private int $scale = 2) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Never use floating-point comparisons at a DECIMAL column boundary.
        if ((! is_string($value) && ! is_int($value) && ! is_float($value))
            || ! preg_match('/^-?[0-9]+(?:\.[0-9]{1,'.$this->scale.'})?$/D', (string) $value)) {
            $fail('validation.decimal_value')->translate(['places' => $this->scale]);

            return;
        }

        $limit = BigDecimal::of(str_repeat('9', $this->precision - $this->scale).'.'.str_repeat('9', $this->scale));
        if (BigDecimal::of((string) $value)->abs()->isGreaterThan($limit)) {
            $fail('validation.decimal_range')->translate();
        }
    }
}
