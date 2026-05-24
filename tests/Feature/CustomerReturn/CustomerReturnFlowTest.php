<?php

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\PriceTier;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Billing\InvoicePdfRenderer;
use App\Services\CustomerReturn\CreditNoteService;
use App\Services\CustomerReturn\CustomerReturnService;
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

function crUser(string $roleCode): User
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

function crSetupContext(float $invoiceTotal = 300_000, int $qtyReturned = 0): array
{
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-CR-'.random_int(1000, 9999),
        'name' => 'Customer CR',
        'price_tier_id' => $tier->id,
        'credit_limit' => 100_000_000,
        'payment_term_days' => 7,
        'is_active' => true,
        'address' => 'Jl. Test',
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk CR '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $unit = $product->units->first();
    ProductPrice::query()
        ->where('product_id', $product->id)
        ->where('product_unit_id', $unit->id)
        ->where('price_tier_id', $tier->id)
        ->update(['price' => $invoiceTotal]);

    $batch = ProductBatch::create([
        'product_id' => $product->id,
        'batch_code' => 'B-CR-'.random_int(1000, 9999),
        'expired_date' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $unit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 1000, 'cost_price' => 1000,
        'ref_type' => 'SEED', 'ref_id' => 1,
    ]);

    $admin = crUser(Role::CODE_SUPERADMIN);
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => 7],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 1]],
        $admin,
    );
    app(SalesOrderService::class)->submit($so, $admin);
    app(SalesOrderService::class)->approve($so, $admin);

    $driver = Driver::create([
        'code' => 'DRV-'.random_int(100, 999),
        'name' => 'D',
        'license_expired_date' => now()->addYear()->toDateString(),
        'is_active' => true,
        'status' => Driver::STATUS_IDLE,
    ]);
    $vehicle = Vehicle::create([
        'code' => 'VEH-'.random_int(100, 999),
        'plate_number' => 'BL '.random_int(1000, 9999).' P',
        'type' => 'pickup',
        'is_active' => true,
        'status' => Vehicle::STATUS_IDLE,
    ]);
    VehicleDocument::create([
        'vehicle_id' => $vehicle->id, 'type' => 'STNK', 'title' => 'STNK',
        'file_path' => 'd.pdf', 'expires_date' => now()->addYear()->toDateString(),
    ]);

    $do = app(DeliveryOrderService::class)->createFromSo(
        $so,
        [['so_item_id' => $so->items->first()->id, 'qty_planned' => 1]],
        $admin,
    );
    app(DeliveryOrderService::class)->startPicking($do, $admin);
    app(DeliveryOrderService::class)->confirmPicks(
        $do,
        [['item_id' => $do->items->first()->id, 'qty_picked' => 1]],
        $admin,
    );
    app(DeliveryOrderService::class)->markPacked($do, $driver->id, $vehicle->id, $admin);
    app(DeliveryOrderService::class)->startDelivery($do, $admin);
    app(DeliveryOrderService::class)->markDelivered(
        $do,
        [
            'receiver_name' => 'X',
            'item_quantities' => [
                ['item_id' => $do->items->first()->id, 'qty_delivered' => 1, 'qty_returned' => $qtyReturned],
            ],
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $admin,
    );

    $invoice = Invoice::query()->where('delivery_order_id', $do->id)->firstOrFail();

    return compact('customer', 'product', 'unit', 'batch', 'invoice', 'do', 'admin');
}

test('manual create CR draft', function (): void {
    $ctx = crSetupContext();

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'invoice_id' => $ctx['invoice']->id,
        'reason_code' => 'damaged',
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 5,
            'unit_price' => 1000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);

    expect($cr->status)->toBe(CustomerReturn::STATUS_DRAFT);
    expect($cr->return_number)->toStartWith('RTR-C-');
    expect($cr->items)->toHaveCount(1);
    expect((float) $cr->total_value)->toBe(5000.0);
});

