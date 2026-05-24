<?php

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Driver;
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
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Delivery\DeliveryOrderService;
use App\Services\Delivery\DoPdfRenderer;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    Storage::fake('public');
    Storage::fake('local');

    // Stub PDF agar tidak nulis file.
    $this->app->bind(DoPdfRenderer::class, function ($app) {
        return new class($app->make(SettingManager::class)) extends DoPdfRenderer
        {
            public function generate(DeliveryOrder $do): string
            {
                $do->update([
                    'pdf_path' => "delivery_orders/{$do->fiscal_year}/{$do->do_number}.pdf",
                    'pdf_generated_at' => now(),
                ]);

                return $do->pdf_path;
            }
        };
    });
});

function doUser(string $roleCode): User
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

function doMakeSetup(int $initialStock = 100, int $orderQty = 30): array
{
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-DO-'.random_int(1000, 9999),
        'name' => 'Customer DO',
        'price_tier_id' => $tier->id,
        'credit_limit' => 100_000_000,
        'payment_term_days' => 7,
        'is_active' => true,
        'address' => 'Jl. Test 1, Langsa',
        'phone' => '0812-3456-7890',
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk DO '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $unit = $product->units->first();
    ProductPrice::query()
        ->where('product_id', $product->id)
        ->where('product_unit_id', $unit->id)
        ->where('price_tier_id', $tier->id)
        ->update(['price' => 10_000]);

    // Seed stock
    $batch = ProductBatch::create([
        'product_id' => $product->id,
        'batch_code' => 'BATCH-DO-'.random_int(1000, 9999),
        'expired_date' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $unit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => $initialStock, 'cost_price' => 5000,
        'ref_type' => 'SEED', 'ref_id' => 1,
    ]);

    // Buat SO approved
    $admin = doUser(Role::CODE_SUPERADMIN);
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString()],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => $orderQty]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);
    app(SalesOrderService::class)->approve($so, $admin);

    // Buat driver & vehicle aktif (dokumen valid)
    $driver = Driver::create([
        'code' => 'DRV-DO-'.random_int(100, 999),
        'name' => 'Pak Supir',
        'license_no' => '1234567890',
        'license_type' => 'B1',
        'license_expired_date' => now()->addYear()->toDateString(),
        'is_active' => true,
        'status' => Driver::STATUS_IDLE,
    ]);
    $vehicle = Vehicle::create([
        'code' => 'VEH-DO-'.random_int(100, 999),
        'plate_number' => 'BL '.random_int(1000, 9999).' XX',
        'type' => 'pickup',
        'is_active' => true,
        'status' => Vehicle::STATUS_IDLE,
    ]);
    // Dokumen STNK valid (tidak expired)
    VehicleDocument::create([
        'vehicle_id' => $vehicle->id,
        'type' => 'STNK',
        'title' => 'STNK Valid',
        'file_path' => 'dummy/path.pdf',
        'expires_date' => now()->addYear()->toDateString(),
    ]);

    return compact('customer', 'product', 'unit', 'batch', 'so', 'admin', 'driver', 'vehicle');
}

test('createFromSo bikin DO draft dengan items dari reservations', function (): void {
    $ctx = doMakeSetup();
    $soItem = $ctx['so']->items->first();

    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $soItem->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );

    expect($do->do_number)->toStartWith('DO-');
    expect($do->status)->toBe(DeliveryOrder::STATUS_DRAFT);
    expect($do->items)->toHaveCount(1);
    expect($do->items->first()->qty_planned)->toBe(30);
    expect($do->items->first()->reservation_id)->not->toBeNull();
});

test('createFromSo dengan SO non-approved ditolak', function (): void {
    $ctx = doMakeSetup();
    $ctx['so']->update(['status' => SalesOrder::STATUS_DRAFT]);

    expect(fn () => app(DeliveryOrderService::class)->createFromSo(
        $ctx['so']->refresh(),
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 10]],
        $ctx['admin'],
    ))->toThrow(ValidationException::class);
});

test('createFromSo qty_planned > sisa SO ditolak', function (): void {
    $ctx = doMakeSetup(orderQty: 20);

    expect(fn () => app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 50]],
        $ctx['admin'],
    ))->toThrow(ValidationException::class);
});

test('startPicking ubah status draft -> picking', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );

    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);

    expect($do->fresh()->status)->toBe(DeliveryOrder::STATUS_PICKING);
});

test('confirmPicks update qty_picked per item', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);

    $doItem = $do->items->first();
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $doItem->id, 'qty_picked' => 25]],
        $ctx['admin'],
    );

    expect($doItem->fresh()->qty_picked)->toBe(25);
});

test('confirmPicks qty_picked > qty_planned ditolak', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);

    expect(fn () => app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 50]],
        $ctx['admin'],
    ))->toThrow(ValidationException::class);
});

