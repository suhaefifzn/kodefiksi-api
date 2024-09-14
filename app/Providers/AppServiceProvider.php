<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Custom validation to check minimum words
         */
        Validator::extend('min_words', function ($attribute, $value, $parameters, $validator) {
            $wordCount = str_word_count($value);
            return $wordCount >= $parameters[0];
        }, 'The :attribute must have at least :min words.');
    }
}
