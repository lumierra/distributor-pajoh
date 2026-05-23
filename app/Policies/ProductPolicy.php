<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
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

    public function view(User $user, Product $product): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->canDelete(self::MENU);
    }

    public function toggleActive(User $user, Product $product): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->canDelete(self::MENU);
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Bulk price update — superadmin only (high-risk operation).
     */
    public function bulkPriceUpdate(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function export(User $user): bool
    {
        return $user->canExport(self::MENU);
    }
}
