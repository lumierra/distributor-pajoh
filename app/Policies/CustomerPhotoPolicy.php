<?php

namespace App\Policies;

use App\Models\CustomerPhoto;
use App\Models\User;

class CustomerPhotoPolicy
{
    private const MENU = 'master.customer';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function create(User $user): bool
    {
        // Admin/Sales (sales upload saat visit). Operator/kasir tidak.
        return $user->canUpdate(self::MENU) || $user->hasRole('sales');
    }

    public function delete(User $user, CustomerPhoto $photo): bool
    {
        // Hanya admin (& superadmin via before).
        return $user->canUpdate(self::MENU);
    }
}
