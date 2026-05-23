<?php

namespace App\Policies;

use App\Models\SupplierCategory;
use App\Models\User;

class SupplierCategoryPolicy
{
    private const MENU = 'master.supplier';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, SupplierCategory $category): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, SupplierCategory $category): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, SupplierCategory $category): bool
    {
        if (! $user->canDelete(self::MENU)) {
            return false;
        }

        return $category->suppliers()->count() === 0;
    }
}
