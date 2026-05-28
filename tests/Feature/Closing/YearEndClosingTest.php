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
use App\Models\YearEndClosing;
use App\Services\Billing\InvoicePdfRenderer;
use App\Services\Closing\YearEndClosingService;
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

function clUser(string $code): User
{
    $uniq = uniqid('', true);

    return User::create([
        'name' => 'U-'.$code.'-'.$uniq,
        'username' => 'u_'.$code.'_'.str_replace('.', '', $uniq),
        'password' => 'secret1234',
        'role_id' => Role::ofCode($code)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);
}

test('pre-check passed kalau tidak ada draft/submitted', function (): void {
    $check = app(YearEndClosingService::class)->preCheck((int) now()->format('Y'));

    expect($check['passed'])->toBeTrue();
    expect($check['issues'])->toBe([]);
});

test('pre-check fail kalau ada SO draft', function (): void {
    $admin = clUser(Role::CODE_SUPERADMIN);
    $sales = clUser(Role::CODE_SALES);

    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-CL-'.random_int(1000, 9999),
        'name' => 'C', 'price_tier_id' => $tier->id,
        'credit_limit' => 100_000_000, 'payment_term_days' => 7,
        'is_active' => true, 'address' => 'X',
    ]);
    $product = app(ProductService::class)->create(
        ['name' => 'P-CL', 'category_id' => ProductCategory::where('code', 'MIE')->value('id')],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    ProductPrice::query()
        ->where('product_id', $product->id)
        ->update(['price' => 100_000]);

    app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => 7],
        [['product_id' => $product->id, 'product_unit_id' => $product->units->first()->id, 'qty' => 1]],
        $sales,
    );

    $check = app(YearEndClosingService::class)->preCheck((int) now()->format('Y'));

    expect($check['passed'])->toBeFalse();
    expect(collect($check['issues'])->pluck('type'))->toContain('sales_orders');
});

test('execute closing — carry-over flag open invoice + summary populated', function (): void {
    $admin = clUser(Role::CODE_SUPERADMIN);
    $sales = clUser(Role::CODE_SALES);
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-Y-'.random_int(1000, 9999), 'name' => 'Y',
        'price_tier_id' => $tier->id, 'credit_limit' => 100_000_000,
        'payment_term_days' => 7, 'is_active' => true, 'address' => 'X',
    ]);
    $product = app(ProductService::class)->create(
        ['name' => 'PY-'.random_int(100, 999), 'category_id' => ProductCategory::where('code', 'MIE')->value('id')],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $unit = $product->units->first();
    ProductPrice::query()
        ->where('product_id', $product->id)
        ->where('product_unit_id', $unit->id)
        ->where('price_tier_id', $tier->id)
        ->update(['price' => 100_000]);
    $batch = ProductBatch::create([
        'product_id' => $product->id, 'batch_code' => 'B-Y-'.random_int(1000, 9999),
        'expired_date' => now()->addYear()->toDateString(), 'is_active' => true,
    ]);
    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $unit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 100, 'cost_price' => 50_000,
        'ref_type' => 'SEED', 'ref_id' => 1,
    ]);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => 7],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 1]],
        $sales,
    );
    app(SalesOrderService::class)->submit($so, $sales);
    app(SalesOrderService::class)->approve($so->refresh(), $admin);

    $driver = Driver::create([
        'code' => 'D-'.random_int(100, 999), 'name' => 'D',
        'license_expired_date' => now()->addYear()->toDateString(),
        'is_active' => true, 'status' => Driver::STATUS_IDLE,
    ]);
    $vehicle = Vehicle::create([
        'code' => 'V-'.random_int(100, 999), 'plate_number' => 'BL '.random_int(1000, 9999).' P',
        'type' => 'pickup', 'is_active' => true, 'status' => Vehicle::STATUS_IDLE,
    ]);

    $do = app(DeliveryOrderService::class)->createFromSo(
        $so->refresh(),
        [['so_item_id' => $so->items->first()->id, 'qty_planned' => 1]],
        $admin,
    );
    app(DeliveryOrderService::class)->startPicking($do, $admin);
    app(DeliveryOrderService::class)->confirmPicks(
        $do, [['item_id' => $do->items->first()->id, 'qty_picked' => 1]], $admin,
    );
    app(DeliveryOrderService::class)->markPacked($do, $driver->id, $vehicle->id, $admin);
    app(DeliveryOrderService::class)->startDelivery($do, $admin);
    app(DeliveryOrderService::class)->markDelivered(
        $do,
        ['receiver_name' => 'X', 'item_quantities' => [['item_id' => $do->items->first()->id, 'qty_delivered' => 1, 'qty_returned' => 0]]],
        UploadedFile::fake()->image('proof.jpg'), null, $admin,
    );

    $invoice = Invoice::query()->where('delivery_order_id', $do->id)->firstOrFail();
    expect((bool) $invoice->is_carry_over)->toBeFalse();

    $closing = app(YearEndClosingService::class)->execute((int) now()->format('Y'), $admin);

    expect($closing->status)->toBe(YearEndClosing::STATUS_COMPLETED);
    expect((bool) $invoice->fresh()->is_carry_over)->toBeTrue();

    expect((float) $closing->total_revenue)->toBe(100_000.0);
    expect($closing->carry_over_summary)->toHaveKey('invoice_count');
    expect($closing->carry_over_summary['invoice_count'])->toBe(1);
    expect($closing->top_5_customers)->not->toBeEmpty();
});

test('execute idempotent: closing yang sudah completed return existing', function (): void {
    $admin = clUser(Role::CODE_SUPERADMIN);
    $year = (int) now()->format('Y');

    $first = app(YearEndClosingService::class)->execute($year, $admin);
    $second = app(YearEndClosingService::class)->execute($year, $admin);

    expect($second->id)->toBe($first->id);
    expect(YearEndClosing::where('fiscal_year', $year)->count())->toBe(1);
});

test('execute fail kalau ada SO draft', function (): void {
    $admin = clUser(Role::CODE_SUPERADMIN);
    $sales = clUser(Role::CODE_SALES);

    $tier = PriceTier::query()->where('code', 'ECERAN')->first();
    $customer = Customer::create([
        'code' => 'CUST-F-'.random_int(1000, 9999),
        'name' => 'C', 'price_tier_id' => $tier->id,
        'credit_limit' => 100_000_000, 'payment_term_days' => 7,
        'is_active' => true, 'address' => 'X',
    ]);
    $product = app(ProductService::class)->create(
        ['name' => 'P-F', 'category_id' => ProductCategory::where('code', 'MIE')->value('id')],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    ProductPrice::query()->where('product_id', $product->id)->update(['price' => 100_000]);

    app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'payment_term_days' => 7],
        [['product_id' => $product->id, 'product_unit_id' => $product->units->first()->id, 'qty' => 1]],
        $sales,
    );

    expect(fn () => app(YearEndClosingService::class)->execute((int) now()->format('Y'), $admin))
        ->toThrow(ValidationException::class);
});

test('controller execute hanya untuk superadmin', function (): void {
    $admin = clUser(Role::CODE_ADMIN);
    $year = (int) now()->format('Y');

    $this->actingAs($admin)
        ->post(route('year-end-closings.execute'), ['fiscal_year' => $year, 'confirm' => 1])
        ->assertForbidden();
});
