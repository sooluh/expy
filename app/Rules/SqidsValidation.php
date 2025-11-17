<?php

namespace App\Rules;

use App\Facades\Sqids;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Validation\ValidationRule;

class SqidsValidation implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            Sqids::decode($value);
        } catch (DecryptException $e) {
            $fail(':attribute harus berisi string terenkripsi yang valid.');
        }
    }
}
