<?php

use App\Models\ArAgingSnapshot;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\InvoiceExtensionLog;
use App\Models\Payment;
use App\Models\PaymentRequest;
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
use App\Services\Delivery\DeliveryOrderService;
use App\Services\Delivery\DoPdfRenderer;
use App\Services\Inventory\StockLedgerWriter;
use App\Services\Payment\ArAgingSnapshotService;
use App\Services\Payment\InvoiceExtensionService;
use App\Services\Payment\PaymentRequestService;
use App\Services\Payment\PaymentService;
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

    // Stub PDF renderers
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

function payUser(string $roleCode): User
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

function paySetupInvoice(float $invoiceTotal = 300_000, int $paymentTermDays = 7): array
{
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-PAY-'.random_int(1000, 9999),
        'name' => 'Customer PAY',
        'price_tier_id' => $tier->id,
        'credit_limit' => 100_000_000,
        'payment_term_days' => $paymentTermDays,
        'is_active' => true,
        'address' => 'Jl. Test',
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk PAY '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $unit = $product->units->first();
    // 1 unit = invoiceTotal, qty=1, supaya tidak ada rounding error
    ProductPrice::query()
        ->where('product_id', $product->id)
        ->where('product_unit_id', $unit->id)
        ->where('price_tier_id', $tier->id)
        ->update(['price' => $invoiceTotal]);

    $batch = ProductBatch::create([
        'product_id' => $product->id,
        'batch_code' => 'B-PAY-'.random_int(1000, 9999),
        'expired_date' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $unit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 1000, 'cost_price' => 1000,
        'ref_type' => 'SEED', 'ref_id' => 1,
    ]);

    $admin = payUser(Role::CODE_SUPERADMIN);
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => $paymentTermDays],
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
            'item_quantities' => [['item_id' => $do->items->first()->id, 'qty_delivered' => 1, 'qty_returned' => 0]],
        ],
        UploadedFile::fake()->image('proof.jpg'),
        null,
        $admin,
    );

    $invoice = Invoice::query()->where('delivery_order_id', $do->id)->firstOrFail();

    return compact('customer', 'invoice', 'admin');
}

test('sales create payment request draft', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 100_000,
        'method' => PaymentRequest::METHOD_CASH,
        'paid_at' => now()->toDateString(),
    ], $sales);

    expect($req->status)->toBe(PaymentRequest::STATUS_DRAFT);
    expect($req->sales_id)->toBe($sales->id);
    expect((float) $req->amount)->toBe(100_000.0);
});

test('amount <= 0 ditolak', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);

    expect(fn () => app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 0,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], $sales))->toThrow(ValidationException::class);
});

test('giro tanpa giro_due_date ditolak', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);

    expect(fn () => app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 100_000,
        'method' => PaymentRequest::METHOD_GIRO,
        'paid_at' => now()->toDateString(),
    ], $sales))->toThrow(ValidationException::class);
});

test('payment request untuk invoice paid ditolak', function (): void {
    $ctx = paySetupInvoice();
    $ctx['invoice']->update(['status' => Invoice::STATUS_PAID]);

    expect(fn () => app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 100_000,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], payUser(Role::CODE_SALES)))->toThrow(ValidationException::class);
});

test('submit request mengubah status ke submitted', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);
    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 100_000,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], $sales);

    app(PaymentRequestService::class)->submit($req, $sales);

    expect($req->fresh()->status)->toBe(PaymentRequest::STATUS_SUBMITTED);
});

test('kasir verify request → Payment posted + invoice paid_amount updated', function (): void {
    $ctx = paySetupInvoice(invoiceTotal: 300_000);
    $sales = payUser(Role::CODE_SALES);
    $kasir = payUser(Role::CODE_KASIR);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 200_000,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], $sales);
    app(PaymentRequestService::class)->submit($req, $sales);

    $payment = app(PaymentService::class)->createFromRequest($req, $kasir);

    expect($payment->payment_number)->toStartWith('PAY-');
    expect($payment->status)->toBe(Payment::STATUS_POSTED);
    expect((float) $payment->applied_amount)->toBe(200_000.0);
    expect($payment->applied_to_invoice_at)->not->toBeNull();

    $invoice = $ctx['invoice']->fresh();
    expect((float) $invoice->paid_amount)->toBe(200_000.0);
    expect((float) $invoice->outstanding)->toBe(100_000.0);
    expect($invoice->status)->toBe(Invoice::STATUS_PARTIAL_PAID);

    expect($req->fresh()->status)->toBe(PaymentRequest::STATUS_VERIFIED);
});

