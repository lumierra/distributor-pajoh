<?php

use App\Jobs\Reports\CleanupOldReportExportsJob;
use App\Models\Customer;
use App\Models\DailySalesSummary;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\PriceTier;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Billing\InvoicePdfRenderer;
use App\Services\Delivery\DeliveryOrderService;
use App\Services\Delivery\DoPdfRenderer;
use App\Services\Inventory\StockLedgerWriter;
use App\Services\Product\ProductService;
use App\Services\Reports\ArAgingReportService;
use App\Services\Reports\MarginReportService;
use App\Services\Reports\ReportService;
use App\Services\Reports\SalesActivityReportService;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\StockReportService;
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
                $do->update(['pdf_path' => 'stub.pdf', 'pdf_generated_at' => now()]);

                return $do->pdf_path;
            }
        };
    });
    $this->app->bind(InvoicePdfRenderer::class, function ($app) {
        return new class($app->make(SettingManager::class)) extends InvoicePdfRenderer
        {
            public function generate(Invoice $invoice): string
            {
                $invoice->update(['pdf_path' => 'stub.pdf', 'pdf_generated_at' => now()]);

                return $invoice->pdf_path;
            }
        };
    });
});

function repUser(string $roleCode): User
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

function repBuildInvoice(): array
{
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-REP-'.random_int(1000, 9999),
        'name' => 'Customer REP',
        'price_tier_id' => $tier->id,
        'credit_limit' => 100_000_000,
        'payment_term_days' => 7,
        'is_active' => true,
        'address' => 'Jl. Test',
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk REP '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $unit = $product->units->first();
    ProductPrice::query()
        ->where('product_id', $product->id)
        ->where('product_unit_id', $unit->id)
        ->where('price_tier_id', $tier->id)
        ->update(['price' => 100_000]);

    $batch = ProductBatch::create([
        'product_id' => $product->id,
        'batch_code' => 'B-REP-'.random_int(1000, 9999),
        'expired_date' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $unit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 1000, 'cost_price' => 60_000,
        'ref_type' => 'SEED', 'ref_id' => 1,
    ]);

    $admin = repUser(Role::CODE_SUPERADMIN);
    $sales = repUser(Role::CODE_SALES);
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => 7],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 1]],
        $sales,
    );
    app(SalesOrderService::class)->submit($so, $sales);
    app(SalesOrderService::class)->approve($so->refresh(), $admin);

    $driver = Driver::create([
        'code' => 'DRV-'.random_int(100, 999), 'name' => 'D',
        'license_expired_date' => now()->addYear()->toDateString(),
        'is_active' => true, 'status' => Driver::STATUS_IDLE,
    ]);
    $vehicle = Vehicle::create([
        'code' => 'VEH-'.random_int(100, 999), 'plate_number' => 'BL '.random_int(1000, 9999).' P',
        'type' => 'pickup', 'is_active' => true, 'status' => Vehicle::STATUS_IDLE,
    ]);
    VehicleDocument::create([
        'vehicle_id' => $vehicle->id, 'type' => 'STNK', 'title' => 'STNK',
        'file_path' => 'd.pdf', 'expires_date' => now()->addYear()->toDateString(),
    ]);

    $do = app(DeliveryOrderService::class)->createFromSo(
        $so->refresh(),
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

    return compact('customer', 'product', 'unit', 'batch', 'invoice', 'so', 'sales', 'admin');
}

test('snapshot sales summary idempotent (run 2x)', function (): void {
    $ctx = repBuildInvoice();

    $count1 = app(SalesReportService::class)->generateSnapshot(now());
    $count2 = app(SalesReportService::class)->generateSnapshot(now());

    expect($count1)->toBe($count2);
    expect(DailySalesSummary::count())->toBeGreaterThan(0);

    // Verify upsert tidak duplikat
    $beforeRows = DailySalesSummary::count();
    app(SalesReportService::class)->generateSnapshot(now());
    expect(DailySalesSummary::count())->toBe($beforeRows);
});

