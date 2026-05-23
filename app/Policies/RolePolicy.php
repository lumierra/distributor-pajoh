<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    private const MENU = 'master.role';

    /**
     * Tetap evaluasi penuh untuk action yang punya invariant
     * (mis. role system tidak bisa diupdate/hapus, role dengan user tidak bisa dihapus).
     */
    private const NOT_BYPASSED_BY_SUPERADMIN = [
        'update',
        'updatePermissions',
        'delete',
    ];

    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isSuperadmin()) {
            return null;
        }

        return in_array($ability, self::NOT_BYPASSED_BY_SUPERADMIN, true) ? null : true;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Buat custom role: hanya superadmin (T02 §3).
     */
    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Edit role.
     * - System role (`is_system`) tidak bisa di-rename code.
     * - Edit permission superadmin diblock (handle di updatePermissions).
     */
    public function update(User $user, Role $role): bool
    {
        if (! $user->isSuperadmin()) {
            return false;
        }

        return ! $role->is_system;
    }

    /**
     * Update permission matrix sebuah role.
     */
    public function updatePermissions(User $user, Role $role): bool
    {
        if (! $user->isSuperadmin()) {
            return false;
        }

        // Permission superadmin diblock — tetap full access (T02 §3).
        return $role->code !== Role::CODE_SUPERADMIN;
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->isSuperadmin()) {
            return false;
        }
        if ($role->is_system) {
            return false;
        }

        return $role->users()->count() === 0;
    }
}
