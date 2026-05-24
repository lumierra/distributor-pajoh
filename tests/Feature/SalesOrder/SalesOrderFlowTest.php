<?php

use App\Models\Customer;
use App\Models\PriceTier;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SoReservation;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Inventory\StockLedgerWriter;
use App\Services\Product\ProductService;
use App\Services\Sales\SalesOrderService;
use App\Services\Setting\SettingManager;
use Database\Seeders\CustomerTypeSeeder;
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
    $this->seed(CustomerTypeSeeder::class);
});

function soUser(string $roleCode): User
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

function soSetup(int $initialStock = 100): array
{
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();

    $customer = Customer::create([
        'code' => 'CUST-SO-'.random_int(1000, 9999),
        'name' => 'Customer SO '.random_int(100, 999),
        'price_tier_id' => $tier->id,
        'credit_limit' => 1_000_000,
        'payment_term_days' => 7,
        'is_active' => true,
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk SO '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    $unit = $product->units->first();

    // Set price untuk tier
    ProductPrice::query()
        ->where('product_id', $product->id)
        ->where('product_unit_id', $unit->id)
        ->where('price_tier_id', $tier->id)
        ->update(['price' => 10_000]);

    // Seed stok awal
    if ($initialStock > 0) {
        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'batch_code' => 'BATCH-SO-'.random_int(1000, 9999),
            'expired_date' => now()->addYear()->toDateString(),
            'is_active' => true,
        ]);
        app(StockLedgerWriter::class)->writeIn([
            'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $unit->id,
            'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => $initialStock, 'cost_price' => 5000,
            'ref_type' => 'SEED', 'ref_id' => 1,
        ]);
    }

    return ['customer' => $customer, 'product' => $product, 'unit' => $unit, 'tier' => $tier];
}

test('sales bisa create SO draft via web', function (): void {
    $sales = soUser(Role::CODE_SALES);
    $ctx = soSetup();

    $this->actingAs($sales)
        ->post(route('sales-orders.store'), [
            'customer_id' => $ctx['customer']->id,
            'so_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $ctx['product']->id,
                'product_unit_id' => $ctx['unit']->id,
                'qty' => 10,
            ]],
        ])
        ->assertRedirect();

    $so = SalesOrder::query()->latest()->first();
    expect($so)->not->toBeNull();
    expect($so->so_number)->toStartWith('SO-');
    expect($so->status)->toBe(SalesOrder::STATUS_DRAFT);
    expect((float) $so->subtotal)->toBe(100_000.0); // 10 × 10000
});

test('sales create SO via service auto-set sales_id ke pemanggil', function (): void {
    $sales = soUser(Role::CODE_SALES);
    $ctx = soSetup();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 5,
        ]],
        $sales,
    );

    expect($so->sales_id)->toBe($sales->id);
});

test('SO untuk customer problem_outlet ditolak', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup();
    $ctx['customer']->update(['tags' => ['problem_outlet']]);

    expect(fn () => app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 5,
        ]],
        $admin,
    ))->toThrow(ValidationException::class);
});

test('bonus item auto unit_price=0 dan line_subtotal=0', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 10,
        ], [
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 2,
            'is_bonus' => true,
        ]],
        $admin,
    );

    $bonus = $so->items->where('is_bonus', true)->first();
    expect((float) $bonus->unit_price)->toBe(0.0);
    expect((float) $bonus->line_subtotal)->toBe(0.0);
    // Total tetap = 10 × 10000 = 100k (bonus excluded)
    expect((float) $so->total)->toBe(100_000.0);
});

test('Z1+Z2 compound discount dihitung benar', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty' => 10,
            'discount_z1_pct' => 10,
            'discount_z2_pct' => 5,
        ]],
        $admin,
    );

    // 10000 × 0.9 × 0.95 = 8550; × 10 = 85500
    expect((float) $so->total)->toBe(85_500.0);
});

test('submit dengan stock cukup → status submitted', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup(initialStock: 100);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 20]],
        $admin,
    );

    app(SalesOrderService::class)->submit($so, $admin);

    expect($so->fresh()->status)->toBe(SalesOrder::STATUS_SUBMITTED);
});

test('submit dengan stock kurang + allow_negative=false ditolak', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup(initialStock: 5);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 50]],
        $admin,
    );

    expect(fn () => app(SalesOrderService::class)->submit($so, $admin))
        ->toThrow(ValidationException::class);
});

test('submit dengan stock kurang + allow_negative=true lolos', function (): void {
    app(SettingManager::class)->set('inventory.allow_negative_stock', true);
    app(SettingManager::class)->forgetCache();

    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup(initialStock: 5);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 50]],
        $admin,
    );

    app(SalesOrderService::class)->submit($so, $admin);

    expect($so->fresh()->status)->toBe(SalesOrder::STATUS_SUBMITTED);
});

