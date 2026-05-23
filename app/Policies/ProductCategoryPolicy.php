<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    private const MENU = 'master.product';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        if (! $user->canDelete(self::MENU)) {
            return false;
        }

        return $category->products()->count() === 0;
    }
}
