<?php

use App\Models\Customer;
use App\Models\CustomerSupplierCreditLimit;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SoReservation;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Delivery\DeliveryOrderService;
use App\Services\Payment\PaymentService;
use App\Services\Product\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Sales\SalesOrderService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperadminSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Validation\ValidationException;

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

function salesAdmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

/**
 * Bikin supplier + produk (PCS base, KARDUS x40) dengan paket harga jual,
 * lalu terima stok via GRN penerimaan langsung sejumlah $stockPcs (base PCS).
 *
 * @return array{supplier: Supplier, product: Product, pcsUnit: ProductUnit}
 */
function seedProductWithStock(int $stockPcs): array
{
    $admin = salesAdmin();

    $supplier = Supplier::create([
        'code' => 'SUP-S1',
        'name' => 'Supplier Sales',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $kardus = Unit::query()->where('name', 'KARDUS')->value('id');

    $product = app(ProductService::class)->create(
        [
            'supplier_id' => $supplier->id,
            'sku' => 'SLS-001',
            'name' => 'Produk Jual',
            'is_active' => true,
            'created_by' => $admin->id,
        ],
        [
            ['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null],
            ['unit_id' => $kardus, 'qty_to_base' => 40, 'barcode' => null],
        ],
        [[
            'name' => 'Harga Reguler',
            'items' => [
                ['unit_index' => 0, 'cost_price' => 2000, 'sell_price' => 3000],
                ['unit_index' => 1, 'cost_price' => 80000, 'sell_price' => 120000],
            ],
        ]],
    )->fresh(['units']);

    $pcsUnit = $product->units->firstWhere('qty_to_base', 1);

    // Terima stok via GRN penerimaan langsung posted.
    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $pcsUnit->id,
            'batch_code' => 'BATCH-SLS',
            'qty_reguler' => $stockPcs,
            'cost_price' => 2000,
            'condition' => 'good',
        ]],
        $admin,
    );
    $svc = app(GoodsReceiptService::class);
    $svc->submit($grn, $admin);
    $svc->post($grn->refresh(), $admin);

    return ['supplier' => $supplier, 'product' => $product, 'pcsUnit' => $pcsUnit];
}

function makeCustomer(int $termDays = 30, float $creditLimit = 100_000_000): Customer
{
    return Customer::create([
        'code' => 'CUST-1',
        'name' => 'Toko Maju',
        'is_active' => true,
        'credit_limit' => $creditLimit,
        'payment_term_days' => $termDays,
        'created_by' => salesAdmin()->id,
    ]);
}

function makeDriverAndVehicle(): array
{
    $admin = salesAdmin();
    $driver = Driver::create([
        'code' => 'DRV-1', 'name' => 'Pak Budi', 'phone' => '0811',
        'status' => Driver::STATUS_IDLE, 'is_active' => true, 'created_by' => $admin->id,
    ]);
    $vehicle = Vehicle::create([
        'code' => 'VHC-1', 'plate_number' => 'B 1 XX', 'type' => 'pickup',
        'status' => Vehicle::STATUS_IDLE, 'is_active' => true, 'created_by' => $admin->id,
    ]);

    return [$driver, $vehicle];
}