test('auto-create CR dari DO partial return', function (): void {
    $ctx = crSetupContext(invoiceTotal: 300_000, qtyReturned: 1);

    $cr = CustomerReturn::query()->where('delivery_order_id', $ctx['do']->id)->first();
    expect($cr)->not->toBeNull();
    expect($cr->status)->toBe(CustomerReturn::STATUS_SORTED);
    expect($cr->items->first()->qty_bs)->toBe(1);
    expect($cr->items->first()->qty_good)->toBe(0);
});

test('sortir: qty_good + qty_bs != qty_total ditolak', function (): void {
    $ctx = crSetupContext();

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'invoice_id' => $ctx['invoice']->id,
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 10,
            'unit_price' => 1000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);

    expect(fn () => app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 3, 'qty_bs' => 5],
    ], $ctx['admin']))->toThrow(ValidationException::class);
});

test('sortir sukses → status sorted dengan qty terdistribusi', function (): void {
    $ctx = crSetupContext();

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 10,
            'unit_price' => 1000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);

    $cr = app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 7, 'qty_bs' => 3],
    ], $ctx['admin']);

    expect($cr->status)->toBe(CustomerReturn::STATUS_SORTED);
    expect($cr->items->first()->qty_good)->toBe(7);
    expect($cr->items->first()->qty_bs)->toBe(3);
});

test('posting: qty_good masuk ke stock_ledger return_in', function (): void {
    $ctx = crSetupContext();

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 10,
            'unit_price' => 1000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);

    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 7, 'qty_bs' => 3],
    ], $ctx['admin']);

    $balanceBefore = StockBalance::query()
        ->where('product_id', $ctx['product']->id)
        ->where('batch_id', $ctx['batch']->id)
        ->value('qty_on_hand');

    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $balanceAfter = StockBalance::query()
        ->where('product_id', $ctx['product']->id)
        ->where('batch_id', $ctx['batch']->id)
        ->value('qty_on_hand');

    expect((int) $balanceAfter - (int) $balanceBefore)->toBe(7);

    $ledger = StockLedger::query()
        ->where('ref_type', 'CustomerReturn')
        ->where('ref_id', $cr->id)
        ->where('type', StockLedger::TYPE_RETURN_IN)
        ->first();
    expect($ledger)->not->toBeNull();
    expect((int) $ledger->qty_in)->toBe(7);
});

test('posting: qty_bs tidak masuk ke stock', function (): void {
    $ctx = crSetupContext();

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 10,
            'unit_price' => 1000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);

    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 0, 'qty_bs' => 10],
    ], $ctx['admin']);

    $balanceBefore = StockBalance::query()
        ->where('product_id', $ctx['product']->id)
        ->where('batch_id', $ctx['batch']->id)
        ->value('qty_on_hand');

    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $balanceAfter = StockBalance::query()
        ->where('product_id', $ctx['product']->id)
        ->where('batch_id', $ctx['batch']->id)
        ->value('qty_on_hand');

    expect((int) $balanceAfter)->toBe((int) $balanceBefore);
});

test('posting menghasilkan credit note dengan remaining penuh (no linked invoice)', function (): void {
    $ctx = crSetupContext();

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        // tanpa invoice_id
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 5,
            'unit_price' => 2000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);

    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 5, 'qty_bs' => 0],
    ], $ctx['admin']);

    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $cr->refresh();
    $cn = CreditNote::query()->where('customer_return_id', $cr->id)->first();
    expect($cn)->not->toBeNull();
    expect((float) $cn->amount)->toBe(10000.0);
    expect((float) $cn->remaining_amount)->toBe(10000.0);
    expect($cn->status)->toBe(CreditNote::STATUS_OPEN);
    expect($cr->status)->toBe(CustomerReturn::STATUS_POSTED);
});

