<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    private const MENU = 'master.vehicle';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    public function restore(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    public function toggleActive(User $user, Vehicle $vehicle): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function setMaintenance(User $user, Vehicle $vehicle): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function uploadDocument(User $user, Vehicle $vehicle): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function deleteDocument(User $user, Vehicle $vehicle): bool
    {
        return false;
    }
}
