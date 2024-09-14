<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TextToBooleanRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        switch ($value) {
            case 'true':
                break;
            case 'false':
                break;
            default:
                $fail("The $attribute field must be string true or false");
                break;
        }
    }
}
