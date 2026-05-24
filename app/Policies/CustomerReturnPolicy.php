<?php

namespace App\Policies;

use App\Models\CustomerReturn;
use App\Models\User;

class CustomerReturnPolicy
{
    private const MENU = 'returns.customer';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, CustomerReturn $cr): bool
    {
        if (! $user->canView(self::MENU)) {
            return false;
        }
        if ($user->hasRole('sales')) {
            return $cr->sales_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, CustomerReturn $cr): bool
    {
        if (! $cr->canBeEdited()) {
            return false;
        }
        if ($user->hasRole('sales')) {
            return $cr->sales_id === $user->id && $user->canCreate(self::MENU);
        }

        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, CustomerReturn $cr): bool
    {
        return false;
    }

    public function sort(User $user, CustomerReturn $cr): bool
    {
        return $cr->canBeSorted() && $user->canUpdate(self::MENU);
    }

    public function post(User $user, CustomerReturn $cr): bool
    {
        return $cr->canBePosted() && $user->canUpdate(self::MENU);
    }

    public function cancel(User $user, CustomerReturn $cr): bool
    {
        if (! $cr->canBeCancelled()) {
            return false;
        }
        if ($user->hasRole('sales')) {
            return $cr->sales_id === $user->id;
        }

        return $user->canUpdate(self::MENU);
    }
}