test('alur penuh SO→approve→DO→delivered→invoice→lunas', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(500);
    $customer = makeCustomer();

    // 1. Buat SO draft: 10 PCS @ sell 3000 = 30.000.
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );
    expect($so->status)->toBe(SalesOrder::STATUS_DRAFT);
    expect((float) $so->total)->toBe(30000.0);

    // 2. Submit → status submitted (stok cukup 500).
    $so = app(SalesOrderService::class)->submit($so, $admin);
    expect($so->status)->toBe(SalesOrder::STATUS_SUBMITTED);

    // 3. Approve → stok fisik LANGSUNG dipotong (model baru). on_hand 500→490,
    //    sale_out ditulis (ref SO), catatan batch CONSUMED. qty_reserved tetap 0.
    $so = app(SalesOrderService::class)->approve($so, $admin);
    expect($so->status)->toBe(SalesOrder::STATUS_APPROVED);
    expect(SoReservation::query()->where('sales_order_id', $so->id)->where('status', SoReservation::STATUS_CONSUMED)->count())->toBeGreaterThan(0);
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_reserved'))->toBe(0);
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(490);
    expect(StockLedger::query()->where('product_id', $product->id)->where('type', StockLedger::TYPE_SALE_OUT)->where('ref_type', 'SO')->exists())->toBeTrue();

    // 4. Buat DO dari SO, plan 10 PCS.
    $soItem = $so->items()->first();
    $do = app(DeliveryOrderService::class)->createFromSo(
        $so,
        [['so_item_id' => $soItem->id, 'qty_planned' => 10]],
        $admin,
    );
    expect($do->status)->toBe(DeliveryOrder::STATUS_DRAFT);
    expect((int) $do->items()->sum('qty_planned'))->toBe(10);

    // 5. Picking lifecycle.
    $doSvc = app(DeliveryOrderService::class);
    $do = $doSvc->startPicking($do, $admin);
    $picks = $do->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_picked' => (int) $i->qty_planned])->all();
    $do = $doSvc->confirmPicks($do, $picks, $admin);

    [$driver, $vehicle] = makeDriverAndVehicle();
    $do = $doSvc->markPacked($do, $driver->id, $vehicle->id, $admin);
    $do = $doSvc->startDelivery($do, $admin);
    expect($do->status)->toBe(DeliveryOrder::STATUS_IN_TRANSIT);

    // 6. Mark delivered — stok TIDAK berkurang lagi (sudah keluar saat approve),
    //    auto-invoice tetap terbit di sini.
    $proof = File::image('proof.jpg');
    $itemQuantities = $do->items()->get()->map(fn ($i) => [
        'item_id' => $i->id, 'qty_delivered' => (int) $i->qty_picked, 'qty_returned' => 0,
    ])->all();
    $do = $doSvc->markDelivered(
        $do,
        ['receiver_name' => 'Ibu Sari', 'item_quantities' => $itemQuantities],
        $proof,
        null,
        $admin,
    );
    expect($do->status)->toBe(DeliveryOrder::STATUS_DELIVERED);

    // Stok tetap 490 (tak dobel-potong). Tidak ada sale_out ber-ref DO.
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(490);
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_reserved'))->toBe(0);
    expect(StockLedger::query()->where('product_id', $product->id)->where('type', StockLedger::TYPE_SALE_OUT)->where('ref_type', 'DO')->exists())->toBeFalse();

    // SO jadi delivered.
    expect($so->refresh()->status)->toBe(SalesOrder::STATUS_DELIVERED);

    // 7. Invoice auto-generate dari DO delivered.
    $invoice = Invoice::query()->where('delivery_order_id', $do->id)->firstOrFail();
    expect((float) $invoice->total)->toBe(30000.0);
    expect((float) $invoice->outstanding)->toBe(30000.0);
    expect($invoice->status)->toBe(Invoice::STATUS_OPEN);

    // 8. Bayar lunas via payment request verified.
    $pr = PaymentRequest::create([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'sales_id' => $admin->id,
        'amount' => 30000,
        'method' => PaymentRequest::METHOD_TRANSFER,
        'reference_no' => 'TRX-1',
        'paid_at' => now(),
        'status' => PaymentRequest::STATUS_SUBMITTED,
        'submitted_at' => now(),
        'created_by' => $admin->id,
    ]);
    app(PaymentService::class)->createFromRequest($pr, $admin);

    $invoice->refresh();
    expect((float) $invoice->paid_amount)->toBe(30000.0);
    expect((float) $invoice->outstanding)->toBe(0.0);
    expect($invoice->status)->toBe(Invoice::STATUS_PAID);
});

test('SO submit ditolak kalau stok tidak cukup', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(5); // cuma 5
    $customer = makeCustomer();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]], // minta 10
        $admin,
    );

    expect(fn () => app(SalesOrderService::class)->submit($so, $admin))
        ->toThrow(ValidationException::class);
});

test('cancel SO approved mengembalikan stok yang sudah dipotong', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(100);
    $customer = makeCustomer();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 20]],
        $admin,
    );
    $so = app(SalesOrderService::class)->submit($so, $admin);
    $so = app(SalesOrderService::class)->approve($so, $admin);
    // Approve memotong stok: 100 → 80.
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(80);

    app(SalesOrderService::class)->cancel($so, 'Batal customer', $admin);
    expect($so->refresh()->status)->toBe(SalesOrder::STATUS_CANCELLED);
    // Stok kembali (return_in) → 100. Catatan batch jadi RELEASED.
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(100);
    expect(SoReservation::query()->where('sales_order_id', $so->id)->where('status', SoReservation::STATUS_RELEASED)->count())->toBeGreaterThan(0);
    expect(StockLedger::query()->where('product_id', $product->id)->where('type', StockLedger::TYPE_RETURN_IN)->exists())->toBeTrue();
});

