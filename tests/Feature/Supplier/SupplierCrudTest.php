<?php

use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\SupplierCategorySeeder::class);
});

function supplierCrudUser(string $roleCode): User
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

test('admin bisa list supplier', function (): void {
    $admin = supplierCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('suppliers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Suppliers/Index'));
});

test('kasir tidak bisa list supplier', function (): void {
    $kasir = supplierCrudUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('suppliers.index'))
        ->assertForbidden();
});

test('admin bisa create supplier dengan kode auto-generate', function (): void {
    $admin = supplierCrudUser(Role::CODE_ADMIN);
    $catId = SupplierCategory::query()->first()->id;

    $this->actingAs($admin)
        ->post(route('suppliers.store'), [
            'name' => 'PT Test Supplier',
            'supplier_category_id' => $catId,
            'phone' => '021-12345678',
        ])
        ->assertRedirect();

    $supplier = Supplier::where('name', 'PT Test Supplier')->first();
    expect($supplier)->not->toBeNull();
    expect($supplier->code)->toMatch('/^SUP-\d{4}$/');
    expect($supplier->is_active)->toBeTrue();
});

test('operator tidak bisa create supplier', function (): void {
    $operator = supplierCrudUser(Role::CODE_OPERATOR);

    $this->actingAs($operator)
        ->post(route('suppliers.store'), [
            'name' => 'Hack',
        ])
        ->assertForbidden();
});

test('NPWP regex divalidasi', function (): void {
    $admin = supplierCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->post(route('suppliers.store'), [
            'name' => 'Bad NPWP',
            'npwp' => 'invalid npwp###',
        ])
        ->assertSessionHasErrors('npwp');
});

test('admin bisa update data supplier', function (): void {
    $admin = supplierCrudUser(Role::CODE_ADMIN);
    $supplier = app(\App\Services\Supplier\SupplierService::class)->create([
        'name' => 'PT Lama',
    ]);

    $this->actingAs($admin)
        ->put(route('suppliers.update', $supplier->id), [
            'name' => 'PT Baru',
            'phone' => '081111111111',
        ])
        ->assertRedirect();

    $supplier->refresh();
    expect($supplier->name)->toBe('PT Baru');
    expect($supplier->phone)->toBe('081111111111');
});

test('superadmin bisa soft-delete supplier saat belum ada transaksi', function (): void {
    $super = supplierCrudUser(Role::CODE_SUPERADMIN);
    $supplier = app(\App\Services\Supplier\SupplierService::class)->create([
        'name' => 'PT Hapus',
    ]);

    $this->actingAs($super)
        ->delete(route('suppliers.destroy', $supplier->id))
        ->assertRedirect(route('suppliers.index'));

    expect(Supplier::withTrashed()->find($supplier->id)->trashed())->toBeTrue();
});

test('admin TIDAK bisa delete supplier (sesuai role_menu matrix)', function (): void {
    $admin = supplierCrudUser(Role::CODE_ADMIN);
    $supplier = app(\App\Services\Supplier\SupplierService::class)->create([
        'name' => 'PT Locked',
    ]);

    $this->actingAs($admin)
        ->delete(route('suppliers.destroy', $supplier->id))
        ->assertForbidden();
});

test('toggle active mengubah is_active', function (): void {
    $admin = supplierCrudUser(Role::CODE_ADMIN);
    $supplier = app(\App\Services\Supplier\SupplierService::class)->create([
        'name' => 'PT Toggle',
    ]);
    expect($supplier->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->post(route('suppliers.toggle-active', $supplier->id))
        ->assertRedirect();

    expect($supplier->fresh()->is_active)->toBeFalse();
});

test('supplier kode unik & sequential', function (): void {
    $service = app(\App\Services\Supplier\SupplierService::class);

    $s1 = $service->create(['name' => 'S1']);
    $s2 = $service->create(['name' => 'S2']);
    $s3 = $service->create(['name' => 'S3']);

    expect([$s1->code, $s2->code, $s3->code])
        ->toBe(['SUP-0001', 'SUP-0002', 'SUP-0003']);
});

test('show menampilkan supplier dengan bank & document', function (): void {
    $admin = supplierCrudUser(Role::CODE_ADMIN);
    $supplier = app(\App\Services\Supplier\SupplierService::class)->create([
        'name' => 'PT Show',
    ]);

    $this->actingAs($admin)
        ->get(route('suppliers.show', $supplier->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Suppliers/Show')
            ->where('supplier.id', $supplier->id));
});