test('posting CR linked invoice → CN auto-applied ke invoice', function (): void {
    $ctx = crSetupContext(invoiceTotal: 300_000);

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'invoice_id' => $ctx['invoice']->id,
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 5,
            'unit_price' => 10_000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);

    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 5, 'qty_bs' => 0],
    ], $ctx['admin']);

    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $invoice = $ctx['invoice']->fresh();
    expect((float) $invoice->paid_amount)->toBe(50_000.0);
    expect((float) $invoice->outstanding)->toBe(250_000.0);

    $cn = CreditNote::query()->where('customer_return_id', $cr->id)->first();
    expect((float) $cn->applied_amount)->toBe(50_000.0);
    expect((float) $cn->remaining_amount)->toBe(0.0);
    expect($cn->status)->toBe(CreditNote::STATUS_CLOSED);
    expect($cr->fresh()->status)->toBe(CustomerReturn::STATUS_CREDITED);
});

test('apply CN > remaining → 422', function (): void {
    $ctx = crSetupContext(invoiceTotal: 1_000_000);

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 1, 'unit_price' => 50_000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 1, 'qty_bs' => 0],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $cn = CreditNote::query()->where('customer_return_id', $cr->id)->first();

    expect(fn () => app(CreditNoteService::class)->applyToInvoice(
        $cn, $ctx['invoice']->fresh(), 100_000, $ctx['admin']
    ))->toThrow(ValidationException::class);
});

test('apply CN > invoice outstanding → 422', function (): void {
    $ctx = crSetupContext(invoiceTotal: 30_000);

    // CR senilai 100k tanpa invoice link
    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 1, 'unit_price' => 100_000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 1, 'qty_bs' => 0],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $cn = CreditNote::query()->where('customer_return_id', $cr->id)->first();

    expect(fn () => app(CreditNoteService::class)->applyToInvoice(
        $cn, $ctx['invoice']->fresh(), 50_000, $ctx['admin']
    ))->toThrow(ValidationException::class);
});

test('multi-apply CN ke beberapa invoice', function (): void {
    $ctx = crSetupContext(invoiceTotal: 30_000);

    // CR 100k tanpa link
    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 1, 'unit_price' => 100_000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 1, 'qty_bs' => 0],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $cn = CreditNote::query()->where('customer_return_id', $cr->id)->first();

    // Apply 20k ke invoice 30k
    app(CreditNoteService::class)->applyToInvoice($cn, $ctx['invoice']->fresh(), 20_000, $ctx['admin']);

    $cn->refresh();
    expect((float) $cn->applied_amount)->toBe(20_000.0);
    expect((float) $cn->remaining_amount)->toBe(80_000.0);
    expect($cn->status)->toBe(CreditNote::STATUS_APPLIED);

    // Apply lagi 10k ke invoice yang sama
    app(CreditNoteService::class)->applyToInvoice($cn->refresh(), $ctx['invoice']->fresh(), 10_000, $ctx['admin']);

    $cn->refresh();
    expect((float) $cn->applied_amount)->toBe(30_000.0);
    expect($cn->applications)->toHaveCount(2);
});

test('CN fully applied → status closed + CR credited', function (): void {
    $ctx = crSetupContext(invoiceTotal: 100_000);

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'invoice_id' => $ctx['invoice']->id,
        'items' => [[
            'product_id' => $ctx['product']->id,
            'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 1, 'unit_price' => 100_000,
            'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 1, 'qty_bs' => 0],
    ], $ctx['admin']);

    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $cn = CreditNote::query()->where('customer_return_id', $cr->id)->first();
    expect($cn->status)->toBe(CreditNote::STATUS_CLOSED);
    expect($cr->fresh()->status)->toBe(CustomerReturn::STATUS_CREDITED);
});

test('cancel draft sukses', function (): void {
    $ctx = crSetupContext();

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 1, 'unit_price' => 1000, 'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);

    app(CustomerReturnService::class)->cancel($cr, 'Test cancel', $ctx['admin']);

    expect($cr->fresh()->status)->toBe(CustomerReturn::STATUS_CANCELLED);
});

