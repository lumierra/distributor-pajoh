<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    private const MENU = 'master.menu';

    /**
     * `delete` menu system harus tetap difilter di policy
     * (`is_system` tidak bisa dihapus walau oleh superadmin).
     */
    private const NOT_BYPASSED_BY_SUPERADMIN = [
        'create',
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

    public function view(User $user, Menu $menu): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Menu baru hanya bisa ditambah via migration (T02 §4).
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Edit metadata menu (label, icon, order) — hanya superadmin.
     */
    public function update(User $user, Menu $menu): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Menu `is_system` tidak bisa dihapus (T02 §4).
     */
    public function delete(User $user, Menu $menu): bool
    {
        if (! $user->isSuperadmin()) {
            return false;
        }

        return ! $menu->is_system;
    }
}
