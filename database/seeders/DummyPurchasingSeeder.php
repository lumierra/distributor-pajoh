<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data contoh PO & GRN untuk mencoba alur pembelian end-to-end: draft,
 * approved, partial_received, closed, cancelled (PO) serta draft, submitted,
 * posted, posted-dgn-discrepancy (GRN). Dibuat lewat service yang sama dgn
 * controller supaya nomor dokumen, snapshot, dan efek stok konsisten dgn
 * alur asli — bukan insert manual.
 *
 * Prasyarat: DummyDataSeeder + DummyProductBatch2Seeder sudah dijalankan
 * (butuh supplier & produk). Aman dijalankan ulang — dilewati kalau PO
 * dengan po_number pola "dummy" sudah pernah dibuat sebelumnya.
 *
 * Jalankan manual: php artisan db:seed --class=DummyPurchasingSeeder
 */
class DummyPurchasingSeeder extends Seeder
{
    private User $actor;

    public function run(): void
    {
        $admin = User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->first();
        if (! $admin) {
            $this->command?->error('Superadmin tidak ditemukan — jalankan SuperadminSeeder dulu.');

            return;
        }
        $this->actor = $admin;

        if (PurchaseOrder::query()->where('notes', 'like', '[DUMMY]%')->exists()) {
            $this->command?->info('Dummy PO/GRN sudah pernah dibuat sebelumnya — dilewati.');

            return;
        }

        $poService = app(PurchaseOrderService::class);
        $grnService = app(GoodsReceiptService::class);

        $indofood = Supplier::query()->where('code', 'SUP-0001')->firstOrFail();
        $mayora = Supplier::query()->where('code', 'SUP-0002')->firstOrFail();
        $garudafood = Supplier::query()->where('code', 'SUP-0003')->firstOrFail();
        $unilever = Supplier::query()->where('code', 'SUP-0004')->firstOrFail();

        // 1) Draft — belum di-apa-apakan.
        $this->makeDraftPo($poService, $indofood, ['IDF-001', 'IDF-002']);

        // 2) Approved — siap di-GRN tapi belum ada penerimaan sama sekali.
        $this->makeApprovedPo($poService, $indofood, ['IDF-003', 'IDF-004']);

        // 3) Approved → GRN posted PENUH → PO otomatis closed.
        $poFull = $this->makeApprovedPo($poService, $mayora, ['MYR-001', 'MYR-002']);
        $this->receiveFully($grnService, $poFull);

        // 4) Approved → GRN posted SEBAGIAN (partial) → PO jadi partial_received,
        //    lalu GRN kedua utk sisanya dibuat tapi baru di-submit (blm posted).
        $poPartial = $this->makeApprovedPo($poService, $garudafood, ['GRD-001', 'GRD-002', 'GRD-003']);
        $this->receivePartially($grnService, $poPartial);

        // 5) Approved → GRN posted dgn qty_damaged → discrepancy flag menyala.
        $poDamaged = $this->makeApprovedPo($poService, $unilever, ['ULV-001', 'ULV-002']);
        $this->receiveWithDamage($grnService, $poDamaged);

        // 6) Cancelled — dibatalkan sebelum ada GRN.
        $this->makeCancelledPo($poService, $indofood, ['IDF-005']);

        $this->command?->info('Dummy PO & GRN selesai dibuat: 6 PO (draft/approved/closed/partial/discrepancy/cancelled).');
    }

    /**
     * @param  array<int, string>  $skus
     */
    private function makeDraftPo(PurchaseOrderService $service, Supplier $supplier, array $skus): PurchaseOrder
    {
        return $service->createDraft(
            [
                'supplier_id' => $supplier->id,
                'po_date' => Carbon::now()->subDays(2)->toDateString(),
                'eta_date' => Carbon::now()->addDays(5)->toDateString(),
                'payment_term_days' => $supplier->payment_term_days,
                'notes' => '[DUMMY] Draft — contoh PO yang belum di-approve.',
            ],
            $this->itemsFor($skus),
            $this->actor,
        );
    }

