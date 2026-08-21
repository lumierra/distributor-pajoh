<?php

namespace App\Policies;

use App\Models\StockOpname;
use App\Models\User;

class StockOpnamePolicy
{
    private const MENU = 'inventory.opname';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, StockOpname $opname): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, StockOpname $opname): bool
    {
        return $opname->canBeEdited() && $user->canUpdate(self::MENU);
    }

    public function delete(User $user, StockOpname $opname): bool
    {
        return false;
    }

    /**
     * Posting ke stok = admin (canApprove) / superadmin only.
     */
    public function post(User $user, StockOpname $opname): bool
    {
        return $user->canApprove(self::MENU);
    }

    public function cancel(User $user, StockOpname $opname): bool
    {
        return $opname->canBeCancelled() && $user->canUpdate(self::MENU);
    }
}
