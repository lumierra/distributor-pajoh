<?php

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\PriceTier;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CustomerTypeSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PriceTierSeeder;
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
    $this->seed(PriceTierSeeder::class);
    $this->seed(CustomerTypeSeeder::class);
});

function alUser(string $code): User
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

test('activity log auto-recorded on model create', function (): void {
    $admin = alUser(Role::CODE_SUPERADMIN);
    $this->actingAs($admin);

    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-AL-'.random_int(1000, 9999),
        'name' => 'Cust AL',
        'price_tier_id' => $tier->id,
        'credit_limit' => 100_000,
        'payment_term_days' => 7,
        'is_active' => true,
    ]);

    $logs = ActivityLog::query()
        ->where('model_type', Customer::class)
        ->where('model_id', $customer->id)
        ->get();
    expect($logs->count())->toBeGreaterThan(0);
});

test('activity log index requires permission', function (): void {
    $sales = alUser(Role::CODE_SALES);
    $this->actingAs($sales)->get(route('activity-logs.index'))->assertForbidden();

    $admin = alUser(Role::CODE_SUPERADMIN);
    $this->actingAs($admin)->get(route('activity-logs.index'))->assertOk();
});

test('trash index hanya untuk superadmin', function (): void {
    $admin = alUser(Role::CODE_ADMIN);
    $this->actingAs($admin)->get(route('trash.index', ['type' => 'customers']))->assertForbidden();

    $superadmin = alUser(Role::CODE_SUPERADMIN);
    $this->actingAs($superadmin)->get(route('trash.index', ['type' => 'customers']))->assertOk();
});

test('restore soft-deleted customer dari trash', function (): void {
    $superadmin = alUser(Role::CODE_SUPERADMIN);
    $this->actingAs($superadmin);

    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-TR-'.random_int(1000, 9999),
        'name' => 'To Trash',
        'price_tier_id' => $tier->id,
        'credit_limit' => 100_000,
        'payment_term_days' => 7,
        'is_active' => true,
    ]);
    $customer->delete();
    expect(Customer::find($customer->id))->toBeNull();
    expect(Customer::withTrashed()->find($customer->id))->not->toBeNull();

    $this->post(route('trash.restore', ['type' => 'customers', 'id' => $customer->id]))->assertRedirect();
    expect(Customer::find($customer->id))->not->toBeNull();
});
