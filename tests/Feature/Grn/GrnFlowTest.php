<?php

use App\Models\GoodsReceipt;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Product\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\GrnPdfRenderer;
use App\Services\Purchasing\PoPdfRenderer;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Setting\SettingManager;
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

    // Stub PDF renderers — jangan tulis file.
    $this->app->bind(PoPdfRenderer::class, function ($app) {
        return new class($app->make(SettingManager::class)) extends PoPdfRenderer
        {
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
    $this->app->bind(GrnPdfRenderer::class, function ($app) {
        return new class($app->make(SettingManager::class)) extends GrnPdfRenderer
        {
            public function generate(GoodsReceipt $grn): string
            {
                $grn->update([
                    'pdf_path' => "grn/{$grn->fiscal_year}/{$grn->grn_number}.pdf",
                    'pdf_generated_at' => now(),
                ]);

                return $grn->pdf_path;
            }
        };
    });
});

function grnUser(string $roleCode): User
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

function buildApprovedPo(User $by): PurchaseOrder
{
    $supplier = Supplier::create([
        'code' => 'SUP-G-'.random_int(1000, 9999),
        'name' => 'Supplier GRN '.random_int(100, 999),
        'is_active' => true,
        'payment_term_days' => 7,
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk GRN '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    SupplierProduct::create([
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'default_cost_price' => 5000,
        'is_active' => true,
    ]);

    $po = app(PurchaseOrderService::class)->createDraft(
        [
            'supplier_id' => $supplier->id,
            'po_date' => now()->toDateString(),
        ],
        [[
            'product_id' => $product->id,
            'product_unit_id' => $product->units->first()->id,
            'qty_ordered' => 100,
            'bonus_qty' => 5,
            'cost_price' => 5000,
        ]],
        $by,
    );

    return app(PurchaseOrderService::class)->approve($po, $by);
}

test('operator bisa create GRN draft ref PO approved', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $this->actingAs($operator)
        ->post(route('grns.store'), [
            'purchase_order_id' => $po->id,
            'received_date' => now()->toDateString(),
            'items' => [[
                'po_item_id' => $poItem->id,
                'product_id' => $poItem->product_id,
                'product_unit_id' => $poItem->product_unit_id,
                'batch_code' => 'BATCH-001',
                'production_date' => now()->subMonth()->toDateString(),
                'expired_date' => now()->addYear()->toDateString(),
                'qty_reguler' => 40,
                'qty_bonus' => 0,
                'cost_price' => 5000,
            ]],
        ])
        ->assertRedirect();

    $grn = GoodsReceipt::query()->latest()->first();
    expect($grn->grn_number)->toStartWith('GRN-');
    expect($grn->status)->toBe(GoodsReceipt::STATUS_DRAFT);
    expect($grn->received_by)->toBe($operator->id);
});

test('create GRN ref PO draft ditolak', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();
    $po->update(['status' => PurchaseOrder::STATUS_DRAFT]);

    expect(fn () => app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B', 'qty_reguler' => 1, 'cost_price' => 5000,
        ]],
        $operator,
    ))->toThrow(ValidationException::class);
});

test('all qty 0 ditolak', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    expect(fn () => app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B', 'qty_reguler' => 0, 'qty_bonus' => 0, 'qty_damaged' => 0,
            'cost_price' => 5000,
        ]],
        $operator,
    ))->toThrow(ValidationException::class);
});

test('product mismatch dengan po_item ditolak', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    expect(fn () => app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => 99999, // beda produk
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B', 'qty_reguler' => 10, 'cost_price' => 5000,
        ]],
        $operator,
    ))->toThrow(ValidationException::class);
});

test('submit GRN draft mengubah status ke submitted', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-SUB', 'qty_reguler' => 30, 'cost_price' => 5000,
        ]],
        $operator,
    );

    $this->actingAs($operator)
        ->post(route('grns.submit', $grn))
        ->assertRedirect();

    expect($grn->fresh()->status)->toBe(GoodsReceipt::STATUS_SUBMITTED);
});

test('reject GRN submitted ubah status ke rejected dengan reason', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-REJ', 'qty_reguler' => 10, 'cost_price' => 5000,
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn, $operator);

    $this->actingAs($admin)
        ->post(route('grns.reject', $grn), ['rejection_reason' => 'Batch code salah, cek ulang.'])
        ->assertRedirect();

    $grn->refresh();
    expect($grn->status)->toBe(GoodsReceipt::STATUS_REJECTED);
    expect($grn->rejection_reason)->toContain('Batch code salah');
});

