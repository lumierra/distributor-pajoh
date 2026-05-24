<?php

namespace App\Policies;

use App\Models\SalesVisitBypassRequest;
use App\Models\User;

class SalesVisitBypassRequestPolicy
{
    private const MENU = 'sales.visit';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU) || $user->hasRole('sales');
    }

    public function view(User $user, SalesVisitBypassRequest $req): bool
    {
        if ($user->hasRole('sales')) {
            return $req->sales_id === $user->id;
        }

        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('sales');
    }

    public function review(User $user, SalesVisitBypassRequest $req): bool
    {
        return $user->canApprove(self::MENU);
    }
}
