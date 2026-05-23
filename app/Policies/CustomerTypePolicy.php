<?php

namespace App\Policies;

use App\Models\CustomerType;
use App\Models\User;

class CustomerTypePolicy
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

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, CustomerType $type): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, CustomerType $type): bool
    {
        return false;
    }
}
