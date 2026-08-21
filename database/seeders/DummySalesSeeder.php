<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Delivery\DeliveryOrderService;
use App\Services\Payment\PaymentService;
use App\Services\Sales\SalesOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data dummy Penjualan: bikin Sales Order lintas semua status (draft, submitted,
 * approved, partially_delivered, delivered, cancelled) dengan menjalankan service
 * asli supaya stok/reservasi/ledger/invoice tetap konsisten. Sebagian delivered
 * lalu di-invoice & sebagian dibayar (lunas / cicil).
 *
 * Idempotent-ish: skip kalau sudah ada Sales Order (biar tidak dobel saat re-run).
 */
class DummySalesSeeder extends Seeder
{
    public function __construct(
        private readonly SalesOrderService $salesOrders,
        private readonly DeliveryOrderService $deliveryOrders,
        private readonly PaymentService $payments,
    ) {}

    public function run(): void
    {
        if (SalesOrder::query()->exists()) {
            $this->command?->warn('DummySalesSeeder dilewati — sudah ada Sales Order.');

            return;
        }

        $admin = User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
        $salesUsers = User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SALES))->get();
        $customers = Customer::query()->where('is_active', true)->orderBy('id')->get();
        $driver = Driver::query()->where('is_active', true)->firstOrFail();
        $vehicle = Vehicle::query()->where('is_active', true)->firstOrFail();

        // Produk berstok dengan base unit-nya (qty pakai base unit biar aman).
        $stocked = $this->stockedBaseProducts();

        if ($stocked->count() < 3 || $customers->count() < 3 || $salesUsers->isEmpty()) {
            $this->command?->error('Data master kurang (butuh ≥3 produk berstok, ≥3 customer, ≥1 sales). Jalankan DummyDataSeeder & DummyPurchasingSeeder dulu.');

            return;
        }

        $products = $stocked->values();
        $p = fn (int $i) => $products[$i % $products->count()];
        $sales = fn (int $i) => $salesUsers[$i % $salesUsers->count()];
        $cust = fn (int $i) => $customers[$i % $customers->count()];

        // 1) SO DRAFT — belum di-submit.
        $this->makeSo($admin, $sales(0), $cust(0), [[$p(0), 5]]);
        $this->command?->info('✓ SO draft dibuat.');

        // 2) SO SUBMITTED — nunggu approval.
        $so = $this->makeSo($admin, $sales(1), $cust(1), [[$p(1), 8], [$p(2), 4]]);
        $this->salesOrders->submit($so, $sales(1));
        $this->command?->info('✓ SO submitted dibuat.');

        // 3) SO APPROVED — reservasi aktif, belum dikirim.
        $so = $this->makeSo($admin, $sales(0), $cust(2), [[$p(0), 6], [$p(3), 10]]);
        $this->salesOrders->submit($so, $sales(0));
        $this->salesOrders->approve($so->refresh(), $admin);
        $this->command?->info('✓ SO approved (reservasi aktif) dibuat.');

        // 4) SO CANCELLED — di-approve lalu dibatalkan (reservasi dilepas).
        $so = $this->makeSo($admin, $sales(1), $cust(3), [[$p(4), 4]]);
        $this->salesOrders->submit($so, $sales(1));
        $this->salesOrders->approve($so->refresh(), $admin);
        $this->salesOrders->cancel($so->refresh(), 'Customer batal order', $admin);
        $this->command?->info('✓ SO cancelled dibuat.');

        // 5) DUA SO DELIVERED penuh → invoice otomatis. Satu dibayar LUNAS,
        //    satu dibayar SEBAGIAN (partial_paid), plus satu invoice OPEN.
        $delivered = [];
        $delivered[] = $this->fullDeliver($admin, $sales(0), $cust(0), [[$p(0), 4], [$p(1), 6]], $driver, $vehicle);
        $delivered[] = $this->fullDeliver($admin, $sales(1), $cust(1), [[$p(2), 5]], $driver, $vehicle);
        $delivered[] = $this->fullDeliver($admin, $sales(0), $cust(4), [[$p(3), 8]], $driver, $vehicle);
        $this->command?->info('✓ 3 SO delivered + invoice otomatis dibuat.');

        // Bayar invoice #1 LUNAS, invoice #2 SEBAGIAN, invoice #3 dibiarkan OPEN.
        $inv1 = Invoice::query()->where('sales_order_id', $delivered[0]->id)->first();
        $inv2 = Invoice::query()->where('sales_order_id', $delivered[1]->id)->first();
        if ($inv1) {
            $this->payInvoice($admin, $inv1, (float) $inv1->outstanding, PaymentRequest::METHOD_TRANSFER); // lunas
        }
        if ($inv2) {
            $this->payInvoice($admin, $inv2, round((float) $inv2->outstanding / 2, 2), PaymentRequest::METHOD_CASH); // cicil
        }
        $this->command?->info('✓ Pembayaran: 1 lunas, 1 cicil, 1 open.');

        // 6) SO PARTIALLY_DELIVERED — SO 30, kirim 20, sisa 10 masih ter-hold.
        $this->partialDeliver($admin, $sales(1), $cust(2), $p(0), 30, 20, $driver, $vehicle);
        $this->command?->info('✓ SO partially_delivered dibuat.');

        $this->command?->info('DummySalesSeeder selesai. Cek menu Penjualan → Sales Order / Surat Jalan / Faktur.');
    }

    /**
     * Produk berstok dengan base unit (qty_to_base=1) yang JUGA berada di
     * minimal satu Product Group aktif — supaya SO atas nama sales (yang terikat
     * group) tidak ditolak aturan product-group. Kembalikan koleksi
     * ['product' => Product, 'unit_id' => int].
     *
     * @return Collection<int, array{product: Product, unit_id: int}>
     */
    private function stockedBaseProducts()
    {
        $stockedIds = StockBalance::query()
            ->where('qty_on_hand', '>', 0)
            ->select('product_id')
            ->groupBy('product_id')
            ->pluck('product_id');

        // Hanya produk yang tercantum di suatu Product Group aktif.
        $inActiveGroupIds = DB::table('product_group_items')
            ->join('product_groups', 'product_groups.id', '=', 'product_group_items.product_group_id')
            ->where('product_groups.is_active', true)
            ->whereNull('product_groups.deleted_at')
            ->pluck('product_group_items.product_id')
            ->unique();

        $productIds = $stockedIds->intersect($inActiveGroupIds);

        return Product::query()
            ->with('units')
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->get()
            ->map(function (Product $product): ?array {
                $base = $product->units->firstWhere('qty_to_base', 1);
                if ($base === null) {
                    return null;
                }

                return ['product' => $product, 'unit_id' => (int) $base->id];
            })
            ->filter()
            ->values();
    }

    /**
     * Bikin SO draft. $lines = array of [ ['product'=>..,'unit_id'=>..], qtyBase ].
     *
     * @param  array<int, array{0: array{product: Product, unit_id: int}, 1: int}>  $lines
     */
    private function makeSo(User $admin, User $salesUser, Customer $customer, array $lines): SalesOrder
    {
        $items = [];
        foreach ($lines as $line) {
            [$prod, $qty] = $line;
            $items[] = [
                'product_id' => $prod['product']->id,
                'product_unit_id' => $prod['unit_id'],
                'qty' => $qty,
            ];
        }

        return $this->salesOrders->createDraft(
            [
                'customer_id' => $customer->id,
                'so_date' => now()->toDateString(),
                'sales_id' => $salesUser->id,
            ],
            $items,
            $admin,
        );
    }

    /**
     * SO → submit → approve → DO → delivered penuh. Return SO delivered.
     *
     * @param  array<int, array{0: array{product: Product, unit_id: int}, 1: int}>  $lines
     */
    private function fullDeliver(User $admin, User $salesUser, Customer $customer, array $lines, Driver $driver, Vehicle $vehicle): SalesOrder
    {
        $so = $this->makeSo($admin, $salesUser, $customer, $lines);
        $this->salesOrders->submit($so, $salesUser);
        $this->salesOrders->approve($so->refresh(), $admin);

        $config = $so->items()->get()->map(fn ($i) => ['so_item_id' => $i->id, 'qty_planned' => (int) $i->qty])->all();
        $do = $this->deliveryOrders->createFromSo($so->refresh(), $config, $admin);
        $do = $this->deliveryOrders->startPicking($do, $admin);
        $picks = $do->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_picked' => (int) $i->qty_planned])->all();
        $do = $this->deliveryOrders->confirmPicks($do, $picks, $admin);
        $do = $this->deliveryOrders->markPacked($do, $driver->id, $vehicle->id, $admin);
        $do = $this->deliveryOrders->startDelivery($do, $admin);

        $qtys = $do->items()->get()->map(fn ($i) => [
            'item_id' => $i->id, 'qty_delivered' => (int) $i->qty_picked, 'qty_returned' => 0,
        ])->all();

        $this->deliveryOrders->markDelivered(
            $do,
            ['receiver_name' => $customer->owner_name ?: $customer->name, 'item_quantities' => $qtys],
            UploadedFile::fake()->image('proof.jpg'),
            null,
            $admin,
        );

        return $so->refresh();
    }

    /**
     * SO $qtyTotal, kirim $qtyKirim (sisanya tetap ter-hold → partially_delivered).
     *
     * @param  array{product: Product, unit_id: int}  $prod
     */
    private function partialDeliver(User $admin, User $salesUser, Customer $customer, array $prod, int $qtyTotal, int $qtyKirim, Driver $driver, Vehicle $vehicle): void
    {
        $so = $this->makeSo($admin, $salesUser, $customer, [[$prod, $qtyTotal]]);
        $this->salesOrders->submit($so, $salesUser);
        $this->salesOrders->approve($so->refresh(), $admin);
        $soItem = $so->items()->first();

        $do = $this->deliveryOrders->createFromSo($so->refresh(), [['so_item_id' => $soItem->id, 'qty_planned' => $qtyKirim]], $admin);
        $do = $this->deliveryOrders->startPicking($do, $admin);
        $do = $this->deliveryOrders->confirmPicks($do, $do->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_picked' => (int) $i->qty_planned])->all(), $admin);
        $do = $this->deliveryOrders->markPacked($do, $driver->id, $vehicle->id, $admin);
        $do = $this->deliveryOrders->startDelivery($do, $admin);
        $this->deliveryOrders->markDelivered(
            $do,
            ['receiver_name' => $customer->name, 'item_quantities' => $do->items()->get()->map(fn ($i) => ['item_id' => $i->id, 'qty_delivered' => (int) $i->qty_picked, 'qty_returned' => 0])->all()],
            UploadedFile::fake()->image('proof.jpg'),
            null,
            $admin,
        );
    }

    private function payInvoice(User $admin, Invoice $invoice, float $amount, string $method): void
    {
        if ($amount <= 0) {
            return;
        }

        $pr = PaymentRequest::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'sales_id' => $invoice->sales_id,
            'amount' => $amount,
            'method' => $method,
            'reference_no' => $method === PaymentRequest::METHOD_CASH ? null : 'TRX-'.$invoice->id,
            'paid_at' => now(),
            'status' => PaymentRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'created_by' => $admin->id,
        ]);

        $this->payments->createFromRequest($pr, $admin);
    }
}
