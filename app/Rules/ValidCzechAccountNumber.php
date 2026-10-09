<?php

declare(strict_types=1);

namespace App\Rules;

use App\Banking\CzechAccountNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCzechAccountNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CzechAccountNumber::isValid($value)) {
            $fail('validation.czech_account_number')->translate();
        }
    }
}