    /**
     * @param  array<int, string>  $skus
     */
    private function makeApprovedPo(PurchaseOrderService $service, Supplier $supplier, array $skus): PurchaseOrder
    {
        $po = $service->createDraft(
            [
                'supplier_id' => $supplier->id,
                'po_date' => Carbon::now()->subDays(5)->toDateString(),
                'eta_date' => Carbon::now()->subDays(1)->toDateString(),
                'payment_term_days' => $supplier->payment_term_days,
                'notes' => '[DUMMY] Approved — contoh PO siap di-GRN.',
            ],
            $this->itemsFor($skus),
            $this->actor,
        );

        return $service->approve($po, $this->actor);
    }

    /**
     * @param  array<int, string>  $skus
     */
    private function makeCancelledPo(PurchaseOrderService $service, Supplier $supplier, array $skus): PurchaseOrder
    {
        $po = $service->createDraft(
            [
                'supplier_id' => $supplier->id,
                'po_date' => Carbon::now()->subDays(10)->toDateString(),
                'payment_term_days' => $supplier->payment_term_days,
                'notes' => '[DUMMY] Cancelled — contoh PO yang dibatalkan.',
            ],
            $this->itemsFor($skus),
            $this->actor,
        );

        return $service->cancel($po, 'Supplier tidak sanggup memenuhi pesanan (data contoh).', $this->actor);
    }

    private function receiveFully(GoodsReceiptService $service, PurchaseOrder $po): void
    {
        $po->load('items');

        $grn = $service->createDraft(
            [
                'purchase_order_id' => $po->id,
                'received_date' => Carbon::now()->toDateString(),
                'supplier_delivery_no' => 'SJ-'.$po->po_number,
                'notes' => '[DUMMY] Penerimaan penuh sesuai PO.',
            ],
            $po->items->map(fn ($i) => [
                'po_item_id' => $i->id,
                'product_id' => $i->product_id,
                'product_unit_id' => $i->product_unit_id,
                'batch_code' => 'BATCH-'.$i->product_sku_snapshot.'-'.now()->format('md'),
                'production_date' => Carbon::now()->subMonths(2)->toDateString(),
                'expired_date' => Carbon::now()->addMonths(10)->toDateString(),
                'qty_reguler' => $i->qty_ordered,
                'qty_bonus' => $i->bonus_qty,
                'qty_damaged' => 0,
                'cost_price' => (float) $i->unit_net_cost,
                'condition' => 'good',
            ])->all(),
            $this->actor,
        );

        $service->submit($grn, $this->actor);
        $service->post($grn, $this->actor);
    }

    private function receivePartially(GoodsReceiptService $service, PurchaseOrder $po): void
    {
        $po->load('items');

        // GRN #1: terima ~60% dari tiap item, langsung posted.
        $grn1 = $service->createDraft(
            [
                'purchase_order_id' => $po->id,
                'received_date' => Carbon::now()->subDays(1)->toDateString(),
                'supplier_delivery_no' => 'SJ-'.$po->po_number.'-A',
                'notes' => '[DUMMY] Penerimaan tahap 1 (partial).',
            ],
            $po->items->map(fn ($i) => [
                'po_item_id' => $i->id,
                'product_id' => $i->product_id,
                'product_unit_id' => $i->product_unit_id,
                'batch_code' => 'BATCH-'.$i->product_sku_snapshot.'-A',
                'production_date' => Carbon::now()->subMonths(1)->toDateString(),
                'expired_date' => Carbon::now()->addMonths(11)->toDateString(),
                'qty_reguler' => max(1, (int) round($i->qty_ordered * 0.6)),
                'qty_bonus' => 0,
                'qty_damaged' => 0,
                'cost_price' => (float) $i->unit_net_cost,
                'condition' => 'good',
            ])->all(),
            $this->actor,
        );
        $service->submit($grn1, $this->actor);
        $service->post($grn1, $this->actor);

        // GRN #2: sisa qty, dibuat & di-submit tapi SENGAJA belum di-posting —
        // supaya ada contoh GRN berstatus "submitted" menunggu admin.
        $po->refresh()->load('items');
        $remainingItems = $po->items
            ->map(fn ($i) => [$i, max(0, (int) $i->qty_ordered - (int) $i->qty_received)])
            ->filter(fn ($pair) => $pair[1] > 0);

        if ($remainingItems->isNotEmpty()) {
            $grn2 = $service->createDraft(
                [
                    'purchase_order_id' => $po->id,
                    'received_date' => Carbon::now()->toDateString(),
                    'supplier_delivery_no' => 'SJ-'.$po->po_number.'-B',
                    'notes' => '[DUMMY] Penerimaan tahap 2 — menunggu posting admin.',
                ],
                $remainingItems->map(fn ($pair) => [
                    'po_item_id' => $pair[0]->id,
                    'product_id' => $pair[0]->product_id,
                    'product_unit_id' => $pair[0]->product_unit_id,
                    'batch_code' => 'BATCH-'.$pair[0]->product_sku_snapshot.'-B',
                    'production_date' => Carbon::now()->toDateString(),
                    'expired_date' => Carbon::now()->addMonths(12)->toDateString(),
                    'qty_reguler' => $pair[1],
                    'qty_bonus' => 0,
                    'qty_damaged' => 0,
                    'cost_price' => (float) $pair[0]->unit_net_cost,
                    'condition' => 'good',
                ])->values()->all(),
                $this->actor,
            );
            $service->submit($grn2, $this->actor);
        }
    }

