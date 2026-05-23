<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enforces the password policy documented in T02 §5.1:
 * - minimum 8 characters
 * - must contain at least one letter and one digit
 * - optionally cannot equal a provided forbidden value (e.g. username)
 *
 * Use together with `NotInPasswordHistory` to also block re-use.
 */
class PasswordPolicy implements ValidationRule
{
    public const MIN_LENGTH = 8;

    public function __construct(private readonly ?string $forbiddenValue = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Password harus berupa teks.');

            return;
        }

        if (strlen($value) < self::MIN_LENGTH) {
            $fail('Password minimal '.self::MIN_LENGTH.' karakter.');
        }

        if (! preg_match('/[A-Za-z]/', $value)) {
            $fail('Password harus mengandung minimal 1 huruf.');
        }

        if (! preg_match('/\d/', $value)) {
            $fail('Password harus mengandung minimal 1 angka.');
        }

        if ($this->forbiddenValue !== null && strcasecmp($value, $this->forbiddenValue) === 0) {
            $fail('Password tidak boleh sama dengan username.');
        }
    }
}
