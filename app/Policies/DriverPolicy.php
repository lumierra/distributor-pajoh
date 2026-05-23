<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    private const MENU = 'master.driver';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, Driver $driver): bool
    {
        return false;
    }

    public function restore(User $user, Driver $driver): bool
    {
        return false;
    }

    public function toggleActive(User $user, Driver $driver): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function setUnavailable(User $user, Driver $driver): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function uploadDocument(User $user, Driver $driver): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function deleteDocument(User $user, Driver $driver): bool
    {
        return false;
    }
}
