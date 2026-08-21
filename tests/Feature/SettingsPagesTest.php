<?php

use App\Models\ActivityLog;
use App\Models\CompanyBankAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Setting\SettingManager;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        SettingSeeder::class,
        RoleSeeder::class,
        MenuSeeder::class,
        RolePermissionSeeder::class,
        SuperadminSeeder::class,
    ]);
});

function settingsAdmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

test('halaman settings berbasis-key render tanpa error', function (string $route, string $component): void {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component)->has('groups')->has('can'));
})->with([
    'numbering' => ['settings.numbering', 'Settings/Numbering'],
    'sales' => ['settings.sales', 'Settings/Sales'],
    'inventory' => ['settings.inventory', 'Settings/Inventory'],
    'system' => ['settings.system', 'Settings/System'],
]);

test('simpan setting sales & mobile mengubah nilai + tercatat di activity log', function (): void {
    $admin = settingsAdmin();
    $settings = app(SettingManager::class);

    // Nilai awal geofence radius (int) & enabled (bool).
    $before = (int) $settings->get('sales.geofence.radius_meter');

    $this->actingAs($admin)->post(route('settings.sales'), [
        'sales' => [
            'geofence.radius_meter' => $before + 50,
            'geofence.enabled' => true,
        ],
    ])->assertRedirect();

    $settings->forgetCache();
    expect((int) $settings->get('sales.geofence.radius_meter'))->toBe($before + 50);

    // Tercatat di activity_logs.
    expect(DB::table('activity_logs')->where('action', 'settings.updated')->exists())->toBeTrue();
});

test('field readonly tidak bisa diubah lewat form', function (): void {
    $admin = settingsAdmin();
    $settings = app(SettingManager::class);

    $tzBefore = (string) $settings->get('system.timezone');

    $this->actingAs($admin)->post(route('settings.system'), [
        'system' => [
            'timezone' => 'Asia/Jakarta_HACKED',
        ],
    ])->assertRedirect();

    $settings->forgetCache();
    // Timezone readonly → tetap.
    expect((string) $settings->get('system.timezone'))->toBe($tzBefore);
});

test('numbering: ubah format PO tersimpan', function (): void {
    $admin = settingsAdmin();
    $settings = app(SettingManager::class);

    $this->actingAs($admin)->post(route('settings.numbering'), [
        'numbering' => [
            'po.format' => 'PO/{YY}{MM}/{seq:04d}',
        ],
    ])->assertRedirect();

    $settings->forgetCache();
    expect((string) $settings->get('numbering.po.format'))->toBe('PO/{YY}{MM}/{seq:04d}');
});

test('non-superadmin tanpa izin tak bisa buka settings', function (): void {
    // Buat user kasir (tidak punya izin settings).
    $kasir = User::factory()->create([
        'role_id' => Role::query()->where('code', Role::CODE_KASIR)->value('id'),
    ]);

    $this->actingAs($kasir)->get(route('settings.sales'))->assertForbidden();
});

test('rekening bank: CRUD lengkap', function (): void {
    $admin = settingsAdmin();

    // Create.
    $this->actingAs($admin)->post(route('settings.bank-accounts.store'), [
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'CV Pajoh',
        'is_active' => true,
        'show_on_invoice' => true,
    ])->assertRedirect();

    $acc = CompanyBankAccount::firstOrFail();
    expect($acc->bank_name)->toBe('BCA');

    // Update.
    $this->actingAs($admin)->put(route('settings.bank-accounts.update', $acc->id), [
        'bank_name' => 'BCA Prioritas',
        'account_number' => '1234567890',
        'account_holder' => 'CV Pajoh',
        'is_active' => true,
        'show_on_invoice' => false,
    ])->assertRedirect();
    expect($acc->refresh()->bank_name)->toBe('BCA Prioritas');
    expect($acc->show_on_invoice)->toBeFalse();

    // Delete (soft).
    $this->actingAs($admin)->delete(route('settings.bank-accounts.destroy', $acc->id))->assertRedirect();
    expect(CompanyBankAccount::count())->toBe(0);
});

test('log sensitif render + hanya memuat aksi sensitif', function (): void {
    $admin = settingsAdmin();

    // Bikin 1 aksi sensitif (settings.updated) & 1 non-sensitif.
    app(ActivityLogger::class)->logAction('settings.updated', ['group' => 'sales']);
    ActivityLog::create(['action' => 'created', 'model_type' => 'App\\Models\\Product', 'user_name_snapshot' => 'x']);

    $this->actingAs($admin)
        ->get(route('settings.sensitive-log'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/SensitiveLog')
            ->has('logs.data', 1) // hanya yang sensitif
            ->where('logs.data.0.action', 'settings.updated')
        );
});
