<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    private const MENU = 'inventory.adjustment';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, StockAdjustment $adjustment): bool
    {
        return $adjustment->canBeEdited() && $user->canUpdate(self::MENU);
    }

    public function delete(User $user, StockAdjustment $adjustment): bool
    {
        return false;
    }

    /**
     * Posting ke stok = admin (canApprove) / superadmin only. Operator boleh
     * buat & edit draft, tapi tidak posting.
     */
    public function post(User $user, StockAdjustment $adjustment): bool
    {
        return $user->canApprove(self::MENU);
    }

    public function cancel(User $user, StockAdjustment $adjustment): bool
    {
        return $adjustment->canBeCancelled() && $user->canUpdate(self::MENU);
    }
}
