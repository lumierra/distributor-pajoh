<?php

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\WaNotification;
use App\Models\WaTemplate;
use App\Services\Notification\WhatsAppService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SettingSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function waUser(string $code): User
{
    $uniq = uniqid('', true);

    return User::create([
        'name' => 'U-'.$code.'-'.$uniq,
        'username' => 'u_'.$code.'_'.str_replace('.', '', $uniq),
        'password' => 'secret1234',
        'role_id' => Role::ofCode($code)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);
}

test('send tanpa phone → skipped', function (): void {
    $n = app(WhatsAppService::class)->send('test', ['phone' => '']);
    expect($n->status)->toBe(WaNotification::STATUS_SKIPPED);
    expect($n->skip_reason)->toBe(WaNotification::SKIP_NO_PHONE);
});

test('toggle off → skipped setting_off', function (): void {
    Setting::create([
        'group' => 'notification',
        'key' => 'wa.category.test',
        'type' => 'boolean',
        'label' => 'Test toggle',
        'value' => false,
        'is_sensitive' => false,
    ]);

    $n = app(WhatsAppService::class)->send('test', ['phone' => '08123']);
    expect($n->status)->toBe(WaNotification::STATUS_SKIPPED);
    expect($n->skip_reason)->toBe(WaNotification::SKIP_SETTING_OFF);
});

test('rate limit per customer per day', function (): void {
    Setting::create([
        'group' => 'notification',
        'key' => 'wa.rate_limit_per_day',
        'type' => 'integer',
        'label' => 'Rate limit',
        'value' => 2,
        'is_sensitive' => false,
    ]);

    $svc = app(WhatsAppService::class);
    $svc->send('test', ['phone' => '08111']);
    $svc->send('test', ['phone' => '08111']);
    $third = $svc->send('test', ['phone' => '08111']);

    expect($third->status)->toBe(WaNotification::STATUS_SKIPPED);
    expect($third->skip_reason)->toBe(WaNotification::SKIP_RATE_LIMITED);
});

test('dedup 24h untuk related_ref yang sama', function (): void {
    $tpl = WaTemplate::create([
        'category' => 'test_dedup', 'name' => 'T', 'body' => 'Hi', 'is_active' => true,
    ]);

    $svc = app(WhatsAppService::class);
    $first = $svc->send('test_dedup', ['phone' => '08222'], [], $tpl);
    $second = $svc->send('test_dedup', ['phone' => '08222'], [], $tpl);

    expect($first->status)->not->toBe(WaNotification::STATUS_SKIPPED);
    expect($second->status)->toBe(WaNotification::STATUS_SKIPPED);
    expect($second->skip_reason)->toBe(WaNotification::SKIP_DUPLICATE);
});

test('manual send bypass rate limit + dedup', function (): void {
    Setting::create([
        'group' => 'notification',
        'key' => 'wa.rate_limit_per_day',
        'type' => 'integer',
        'label' => 'Rate limit',
        'value' => 1,
        'is_sensitive' => false,
    ]);

    $svc = app(WhatsAppService::class);
    $svc->send('test', ['phone' => '08333']);
    $manual = $svc->send('test', ['phone' => '08333'], [], null, manual: true);

    expect($manual->is_manual)->toBeTrue();
    expect($manual->status)->not->toBe(WaNotification::STATUS_SKIPPED);
});

test('template renders placeholders', function (): void {
    WaTemplate::create([
        'category' => 'greet', 'name' => 'G', 'body' => 'Halo {name}, total {amount}', 'is_active' => true,
    ]);

    $n = app(WhatsAppService::class)->send('greet', ['phone' => '08444'], ['name' => 'Budi', 'amount' => 100_000]);
    expect($n->message)->toBe('Halo Budi, total 100000');
});

test('retry increment retry_count', function (): void {
    $n = app(WhatsAppService::class)->send('test', ['phone' => '08555']);
    $before = (int) $n->retry_count;

    app(WhatsAppService::class)->retry($n);
    expect((int) $n->fresh()->retry_count)->toBe($before + 1);
});

test('controller index requires admin', function (): void {
    $sales = waUser(Role::CODE_SALES);
    $this->actingAs($sales)->get(route('wa.notifications.index'))->assertForbidden();
});

test('manual send via controller', function (): void {
    $admin = waUser(Role::CODE_SUPERADMIN);

    $this->actingAs($admin)
        ->post(route('wa.notifications.manual-send'), [
            'category' => 'manual',
            'phone' => '08999',
            'recipient_name' => 'Test',
        ])
        ->assertRedirect();

    expect(WaNotification::where('is_manual', true)->count())->toBe(1);
});
