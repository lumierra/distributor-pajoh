<?php

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\PriceTier;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Billing\InvoicePdfRenderer;
use App\Services\Billing\InvoiceService;
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
use Illuminate\Support\Facades\Cache;
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

    // Stub PDF renderers (DO + Invoice)
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
    $this->app->bind(InvoicePdfRenderer::class, function ($app) {
        return new class($app->make(SettingManager::class)) extends InvoicePdfRenderer
        {
            public function generate(Invoice $invoice): string
            {
                $invoice->update([
                    'pdf_path' => "invoices/{$invoice->fiscal_year}/stub.pdf",
                    'pdf_generated_at' => now(),
                ]);

                return $invoice->pdf_path;
            }
        };
    });
});

function invUser(string $roleCode): User
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

function invFullDeliveredDo(int $orderQty = 30, int $deliveredQty = 30, int $returnedQty = 0, int $paymentTermDays = 7): array
{
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-INV-'.random_int(1000, 9999),
        'name' => 'Customer INV',
        'price_tier_id' => $tier->id,
        'credit_limit' => 100_000_000,
        'payment_term_days' => $paymentTermDays,
        'is_active' => true,
        'address' => 'Jl. Test, Langsa',
        'phone' => '0812-0000-0000',
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk INV '.random_int(100, 999),
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

    $batch = ProductBatch::create([
        'product_id' => $product->id,
        'batch_code' => 'BATCH-INV-'.random_int(1000, 9999),
        'expired_date' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $unit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 1000, 'cost_price' => 5000,
        'ref_type' => 'SEED', 'ref_id' => 1,
    ]);

    $admin = invUser(Role::CODE_SUPERADMIN);
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => $paymentTermDays],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => $orderQty]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);
    app(SalesOrderService::class)->approve($so, $admin);

    $driver = Driver::create([
        'code' => 'DRV-INV-'.random_int(100, 999),
        'name' => 'Driver INV',
        'license_no' => '999',
        'license_type' => 'B1',
        'license_expired_date' => now()->addYear()->toDateString(),
        'is_active' => true,
        'status' => Driver::STATUS_IDLE,
    ]);
    $vehicle = Vehicle::create([
        'code' => 'VEH-INV-'.random_int(100, 999),
        'plate_number' => 'BL '.random_int(1000, 9999).' AA',
        'type' => 'pickup',
        'is_active' => true,
        'status' => Vehicle::STATUS_IDLE,
    ]);
    $do = app(DeliveryOrderService::class)->createFromSo(
        $so,
        [['so_item_id' => $so->items->first()->id, 'qty_planned' => $deliveredQty]],
        $admin,
    );
    app(DeliveryOrderService::class)->startPicking($do, $admin);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => $deliveredQty]],
        $admin,
    );
    app(DeliveryOrderService::class)->markPacked($do, $driver->id, $vehicle->id, $admin);
    app(DeliveryOrderService::class)->startDelivery($do, $admin);
    app(DeliveryOrderService::class)->markDelivered(
        $do,
        [
            'receiver_name' => 'Customer Receiver',
            'item_quantities' => [[
                'item_id' => $do->items->first()->id,
                'qty_delivered' => $deliveredQty,
                'qty_returned' => $returnedQty,
            ]],
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $admin,
    );

    return compact('customer', 'so', 'do', 'admin', 'product', 'unit', 'batch');
}

test('markDelivered auto-generate invoice', function (): void {
    $ctx = invFullDeliveredDo(orderQty: 30, deliveredQty: 30);

    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->invoice_number)->toStartWith('DIS/');
    expect($invoice->status)->toBe(Invoice::STATUS_OPEN);
    expect((float) $invoice->total)->toBe(300_000.0); // 30 × 10000
    expect((float) $invoice->outstanding)->toBe(300_000.0);
    expect((float) $invoice->paid_amount)->toBe(0.0);
});

test('generateFromDeliveredDo idempotent', function (): void {
    $ctx = invFullDeliveredDo();

    // Panggil lagi langsung — should return existing, bukan duplicate.
    $second = app(InvoiceService::class)->generateFromDeliveredDo($ctx['do']->refresh(), $ctx['admin']);
    $count = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->count();

    expect($count)->toBe(1);
    expect($second->id)->toBe(Invoice::query()->where('delivery_order_id', $ctx['do']->id)->value('id'));
});