    private function receiveWithDamage(GoodsReceiptService $service, PurchaseOrder $po): void
    {
        $po->load('items');

        $grn = $service->createDraft(
            [
                'purchase_order_id' => $po->id,
                'received_date' => Carbon::now()->toDateString(),
                'supplier_delivery_no' => 'SJ-'.$po->po_number,
                'notes' => '[DUMMY] Penerimaan dgn sebagian barang rusak.',
                'discrepancy_notes' => 'Beberapa dus penyok saat bongkar muat — dicatat sbg damaged (data contoh).',
            ],
            $po->items->map(function ($i, $idx) {
                $damaged = $idx === 0 ? max(1, (int) round($i->qty_ordered * 0.1)) : 0;

                return [
                    'po_item_id' => $i->id,
                    'product_id' => $i->product_id,
                    'product_unit_id' => $i->product_unit_id,
                    'batch_code' => 'BATCH-'.$i->product_sku_snapshot,
                    'production_date' => Carbon::now()->subMonths(1)->toDateString(),
                    'expired_date' => Carbon::now()->addMonths(9)->toDateString(),
                    'qty_reguler' => max(0, $i->qty_ordered - $damaged),
                    'qty_bonus' => $i->bonus_qty,
                    'qty_damaged' => $damaged,
                    'cost_price' => (float) $i->unit_net_cost,
                    'condition' => $damaged > 0 ? 'mixed' : 'good',
                ];
            })->values()->all(),
            $this->actor,
        );

        $service->submit($grn, $this->actor);
        $service->post($grn, $this->actor);
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<int, array<string, mixed>>
     */
    private function itemsFor(array $skus): array
    {
        $products = Product::query()
            ->whereIn('sku', $skus)
            ->with(['units' => fn ($q) => $q->orderByDesc('qty_to_base'), 'pricePackages.items'])
            ->get()
            ->keyBy('sku');

        $items = [];
        foreach ($skus as $sku) {
            $product = $products->get($sku);
            if (! $product) {
                continue;
            }

            // Pakai satuan terbesar (mis. KARDUS/KRAT) sebagai satuan order PO —
            // lazimnya distributor beli dalam satuan besar.
            $unit = $product->units->first();
            if (! $unit) {
                continue;
            }

            // Harga modal referensi dari paket harga pertama produk, kalau ada;
            // fallback ke angka wajar supaya PO tetap valid.
            $defaultPackage = $product->pricePackages->first();
            $costItem = $defaultPackage?->items->firstWhere('product_unit_id', $unit->id);
            $costPrice = $costItem ? (float) $costItem->cost_price : 50000.0;

            $items[] = [
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'qty_ordered' => random_int(10, 30),
                'bonus_qty' => random_int(0, 1) === 1 ? random_int(1, 3) : 0,
                'cost_price' => $costPrice,
                'discount_z1_pct' => 0,
                'discount_z2_pct' => 0,
            ];
        }

        return $items;
    }
}
