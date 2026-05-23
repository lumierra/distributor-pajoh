<?php

namespace App\Policies;

use App\Models\ProductGroup;
use App\Models\User;

class ProductGroupPolicy
{
    private const MENU = 'master.product_group';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, ProductGroup $group): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, ProductGroup $group): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, ProductGroup $group): bool
    {
        return $user->canDelete(self::MENU);
    }
}