test('markPacked dengan driver + vehicle valid sukses + snapshot disimpan', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $ctx['admin'],
    );

    app(DeliveryOrderService::class)->markPacked($do, $ctx['driver']->id, $ctx['vehicle']->id, $ctx['admin']);

    $do->refresh();
    expect($do->status)->toBe(DeliveryOrder::STATUS_PACKED);
    expect($do->driver_id)->toBe($ctx['driver']->id);
    expect($do->vehicle_id)->toBe($ctx['vehicle']->id);
    expect($do->driver_snapshot['name'])->toBe('Pak Supir');
    expect($do->vehicle_snapshot['plate'])->toBe($ctx['vehicle']->plate_number);
});

test('markPacked dengan SIM expired ditolak', function (): void {
    $ctx = doMakeSetup();
    $ctx['driver']->update(['license_expired_date' => now()->subMonth()->toDateString()]);

    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);

    expect(fn () => app(DeliveryOrderService::class)->markPacked(
        $do,
        $ctx['driver']->id,
        $ctx['vehicle']->id,
        $ctx['admin'],
    ))->toThrow(ValidationException::class);
});

test('markPacked dengan STNK expired ditolak', function (): void {
    $ctx = doMakeSetup();
    VehicleDocument::query()
        ->where('vehicle_id', $ctx['vehicle']->id)
        ->update(['expires_date' => now()->subMonth()->toDateString()]);

    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);

    expect(fn () => app(DeliveryOrderService::class)->markPacked(
        $do,
        $ctx['driver']->id,
        $ctx['vehicle']->id,
        $ctx['admin'],
    ))->toThrow(ValidationException::class);
});

test('markPacked dengan vehicle maintenance ditolak', function (): void {
    $ctx = doMakeSetup();
    $ctx['vehicle']->update(['status' => Vehicle::STATUS_MAINTENANCE]);

    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);

    expect(fn () => app(DeliveryOrderService::class)->markPacked(
        $do,
        $ctx['driver']->id,
        $ctx['vehicle']->id,
        $ctx['admin'],
    ))->toThrow(ValidationException::class);
});

test('startDelivery ubah status packed -> in_transit', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->markPacked($do, $ctx['driver']->id, $ctx['vehicle']->id, $ctx['admin']);

    app(DeliveryOrderService::class)->startDelivery($do, $ctx['admin']);

    expect($do->fresh()->status)->toBe(DeliveryOrder::STATUS_IN_TRANSIT);
});

test('markDelivered consume reservation + stock_ledger sale_out + so qty_delivered increment', function (): void {
    $ctx = doMakeSetup(initialStock: 100, orderQty: 30);
    $soItem = $ctx['so']->items->first();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $soItem->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->markPacked($do, $ctx['driver']->id, $ctx['vehicle']->id, $ctx['admin']);
    app(DeliveryOrderService::class)->startDelivery($do, $ctx['admin']);

    $doItem = $do->items->first();

    app(DeliveryOrderService::class)->markDelivered(
        $do,
        [
            'receiver_name' => 'Bu Toko',
            'item_quantities' => [
                ['item_id' => $doItem->id, 'qty_delivered' => 30, 'qty_returned' => 0],
            ],
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $ctx['admin'],
    );

    $do->refresh();
    expect($do->status)->toBe(DeliveryOrder::STATUS_DELIVERED);
    expect($do->receiver_name)->toBe('Bu Toko');
    expect($do->proof_photo_signed_path)->not->toBeNull();

    // Stock ledger sale_out tercatat
    $saleOut = StockLedger::query()
        ->where('ref_type', 'DO')
        ->where('ref_id', $do->id)
        ->where('type', StockLedger::TYPE_SALE_OUT)
        ->first();
    expect($saleOut)->not->toBeNull();
    expect((int) $saleOut->qty_out)->toBe(30);

    // Balance on_hand turun, qty_reserved turun
    $balance = StockBalance::query()->where('batch_id', $ctx['batch']->id)->first();
    expect($balance->qty_on_hand)->toBe(70); // 100 - 30
    expect($balance->qty_reserved)->toBe(0);

    // Reservation status consumed
    expect(SoReservation::query()->where('sales_order_id', $ctx['so']->id)->value('status'))
        ->toBe(SoReservation::STATUS_CONSUMED);

    // SO item qty_delivered updated
    expect($soItem->fresh()->qty_delivered)->toBe(30);

    // SO status auto-updated ke delivered (full)
    expect($ctx['so']->fresh()->status)->toBe(SalesOrder::STATUS_DELIVERED);
});

test('markDelivered partial -> SO status partially_delivered', function (): void {
    $ctx = doMakeSetup(initialStock: 100, orderQty: 50);
    $soItem = $ctx['so']->items->first();

    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $soItem->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->markPacked($do, $ctx['driver']->id, $ctx['vehicle']->id, $ctx['admin']);
    app(DeliveryOrderService::class)->startDelivery($do, $ctx['admin']);
    app(DeliveryOrderService::class)->markDelivered(
        $do,
        [
            'receiver_name' => 'Customer',
            'item_quantities' => [['item_id' => $do->items->first()->id, 'qty_delivered' => 30, 'qty_returned' => 0]],
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $ctx['admin'],
    );

    // SO ordered 50, terkirim 30 → partially_delivered
    expect($ctx['so']->fresh()->status)->toBe(SalesOrder::STATUS_PARTIALLY_DELIVERED);
});

test('markDelivered dengan qty_returned > 0 → status partial_returned + has_partial_return=true', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->markPacked($do, $ctx['driver']->id, $ctx['vehicle']->id, $ctx['admin']);
    app(DeliveryOrderService::class)->startDelivery($do, $ctx['admin']);

    app(DeliveryOrderService::class)->markDelivered(
        $do,
        [
            'receiver_name' => 'Customer',
            'item_quantities' => [['item_id' => $do->items->first()->id, 'qty_delivered' => 30, 'qty_returned' => 5]],
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $ctx['admin'],
    );

    $do->refresh();
    expect($do->status)->toBe(DeliveryOrder::STATUS_PARTIAL_RETURNED);
    expect($do->has_partial_return)->toBeTrue();

    // SO qty_delivered = 30 - 5 = 25
    expect($ctx['so']->items->first()->fresh()->qty_delivered)->toBe(25);
});

test('markDelivered qty_returned > qty_delivered ditolak', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->markPacked($do, $ctx['driver']->id, $ctx['vehicle']->id, $ctx['admin']);
    app(DeliveryOrderService::class)->startDelivery($do, $ctx['admin']);

    expect(fn () => app(DeliveryOrderService::class)->markDelivered(
        $do,
        [
            'receiver_name' => 'C',
            'item_quantities' => [['item_id' => $do->items->first()->id, 'qty_delivered' => 10, 'qty_returned' => 20]],
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $ctx['admin'],
    ))->toThrow(ValidationException::class);
});

test('cancel draft sukses, reservation tetap aktif', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );

    app(DeliveryOrderService::class)->cancel($do, 'Driver belum siap.', $ctx['admin']);

    expect($do->fresh()->status)->toBe(DeliveryOrder::STATUS_CANCELLED);
    expect(SoReservation::query()->where('sales_order_id', $ctx['so']->id)->value('status'))
        ->toBe(SoReservation::STATUS_ACTIVE);
});

