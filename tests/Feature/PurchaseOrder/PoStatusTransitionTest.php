<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Product\ProductService;
use App\Services\Purchasing\PoPdfRenderer;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\PoStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ProductCategorySeeder::class);
    $this->seed(\Database\Seeders\PriceTierSeeder::class);

    // Stub PoPdfRenderer agar tidak menulis file PDF saat test
    $this->app->bind(PoPdfRenderer::class, function ($app) {
        return new class($app->make(\App\Services\Setting\SettingManager::class)) extends PoPdfRenderer {
            public function generate(PurchaseOrder $po): string
            {
                $po->update([
                    'pdf_path' => "purchase_orders/{$po->fiscal_year}/{$po->po_number}.pdf",
                    'pdf_generated_at' => now(),
                ]);

                return $po->pdf_path;
            }
        };
    });
});

function poTransUser(string $roleCode): User
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

function buildDraftPo(User $by): PurchaseOrder
{
    $supplier = Supplier::create([
        'code' => 'SUP-TR-'.random_int(1000, 9999),
        'name' => 'Supplier TR',
        'is_active' => true,
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk TR-'.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    SupplierProduct::create([
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'default_cost_price' => 1000,
        'is_active' => true,
    ]);

    return app(PurchaseOrderService::class)->createDraft(
        [
            'supplier_id' => $supplier->id,
            'po_date' => now()->format('Y-m-d'),
        ],
        [[
            'product_id' => $product->id,
            'product_unit_id' => $product->units->first()->id,
            'qty_ordered' => 10,
            'cost_price' => 1000,
        ]],
        $by,
    );
}

test('approve set status approved + supplier_snapshot + PDF', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);

    $this->actingAs($sa)
        ->post(route('purchase-orders.approve', $po))
        ->assertRedirect();

    $po->refresh();
    expect($po->status)->toBe(PurchaseOrder::STATUS_APPROVED);
    expect($po->supplier_snapshot)->toBeArray();
    expect($po->supplier_snapshot['name'])->toBe('Supplier TR');
    expect($po->pdf_path)->not->toBeNull();
});

test('edit setelah approved ditolak', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);
    app(PurchaseOrderService::class)->approve($po, $sa);

    $this->actingAs($sa)
        ->get(route('purchase-orders.edit', $po))
        ->assertStatus(422);
});

test('cancel draft sukses', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);

    $this->actingAs($sa)
        ->post(route('purchase-orders.cancel', $po), [
            'cancel_reason' => 'Salah supplier, batal pesan.',
        ])
        ->assertRedirect();

    $po->refresh();
    expect($po->status)->toBe(PurchaseOrder::STATUS_CANCELLED);
    expect($po->cancel_reason)->toBe('Salah supplier, batal pesan.');
});

test('cancel approved tanpa GRN sukses', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);
    app(PurchaseOrderService::class)->approve($po, $sa);

    $this->actingAs($sa)
        ->post(route('purchase-orders.cancel', $po), [
            'cancel_reason' => 'Supplier delay, cancel.',
        ])
        ->assertRedirect();

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_CANCELLED);
});

test('cancel approved dengan qty_received > 0 ditolak', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);
    app(PurchaseOrderService::class)->approve($po, $sa);

    $po->items->first()->update(['qty_received' => 5]);

    expect(fn () => app(PurchaseOrderService::class)->cancel($po, 'mau cancel', $sa))
        ->toThrow(ValidationException::class);
});

test('close approved sukses dengan reason', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);
    app(PurchaseOrderService::class)->approve($po, $sa);

    $this->actingAs($sa)
        ->post(route('purchase-orders.close', $po), [
            'close_reason' => 'Supplier short-shipped, terima apa adanya.',
        ])
        ->assertRedirect();

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_CLOSED);
});

test('close draft ditolak', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);

    $this->actingAs($sa)
        ->from(route('purchase-orders.show', $po))
        ->post(route('purchase-orders.close', $po), [
            'close_reason' => 'Coba close draft padahal ga boleh.',
        ])
        ->assertSessionHasErrors('status');
});

test('PoStatusResolver set partial_received ketika sebagian qty diterima', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);
    app(PurchaseOrderService::class)->approve($po, $sa);

    $po->items->first()->update(['qty_received' => 3]); // partial dari 10

    app(PoStatusResolver::class)->resolveAfterGrnChange($po);

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIAL_RECEIVED);
});

test('PoStatusResolver set closed ketika semua qty diterima', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);
    app(PurchaseOrderService::class)->approve($po, $sa);

    $po->items->first()->update(['qty_received' => 10]); // full

    app(PoStatusResolver::class)->resolveAfterGrnChange($po);

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_CLOSED);
});

test('PoStatusResolver tidak mengubah PO yang sudah cancelled/closed', function (): void {
    $sa = poTransUser(Role::CODE_SUPERADMIN);
    $po = buildDraftPo($sa);
    app(PurchaseOrderService::class)->approve($po, $sa);
    $po->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

    $po->items->first()->update(['qty_received' => 5]);

    app(PoStatusResolver::class)->resolveAfterGrnChange($po);

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_CANCELLED);
});
