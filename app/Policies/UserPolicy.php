<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    private const MENU = 'master.user';

    /**
     * Action yang tetap difilter di policy meskipun user superadmin —
     * biasanya kasus self-action atau invariant (mis. minimum 1 superadmin).
     */
    private const NOT_BYPASSED_BY_SUPERADMIN = [
        'delete',
        'forceDelete',
        'resetPassword',
        'toggleActive',
        'forceLogout',
        'updateMenuOverrides',
    ];

    /**
     * Superadmin bypass — kecuali action yang masuk daftar `NOT_BYPASSED_BY_SUPERADMIN`,
     * yang harus dievaluasi penuh (mis. tetap tidak bisa hapus diri sendiri).
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isSuperadmin()) {
            return null;
        }

        return in_array($ability, self::NOT_BYPASSED_BY_SUPERADMIN, true) ? null : true;
    }

    /**
     * Daftar user (index).
     */
    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    /**
     * Lihat detail user lain.
     * Admin tidak boleh melihat detail superadmin (T02 §2).
     */
    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true; // diri sendiri selalu boleh
        }
        if (! $user->canView(self::MENU)) {
            return false;
        }
        if ($model->role?->code === Role::CODE_SUPERADMIN && ! $user->isSuperadmin()) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    /**
     * Khusus superadmin yang boleh membuat user dengan role superadmin.
     */
    public function createSuperadmin(User $user): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Edit user.
     * - Tidak bisa edit superadmin lain (T02 §2).
     * - Tidak bisa demote diri sendiri dari superadmin (cek di controller/request).
     */
    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }
        if (! $user->canUpdate(self::MENU)) {
            return false;
        }
        if ($model->role?->code === Role::CODE_SUPERADMIN && ! $user->isSuperadmin()) {
            return false;
        }

        return true;
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false; // tidak bisa hapus diri sendiri
        }
        if (! $user->canDelete(self::MENU)) {
            return false;
        }
        if ($model->role?->code === Role::CODE_SUPERADMIN && ! $user->isSuperadmin()) {
            return false;
        }

        return true;
    }

    public function restore(User $user, User $model): bool
    {
        return $user->canDelete(self::MENU);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Reset password user lain (modal di halaman detail).
     */
    public function resetPassword(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false; // pakai flow ganti password sendiri
        }
        if (! $user->canUpdate(self::MENU)) {
            return false;
        }
        if ($model->role?->code === Role::CODE_SUPERADMIN) {
            return false; // admin tidak boleh reset superadmin
        }

        return true;
    }

    /**
     * Toggle status aktif user.
     */
    public function toggleActive(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }
        if (! $user->canUpdate(self::MENU)) {
            return false;
        }
        if ($model->role?->code === Role::CODE_SUPERADMIN && ! $user->isSuperadmin()) {
            return false;
        }

        return true;
    }

    /**
     * Force logout user dari semua device.
     */
    public function forceLogout(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }
        if (! $user->canUpdate(self::MENU)) {
            return false;
        }
        if ($model->role?->code === Role::CODE_SUPERADMIN && ! $user->isSuperadmin()) {
            return false;
        }

        return true;
    }

    /**
     * Atur menu override per user (tab override).
     */
    public function updateMenuOverrides(User $user, User $model): bool
    {
        // Hanya superadmin yang boleh karena override bypass role permission default.
        return $user->isSuperadmin() && $model->role?->code !== Role::CODE_SUPERADMIN;
    }
}
