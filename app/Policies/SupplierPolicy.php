<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
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

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->canDelete(self::MENU);
    }

    public function toggleActive(User $user, Supplier $supplier): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->canDelete(self::MENU);
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return $user->isSuperadmin();
    }

    public function export(User $user): bool
    {
        return $user->canExport(self::MENU);
    }
}
