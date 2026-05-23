<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionInvalidatorService
{
    /**
     * Wipe all database-driver sessions belonging to the user. Returns count.
     */
    public function forceLogout(User $user): int
    {
        return DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    /**
     * Revoke every Sanctum personal access token for the user. Returns count.
     */
    public function revokeAllTokens(User $user): int
    {
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        return $count;
    }

    /**
     * Combined: wipe sessions and revoke all tokens. Returns [sessions, tokens].
     *
     * @return array{sessions:int, tokens:int}
     */
    public function fullLogout(User $user): array
    {
        return [
            'sessions' => $this->forceLogout($user),
            'tokens' => $this->revokeAllTokens($user),
        ];
    }
}
