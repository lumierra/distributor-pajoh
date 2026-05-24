<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDevice;

class UserDevicePolicy
{
    private const MENU = 'master.user';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, UserDevice $device): bool
    {
        if ($user->id === $device->user_id) {
            return true;
        }

        return $user->canView(self::MENU);
    }

    public function approve(User $user): bool
    {
        return $user->canApprove(self::MENU);
    }

    public function revoke(User $user, UserDevice $device): bool
    {
        return $user->canUpdate(self::MENU);
    }
}
