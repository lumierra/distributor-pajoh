<?php

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMenuOverride;
use App\Services\Permission\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->resolver = app(PermissionResolver::class);
});

function makeUserOfRole(string $roleCode): User
{
    return User::create([
        'name' => 'U-'.$roleCode,
        'username' => 'u_'.$roleCode,
        'password' => 'secret1234',
        'role_id' => Role::ofCode($roleCode)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);
}

test('superadmin bypass returns true for every menu', function (): void {
    $user = makeUserOfRole(Role::CODE_SUPERADMIN);

    expect($this->resolver->resolve($user, 'view', 'settings.system'))->toBeTrue();
    expect($this->resolver->resolve($user, 'delete', 'master.user'))->toBeTrue();
    expect($this->resolver->resolve($user, 'view', 'does.not.exist'))->toBeTrue();
});

test('admin has view+create+update on master.supplier but not delete', function (): void {
    $admin = makeUserOfRole(Role::CODE_ADMIN);

    expect($this->resolver->resolve($admin, 'view', 'master.supplier'))->toBeTrue();
    expect($this->resolver->resolve($admin, 'create', 'master.supplier'))->toBeTrue();
    expect($this->resolver->resolve($admin, 'update', 'master.supplier'))->toBeTrue();
    expect($this->resolver->resolve($admin, 'delete', 'master.supplier'))->toBeFalse();
});

test('kasir cannot view master.supplier (no role permission row)', function (): void {
    $kasir = makeUserOfRole(Role::CODE_KASIR);

    expect($this->resolver->resolve($kasir, 'view', 'master.supplier'))->toBeFalse();
});

test('user-level override TRUE grants access even when role denies', function (): void {
    $kasir = makeUserOfRole(Role::CODE_KASIR);

    $menu = Menu::query()->where('code', 'master.supplier')->first();
    UserMenuOverride::create([
        'user_id' => $kasir->id,
        'menu_id' => $menu->id,
        'can_view' => true,
    ]);

    $this->resolver->invalidateCache($kasir);

    expect($this->resolver->resolve($kasir, 'view', 'master.supplier'))->toBeTrue();
});

test('user-level override FALSE denies access even when role grants', function (): void {
    $admin = makeUserOfRole(Role::CODE_ADMIN);

    $menu = Menu::query()->where('code', 'master.supplier')->first();
    UserMenuOverride::create([
        'user_id' => $admin->id,
        'menu_id' => $menu->id,
        'can_view' => false,
    ]);

    $this->resolver->invalidateCache($admin);

    expect($this->resolver->resolve($admin, 'view', 'master.supplier'))->toBeFalse();
});

test('override NULL falls through to role permission', function (): void {
    $admin = makeUserOfRole(Role::CODE_ADMIN);

    $menu = Menu::query()->where('code', 'master.supplier')->first();
    UserMenuOverride::create([
        'user_id' => $admin->id,
        'menu_id' => $menu->id,
        'can_view' => null,
        'can_delete' => null,
    ]);

    $this->resolver->invalidateCache($admin);

    expect($this->resolver->resolve($admin, 'view', 'master.supplier'))->toBeTrue();
    expect($this->resolver->resolve($admin, 'delete', 'master.supplier'))->toBeFalse();
});

test('buildCache caches the matrix; invalidateCache clears it', function (): void {
    $admin = makeUserOfRole(Role::CODE_ADMIN);

    $first = $this->resolver->buildCache($admin);
    expect(Cache::has("perm:user:{$admin->id}"))->toBeTrue();

    $this->resolver->invalidateCache($admin);
    expect(Cache::has("perm:user:{$admin->id}"))->toBeFalse();

    $second = $this->resolver->buildCache($admin);
    expect($second)->toEqual($first);
});

test('User::hasPermission matches PermissionResolver::resolve', function (): void {
    $admin = makeUserOfRole(Role::CODE_ADMIN);

    expect($admin->hasPermission('view', 'master.supplier'))
        ->toBe($this->resolver->resolve($admin, 'view', 'master.supplier'));

    expect($admin->canView('master.supplier'))->toBeTrue();
    expect($admin->canDelete('master.supplier'))->toBeFalse();
});
