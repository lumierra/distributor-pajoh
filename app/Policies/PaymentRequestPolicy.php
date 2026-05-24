<?php

namespace App\Policies;

use App\Models\PaymentRequest;
use App\Models\User;

class PaymentRequestPolicy
{
    private const MENU = 'finance.payment_request';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, PaymentRequest $req): bool
    {
        if (! $user->canView(self::MENU)) {
            return false;
        }

        // Sales hanya lihat punyanya. Kasir/admin lihat semua.
        if ($user->hasRole('sales')) {
            return $req->sales_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, PaymentRequest $req): bool
    {
        if (! $req->canBeEdited()) {
            return false;
        }
        if ($user->hasRole('sales')) {
            return $req->sales_id === $user->id;
        }

        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, PaymentRequest $req): bool
    {
        return false;
    }

    public function submit(User $user, PaymentRequest $req): bool
    {
        return $this->update($user, $req);
    }

    public function cancel(User $user, PaymentRequest $req): bool
    {
        if (! $req->canBeCancelled()) {
            return false;
        }
        if ($user->hasRole('sales')) {
            return $req->sales_id === $user->id;
        }

        return $user->canUpdate(self::MENU);
    }

    /**
     * Verify = kasir/admin (via menu update permission).
     */
    public function verify(User $user, PaymentRequest $req): bool
    {
        return $req->canBeVerified() && $user->canUpdate(self::MENU);
    }

    public function reject(User $user, PaymentRequest $req): bool
    {
        return $req->canBeRejected() && $user->canUpdate(self::MENU);
    }
}
