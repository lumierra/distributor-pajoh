<?php

use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function poPolicyUser(string $roleCode): User
{
    $uniq = uniqid('', true);

    return User::create([
        'name' => 'U-'.$roleCode.'-'.$uniq,
        'username' => 'u_'.$roleCode.'_'.str_replace('.', '', $uniq),
        'password' => 'secret1234',
        'role_id' => Role::ofCode($roleCode)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);
}

function buildEmptyPo(): PurchaseOrder
{
    $supplier = Supplier::create([
        'code' => 'SUP-POL-'.random_int(1000, 9999),
        'name' => 'Supplier Policy',
        'is_active' => true,
    ]);

    return PurchaseOrder::create([
        'po_number' => 'PO-POL-'.random_int(1000, 9999),
        'supplier_id' => $supplier->id,
        'po_date' => now()->toDateString(),
        'fiscal_year' => (int) now()->format('Y'),
        'status' => PurchaseOrder::STATUS_DRAFT,
    ]);
}

test('admin bisa view list PO', function (): void {
    $admin = poPolicyUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('purchase-orders.index'))
        ->assertOk();
});

test('admin tidak bisa create PO', function (): void {
    $admin = poPolicyUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('purchase-orders.create'))
        ->assertForbidden();
});

test('admin tidak bisa approve/cancel/close PO', function (): void {
    $admin = poPolicyUser(Role::CODE_ADMIN);
    $po = buildEmptyPo();

    $this->actingAs($admin)
        ->post(route('purchase-orders.approve', $po))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('purchase-orders.cancel', $po), ['cancel_reason' => 'mau cancel saja'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('purchase-orders.close', $po), ['close_reason' => 'mau close saja'])
        ->assertForbidden();
});

test('kasir tidak bisa lihat PO', function (): void {
    $kasir = poPolicyUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('purchase-orders.index'))
        ->assertForbidden();
});

test('admin bisa download PDF kalau ada', function (): void {
    $admin = poPolicyUser(Role::CODE_ADMIN);
    $po = buildEmptyPo();

    // Tanpa PDF → 404
    $this->actingAs($admin)
        ->get(route('purchase-orders.pdf', $po))
        ->assertNotFound();
});
