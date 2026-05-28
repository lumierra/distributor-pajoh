<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    private const MENU = 'master.customer';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, Customer $customer): bool
    {
        // Superadmin only (rule §7 — admin tidak boleh delete).
        return false;
    }

    public function restore(User $user, Customer $customer): bool
    {
        return false;
    }

    public function toggleActive(User $user, Customer $customer): bool
    {
        return $user->canUpdate(self::MENU);
    }

    /**
     * Field-level guard: credit limit → superadmin only.
     */
    public function updateCreditLimit(User $user, Customer $customer): bool
    {
        return false;
    }

    /**
     * Field-level guard: assigned sales → admin & superadmin.
     */
    public function updateAssignedSales(User $user, Customer $customer): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function approveGeoPending(User $user, Customer $customer): bool
    {
        return $user->canUpdate(self::MENU);
    }

    /**
     * Sales boleh update field terbatas via mobile.
     */
    public function mobileUpdate(User $user, Customer $customer): bool
    {
        return $user->hasRole('sales');
    }

    public function export(User $user): bool
    {
        return $user->canExport(self::MENU);
    }
}
