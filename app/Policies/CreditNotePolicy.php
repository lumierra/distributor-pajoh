<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy
{
    private const MENU = 'returns.credit_note';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, CreditNote $cn): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CreditNote $cn): bool
    {
        return false;
    }

    public function delete(User $user, CreditNote $cn): bool
    {
        return false;
    }

    public function apply(User $user, CreditNote $cn): bool
    {
        return $cn->canBeApplied() && $user->canApprove('returns.customer');
    }
}
