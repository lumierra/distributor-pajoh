<?php

namespace App\Policies;

use App\Models\SupplierReturn;
use App\Models\User;

class SupplierReturnPolicy
{
    private const MENU = 'returns.supplier';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, SupplierReturn $sr): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, SupplierReturn $sr): bool
    {
        return $sr->canBeEdited() && $user->canUpdate(self::MENU);
    }

    public function delete(User $user, SupplierReturn $sr): bool
    {
        return false;
    }

    /**
     * Approve = superadmin only (di-handle oleh before()).
     */
    public function approve(User $user, SupplierReturn $sr): bool
    {
        return false;
    }

    public function markSent(User $user, SupplierReturn $sr): bool
    {
        return $sr->canBeSent() && $user->canApprove(self::MENU);
    }

    public function settle(User $user, SupplierReturn $sr): bool
    {
        return $sr->canBeSettled() && $user->canApprove(self::MENU);
    }

    public function cancel(User $user, SupplierReturn $sr): bool
    {
        return $sr->canBeCancelled() && $user->canUpdate(self::MENU);
    }
}
