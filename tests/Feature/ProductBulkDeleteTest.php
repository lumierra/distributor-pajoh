<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Product\ProductService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperadminSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        SettingSeeder::class,
        RoleSeeder::class,
        MenuSeeder::class,
        RolePermissionSeeder::class,
        SuperadminSeeder::class,
        UnitSeeder::class,
    ]);
});

function bulkAdmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

/** Bikin N produk sederhana (1 satuan PCS) untuk uji bulk delete. */
function makeProducts(int $n, string $prefix = 'BULK'): Collection
{
    $admin = bulkAdmin();
    $supplier = Supplier::create(['code' => "SUP-{$prefix}", 'name' => "Supplier {$prefix}", 'is_active' => true, 'created_by' => $admin->id]);
    $pcs = Unit::query()->where('name', 'PCS')->value('id');

    $products = collect();
    for ($i = 1; $i <= $n; $i++) {
        $products->push(app(ProductService::class)->create(
            ['supplier_id' => $supplier->id, 'sku' => "{$prefix}-{$i}", 'name' => "Produk {$prefix} {$i}", 'is_active' => true, 'created_by' => $admin->id],
            [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
            [['name' => 'Harga Reguler', 'items' => [['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500]]]],
        ));
    }

    return $products;
}

test('bulk destroy menghapus banyak produk sekaligus (soft delete)', function (): void {
    $admin = bulkAdmin();
    $products = makeProducts(3);
    $ids = $products->pluck('id')->all();

    $this->actingAs($admin)
        ->post(route('products.bulk-destroy'), ['ids' => $ids])
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('flash.success');

    foreach ($ids as $id) {
        expect(Product::find($id))->toBeNull(); // soft-deleted → tak muncul di query default
        expect(Product::withTrashed()->find($id))->not->toBeNull();
        expect(Product::withTrashed()->find($id)->deleted_at)->not->toBeNull();
    }
});

test('bulk destroy hanya menghapus id yang dikirim, produk lain tetap ada', function (): void {
    $admin = bulkAdmin();
    $products = makeProducts(3);
    $toDelete = $products->take(2)->pluck('id')->all();
    $untouched = $products->last();

    $this->actingAs($admin)
        ->post(route('products.bulk-destroy'), ['ids' => $toDelete])
        ->assertRedirect();

    expect(Product::find($untouched->id))->not->toBeNull();
});

test('bulk destroy ditolak tanpa ids (validasi)', function (): void {
    $admin = bulkAdmin();

    $this->actingAs($admin)
        ->post(route('products.bulk-destroy'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});

test('bulk destroy ditolak untuk id yang tidak ada', function (): void {
    $admin = bulkAdmin();
    $product = makeProducts(1)->first();

    $this->actingAs($admin)
        ->post(route('products.bulk-destroy'), ['ids' => [$product->id, 999999]])
        ->assertSessionHasErrors('ids.1');
});

test('user tanpa izin delete produk dilewati (tidak ikut terhapus)', function (): void {
    $admin = bulkAdmin();
    $products = makeProducts(2);

    // Role admin: master.product punya view/create/update tapi TIDAK delete.
    $adminRole = Role::query()->where('code', Role::CODE_ADMIN)->firstOrFail();
    $nonSuper = User::factory()->create(['role_id' => $adminRole->id]);

    $ids = $products->pluck('id')->all();
    $this->actingAs($nonSuper)
        ->post(route('products.bulk-destroy'), ['ids' => $ids])
        ->assertRedirect()
        ->assertSessionHas('flash.error');

    // Tidak ada yang terhapus karena role admin tak punya permission delete.
    foreach ($ids as $id) {
        expect(Product::find($id))->not->toBeNull();
    }
});
