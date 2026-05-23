<?php

namespace App\Services\Auth;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordPolicyValidator
{
    public const MIN_LENGTH = 8;

    public const HISTORY_DEPTH = 3;

    /**
     * Validate a candidate password against the policy.
     *
     * Rules: min 8, must contain letter and digit, not match username,
     * not match any of the last N stored password hashes, optional check
     * that new differs from current.
     *
     * @return array{valid:bool, errors:array<int,string>}
     */
    public function check(string $password, User $user, ?string $currentPassword = null): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'Password minimal '.self::MIN_LENGTH.' karakter.';
        }
        if (! preg_match('/[A-Za-z]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 huruf.';
        }
        if (! preg_match('/\d/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 angka.';
        }
        if ($user->username !== null && strcasecmp($password, $user->username) === 0) {
            $errors[] = 'Password tidak boleh sama dengan username.';
        }
        if ($currentPassword !== null && $password === $currentPassword) {
            $errors[] = 'Password baru tidak boleh sama dengan password saat ini.';
        }
        if ($this->matchesHistory($password, $user)) {
            $errors[] = 'Password tidak boleh sama dengan '.self::HISTORY_DEPTH.' password terakhir.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Append a hash to the user's password history. Caller is responsible for
     * ensuring the matching `users.password` update happens in the same flow.
     */
    public function recordHistory(User $user, string $plainPassword): void
    {
        PasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => Hash::make($plainPassword),
        ]);
    }

    private function matchesHistory(string $password, User $user): bool
    {
        if (! $user->exists) {
            return false;
        }

        $recent = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_DEPTH)
            ->pluck('password_hash');

        foreach ($recent as $hash) {
            if (Hash::check($password, $hash)) {
                return true;
            }
        }

        return false;
    }
}
