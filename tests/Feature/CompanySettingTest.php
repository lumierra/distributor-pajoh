<?php

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Setting\SettingManager;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

function superadmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

function nonSuperadmin(string $roleCode): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', $roleCode)->value('id'),
        'is_active' => true,
    ]);
}

test('superadmin can open company settings page with all tabs', function (): void {
    $this->actingAs(superadmin())
        ->get(route('settings.company'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Settings/Company')
                ->where('profile.name', 'CV Ananda Berkah Sejahtera - LGS')
                ->where('can.manageAssets', true)
                ->has('assets')
        );
});

test('guest is redirected away from company settings', function (): void {
    $this->get(route('settings.company'))->assertRedirect(route('login'));
});

test('profile update persists and busts the settings cache', function (): void {
    $this->actingAs(superadmin())
        ->post(route('settings.company.profile.update'), [
            'name' => 'CV Pajoh Jaya',
            'city' => 'Langsa',
            'email' => 'admin@pajoh.test',
        ])
        ->assertRedirect();

    // Dibaca lewat manager supaya sekaligus membuktikan cache-nya ikut ter-invalidate.
    $manager = app(SettingManager::class);
    expect($manager->get('company.name'))->toBe('CV Pajoh Jaya')
        ->and($manager->get('company.email'))->toBe('admin@pajoh.test');
});

test('profile update rejects an invalid email and a missing name', function (): void {
    $this->actingAs(superadmin())
        ->post(route('settings.company.profile.update'), ['name' => '', 'email' => 'bukan-email'])
        ->assertSessionHasErrors(['name', 'email']);
});

test('invoice text update persists', function (): void {
    $this->actingAs(superadmin())
        ->post(route('settings.company.invoice-text.update'), [
            'footer_text' => 'Terima kasih.',
            'payment_instruction' => 'Transfer ke rekening berikut:',
        ])
        ->assertRedirect();

    expect(setting('company.invoice_text.footer_text'))->toBe('Terima kasih.');
});

test('settings change is written to the activity log', function (): void {
    $this->actingAs(superadmin())
        ->post(route('settings.company.profile.update'), ['name' => 'CV Baru']);

    $log = ActivityLog::query()->where('action', 'settings.updated')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->context['group'])->toBe('company')
        ->and($log->context['after']['name'])->toBe('CV Baru');
});

test('unchanged values do not produce an activity log entry', function (): void {
    $current = setting('company.name');

    $this->actingAs(superadmin())
        ->post(route('settings.company.profile.update'), ['name' => $current]);

    expect(ActivityLog::query()->where('action', 'settings.updated')->count())->toBe(0);
});

test('uploading an asset stores the file and records the path', function (): void {
    Storage::fake('public');

    $this->actingAs(superadmin())
        ->post(route('settings.company.assets.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 120),
        ])
        ->assertRedirect();

    $path = setting('company.assets.logo_path');

    expect($path)->toStartWith('company/logo/');
    Storage::disk('public')->assertExists($path);
});

test('sensitive asset values are masked in the activity log', function (): void {
    Storage::fake('public');

    $this->actingAs(superadmin())
        ->post(route('settings.company.assets.update'), [
            'signature' => UploadedFile::fake()->image('ttd.png'),
        ]);

    $log = ActivityLog::query()->where('action', 'settings.updated')->latest('id')->firstOrFail();

    // Path TTD bersifat sensitif — nilainya tidak boleh bocor ke log.
    expect($log->context['after']['signature_path'])->toBe('***')
        ->and(setting('company.assets.signature_path'))->toStartWith('company/signature/');
});

test('removing an asset clears the setting and deletes the stored file', function (): void {
    Storage::fake('public');

    $this->actingAs(superadmin())
        ->post(route('settings.company.assets.update'), ['stamp' => UploadedFile::fake()->image('stamp.png')]);

    $path = setting('company.assets.stamp_path');
    Storage::disk('public')->assertExists($path);

    $this->actingAs(superadmin())
        ->post(route('settings.company.assets.update'), ['remove_stamp' => true]);

    expect(setting('company.assets.stamp_path'))->toBe('');
    Storage::disk('public')->assertMissing($path);
});

test('asset upload rejects a non-image file', function (): void {
    Storage::fake('public');

    $this->actingAs(superadmin())
        ->post(route('settings.company.assets.update'), [
            'logo' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('logo');
});

test('non superadmin cannot reach the asset endpoint and gets no asset payload', function (): void {
    $admin = nonSuperadmin(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('settings.company'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.manageAssets', false)->where('assets', null));

    $this->actingAs($admin)
        ->post(route('settings.company.assets.update'), ['show_stamp_on_invoice' => false])
        ->assertForbidden();
});

test('a role without the settings menu cannot view the page', function (): void {
    $this->actingAs(nonSuperadmin(Role::CODE_SALES))
        ->get(route('settings.company'))
        ->assertForbidden();
});

test('the seeded company settings cover every group the page edits', function (): void {
    expect(Setting::ofGroup('company')->count())->toBe(12)
        ->and(Setting::ofGroup('company.assets')->count())->toBe(5)
        ->and(Setting::ofGroup('company.invoice_text')->count())->toBe(4);
});