test('generateFromDeliveredDo dengan DO non-delivered ditolak', function (): void {
    // Bikin DO draft (belum delivered)
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-X-'.random_int(1000, 9999),
        'name' => 'X', 'price_tier_id' => $tier->id, 'is_active' => true, 'credit_limit' => 1_000_000,
    ]);
    $product = app(ProductService::class)->create(
        ['name' => 'P', 'category_id' => ProductCategory::where('code', 'MIE')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $admin = invUser(Role::CODE_SUPERADMIN);
    $batch = ProductBatch::create(['product_id' => $product->id, 'batch_code' => 'B', 'is_active' => true]);
    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $product->units->first()->id,
        'type' => 'purchase_in', 'qty_in' => 100, 'cost_price' => 100, 'ref_type' => 'SEED', 'ref_id' => 1,
    ]);
    ProductPrice::query()->where('product_id', $product->id)->update(['price' => 10000]);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString()],
        [['product_id' => $product->id, 'product_unit_id' => $product->units->first()->id, 'qty' => 5]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);
    app(SalesOrderService::class)->approve($so, $admin);

    $do = app(DeliveryOrderService::class)->createFromSo(
        $so,
        [['so_item_id' => $so->items->first()->id, 'qty_planned' => 5]],
        $admin,
    );

    expect(fn () => app(InvoiceService::class)->generateFromDeliveredDo($do, $admin))
        ->toThrow(ValidationException::class);
});

test('invoice items snapshot dari SO items (price + Z1/Z2 + bonus)', function (): void {
    // SO dengan Z1+Z2 + bonus
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-SS-'.random_int(1000, 9999),
        'name' => 'CS', 'price_tier_id' => $tier->id, 'is_active' => true, 'credit_limit' => 100_000_000,
        'payment_term_days' => 7,
    ]);
    $product = app(ProductService::class)->create(
        ['name' => 'Mie ABC', 'category_id' => ProductCategory::where('code', 'MIE')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $unit = $product->units->first();
    ProductPrice::query()->where('product_id', $product->id)->where('product_unit_id', $unit->id)
        ->where('price_tier_id', $tier->id)->update(['price' => 20_000]);
    $batch = ProductBatch::create(['product_id' => $product->id, 'batch_code' => 'BSnap', 'expired_date' => now()->addYear()->toDateString(), 'is_active' => true]);
    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $unit->id,
        'type' => 'purchase_in', 'qty_in' => 1000, 'cost_price' => 8000, 'ref_type' => 'SEED', 'ref_id' => 1,
    ]);

    $admin = invUser(Role::CODE_SUPERADMIN);
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString()],
        [
            ['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 10, 'discount_z1_pct' => 10, 'discount_z2_pct' => 5],
            ['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 2, 'is_bonus' => true],
        ],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);
    app(SalesOrderService::class)->approve($so, $admin);

    // SO total = 10 × 20000 × 0.9 × 0.95 = 171000

    $driver = Driver::create(['code' => 'DRV', 'name' => 'D', 'license_expired_date' => now()->addYear()->toDateString(), 'is_active' => true, 'status' => 'idle']);
    $vehicle = Vehicle::create(['code' => 'VEH', 'plate_number' => 'BL 1234 SS', 'type' => 'pickup', 'is_active' => true, 'status' => 'idle']);

    $do = app(DeliveryOrderService::class)->createFromSo(
        $so,
        [
            ['so_item_id' => $so->items->first()->id, 'qty_planned' => 10],
            ['so_item_id' => $so->items->last()->id, 'qty_planned' => 2],
        ],
        $admin,
    );
    app(DeliveryOrderService::class)->startPicking($do, $admin);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        $do->items->map(fn ($i) => ['item_id' => $i->id, 'qty_picked' => $i->qty_planned])->all(),
        $admin,
    );
    app(DeliveryOrderService::class)->markPacked($do, $driver->id, $vehicle->id, $admin);
    app(DeliveryOrderService::class)->startDelivery($do, $admin);
    app(DeliveryOrderService::class)->markDelivered(
        $do,
        [
            'receiver_name' => 'X',
            'item_quantities' => $do->items->map(fn ($i) => [
                'item_id' => $i->id, 'qty_delivered' => $i->qty_planned, 'qty_returned' => 0,
            ])->all(),
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $admin,
    );

    $invoice = Invoice::query()->where('delivery_order_id', $do->id)->firstOrFail();
    expect($invoice->items)->toHaveCount(2);

    $reg = $invoice->items->where('is_bonus', false)->first();
    expect((float) $reg->unit_price)->toBe(20_000.0);
    expect((float) $reg->discount_z1_pct)->toBe(10.0);
    expect((float) $reg->discount_z2_pct)->toBe(5.0);
    expect((float) $reg->unit_net_price)->toBe(17_100.0); // 20000 × 0.9 × 0.95
    expect((float) $reg->line_subtotal)->toBe(171_000.0);

    $bonus = $invoice->items->where('is_bonus', true)->first();
    expect((float) $bonus->unit_price)->toBe(0.0);
    expect((float) $bonus->line_subtotal)->toBe(0.0);
    expect($bonus->qty)->toBe(2);

    expect((float) $invoice->total)->toBe(171_000.0);
});