test('submit over credit limit → status pending_credit_review', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup(initialStock: 1000);
    $ctx['customer']->update(['credit_limit' => 50_000]);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 100]],
        $admin,
    );

    // Total = 100 × 10000 = 1jt; limit 50k → over.
    app(SalesOrderService::class)->submit($so, $admin);

    expect($so->fresh()->status)->toBe(SalesOrder::STATUS_PENDING_CREDIT_REVIEW);
    expect($so->fresh()->credit_review_required)->toBeTrue();
});

test('approve submitted SO → status approved + reservations created + balance.qty_reserved bumped', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup(initialStock: 100);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 30]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);

    app(SalesOrderService::class)->approve($so, $admin);

    $so->refresh();
    expect($so->status)->toBe(SalesOrder::STATUS_APPROVED);
    expect($so->customer_snapshot)->toBeArray();

    $reservations = SoReservation::query()->where('sales_order_id', $so->id)->get();
    expect($reservations)->toHaveCount(1);
    expect($reservations->first()->qty_reserved)->toBe(30);
    expect($reservations->first()->status)->toBe(SoReservation::STATUS_ACTIVE);

    $balance = StockBalance::query()
        ->where('product_id', $ctx['product']->id)
        ->where('batch_id', $reservations->first()->batch_id)
        ->first();
    expect($balance->qty_reserved)->toBe(30);
});

test('admin biasa tidak bisa approve pending_credit_review', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup(initialStock: 100);
    $ctx['customer']->update(['credit_limit' => 1]);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 10]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);

    // status=pending_credit_review now
    expect(fn () => app(SalesOrderService::class)->approve($so, $admin))
        ->toThrow(ValidationException::class);
});

test('superadmin bisa approveOverride pending_credit_review', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $sa = soUser(Role::CODE_SUPERADMIN);
    $ctx = soSetup(initialStock: 100);
    $ctx['customer']->update(['credit_limit' => 1]);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 10]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);

    app(SalesOrderService::class)->approveOverride($so, 'Customer trusted partner', $sa);

    $so->refresh();
    expect($so->status)->toBe(SalesOrder::STATUS_APPROVED);
    expect($so->credit_override_approved_by)->toBe($sa->id);
    expect($so->credit_override_reason)->toContain('trusted partner');
});

test('reject SO submitted → status rejected', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 10]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);

    app(SalesOrderService::class)->reject($so, 'Customer batal pesan', $admin);

    expect($so->fresh()->status)->toBe(SalesOrder::STATUS_REJECTED);
    expect($so->fresh()->rejection_reason)->toContain('batal pesan');
});

test('cancel draft sukses tanpa release reservation', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 5]],
        $admin,
    );

    app(SalesOrderService::class)->cancel($so, 'Salah customer', $admin);

    expect($so->fresh()->status)->toBe(SalesOrder::STATUS_CANCELLED);
});

test('cancel approved → release reservations & turunkan qty_reserved', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup(initialStock: 100);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 25]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);
    app(SalesOrderService::class)->approve($so, $admin);

    $batchId = SoReservation::query()->where('sales_order_id', $so->id)->value('batch_id');
    expect(StockBalance::query()->where('batch_id', $batchId)->value('qty_reserved'))->toBe(25);

    app(SalesOrderService::class)->cancel($so, 'Customer cancel order', $admin);

    expect($so->fresh()->status)->toBe(SalesOrder::STATUS_CANCELLED);
    expect(StockBalance::query()->where('batch_id', $batchId)->value('qty_reserved'))->toBe(0);
    expect(
        SoReservation::query()->where('sales_order_id', $so->id)->where('status', SoReservation::STATUS_ACTIVE)->count(),
    )->toBe(0);
});

test('cancel partially_delivered ditolak', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup(initialStock: 100);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 10]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);
    app(SalesOrderService::class)->approve($so, $admin);
    $so->update(['status' => SalesOrder::STATUS_PARTIALLY_DELIVERED]);

    expect(fn () => app(SalesOrderService::class)->cancel($so, 'mau cancel', $admin))
        ->toThrow(ValidationException::class);
});

test('approve re-check stock saat race condition', function (): void {
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup(initialStock: 100);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $ctx['customer']->id, 'so_date' => now()->toDateString()],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 80]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);

    // Race: ada SO lain yang reserve stock dalam interim
    $balance = StockBalance::query()->where('product_id', $ctx['product']->id)->first();
    $balance->qty_reserved = 50;
    $balance->save();

    expect(fn () => app(SalesOrderService::class)->approve($so, $admin))
        ->toThrow(ValidationException::class);
});

test('sales user tidak bisa lihat SO sales lain', function (): void {
    $sales1 = soUser(Role::CODE_SALES);
    $sales2 = soUser(Role::CODE_SALES);
    $admin = soUser(Role::CODE_ADMIN);
    $ctx = soSetup();

    $so = app(SalesOrderService::class)->createDraft(
        [
            'customer_id' => $ctx['customer']->id,
            'sales_id' => $sales1->id,
            'so_date' => now()->toDateString(),
        ],
        [['product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id, 'qty' => 5]],
        $admin,
    );

    $this->actingAs($sales2)
        ->get(route('sales-orders.show', $so))
        ->assertForbidden();
});
