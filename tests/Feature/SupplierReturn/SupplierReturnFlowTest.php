<?php

use App\Models\GoodsReceipt;
use App\Models\GrnItem;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\Product\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\GrnPdfRenderer;
use App\Services\Purchasing\PoPdfRenderer;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Setting\SettingManager;
use App\Services\SupplierReturn\SupplierReturnService;
use App\Services\SupplierReturn\SupplierReturnSourceResolver;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PriceTierSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SettingSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ProductCategorySeeder::class);
    $this->seed(PriceTierSeeder::class);

    $this->app->bind(PoPdfRenderer::class, function ($app) {
        return new class($app->make(SettingManager::class)) extends PoPdfRenderer
        {
            public function generate(PurchaseOrder $po): string
            {
                $po->update(['pdf_path' => 'stub.pdf', 'pdf_generated_at' => now()]);

                return $po->pdf_path;
            }
        };
    });
    $this->app->bind(GrnPdfRenderer::class, function ($app) {
        return new class($app->make(SettingManager::class)) extends GrnPdfRenderer
        {
            public function generate(GoodsReceipt $grn): string
            {
                $grn->update(['pdf_path' => 'stub.pdf', 'pdf_generated_at' => now()]);

                return $grn->pdf_path;
            }
        };
    });
});

function srUser(string $roleCode): User
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

function srSetupGrnDamaged(int $qtyDamaged = 5): array
{
    $admin = srUser(Role::CODE_SUPERADMIN);
    $operator = srUser(Role::CODE_OPERATOR);

    $supplier = Supplier::create([
        'code' => 'SUP-SR-'.random_int(1000, 9999),
        'name' => 'Supplier SR',
        'is_active' => true,
        'payment_term_days' => 7,
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk SR '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $unit = $product->units->first();

    SupplierProduct::create([
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'default_cost_price' => 5000,
        'is_active' => true,
    ]);

    $po = app(PurchaseOrderService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'po_date' => now()->toDateString()],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty_ordered' => 100, 'cost_price' => 5000]],
        $admin,
    );
    app(PurchaseOrderService::class)->approve($po, $admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'batch_code' => 'B-SR-'.random_int(1000, 9999),
            'qty_reguler' => 30,
            'qty_damaged' => $qtyDamaged,
            'cost_price' => 5000,
            'condition' => 'mixed',
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn, $operator);
    app(GoodsReceiptService::class)->post($grn, $admin);

    $grnItem = $grn->fresh()->items->first();

    return compact('supplier', 'product', 'unit', 'grn', 'grnItem', 'admin', 'operator');
}

test('create SR draft dari GRN damaged', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 5);

    $sr = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'source_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 5,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);

    expect($sr->return_number)->toStartWith('RTR-S-');
    expect($sr->status)->toBe(SupplierReturn::STATUS_DRAFT);
    expect((float) $sr->claim_amount)->toBe(25_000.0);
});

test('qty > available di GRN ditolak saat create', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 5);

    expect(fn () => app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 10, // > 5 damaged
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']))->toThrow(ValidationException::class);
});

test('GRN dari supplier lain ditolak', function (): void {
    $ctx = srSetupGrnDamaged();
    $otherSupplier = Supplier::create([
        'code' => 'SUP-OTHER-'.random_int(1000, 9999),
        'name' => 'Other Supplier',
        'is_active' => true,
        'payment_term_days' => 7,
    ]);

    expect(fn () => app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $otherSupplier->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 1,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']))->toThrow(ValidationException::class);
});

test('approve → source tracking incremented', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 5);

    $sr = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 3,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);

    app(SupplierReturnService::class)->approve($sr, $ctx['admin']);

    expect($sr->fresh()->status)->toBe(SupplierReturn::STATUS_APPROVED);
    expect((int) GrnItem::find($ctx['grnItem']->id)->qty_returned_to_supplier)->toBe(3);
});

test('double-claim same GRN damaged ditolak', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 5);

    $sr1 = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 4,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);
    app(SupplierReturnService::class)->approve($sr1, $ctx['admin']);

    // Hanya 1 sisa, klaim 2 → 422
    expect(fn () => app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 2,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']))->toThrow(ValidationException::class);
});

test('mark sent dari GRN damaged tidak ada stock impact', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 5);

    $sr = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 3,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);
    app(SupplierReturnService::class)->approve($sr, $ctx['admin']);

    // Get balance saat ini
    $batch = ProductBatch::query()->where('product_id', $ctx['product']->id)->first();
    $balanceBefore = (int) (StockBalance::query()->where('batch_id', $batch->id)->value('qty_on_hand') ?? 0);

    app(SupplierReturnService::class)->markSent($sr->refresh(), null, $ctx['admin']);

    $balanceAfter = (int) (StockBalance::query()->where('batch_id', $batch->id)->value('qty_on_hand') ?? 0);

    expect($sr->fresh()->status)->toBe(SupplierReturn::STATUS_SENT);
    expect($balanceAfter)->toBe($balanceBefore); // no stock impact
});