test('partial delivered invoice hanya cover qty effective', function (): void {
    // Order 30, delivered 20, returned 5 → effective = 15
    $ctx = invFullDeliveredDo(orderQty: 30, deliveredQty: 20, returnedQty: 5);

    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();
    expect($invoice->items->first()->qty)->toBe(15); // 20 - 5
    expect((float) $invoice->total)->toBe(150_000.0); // 15 × 10000
});

test('applyPayment partial → status partial_paid', function (): void {
    $ctx = invFullDeliveredDo();
    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();

    app(InvoiceService::class)->applyPayment($invoice, 100_000, $ctx['admin']);

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PARTIAL_PAID);
    expect((float) $invoice->paid_amount)->toBe(100_000.0);
    expect((float) $invoice->outstanding)->toBe(200_000.0);
});

test('applyPayment full → status paid + paid_at set', function (): void {
    $ctx = invFullDeliveredDo();
    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();

    app(InvoiceService::class)->applyPayment($invoice, 300_000, $ctx['admin']);

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PAID);
    expect((float) $invoice->outstanding)->toBe(0.0);
    expect($invoice->paid_at)->not->toBeNull();
});

test('applyPayment amount <= 0 ditolak', function (): void {
    $ctx = invFullDeliveredDo();
    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();

    expect(fn () => app(InvoiceService::class)->applyPayment($invoice, 0, $ctx['admin']))
        ->toThrow(ValidationException::class);
    expect(fn () => app(InvoiceService::class)->applyPayment($invoice, -100, $ctx['admin']))
        ->toThrow(ValidationException::class);
});

test('reversePayment kurangi paid_amount + reset paid_at', function (): void {
    $ctx = invFullDeliveredDo();
    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();

    app(InvoiceService::class)->applyPayment($invoice, 300_000, $ctx['admin']);
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);

    app(InvoiceService::class)->reversePayment($invoice, 300_000, $ctx['admin']);
    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_OPEN);
    expect((float) $invoice->paid_amount)->toBe(0.0);
    expect($invoice->paid_at)->toBeNull();
});

test('cash invoice (term=0) → is_cash=true + due_date=invoice_date', function (): void {
    $ctx = invFullDeliveredDo(paymentTermDays: 0);
    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();

    expect($invoice->is_cash)->toBeTrue();
    expect($invoice->due_date->toDateString())->toBe($invoice->invoice_date->toDateString());
});

test('markOverdueDue set status overdue untuk invoice yang sudah lewat due_date', function (): void {
    $ctx = invFullDeliveredDo();
    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();

    // Backdate due_date supaya overdue
    $invoice->update(['due_date' => now()->subDays(5)]);

    $count = app(InvoiceService::class)->markOverdueDue();

    expect($count)->toBeGreaterThanOrEqual(1);
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_OVERDUE);
    expect($invoice->fresh()->overdue_set_at)->not->toBeNull();
});

test('markOverdueDue tidak mengubah invoice paid', function (): void {
    $ctx = invFullDeliveredDo();
    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();

    app(InvoiceService::class)->applyPayment($invoice, 300_000, $ctx['admin']);
    $invoice->update(['due_date' => now()->subDays(5)]); // due lewat

    app(InvoiceService::class)->markOverdueDue();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

test('admin bisa lihat list invoice', function (): void {
    $admin = invUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Invoices/Index'));
});

test('kasir bisa lihat list invoice (read-only)', function (): void {
    $kasir = invUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('invoices.index'))
        ->assertOk();
});

test('show menampilkan invoice detail dengan items', function (): void {
    $ctx = invFullDeliveredDo();
    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();
    $admin = invUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Invoices/Show')
            ->where('invoice.id', $invoice->id),
        );
});

