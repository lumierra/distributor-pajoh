<?php

namespace App\Policies;

use App\Models\SalesVisit;
use App\Models\User;

class SalesVisitPolicy
{
    private const MENU = 'sales.visit';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, SalesVisit $visit): bool
    {
        if (! $user->canView(self::MENU)) {
            return false;
        }
        if ($user->hasRole('sales')) {
            return $visit->sales_id === $user->id;
        }

        return true;
    }

    public function checkin(User $user): bool
    {
        return $user->hasRole('sales') && $user->canCreate(self::MENU);
    }

    public function checkout(User $user, SalesVisit $visit): bool
    {
        if ($user->hasRole('sales')) {
            return $visit->sales_id === $user->id;
        }

        return $user->canUpdate(self::MENU);
    }

    public function cancel(User $user, SalesVisit $visit): bool
    {
        if ($user->hasRole('sales')) {
            return $visit->sales_id === $user->id && $visit->isActive();
        }

        return $user->canUpdate(self::MENU);
    }
}