test('partial delivery: kirim sebagian → SO partially_delivered, sisa bisa di-DO lagi', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(200);
    $customer = makeCustomer();

    // SO 30 PCS.
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 30]],
        $admin,
    );
    $so = app(SalesOrderService::class)->submit($so, $admin);
    $so = app(SalesOrderService::class)->approve($so, $admin);
    $soItem = $so->items()->first();

    // DO pertama: kirim 20 dari 30.
    $doSvc = app(DeliveryOrderService::class);
    $do = $doSvc->createFromSo($so, [['so_item_id' => $soItem->id, 'qty_planned' => 20]], $admin);
    $do = $doSvc->startPicking($do, $admin);
    $do = $doSvc->confirmPicks($do, $do->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_picked' => 20])->all(), $admin);
    [$driver, $vehicle] = makeDriverAndVehicle();
    $do = $doSvc->markPacked($do, $driver->id, $vehicle->id, $admin);
    $do = $doSvc->startDelivery($do, $admin);
    $do = $doSvc->markDelivered(
        $do,
        ['receiver_name' => 'X', 'item_quantities' => $do->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_delivered' => 20, 'qty_returned' => 0])->all()],
        File::image('p.jpg'),
        null,
        $admin,
    );

    // SO partially_delivered, qty_delivered 20.
    expect($so->refresh()->status)->toBe(SalesOrder::STATUS_PARTIALLY_DELIVERED);
    expect((int) $soItem->refresh()->qty_delivered)->toBe(20);
    // Stok sudah dipotong PENUH 30 saat approve (200 → 170); kirim 20 tak
    // memotong lagi.
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(170);
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_reserved'))->toBe(0);

    // Sisa 10 masih bisa di-DO lagi (batch tercatat, belum semua dialokasikan).
    $do2 = $doSvc->createFromSo($so->refresh(), [['so_item_id' => $soItem->id, 'qty_planned' => 10]], $admin);
    expect((int) $do2->items()->sum('qty_planned'))->toBe(10);
    $do2 = $doSvc->startPicking($do2, $admin);
    $do2 = $doSvc->confirmPicks($do2, $do2->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_picked' => 10])->all(), $admin);
    $do2 = $doSvc->markPacked($do2, $driver->id, $vehicle->id, $admin);
    $do2 = $doSvc->startDelivery($do2, $admin);
    $do2 = $doSvc->markDelivered(
        $do2,
        ['receiver_name' => 'Y', 'item_quantities' => $do2->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_delivered' => 10, 'qty_returned' => 0])->all()],
        File::image('p2.jpg'),
        null,
        $admin,
    );
    // Semua 30 terkirim → SO delivered, stok tetap 170 (tak dobel-potong).
    expect($so->refresh()->status)->toBe(SalesOrder::STATUS_DELIVERED);
    expect((int) $soItem->refresh()->qty_delivered)->toBe(30);
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(170);
});

test('submit SO over credit limit → flash.error (tidak silent)', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(500);
    $customer = makeCustomer();

    // Set limit per-supplier customer ke kecil (Rp 1.000) untuk supplier produk ini.
    CustomerSupplierCreditLimit::create([
        'customer_id' => $customer->id,
        'supplier_id' => $product->supplier_id,
        'credit_limit' => 1000,
        'created_by' => $admin->id,
    ]);

    // SO 10 PCS @ 3000 = 30.000 → jauh di atas limit 1.000.
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );

    // Submit via HTTP → harus redirect dengan flash.error, BUKAN diam-diam.
    $this->actingAs($admin)
        ->post(route('sales-orders.submit', $so->id))
        ->assertRedirect()
        ->assertSessionHas('flash.error');

    // Status tetap draft (tidak berubah).
    expect($so->refresh()->status)->toBe(SalesOrder::STATUS_DRAFT);
});

test('edit SO submitted mengubah qty & total, status tetap submitted', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(500);
    $customer = makeCustomer();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );
    $so = app(SalesOrderService::class)->submit($so, $admin);
    expect($so->status)->toBe(SalesOrder::STATUS_SUBMITTED);
    expect($so->canBeEdited())->toBeTrue();

    // Edit qty 10 → 15. Stok belum dipotong (submitted), jadi on_hand tetap 500.
    app(SalesOrderService::class)->updateDraft(
        $so,
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => 7],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 15]],
        $admin,
    );

    $so->refresh();
    expect($so->status)->toBe(SalesOrder::STATUS_SUBMITTED);
    expect((int) $so->items()->first()->qty)->toBe(15);
    expect((float) $so->total)->toBe(45000.0); // 15 × 3000
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(500);
});

test('edit SO approved (belum ada DO) menyinkronkan ulang stok, status tetap approved', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(500);
    $customer = makeCustomer();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );
    $so = app(SalesOrderService::class)->submit($so, $admin);
    $so = app(SalesOrderService::class)->approve($so, $admin);
    // Approve memotong 10 → 490.
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(490);
    expect($so->canBeEdited())->toBeTrue(); // belum ada DO

    // Edit qty 10 → 25. Sistem balikkan 10 lalu potong 25 → 500 − 25 = 475.
    app(SalesOrderService::class)->updateDraft(
        $so,
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => 7],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 25]],
        $admin,
    );

    $so->refresh();
    expect($so->status)->toBe(SalesOrder::STATUS_APPROVED); // status TETAP
    expect((int) $so->items()->first()->qty)->toBe(25);
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(475);
    // Ada catatan batch CONSUMED baru untuk item baru.
    expect(SoReservation::query()->where('sales_order_id', $so->id)->where('status', SoReservation::STATUS_CONSUMED)->exists())->toBeTrue();
});

