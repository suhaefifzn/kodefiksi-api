<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MinWordsRule implements ValidationRule
{
    protected $min;

    public function __construct(int $min) {
        $this->min = $min;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $wordCount = str_word_count($value);

        if ($wordCount < $this->min) {
            $fail("The $attribute must have at least $this->min words.");
        }
    }
}
