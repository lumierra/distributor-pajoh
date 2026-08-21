<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tanpa X-Inertia-Version yang cocok, Laravel Inertia menganggap client
 * "outdated" dan membalas 409 (X-Inertia-Location) supaya browser full-reload —
 * itu bukan bug, tapi test butuh header ini supaya request ke-2/ke-3 dianggap
 * navigasi Inertia client-side biasa, bukan initial load.
 */
function currentInertiaAssetVersion(): string
{
    return app(HandleInertiaRequests::class)->version(request());
}

beforeEach(function (): void {
    $this->seed([
        SettingSeeder::class,
        RoleSeeder::class,
        MenuSeeder::class,
        RolePermissionSeeder::class,
        SuperadminSeeder::class,
    ]);
});

function flashSuperadmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

test('flash success does not leak into the router.reload() request that follows a create', function (): void {
    $superadmin = flashSuperadmin();
    $version = currentInertiaAssetVersion();

    // Request #1: create driver — controller does back()->with('flash.success', ...) and
    // replies with a 302 redirect. Inertia's XHR client follows this redirect itself as
    // a normal GET, which is the FIRST time the flash actually reaches an Inertia render.
    $createResponse = $this->actingAs($superadmin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'Referer' => route('drivers.index')])
        ->post(route('drivers.store'), [
            'name' => 'Andi Supir',
            'whatsapp' => '081234567890',
            'is_active' => true,
        ]);

    $createResponse->assertSessionHas('flash.success');
    $createResponse->assertRedirect(route('drivers.index'));

    // Request #2: Inertia auto-following the redirect from request #1 — this is the
    // toast's first (and only correct) appearance.
    $followResponse = $this->actingAs($superadmin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'Referer' => route('drivers.index')])
        ->get(route('drivers.index'));
    $followPayload = json_decode($followResponse->getContent(), true);
    expect($followPayload['props']['flash']['success'] ?? null)->not->toBeNull();

    // Request #3: the page's onSaved() then calls router.reload({ only: [...] }) right
    // after. Laravel's session flash reflash window would normally still carry the
    // message into this next request too — the middleware must pull() it on request #2
    // so it does NOT surface a second time here.
    $reloadResponse = $this->actingAs($superadmin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'Referer' => route('drivers.index')])
        ->get(route('drivers.index'));

    // assertInertia() assumes a full Blade page load (assertViewHas('page')); an
    // XHR request with X-Inertia:true gets a raw JSON body instead, so decode it directly.
    $reloadResponse->assertOk();
    $reloadPayload = json_decode($reloadResponse->getContent(), true);

    expect($reloadPayload['props']['flash']['success'] ?? null)->toBeNull();
});

test('flash success still surfaces on a genuinely new action even if the message text is identical', function (): void {
    $superadmin = flashSuperadmin();
    $version = currentInertiaAssetVersion();
    $driver = Driver::create([
        'code' => 'DRV-TEST1',
        'name' => 'Budi Supir',
        'is_active' => true,
        'status' => 'idle',
    ]);

    // Two independent toggle-active calls in a row (e.g. user double-clicks Suspend then
    // Activate) each flash their own message in their own request — pull() must not
    // suppress the second one just because AppLayout already showed one earlier.
    $first = $this->actingAs($superadmin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'Referer' => route('drivers.index')])
        ->post(route('drivers.toggle-active', $driver));
    $first->assertSessionHas('flash.success');

    $driver->refresh();

    $second = $this->actingAs($superadmin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'Referer' => route('drivers.index')])
        ->post(route('drivers.toggle-active', $driver));
    $second->assertSessionHas('flash.success');
});