test('sales summary kpi accurate', function (): void {
    $ctx = repBuildInvoice();

    app(SalesReportService::class)->generateSnapshot(now());

    $kpi = app(SalesReportService::class)->getSummary([
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect($kpi['invoices'])->toBeGreaterThan(0);
    expect($kpi['revenue'])->toBeGreaterThan(0);
});

test('stock position snapshot idempotent + value calculation', function (): void {
    $ctx = repBuildInvoice();

    $count1 = app(StockReportService::class)->generateSnapshot(now());
    $count2 = app(StockReportService::class)->generateSnapshot(now());

    expect($count1)->toBe($count2);

    $kpi = app(StockReportService::class)->getSummary(['date' => now()->toDateString()]);
    expect($kpi['products'])->toBeGreaterThan(0);
    expect($kpi['total_value'])->toBeGreaterThan(0);
});

test('ar aging snapshot generates dari outstanding invoices', function (): void {
    $ctx = repBuildInvoice();

    app(ArAgingReportService::class)->generateSnapshot(now());

    $kpi = app(ArAgingReportService::class)->getSummary(['date' => now()->toDateString()]);
    expect($kpi['customers'])->toBeGreaterThan(0);
    expect($kpi['total_outstanding'])->toBeGreaterThan(0);
});

test('margin calculation: revenue - cost dari ledger', function (): void {
    $ctx = repBuildInvoice();

    app(SalesReportService::class)->generateSnapshot(now());

    $margin = app(MarginReportService::class)->getSummary([
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    // Revenue 100_000, cost 60_000 → margin 40_000
    expect($margin['revenue'])->toBe(100_000.0);
    expect($margin['cost'])->toBe(60_000.0);
    expect($margin['margin'])->toBe(40_000.0);
    expect($margin['margin_percent'])->toBe(40.0);
});

test('sales activity snapshot dari sales_orders', function (): void {
    $ctx = repBuildInvoice();

    $count = app(SalesActivityReportService::class)->generateSnapshot(now());
    expect($count)->toBeGreaterThan(0);

    $kpi = app(SalesActivityReportService::class)->getSummary([
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]);
    expect($kpi['so_count'])->toBeGreaterThan(0);
    expect($kpi['so_value'])->toBeGreaterThan(0);
});

test('sales activity scope: sales hanya lihat data sendiri via filter', function (): void {
    $ctx = repBuildInvoice();
    app(SalesActivityReportService::class)->generateSnapshot(now());

    // Filter scoped ke sales_id
    $kpi = app(SalesActivityReportService::class)->getSummary([
        'sales_id' => $ctx['sales']->id,
    ]);
    expect($kpi['sales'])->toBe(1);

    // Sales lain → 0
    $otherSales = repUser(Role::CODE_SALES);
    $kpiOther = app(SalesActivityReportService::class)->getSummary([
        'sales_id' => $otherSales->id,
    ]);
    expect($kpiOther['sales'])->toBe(0);
});

test('regenerate orchestrator menjalankan semua snapshot generators', function (): void {
    $ctx = repBuildInvoice();

    $counts = app(ReportService::class)->regenerateForDate(now());

    expect($counts)->toHaveKeys(['sales', 'stock', 'sales_activity', 'ar_aging']);
    expect($counts['sales'])->toBeGreaterThan(0);
    expect($counts['stock'])->toBeGreaterThan(0);
});

test('sales report controller scope force sales_id untuk role sales', function (): void {
    $ctx = repBuildInvoice();
    app(SalesReportService::class)->generateSnapshot(now());

    $this->actingAs($ctx['sales'])
        ->get(route('reports.sales'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Reports/Sales/Index')
            ->where('filters.sales_id', $ctx['sales']->id));
});

test('admin lihat semua data (no force sales filter)', function (): void {
    $ctx = repBuildInvoice();
    app(SalesReportService::class)->generateSnapshot(now());

    $this->actingAs($ctx['admin'])
        ->get(route('reports.sales'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Reports/Sales/Index')
            ->missing('filters.sales_id'));
});

test('export creates report_exports record + file', function (): void {
    $ctx = repBuildInvoice();
    app(SalesReportService::class)->generateSnapshot(now());

    $this->actingAs($ctx['admin'])
        ->get(route('reports.export', 'sales_summary'))
        ->assertOk();

    expect(ReportExport::count())->toBe(1);
    $export = ReportExport::first();
    expect($export->report_type)->toBe('sales_summary');
    expect($export->format)->toBe('xlsx');
    expect($export->user_id)->toBe($ctx['admin']->id);
});

test('regenerate endpoint butuh permission approve', function (): void {
    $ctx = repBuildInvoice();
    $sales = repUser(Role::CODE_SALES);

    // Sales tidak bisa regenerate
    $this->actingAs($sales)
        ->post(route('reports.regenerate'))
        ->assertForbidden();

    // Admin/Superadmin bisa
    $this->actingAs($ctx['admin'])
        ->post(route('reports.regenerate'))
        ->assertRedirect();
});

test('cleanup job menghapus export tua', function (): void {
    $ctx = repBuildInvoice();

    Storage::disk('local')->put('report_exports/old.xlsx', 'fake content');

    $oldExport = ReportExport::create([
        'user_id' => $ctx['admin']->id,
        'report_type' => 'sales_summary',
        'format' => 'xlsx',
        'filters' => [],
        'file_path' => 'report_exports/old.xlsx',
        'exported_at' => now()->subDays(10),
    ]);

    $recentExport = ReportExport::create([
        'user_id' => $ctx['admin']->id,
        'report_type' => 'sales_summary',
        'format' => 'xlsx',
        'filters' => [],
        'file_path' => 'report_exports/recent.xlsx',
        'exported_at' => now()->subDays(2),
    ]);
    Storage::disk('local')->put('report_exports/recent.xlsx', 'fake');

    $deleted = (new CleanupOldReportExportsJob(daysToKeep: 7))->handle();

    expect($deleted)->toBe(1);
    expect(ReportExport::find($oldExport->id))->toBeNull();
    expect(ReportExport::find($recentExport->id))->not->toBeNull();
    expect(Storage::disk('local')->exists('report_exports/old.xlsx'))->toBeFalse();
});