test('verify full amount → invoice paid', function (): void {
    $ctx = paySetupInvoice(invoiceTotal: 300_000);
    $sales = payUser(Role::CODE_SALES);
    $kasir = payUser(Role::CODE_KASIR);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 300_000,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], $sales);
    app(PaymentRequestService::class)->submit($req, $sales);

    app(PaymentService::class)->createFromRequest($req, $kasir);

    expect($ctx['invoice']->fresh()->status)->toBe(Invoice::STATUS_PAID);
    expect((float) $ctx['invoice']->fresh()->outstanding)->toBe(0.0);
});

test('overpayment → applied_amount = outstanding, overpayment_amount = sisanya', function (): void {
    $ctx = paySetupInvoice(invoiceTotal: 100_000);
    $sales = payUser(Role::CODE_SALES);
    $kasir = payUser(Role::CODE_KASIR);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 150_000, // bayar lebih
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], $sales);
    app(PaymentRequestService::class)->submit($req, $sales);

    $payment = app(PaymentService::class)->createFromRequest($req, $kasir);

    expect((float) $payment->applied_amount)->toBe(100_000.0);
    expect((float) $payment->overpayment_amount)->toBe(50_000.0);
    expect($ctx['invoice']->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

test('giro method → Payment status pending_clearing, invoice belum ter-apply', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);
    $kasir = payUser(Role::CODE_KASIR);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 200_000,
        'method' => PaymentRequest::METHOD_GIRO,
        'giro_due_date' => now()->addMonth()->toDateString(),
        'paid_at' => now()->toDateString(),
        'bank_name' => 'BNI',
        'reference_no' => 'GIR-001',
    ], $sales);
    app(PaymentRequestService::class)->submit($req, $sales);

    $payment = app(PaymentService::class)->createFromRequest($req, $kasir);

    expect($payment->status)->toBe(Payment::STATUS_PENDING_CLEARING);
    expect((float) $payment->applied_amount)->toBe(0.0);
    expect($payment->applied_to_invoice_at)->toBeNull();

    // Invoice tetap open
    expect($ctx['invoice']->fresh()->status)->toBe(Invoice::STATUS_OPEN);
    expect((float) $ctx['invoice']->fresh()->paid_amount)->toBe(0.0);
});

test('clearGiro → apply ke invoice + status cleared', function (): void {
    $ctx = paySetupInvoice(invoiceTotal: 300_000);
    $sales = payUser(Role::CODE_SALES);
    $kasir = payUser(Role::CODE_KASIR);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 300_000,
        'method' => PaymentRequest::METHOD_GIRO,
        'giro_due_date' => now()->addMonth()->toDateString(),
        'paid_at' => now()->toDateString(),
        'bank_name' => 'BNI',
    ], $sales);
    app(PaymentRequestService::class)->submit($req, $sales);

    $payment = app(PaymentService::class)->createFromRequest($req, $kasir);

    app(PaymentService::class)->clearGiro($payment, $kasir);

    $payment->refresh();
    expect($payment->status)->toBe(Payment::STATUS_CLEARED);
    expect($payment->cleared_at)->not->toBeNull();
    expect((float) $payment->applied_amount)->toBe(300_000.0);

    expect($ctx['invoice']->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

test('bounceGiro → status bounced + invoice tidak ter-apply', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);
    $kasir = payUser(Role::CODE_KASIR);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 200_000,
        'method' => PaymentRequest::METHOD_GIRO,
        'giro_due_date' => now()->addMonth()->toDateString(),
        'paid_at' => now()->toDateString(),
        'bank_name' => 'BNI',
    ], $sales);
    app(PaymentRequestService::class)->submit($req, $sales);

    $payment = app(PaymentService::class)->createFromRequest($req, $kasir);

    app(PaymentService::class)->bounceGiro($payment, 'Saldo customer kosong', $kasir);

    $payment->refresh();
    expect($payment->status)->toBe(Payment::STATUS_BOUNCED);
    expect($payment->bounce_reason)->toContain('Saldo');
    expect((float) $payment->applied_amount)->toBe(0.0);

    // Invoice tetap open
    expect((float) $ctx['invoice']->fresh()->paid_amount)->toBe(0.0);
});

