<?php

namespace App\Services\Permission;

use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleMenu;
use App\Models\User;
use App\Models\UserMenuOverride;
use Illuminate\Support\Facades\Cache;

class PermissionResolver
{
    public const ACTIONS = ['view', 'create', 'update', 'delete', 'approve', 'export'];

    private const CACHE_TTL_SECONDS = 3600;

    public function resolve(User $user, string $action, string $menuCode): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        $matrix = $this->buildCache($user);

        return (bool) ($matrix[$menuCode][$action] ?? false);
    }

    /**
     * Build a permission matrix for the user keyed by menu code.
     *
     * @return array<string, array<string, bool>>
     */
    public function buildCache(User $user): array
    {
        return Cache::remember(
            $this->cacheKey($user->id),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->compileMatrix($user),
        );
    }

    public function invalidateCache(User $user): void
    {
        Cache::forget($this->cacheKey($user->id));
    }

    /**
     * Drop the cache for every user in a role — call after role permissions change.
     */
    public function invalidateRole(Role $role): void
    {
        $role->users()->pluck('id')->each(function (int $userId): void {
            Cache::forget($this->cacheKey($userId));
        });
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function compileMatrix(User $user): array
    {
        $matrix = [];

        $rolePerms = RoleMenu::query()
            ->where('role_id', $user->role_id)
            ->get();

        $menuIds = $rolePerms->pluck('menu_id')->all();
        $menus = Menu::query()->whereIn('id', $menuIds)->get()->keyBy('id');

        foreach ($rolePerms as $perm) {
            $menu = $menus->get($perm->menu_id);
            if (! $menu) {
                continue;
            }
            $matrix[$menu->code] = $this->extractActions($perm);
        }

        $overrides = UserMenuOverride::query()
            ->where('user_id', $user->id)
            ->with('menu:id,code')
            ->get();

        foreach ($overrides as $override) {
            $code = $override->menu?->code;
            if (! $code) {
                continue;
            }
            $row = $matrix[$code] ?? array_fill_keys(self::ACTIONS, false);
            foreach (self::ACTIONS as $action) {
                $value = $override->{"can_{$action}"};
                if ($value !== null) {
                    $row[$action] = (bool) $value;
                }
            }
            $matrix[$code] = $row;
        }

        return $matrix;
    }

    /**
     * @return array<string, bool>
     */
    private function extractActions(RoleMenu $perm): array
    {
        $row = [];
        foreach (self::ACTIONS as $action) {
            $row[$action] = (bool) $perm->{"can_{$action}"};
        }

        return $row;
    }

    private function cacheKey(int $userId): string
    {
        return "perm:user:{$userId}";
    }
}