test('post GRN bikin batch baru, stock_ledger, dan update po_item.qty_received', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'BATCH-POST',
            'expired_date' => now()->addYear()->toDateString(),
            'qty_reguler' => 40,
            'qty_bonus' => 3,
            'cost_price' => 5000,
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn, $operator);

    $this->actingAs($admin)
        ->post(route('grns.post', $grn))
        ->assertRedirect();

    $grn->refresh();
    expect($grn->status)->toBe(GoodsReceipt::STATUS_POSTED);
    expect($grn->posted_at)->not->toBeNull();

    // Batch dibuat
    $batch = ProductBatch::query()->where('batch_code', 'BATCH-POST')->first();
    expect($batch)->not->toBeNull();
    expect($batch->initial_qty_base)->toBe(43); // 40 + 3 bonus

    // Stock ledger ada 2 row (purchase_in + bonus_in)
    $ledgers = StockLedger::query()->where('batch_id', $batch->id)->get();
    expect($ledgers)->toHaveCount(2);
    expect($ledgers->where('type', 'purchase_in')->first()->qty_in)->toBe(40);
    expect($ledgers->where('type', 'bonus_in')->first()->qty_in)->toBe(3);
    expect((bool) $ledgers->where('type', 'bonus_in')->first()->is_bonus_pool)->toBeTrue();

    // Stock balance
    $balance = StockBalance::query()->where('batch_id', $batch->id)->first();
    expect($balance->qty_on_hand)->toBe(43);
    expect($balance->qty_bonus_pool)->toBe(3);

    // PO item qty_received updated
    $poItem->refresh();
    expect($poItem->qty_received)->toBe(40);
    expect($poItem->bonus_qty_received)->toBe(3);

    // PO status → partial_received (karena belum full 100)
    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIAL_RECEIVED);
});

test('post GRN full quantity → PO status closed', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-FULL', 'qty_reguler' => 100, 'cost_price' => 5000,
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn, $operator);
    app(GoodsReceiptService::class)->post($grn, $admin);

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_CLOSED);
});

test('post dengan over-receive tanpa approve flag ditolak', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-OVER', 'qty_reguler' => 110, 'cost_price' => 5000,
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn, $operator);

    expect(fn () => app(GoodsReceiptService::class)->post($grn, $admin, approveOverReceive: false))
        ->toThrow(ValidationException::class);
});

test('post dengan over-receive + approve flag sukses', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-OVER-OK', 'qty_reguler' => 110, 'cost_price' => 5000,
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn, $operator);
    app(GoodsReceiptService::class)->post($grn, $admin, approveOverReceive: true);

    expect($grn->fresh()->status)->toBe(GoodsReceipt::STATUS_POSTED);
    expect($poItem->fresh()->qty_received)->toBe(110);
});

test('cancel GRN draft sukses', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-CAN', 'qty_reguler' => 10, 'cost_price' => 5000,
        ]],
        $operator,
    );

    $this->actingAs($operator)
        ->post(route('grns.cancel', $grn), ['cancel_reason' => 'Salah PO, batalkan dulu.'])
        ->assertRedirect();

    expect($grn->fresh()->status)->toBe(GoodsReceipt::STATUS_CANCELLED);
});

test('damaged qty tidak masuk stock_ledger (cuma reguler dan bonus)', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-DMG', 'qty_reguler' => 30, 'qty_damaged' => 5, 'cost_price' => 5000,
            'condition' => 'mixed',
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn, $operator);
    app(GoodsReceiptService::class)->post($grn, $admin);

    $batch = ProductBatch::query()->where('batch_code', 'B-DMG')->first();
    $balance = StockBalance::query()->where('batch_id', $batch->id)->first();

    // 30 reguler masuk, 5 damaged tidak masuk
    expect($balance->qty_on_hand)->toBe(30);
    expect($grn->fresh()->has_discrepancy)->toBeTrue();
});

test('posted GRN tidak bisa diedit', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-LOCK', 'qty_reguler' => 10, 'cost_price' => 5000,
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn, $operator);
    app(GoodsReceiptService::class)->post($grn, $admin);

    expect($grn->fresh()->canBeEdited())->toBeFalse();
    expect($grn->fresh()->canBeCancelled())->toBeFalse();
});

test('multi-GRN ke PO sama bikin batch beda, stock akumulasi benar', function (): void {
    $operator = grnUser(Role::CODE_OPERATOR);
    $admin = grnUser(Role::CODE_SUPERADMIN);
    $po = buildApprovedPo($admin);
    $poItem = $po->items->first();

    // GRN 1: 40 dari batch B1
    $grn1 = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-1', 'qty_reguler' => 40, 'cost_price' => 5000,
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn1, $operator);
    app(GoodsReceiptService::class)->post($grn1, $admin);

    // GRN 2: 50 dari batch B2
    $grn2 = app(GoodsReceiptService::class)->createDraft(
        ['purchase_order_id' => $po->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'product_unit_id' => $poItem->product_unit_id,
            'batch_code' => 'B-2', 'qty_reguler' => 50, 'cost_price' => 5000,
        ]],
        $operator,
    );
    app(GoodsReceiptService::class)->submit($grn2, $operator);
    app(GoodsReceiptService::class)->post($grn2, $admin);

    expect(ProductBatch::query()->where('product_id', $poItem->product_id)->count())->toBe(2);
    expect($poItem->fresh()->qty_received)->toBe(90);
    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIAL_RECEIVED);
});
