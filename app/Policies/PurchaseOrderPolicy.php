<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    private const MENU = 'purchasing.po';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Create PO: superadmin only (admin view-only per business rule).
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return false;
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        return false;
    }

    public function approve(User $user, PurchaseOrder $po): bool
    {
        return false;
    }

    public function cancel(User $user, PurchaseOrder $po): bool
    {
        return false;
    }

    public function close(User $user, PurchaseOrder $po): bool
    {
        return false;
    }

    public function downloadPdf(User $user, PurchaseOrder $po): bool
    {
        return $user->canView(self::MENU);
    }

    public function regeneratePdf(User $user, PurchaseOrder $po): bool
    {
        return false;
    }
}