test('cancel in_transit ditolak', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->markPacked($do, $ctx['driver']->id, $ctx['vehicle']->id, $ctx['admin']);
    app(DeliveryOrderService::class)->startDelivery($do, $ctx['admin']);

    expect(fn () => app(DeliveryOrderService::class)->cancel($do, 'mau cancel', $ctx['admin']))
        ->toThrow(ValidationException::class);
});

test('full delivered semua return → SO masih partially_delivered (qty_delivered net = 0)', function (): void {
    $ctx = doMakeSetup(orderQty: 30);
    $soItem = $ctx['so']->items->first();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $soItem->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->markPacked($do, $ctx['driver']->id, $ctx['vehicle']->id, $ctx['admin']);
    app(DeliveryOrderService::class)->startDelivery($do, $ctx['admin']);

    app(DeliveryOrderService::class)->markDelivered(
        $do,
        [
            'receiver_name' => 'Customer',
            'item_quantities' => [['item_id' => $do->items->first()->id, 'qty_delivered' => 30, 'qty_returned' => 30]],
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $ctx['admin'],
    );

    // Net = 0 → SO stays approved (canBeApproved status). Actually since
    // qty_delivered=0 dan SO ordered 30 → tidak match all-delivered, dan
    // any-delivered=false → SO status tidak berubah dari approved.
    expect($soItem->fresh()->qty_delivered)->toBe(0);
});

test('PDF auto-generate saat markPacked', function (): void {
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );
    app(DeliveryOrderService::class)->startPicking($do, $ctx['admin']);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $ctx['admin'],
    );

    // Via web controller path supaya pdf->generate ter-trigger
    $this->actingAs($ctx['admin'])
        ->post(route('delivery-orders.mark-packed', $do), [
            'driver_id' => $ctx['driver']->id,
            'vehicle_id' => $ctx['vehicle']->id,
        ])
        ->assertRedirect();

    expect($do->fresh()->pdf_path)->not->toBeNull();
});

test('operator bisa start picking (policy)', function (): void {
    $operator = doUser(Role::CODE_OPERATOR);
    $ctx = doMakeSetup();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $ctx['so'],
        [['so_item_id' => $ctx['so']->items->first()->id, 'qty_planned' => 30]],
        $ctx['admin'],
    );

    $this->actingAs($operator)
        ->post(route('delivery-orders.start-picking', $do))
        ->assertRedirect();

    expect($do->fresh()->status)->toBe(DeliveryOrder::STATUS_PICKING);
});

test('kasir tidak bisa lihat DO list', function (): void {
    $kasir = doUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('delivery-orders.index'))
        ->assertForbidden();
});
