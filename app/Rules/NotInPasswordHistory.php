<?php

namespace App\Rules;

use App\Models\PasswordHistory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Rejects a candidate password that matches one of the last N stored hashes
 * for the given user. N defaults to 3 (T02 §5.1).
 */
class NotInPasswordHistory implements ValidationRule
{
    public function __construct(
        private readonly int $userId,
        private readonly int $depth = 3,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $recent = PasswordHistory::query()
            ->where('user_id', $this->userId)
            ->orderByDesc('created_at')
            ->limit($this->depth)
            ->pluck('password_hash');

        foreach ($recent as $hash) {
            if (Hash::check($value, $hash)) {
                $fail('Password tidak boleh sama dengan '.$this->depth.' password terakhir.');

                return;
            }
        }
    }
}