test('reject submitted request → status rejected', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);
    $kasir = payUser(Role::CODE_KASIR);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 100_000,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], $sales);
    app(PaymentRequestService::class)->submit($req, $sales);

    app(PaymentRequestService::class)->reject($req, 'Bukti tidak jelas', $kasir);

    $req->refresh();
    expect($req->status)->toBe(PaymentRequest::STATUS_REJECTED);
    expect($req->rejection_reason)->toContain('tidak jelas');

    // Bisa re-submit setelah edit
    expect($req->canBeEdited())->toBeTrue();
    expect($req->canBeSubmitted())->toBeTrue();
});

test('verify request yang sudah verified ditolak (idempotency)', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);
    $kasir = payUser(Role::CODE_KASIR);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 100_000,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], $sales);
    app(PaymentRequestService::class)->submit($req, $sales);
    app(PaymentService::class)->createFromRequest($req, $kasir);

    expect(fn () => app(PaymentService::class)->createFromRequest($req->fresh(), $kasir))
        ->toThrow(ValidationException::class);
});

test('sales tidak bisa lihat payment request sales lain', function (): void {
    $ctx = paySetupInvoice();
    $sales1 = payUser(Role::CODE_SALES);
    $sales2 = payUser(Role::CODE_SALES);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 100_000,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], $sales1);

    $this->actingAs($sales2)
        ->get(route('payment-requests.show', $req))
        ->assertForbidden();
});

test('extension request: sales request → status pending', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);

    $log = app(InvoiceExtensionService::class)->requestExtension(
        $ctx['invoice'],
        [
            'new_due_date' => $ctx['invoice']->due_date->copy()->addDays(14),
            'reason' => 'Customer belum bisa bayar sekarang',
        ],
        $sales,
    );

    expect($log->status)->toBe(InvoiceExtensionLog::STATUS_PENDING);
    expect($log->old_due_date->toDateString())->toBe($ctx['invoice']->due_date->toDateString());
});

test('extension request dengan new_due_date <= due_date ditolak', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);

    expect(fn () => app(InvoiceExtensionService::class)->requestExtension(
        $ctx['invoice'],
        [
            'new_due_date' => $ctx['invoice']->due_date->copy()->subDay(),
            'reason' => 'mau perpanjang',
        ],
        $sales,
    ))->toThrow(ValidationException::class);
});

test('multiple pending extension untuk invoice sama ditolak', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);

    app(InvoiceExtensionService::class)->requestExtension(
        $ctx['invoice'],
        ['new_due_date' => $ctx['invoice']->due_date->copy()->addDays(7), 'reason' => 'r1 alasannya'],
        $sales,
    );

    expect(fn () => app(InvoiceExtensionService::class)->requestExtension(
        $ctx['invoice'],
        ['new_due_date' => $ctx['invoice']->due_date->copy()->addDays(14), 'reason' => 'r2 alasannya'],
        $sales,
    ))->toThrow(ValidationException::class);
});

test('admin approve extension → invoice.due_date updated + original_due_date snapshot', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);
    $admin = payUser(Role::CODE_ADMIN);

    $oldDue = $ctx['invoice']->due_date;
    $log = app(InvoiceExtensionService::class)->requestExtension(
        $ctx['invoice'],
        [
            'new_due_date' => $oldDue->copy()->addDays(30),
            'reason' => 'Customer minta perpanjangan',
        ],
        $sales,
    );

    app(InvoiceExtensionService::class)->approve($log, null, $admin);

    $invoice = $ctx['invoice']->fresh();
    expect($invoice->due_date->toDateString())->toBe($oldDue->copy()->addDays(30)->toDateString());
    expect($invoice->original_due_date->toDateString())->toBe($oldDue->toDateString());

    expect($log->fresh()->status)->toBe(InvoiceExtensionLog::STATUS_APPROVED);
});

