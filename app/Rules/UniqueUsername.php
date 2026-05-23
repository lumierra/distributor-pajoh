<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Unique check for `users.username`, case-insensitive.
 * Excludes soft-deleted rows (so a deleted user's username can be reused).
 * If `$ignoreUserId` is set, that user row is ignored (useful on update).
 */
class UniqueUsername implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $query = User::query()
            ->whereRaw('LOWER(username) = ?', [strtolower($value)]);

        if ($this->ignoreUserId !== null) {
            $query->where('id', '!=', $this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail('Username sudah digunakan.');
        }
    }
}
