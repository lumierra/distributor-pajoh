<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    private const MENU = 'master.unit';

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

    public function update(User $user, Unit $unit): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->canDelete(self::MENU);
    }
}
