<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    private const MENU = 'sales.so';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, SalesOrder $so): bool
    {
        if (! $user->canView(self::MENU)) {
            return false;
        }

        // Sales hanya boleh lihat SO miliknya sendiri (kecuali admin).
        if ($user->hasRole('sales')) {
            return $so->sales_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, SalesOrder $so): bool
    {
        if (! $so->canBeEdited()) {
            return false;
        }

        // Sales hanya boleh edit own draft.
        if ($user->hasRole('sales')) {
            return $so->sales_id === $user->id;
        }

        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, SalesOrder $so): bool
    {
        return false;
    }

    public function submit(User $user, SalesOrder $so): bool
    {
        return $this->update($user, $so);
    }

    public function cancel(User $user, SalesOrder $so): bool
    {
        // Sales boleh cancel own draft. Admin boleh cancel apapun yang
        // canBeCancelled().
        if (! $so->canBeCancelled()) {
            return false;
        }

        if ($user->hasRole('sales')) {
            return $so->sales_id === $user->id && $so->status === SalesOrder::STATUS_DRAFT;
        }

        return $user->canUpdate(self::MENU);
    }

    public function approve(User $user, SalesOrder $so): bool
    {
        return $user->canUpdate(self::MENU)
            && $so->status === SalesOrder::STATUS_SUBMITTED;
    }

    /**
     * Approve credit override = superadmin only (lewat before()).
     * Admin biasa tidak boleh.
     */
    public function approveOverride(User $user, SalesOrder $so): bool
    {
        return false;
    }

    public function reject(User $user, SalesOrder $so): bool
    {
        return $user->canUpdate(self::MENU) && $so->canBeRejected();
    }
}