test('cancel posted ditolak', function (): void {
    $ctx = crSetupContext();

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 1, 'unit_price' => 1000, 'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 1, 'qty_bs' => 0],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    expect(fn () => app(CustomerReturnService::class)->cancel($cr->fresh(), 'X', $ctx['admin']))
        ->toThrow(ValidationException::class);
});

test('CN cross-customer ditolak', function (): void {
    $ctx = crSetupContext();
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $otherCustomer = Customer::create([
        'code' => 'CUST-OTHER-'.random_int(1000, 9999),
        'name' => 'Other',
        'price_tier_id' => $tier->id,
        'credit_limit' => 100_000_000,
        'payment_term_days' => 7,
        'is_active' => true,
    ]);

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $otherCustomer->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 1, 'unit_price' => 50_000, 'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 1, 'qty_bs' => 0],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $cn = CreditNote::query()->where('customer_return_id', $cr->id)->first();

    expect(fn () => app(CreditNoteService::class)->applyToInvoice(
        $cn, $ctx['invoice'], 1000, $ctx['admin']
    ))->toThrow(ValidationException::class);
});

test('CR qty_good + qty_bs > qty_total saat create ditolak', function (): void {
    $ctx = crSetupContext();

    expect(fn () => app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 10, 'qty_good' => 7, 'qty_bs' => 5,
            'unit_price' => 1000, 'batch_id' => $ctx['batch']->id,
        ]],
    ], $ctx['admin']))->toThrow(ValidationException::class);
});

test('idempotent: DO partial return tidak duplikat CR', function (): void {
    $ctx = crSetupContext(invoiceTotal: 300_000, qtyReturned: 1);

    // Trigger ulang manual
    $cr1 = CustomerReturn::query()->where('delivery_order_id', $ctx['do']->id)->first();
    $cr2 = app(CustomerReturnService::class)->createFromDeliveryReject($ctx['do']->fresh(), $ctx['admin']);

    expect($cr2->id)->toBe($cr1->id);
    expect(CustomerReturn::query()->where('delivery_order_id', $ctx['do']->id)->count())->toBe(1);
});

test('sales tidak bisa lihat CR sales lain (policy)', function (): void {
    $ctx = crSetupContext();
    $sales1 = crUser(Role::CODE_SALES);
    $sales2 = crUser(Role::CODE_SALES);

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'sales_id' => $sales1->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 1, 'unit_price' => 1000, 'batch_id' => $ctx['batch']->id,
        ]],
    ], $sales1);

    expect($sales1->can('view', $cr))->toBeTrue();
    expect($sales2->can('view', $cr))->toBeFalse();
});

test('return tanpa batch → auto-create return pool batch', function (): void {
    $ctx = crSetupContext();

    $cr = app(CustomerReturnService::class)->createDraft([
        'customer_id' => $ctx['customer']->id,
        'return_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $ctx['product']->id, 'product_unit_id' => $ctx['unit']->id,
            'qty_total' => 5, 'unit_price' => 1000,
            // tanpa batch_id
        ]],
    ], $ctx['admin']);
    app(CustomerReturnService::class)->updateSortResult($cr, [
        ['id' => $cr->items->first()->id, 'qty_good' => 5, 'qty_bs' => 0],
    ], $ctx['admin']);

    app(CustomerReturnService::class)->post($cr->refresh(), $ctx['admin']);

    $poolBatch = ProductBatch::query()
        ->where('product_id', $ctx['product']->id)
        ->where('batch_code', 'RETURN-POOL-'.$ctx['product']->id)
        ->first();

    expect($poolBatch)->not->toBeNull();

    $bal = StockBalance::query()
        ->where('product_id', $ctx['product']->id)
        ->where('batch_id', $poolBatch->id)
        ->value('qty_on_hand');
    expect((int) $bal)->toBe(5);
});
