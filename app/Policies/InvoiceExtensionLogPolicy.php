<?php

namespace App\Policies;

use App\Models\InvoiceExtensionLog;
use App\Models\User;

class InvoiceExtensionLogPolicy
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

    public function view(User $user, InvoiceExtensionLog $log): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Sales boleh request extension untuk invoice yang related ke SO miliknya.
     */
    public function create(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Admin yang approve/reject. Bukan superadmin only — admin cukup.
     */
    public function review(User $user, InvoiceExtensionLog $log): bool
    {
        return $log->status === InvoiceExtensionLog::STATUS_PENDING
            && $user->canUpdate(self::MENU);
    }

    public function cancel(User $user, InvoiceExtensionLog $log): bool
    {
        if ($log->status !== InvoiceExtensionLog::STATUS_PENDING) {
            return false;
        }
        if ($user->hasRole('sales')) {
            return $log->requested_by === $user->id;
        }

        return $user->canUpdate(self::MENU);
    }
}