test('mark sent dari stock → stock_ledger return_out + balance decrement', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 0);

    // Set supplier_id pada batch supaya bisa di-claim
    $batch = ProductBatch::query()->where('product_id', $ctx['product']->id)->first();
    $batch->update(['supplier_id' => $ctx['supplier']->id]);

    $sr = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'stock',
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'batch_id' => $batch->id,
            'qty' => 5,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);
    app(SupplierReturnService::class)->approve($sr, $ctx['admin']);

    $balanceBefore = (int) StockBalance::query()->where('batch_id', $batch->id)->value('qty_on_hand');

    app(SupplierReturnService::class)->markSent($sr->refresh(), null, $ctx['admin']);

    $balanceAfter = (int) StockBalance::query()->where('batch_id', $batch->id)->value('qty_on_hand');

    expect($balanceBefore - $balanceAfter)->toBe(5);

    $ledger = StockLedger::query()
        ->where('ref_type', 'SupplierReturn')
        ->where('ref_id', $sr->id)
        ->where('type', StockLedger::TYPE_RETURN_OUT_TO_SUPPLIER)
        ->first();
    expect($ledger)->not->toBeNull();
    expect((int) $ledger->qty_out)->toBe(5);
});

test('stock source qty > available ditolak', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 0);
    $batch = ProductBatch::query()->where('product_id', $ctx['product']->id)->first();
    $batch->update(['supplier_id' => $ctx['supplier']->id]);

    // Balance hanya 30
    expect(fn () => app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'stock',
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'batch_id' => $batch->id,
            'qty' => 100,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']))->toThrow(ValidationException::class);
});

test('settle dengan amount=claim → status settled', function (): void {
    $ctx = srSetupGrnDamaged();

    $sr = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 5,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);
    app(SupplierReturnService::class)->approve($sr, $ctx['admin']);
    app(SupplierReturnService::class)->markSent($sr->refresh(), null, $ctx['admin']);

    app(SupplierReturnService::class)->settle($sr->refresh(), 25_000, 'Full settlement', $ctx['admin']);

    expect($sr->fresh()->status)->toBe(SupplierReturn::STATUS_SETTLED);
    expect((float) $sr->fresh()->settled_amount)->toBe(25_000.0);
});

test('partial settlement < claim allowed', function (): void {
    $ctx = srSetupGrnDamaged();

    $sr = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 5,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);
    app(SupplierReturnService::class)->approve($sr, $ctx['admin']);
    app(SupplierReturnService::class)->markSent($sr->refresh(), null, $ctx['admin']);
    app(SupplierReturnService::class)->settle($sr->refresh(), 15_000, 'Partial', $ctx['admin']);

    expect($sr->fresh()->status)->toBe(SupplierReturn::STATUS_SETTLED);
    expect((float) $sr->fresh()->settled_amount)->toBe(15_000.0);
});

test('cancel approved → source tracking reverted', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 5);

    $sr = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 3,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);
    app(SupplierReturnService::class)->approve($sr, $ctx['admin']);

    expect((int) GrnItem::find($ctx['grnItem']->id)->qty_returned_to_supplier)->toBe(3);

    app(SupplierReturnService::class)->cancel($sr->refresh(), 'Salah claim', $ctx['admin']);

    expect($sr->fresh()->status)->toBe(SupplierReturn::STATUS_CANCELLED);
    expect((int) GrnItem::find($ctx['grnItem']->id)->qty_returned_to_supplier)->toBe(0);
});

test('cancel sent ditolak', function (): void {
    $ctx = srSetupGrnDamaged();

    $sr = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 3,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);
    app(SupplierReturnService::class)->approve($sr, $ctx['admin']);
    app(SupplierReturnService::class)->markSent($sr->refresh(), null, $ctx['admin']);

    expect(fn () => app(SupplierReturnService::class)->cancel($sr->refresh(), 'X', $ctx['admin']))
        ->toThrow(ValidationException::class);
});

test('source resolver: GRN damaged available', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 5);

    $rows = app(SupplierReturnSourceResolver::class)->fromGrnDamaged($ctx['supplier']);

    expect($rows)->toHaveCount(1);
    expect($rows->first()['available_qty'])->toBe(5);
    expect($rows->first()['source_type'])->toBe('grn_damaged');
});

test('source resolver: stock balance available', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 0);
    $batch = ProductBatch::query()->where('product_id', $ctx['product']->id)->first();
    $batch->update(['supplier_id' => $ctx['supplier']->id]);

    $rows = app(SupplierReturnSourceResolver::class)->fromStock($ctx['supplier']);

    expect($rows->count())->toBeGreaterThan(0);
    expect($rows->first()['source_type'])->toBe('stock');
    expect($rows->first()['available_qty'])->toBe(30);
});

test('approve setelah source berkurang (race condition simulasi) → 422', function (): void {
    $ctx = srSetupGrnDamaged(qtyDamaged: 5);

    $sr = app(SupplierReturnService::class)->createDraft([
        'supplier_id' => $ctx['supplier']->id,
        'return_date' => now()->toDateString(),
        'reason_code' => 'damaged',
        'items' => [[
            'source_type' => 'grn_damaged',
            'grn_item_id' => $ctx['grnItem']->id,
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 5,
            'cost_price' => 5000,
        ]],
    ], $ctx['admin']);

    // Race: source duluan ke-claim oleh klaim lain
    GrnItem::where('id', $ctx['grnItem']->id)->update(['qty_returned_to_supplier' => 5]);

    expect(fn () => app(SupplierReturnService::class)->approve($sr, $ctx['admin']))
        ->toThrow(ValidationException::class);
});
