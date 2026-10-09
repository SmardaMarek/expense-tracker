<?php

declare(strict_types=1);

namespace App\Rules;

use App\Money\Amount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidAmount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Amount::parse($value) === null) {
            $fail('validation.amount')->translate();
        }
    }
}
