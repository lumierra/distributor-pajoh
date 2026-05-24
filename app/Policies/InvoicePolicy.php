<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    private const MENU = 'sales.invoice';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Invoice tidak di-create manual. Auto-generate dari DO delivered.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function downloadPdf(User $user, Invoice $invoice): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Re-generate PDF. Admin/superadmin only.
     */
    public function regeneratePdf(User $user, Invoice $invoice): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function markOverdue(User $user): bool
    {
        return $user->canUpdate(self::MENU);
    }
}