test('extension approve untuk invoice overdue → status balik ke open/partial_paid', function (): void {
    $ctx = paySetupInvoice();
    $ctx['invoice']->update([
        'due_date' => now()->subDays(5),
        'status' => Invoice::STATUS_OVERDUE,
    ]);

    $sales = payUser(Role::CODE_SALES);
    $admin = payUser(Role::CODE_ADMIN);

    $log = app(InvoiceExtensionService::class)->requestExtension(
        $ctx['invoice']->refresh(),
        ['new_due_date' => now()->addDays(14), 'reason' => 'minta extension overdue'],
        $sales,
    );
    app(InvoiceExtensionService::class)->approve($log, null, $admin);

    expect($ctx['invoice']->fresh()->status)->toBe(Invoice::STATUS_OPEN);
});

test('admin reject extension', function (): void {
    $ctx = paySetupInvoice();
    $sales = payUser(Role::CODE_SALES);
    $admin = payUser(Role::CODE_ADMIN);

    $log = app(InvoiceExtensionService::class)->requestExtension(
        $ctx['invoice'],
        ['new_due_date' => $ctx['invoice']->due_date->copy()->addDays(30), 'reason' => 'alasan'],
        $sales,
    );

    app(InvoiceExtensionService::class)->reject($log, 'Customer terlalu sering minta extension', $admin);

    expect($log->fresh()->status)->toBe(InvoiceExtensionLog::STATUS_REJECTED);
    expect($ctx['invoice']->fresh()->due_date->toDateString())
        ->toBe(now()->addDays(7)->toDateString()); // unchanged
});

test('AR aging snapshot agregat outstanding per bucket', function (): void {
    $ctx = paySetupInvoice();

    // Set due_date supaya masuk bucket 31-60 hari
    $ctx['invoice']->update(['due_date' => now()->subDays(45)]);

    $count = app(ArAgingSnapshotService::class)->generateFor();

    expect($count)->toBeGreaterThanOrEqual(1);

    $snap = ArAgingSnapshot::query()
        ->where('customer_id', $ctx['customer']->id)
        ->where('snapshot_date', now()->toDateString())
        ->first();

    expect($snap)->not->toBeNull();
    expect((float) $snap->bucket_31_60)->toBe((float) $ctx['invoice']->outstanding);
    expect((float) $snap->total_outstanding)->toBe((float) $ctx['invoice']->outstanding);
});

test('AR aging snapshot exclude invoice paid', function (): void {
    $ctx = paySetupInvoice();
    $ctx['invoice']->update(['status' => Invoice::STATUS_PAID, 'outstanding' => 0]);

    $count = app(ArAgingSnapshotService::class)->generateFor();

    expect($count)->toBe(0);
});

test('AR aging snapshot idempotent (re-run sama tanggal hapus snapshot lama)', function (): void {
    $ctx = paySetupInvoice();
    $ctx['invoice']->update(['due_date' => now()->subDays(45)]);

    app(ArAgingSnapshotService::class)->generateFor();
    app(ArAgingSnapshotService::class)->generateFor(); // re-run

    $count = ArAgingSnapshot::query()
        ->where('customer_id', $ctx['customer']->id)
        ->where('snapshot_date', now()->toDateString())
        ->count();

    expect($count)->toBe(1);
});

test('kasir bisa lihat list payment requests', function (): void {
    $kasir = payUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('payment-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('PaymentRequests/Index'));
});

test('kasir bisa lihat list payments', function (): void {
    $kasir = payUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Payments/Index'));
});

test('reversePayment restore outstanding & status', function (): void {
    $ctx = paySetupInvoice(invoiceTotal: 200_000);
    $sales = payUser(Role::CODE_SALES);
    $kasir = payUser(Role::CODE_KASIR);

    $req = app(PaymentRequestService::class)->createDraft([
        'invoice_id' => $ctx['invoice']->id,
        'amount' => 200_000,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
    ], $sales);
    app(PaymentRequestService::class)->submit($req, $sales);

    $payment = app(PaymentService::class)->createFromRequest($req, $kasir);
    expect($ctx['invoice']->fresh()->status)->toBe(Invoice::STATUS_PAID);

    // Reverse via giro path
    $payment->update(['status' => Payment::STATUS_PENDING_CLEARING, 'applied_amount' => 200_000]);
    app(PaymentService::class)->bounceGiro($payment, 'Reverse test', $kasir);

    expect($ctx['invoice']->fresh()->status)->toBe(Invoice::STATUS_OPEN);
    expect((float) $ctx['invoice']->fresh()->outstanding)->toBe(200_000.0);
});