test('regenerate PDF endpoint update pdf_generated_at', function (): void {
    $ctx = invFullDeliveredDo();
    $invoice = Invoice::query()->where('delivery_order_id', $ctx['do']->id)->firstOrFail();

    // Generate PDF awal supaya pdf_generated_at terisi
    app(InvoicePdfRenderer::class)->generate($invoice);
    $beforeGenAt = $invoice->fresh()->pdf_generated_at;
    expect($beforeGenAt)->not->toBeNull();

    // Maju 1 menit agar timestamp regenerate beda
    $this->travelTo(now()->addMinute());

    $this->actingAs($ctx['admin'])
        ->post(route('invoices.regenerate-pdf', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->pdf_generated_at->gt($beforeGenAt))->toBeTrue();
});

test('header discount SO ter-prorate ke invoice', function (): void {
    // Buat SO dengan header discount 10%, lalu DO full
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-HD-'.random_int(1000, 9999),
        'name' => 'CHD', 'price_tier_id' => $tier->id, 'is_active' => true,
        'credit_limit' => 100_000_000, 'payment_term_days' => 7,
    ]);
    $product = app(ProductService::class)->create(
        ['name' => 'P', 'category_id' => ProductCategory::where('code', 'MIE')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $unit = $product->units->first();
    ProductPrice::query()->where('product_id', $product->id)->where('product_unit_id', $unit->id)
        ->where('price_tier_id', $tier->id)->update(['price' => 10_000]);
    $batch = ProductBatch::create(['product_id' => $product->id, 'batch_code' => 'BHD', 'expired_date' => now()->addYear()->toDateString(), 'is_active' => true]);
    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $unit->id,
        'type' => 'purchase_in', 'qty_in' => 1000, 'cost_price' => 5000, 'ref_type' => 'SEED', 'ref_id' => 1,
    ]);

    $admin = invUser(Role::CODE_SUPERADMIN);
    $so = app(SalesOrderService::class)->createDraft(
        [
            'customer_id' => $customer->id,
            'so_date' => now()->toDateString(),
            'header_discount_type' => 'percent',
            'header_discount_value' => 10,
        ],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 30]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);
    app(SalesOrderService::class)->approve($so, $admin);

    // SO total = 300k - 30k = 270k

    $driver = Driver::create(['code' => 'DRV-HD', 'name' => 'D', 'license_expired_date' => now()->addYear()->toDateString(), 'is_active' => true, 'status' => 'idle']);
    $vehicle = Vehicle::create(['code' => 'VEH-HD', 'plate_number' => 'BL 1 HD', 'type' => 'pickup', 'is_active' => true, 'status' => 'idle']);

    $do = app(DeliveryOrderService::class)->createFromSo(
        $so,
        [['so_item_id' => $so->items->first()->id, 'qty_planned' => 30]],
        $admin,
    );
    app(DeliveryOrderService::class)->startPicking($do, $admin);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 30]],
        $admin,
    );
    app(DeliveryOrderService::class)->markPacked($do, $driver->id, $vehicle->id, $admin);
    app(DeliveryOrderService::class)->startDelivery($do, $admin);
    app(DeliveryOrderService::class)->markDelivered(
        $do,
        [
            'receiver_name' => 'X',
            'item_quantities' => [['item_id' => $do->items->first()->id, 'qty_delivered' => 30, 'qty_returned' => 0]],
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $admin,
    );

    $invoice = Invoice::query()->where('delivery_order_id', $do->id)->firstOrFail();
    // DO subtotal = 300k, SO subtotal = 300k → ratio 1.0
    // header_discount full 30k ke invoice ini
    expect((float) $invoice->subtotal)->toBe(300_000.0);
    expect((float) $invoice->header_discount_amount)->toBe(30_000.0);
    expect((float) $invoice->total)->toBe(270_000.0);
});

test('customer outstanding cache di-invalidate setelah invoice generated', function (): void {
    // Test sederhana: cache key terhapus.
    Cache::put('customer:1:outstanding', 999.0, 300);

    $ctx = invFullDeliveredDo();

    expect(Cache::has("customer:{$ctx['customer']->id}:outstanding"))
        ->toBeFalse();
});