test('edit SO approved ditolak kalau sudah punya DO', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(500);
    $customer = makeCustomer();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );
    $so = app(SalesOrderService::class)->submit($so, $admin);
    $so = app(SalesOrderService::class)->approve($so, $admin);

    // Buat DO → sekarang SO tak boleh diedit.
    $soItem = $so->items()->first();
    app(DeliveryOrderService::class)->createFromSo($so, [['so_item_id' => $soItem->id, 'qty_planned' => 10]], $admin);

    expect($so->refresh()->canBeEdited())->toBeFalse();

    expect(fn () => app(SalesOrderService::class)->updateDraft(
        $so,
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => 7],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 5]],
        $admin,
    ))->toThrow(ValidationException::class);

    // Stok & qty item tak berubah.
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(490);
    expect((int) $so->items()->first()->qty)->toBe(10);
});

/** Bawa SO sampai delivered + invoice terbit. Return [so, invoice]. */
function deliverSoFully(User $admin, Product $product, ProductUnit $pcsUnit, Customer $customer): array
{
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );
    $so = app(SalesOrderService::class)->submit($so, $admin);
    $so = app(SalesOrderService::class)->approve($so, $admin);
    $soItem = $so->items()->first();

    $doSvc = app(DeliveryOrderService::class);
    $do = $doSvc->createFromSo($so, [['so_item_id' => $soItem->id, 'qty_planned' => 10]], $admin);
    $do = $doSvc->startPicking($do, $admin);
    $do = $doSvc->confirmPicks($do, $do->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_picked' => 10])->all(), $admin);
    [$driver, $vehicle] = makeDriverAndVehicle();
    $do = $doSvc->markPacked($do, $driver->id, $vehicle->id, $admin);
    $do = $doSvc->startDelivery($do, $admin);
    $do = $doSvc->markDelivered(
        $do,
        ['receiver_name' => 'X', 'item_quantities' => $do->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_delivered' => 10, 'qty_returned' => 0])->all()],
        File::image('p.jpg'),
        null,
        $admin,
    );

    $invoice = Invoice::query()->where('sales_order_id', $so->id)->firstOrFail();

    return [$so->refresh(), $invoice];
}

test('SO delivered: halaman show mengirim canAdjust + dokumen DO & faktur', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(500);
    $customer = makeCustomer();
    [$so, $invoice] = deliverSoFully($admin, $product, $pcsUnit, $customer);

    $this->actingAs($admin)
        ->get(route('sales-orders.show', $so->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canAdjust', true)
            ->where('canEdit', false) // sudah delivered → tak bisa edit langsung
            ->has('salesOrder.delivery_orders', 1)
            ->has('salesOrder.invoices', 1)
        );
});

test('SO approved belum delivered: canAdjust false', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(500);
    $customer = makeCustomer();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );
    $so = app(SalesOrderService::class)->submit($so, $admin);
    $so = app(SalesOrderService::class)->approve($so, $admin);

    $this->actingAs($admin)
        ->get(route('sales-orders.show', $so->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canAdjust', false)
            ->where('canEdit', true) // approved belum ada DO → masih bisa edit
        );
});

test('halaman buat retur prefill customer & invoice dari query', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(500);
    $customer = makeCustomer();
    [$so, $invoice] = deliverSoFully($admin, $product, $pcsUnit, $customer);

    $this->actingAs($admin)
        ->get(route('customer-returns.create', ['invoice_id' => $invoice->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('prefill.invoice_id', $invoice->id)
            ->where('prefill.customer_id', $customer->id)
        );
});

test('endpoint ESC/P DO mengembalikan payload dot-matrix dengan kode kontrol', function (): void {
    $admin = salesAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = seedProductWithStock(500);
    $customer = makeCustomer();
    deliverSoFully($admin, $product, $pcsUnit, $customer);
    $do = DeliveryOrder::query()->latest('id')->firstOrFail();

    $res = $this->actingAs($admin)->get(route('delivery-orders.escp', $do->id));
    $res->assertOk();
    $body = $res->getContent();

    // Kode kontrol ESC/P: reset (1B40) + NLQ (1B7831) + condensed (0F) di awal,
    // form feed (0C) di akhir.
    expect(str_starts_with($body, "\x1B@\x1Bx1\x0F"))->toBeTrue();
    expect(str_ends_with($body, "\x0C"))->toBeTrue();
    // Data DO ikut tercetak.
    expect($body)->toContain($do->do_number);
    expect($body)->toContain('FAKTUR PENJUALAN');
    expect($body)->toContain('Prod'); // nama produk seed
});
