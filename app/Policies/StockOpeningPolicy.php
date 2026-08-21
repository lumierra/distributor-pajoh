<?php

namespace App\Policies;

use App\Models\StockOpening;
use App\Models\User;

class StockOpeningPolicy
{
    private const MENU = 'inventory.opening';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, StockOpening $opening): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, StockOpening $opening): bool
    {
        return $opening->canBeEdited() && $user->canUpdate(self::MENU);
    }

    public function delete(User $user, StockOpening $opening): bool
    {
        return false;
    }

    /**
     * Posting ke stok = admin (canApprove) / superadmin only. Operator boleh
     * buat & edit draft, tapi tidak posting.
     */
    public function post(User $user, StockOpening $opening): bool
    {
        return $user->canApprove(self::MENU);
    }

    public function cancel(User $user, StockOpening $opening): bool
    {
        return $opening->canBeCancelled() && $user->canUpdate(self::MENU);
    }
}
