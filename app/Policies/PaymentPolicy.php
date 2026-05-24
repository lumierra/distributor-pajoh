<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    private const MENU = 'finance.payment';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Payment dibuat lewat verify PaymentRequest — tidak ada create manual.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function clearGiro(User $user, Payment $payment): bool
    {
        return $payment->canBeCleared() && $user->canUpdate(self::MENU);
    }

    public function bounceGiro(User $user, Payment $payment): bool
    {
        return $payment->canBeBounced() && $user->canUpdate(self::MENU);
    }
}
