<?php

namespace App\Services\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\GrnItem;
use App\Models\PoItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Inventory\StockLedgerWriter;
use App\Services\Numbering\NumberingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly BatchResolver $batchResolver,
        private readonly StockLedgerWriter $ledger,
        private readonly PoStatusResolver $poStatus,
    ) {}

    /**
     * Buat GRN draft. Dua mode:
     *  - Dari PO: kirim purchase_order_id (PO wajib approved/partial_received).
     *    supplier ikut dari PO.
     *  - Penerimaan langsung (tanpa PO): purchase_order_id null, kirim
     *    supplier_id + item produk manual (produk wajib milik supplier itu).
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function createDraft(array $headerData, array $itemsData, User $by): GoodsReceipt
    {
        return DB::transaction(function () use ($headerData, $itemsData, $by): GoodsReceipt {
            $po = ! empty($headerData['purchase_order_id'])
                ? PurchaseOrder::query()->findOrFail($headerData['purchase_order_id'])
                : null;

            if ($po !== null) {
                $this->assertPoOpen($po);
                $supplierId = (int) $po->supplier_id;
            } else {
                // Penerimaan langsung: supplier wajib dari input.
                $supplierId = (int) ($headerData['supplier_id'] ?? 0);
                if ($supplierId <= 0) {
                    throw ValidationException::withMessages([
                        'supplier_id' => 'Supplier wajib dipilih untuk penerimaan langsung tanpa PO.',
                    ]);
                }
            }

            $receivedDate = $headerData['received_date'] instanceof CarbonInterface
                ? $headerData['received_date']
                : Carbon::parse($headerData['received_date']);

            $grn = new GoodsReceipt([
                'purchase_order_id' => $po?->id,
                'supplier_id' => $supplierId,
                'received_date' => $receivedDate,
                'supplier_delivery_no' => $headerData['supplier_delivery_no'] ?? null,
                'status' => GoodsReceipt::STATUS_DRAFT,
                'fiscal_year' => (int) $receivedDate->format('Y'),
                'received_by' => $by->id,
                'notes' => $headerData['notes'] ?? null,
                'discrepancy_notes' => $headerData['discrepancy_notes'] ?? null,
                'created_by' => $by->id,
            ]);
            $grn->grn_number = $this->numbering->next('grn');
            $grn->save();

            $this->syncItems($grn, $po, $itemsData);

            return $grn->refresh();
        });
    }

    /**
     * Update GRN draft (atau rejected → operator revisi).
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function updateDraft(GoodsReceipt $grn, array $headerData, array $itemsData, User $by): GoodsReceipt
    {
        if (! $grn->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'GRN tidak bisa diedit pada status saat ini.']);
        }

        return DB::transaction(function () use ($grn, $headerData, $itemsData, $by): GoodsReceipt {
            $po = $grn->purchaseOrder;
            if ($po !== null) {
                $this->assertPoOpen($po);
            }

            $receivedDate = $headerData['received_date'] instanceof CarbonInterface
                ? $headerData['received_date']
                : Carbon::parse($headerData['received_date']);

            $grn->fill([
                'received_date' => $receivedDate,
                'supplier_delivery_no' => $headerData['supplier_delivery_no'] ?? null,
                'fiscal_year' => (int) $receivedDate->format('Y'),
                'notes' => $headerData['notes'] ?? null,
                'discrepancy_notes' => $headerData['discrepancy_notes'] ?? null,
                'updated_by' => $by->id,
            ]);

            // Kembalikan ke draft kalau sebelumnya rejected, sambil bersihkan flag rejection.
            if ($grn->status === GoodsReceipt::STATUS_REJECTED) {
                $grn->status = GoodsReceipt::STATUS_DRAFT;
            }
            $grn->save();

            $this->syncItems($grn, $po, $itemsData);

            return $grn->refresh();
        });
    }

    public function submit(GoodsReceipt $grn, User $by): GoodsReceipt
    {
        if (! $grn->canBeSubmitted()) {
            throw ValidationException::withMessages(['status' => 'GRN tidak bisa di-submit (status atau items kosong).']);
        }

        $grn->update([
            'status' => GoodsReceipt::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => $by->id,
            // Reset rejection flag saat submit ulang
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
        ]);

        return $grn->refresh();
    }

    public function reject(GoodsReceipt $grn, string $reason, User $by): GoodsReceipt
    {
        if (! $grn->canBeRejected()) {
            throw ValidationException::withMessages(['status' => 'GRN tidak bisa di-reject pada status saat ini.']);
        }

        $grn->update([
            'status' => GoodsReceipt::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $by->id,
            'rejection_reason' => $reason,
        ]);

        return $grn->refresh();
    }

    /**
     * Tandai pending SATU ITEM penerimaan langsung selesai — pending item itu
     * berhenti dihitung di halaman stok. Angka item tidak diubah, hanya
     * ditandai. Susulan datang per-produk jadi ditandai per baris.
     */
    public function settleItemPending(GrnItem $item, User $by): GrnItem
    {
        if (! $item->hasUnsettledPending()) {
            throw ValidationException::withMessages([
                'status' => 'Item ini tidak punya pending penerimaan langsung yang bisa ditandai selesai.',
            ]);
        }

        $item->update([
            'pending_settled_at' => now(),
            'pending_settled_by' => $by->id,
        ]);

        return $item->refresh();
    }

    /**
     * Batalkan penandaan selesai satu item — pending item muncul lagi di stok.
     */
    public function unsettleItemPending(GrnItem $item, User $by): GrnItem
    {
        if ($item->pending_settled_at === null) {
            throw ValidationException::withMessages([
                'status' => 'Item ini belum ditandai pending selesai.',
            ]);
        }

        $item->update([
            'pending_settled_at' => null,
            'pending_settled_by' => null,
        ]);

        return $item->refresh();
    }

    public function cancel(GoodsReceipt $grn, string $reason, User $by): GoodsReceipt
    {
        if (! $grn->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'Hanya GRN draft yang bisa di-cancel.']);
        }

        $grn->update([
            'status' => GoodsReceipt::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
            'cancel_reason' => $reason,
        ]);

        return $grn->refresh();
    }

    /**
     * Posting GRN: resolve batch, write stock_ledger, update po_items, refresh
     * PO status. Atomic — semua atau tidak sama sekali.
     */
    public function post(GoodsReceipt $grn, User $by, bool $approveOverReceive = false): GoodsReceipt
    {
        if (! $grn->canBePosted()) {
            throw ValidationException::withMessages(['status' => 'GRN tidak bisa di-post pada status saat ini.']);
        }

        $grn->load(['items.poItem', 'items.productUnit', 'items.product', 'purchaseOrder']);

        if ($grn->hasOverReceive() && ! $approveOverReceive) {
            throw ValidationException::withMessages([
                'over_receive' => 'GRN mengandung over-receive. Butuh approval untuk lanjut posting.',
            ]);
        }

        return DB::transaction(function () use ($grn, $by): GoodsReceipt {
            // Lock PO supaya tidak race dengan GRN concurrent (skip untuk GRN tanpa PO).
            if ($grn->purchase_order_id !== null) {
                PurchaseOrder::query()
                    ->where('id', $grn->purchase_order_id)
                    ->lockForUpdate()
                    ->first();
            }

            foreach ($grn->items as $item) {
                /** @var ProductUnit $unit */
                $unit = $item->productUnit;
                /** @var Product $product */
                $product = $item->product;

                // 1. Resolve / create batch
                $batch = $this->batchResolver->resolveOrCreate([
                    'product_id' => $product->id,
                    'supplier_id' => $grn->supplier_id,
                    'batch_code' => $item->batch_code,
                    'production_date' => $item->production_date?->toDateString(),
                    'expired_date' => $item->expired_date?->toDateString(),
                ]);

                $qtyRegBase = (int) $item->qty_reguler * (int) $unit->qty_to_base;
                $qtyBonusBase = (int) $item->qty_bonus * (int) $unit->qty_to_base;
                $costBase = (int) $unit->qty_to_base > 0
                    ? round((float) $item->cost_price / (int) $unit->qty_to_base, 4)
                    : 0.0;

                // 2. Update grn_item dengan batch_id, qty_base, cost_base
                $item->update([
                    'batch_id' => $batch->id,
                    'qty_reguler_base' => $qtyRegBase,
                    'qty_bonus_base' => $qtyBonusBase,
                    'cost_price_base' => $costBase,
                ]);

                // 3. Update initial_qty_base di batch (cumulative)
                $batch->initial_qty_base += $qtyRegBase + $qtyBonusBase;
                $batch->last_received_at = now();
                $batch->save();

                // 4. Write stock_ledger via writer
                if ($qtyRegBase > 0) {
                    $this->ledger->writeIn([
                        'product_id' => $product->id,
                        'batch_id' => $batch->id,
                        'product_unit_id' => $product->base_unit_id ?? $unit->id,
                        'type' => StockLedger::TYPE_PURCHASE_IN,
                        'is_bonus_pool' => false,
                        'qty_in' => $qtyRegBase,
                        'cost_price' => $costBase,
                        'ref_type' => 'GRN',
                        'ref_id' => $grn->id,
                    ], $by);
                }

                if ($qtyBonusBase > 0) {
                    $this->ledger->writeIn([
                        'product_id' => $product->id,
                        'batch_id' => $batch->id,
                        'product_unit_id' => $product->base_unit_id ?? $unit->id,
                        'type' => StockLedger::TYPE_BONUS_IN,
                        'is_bonus_pool' => true,
                        'qty_in' => $qtyBonusBase,
                        'cost_price' => 0,
                        'ref_type' => 'GRN',
                        'ref_id' => $grn->id,
                    ], $by);
                }

                // 5. Update po_items cumulative (hanya untuk GRN yang tertaut PO)
                $poItem = $item->poItem;
                if ($poItem !== null) {
                    $poItem->qty_received = (int) $poItem->qty_received + (int) $item->qty_reguler;
                    $poItem->bonus_qty_received = (int) $poItem->bonus_qty_received + (int) $item->qty_bonus;
                    $poItem->save();
                }
            }

            // 6. Discrepancy detection (cumulative basis)
            $hasDiscrepancy = $this->detectDiscrepancy($grn);

            $grn->update([
                'status' => GoodsReceipt::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $by->id,
                'has_discrepancy' => $hasDiscrepancy,
            ]);

            // 7. Trigger PO status resolution (hanya untuk GRN yang tertaut PO)
            if ($grn->purchaseOrder !== null) {
                $this->poStatus->resolveAfterGrnChange($grn->purchaseOrder->fresh());
            }

            return $grn->refresh();
        });
    }

    /**
     * Cek discrepancy: ada qty_damaged, condition != good, atau bonus mismatch.
     */
    private function detectDiscrepancy(GoodsReceipt $grn): bool
    {
        $grn->loadMissing('items.poItem');

        foreach ($grn->items as $item) {
            if ((int) $item->qty_damaged > 0) {
                return true;
            }
            if ($item->condition !== GrnItem::CONDITION_GOOD) {
                return true;
            }
            // Bonus mismatch: po_item.bonus_qty > 0 tapi belum tercukupi setelah ini.
            // Hanya berlaku untuk GRN tertaut PO (tanpa PO tidak ada baseline bonus).
            $poItem = $item->poItem;
            if ($poItem !== null
                && (int) $poItem->bonus_qty > 0
                && (int) $poItem->bonus_qty_received < (int) $poItem->bonus_qty) {
                // Hanya flag kalau ini bukan partial — tapi simple flag dulu.
                // (Bisa di-refine saat T16 retur supplier.)
                return true;
            }
        }

        return false;
    }

    /**
     * Sinkron ulang item GRN. Kalau $po ada → item tertaut po_item dgn strict
     * match (produk/unit harus sama dengan PO). Kalau $po null (penerimaan
     * langsung) → produk & unit diambil langsung dari master, wajib milik
     * supplier GRN, po_item_id null, dan cost_price wajib dari input.
     *
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    private function syncItems(GoodsReceipt $grn, ?PurchaseOrder $po, array $itemsData): void
    {
        $grn->items()->delete();

        $poItemIds = $po !== null ? $po->items->pluck('id')->all() : [];

        foreach ($itemsData as $idx => $row) {
            $reg = (int) ($row['qty_reguler'] ?? 0);
            $bon = (int) ($row['qty_bonus'] ?? 0);
            $dmg = (int) ($row['qty_damaged'] ?? 0);

            if (($reg + $bon + $dmg) <= 0) {
                throw ValidationException::withMessages([
                    "items.{$idx}.qty_reguler" => 'Minimal 1 qty (reguler/bonus/rusak) harus > 0.',
                ]);
            }

            if ($po !== null) {
                [$poItem, $product, $unit, $cost, $costOverridden] = $this->resolvePoBackedItem($poItemIds, $row, $idx);
                // Dari PO: pending dihitung dari PO, kolom surat jalan tak dipakai.
                $qtyDeliveryNote = 0;
            } else {
                [$poItem, $product, $unit, $cost, $costOverridden] = $this->resolveDirectItem($grn, $row, $idx);
                // Penerimaan langsung: qty surat jalan = acuan pending. Default
                // ke qty_reguler (tanpa pending) kalau tidak diisi. Tidak boleh
                // kurang dari qty diterima.
                $qtyDeliveryNote = max($reg, (int) ($row['qty_delivery_note'] ?? $reg));
            }

            GrnItem::create([
                'goods_receipt_id' => $grn->id,
                'po_item_id' => $poItem?->id,
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'product_unit_name_snapshot' => $unit->name,
                'batch_id' => null,
                'batch_code' => (string) ($row['batch_code'] ?? ''),
                'production_date' => $row['production_date'] ?? null,
                'expired_date' => $row['expired_date'] ?? null,
                'qty_reguler' => $reg,
                'qty_delivery_note' => $qtyDeliveryNote,
                'qty_bonus' => $bon,
                'qty_damaged' => $dmg,
                'qty_reguler_base' => 0,
                'qty_bonus_base' => 0,
                'cost_price' => $cost,
                'cost_price_base' => 0, // computed saat posting
                'cost_overridden' => $costOverridden,
                'cost_override_reason' => $row['cost_override_reason'] ?? null,
                'condition' => $row['condition'] ?? GrnItem::CONDITION_GOOD,
                'notes' => $row['notes'] ?? null,
                'sort_order' => $idx,
            ]);
        }
    }

    /**
     * Mode dari-PO: validasi po_item milik PO + strict match produk/unit,
     * cost fallback ke unit_net_cost PO.
     *
     * @param  array<int, int>  $poItemIds
     * @param  array<string, mixed>  $row
     * @return array{0: PoItem, 1: Product, 2: ProductUnit, 3: float, 4: bool}
     */
    private function resolvePoBackedItem(array $poItemIds, array $row, int $idx): array
    {
        $poItemId = (int) ($row['po_item_id'] ?? 0);

        if (! in_array($poItemId, $poItemIds, true)) {
            throw ValidationException::withMessages([
                "items.{$idx}.po_item_id" => 'po_item tidak tertaut ke PO ini.',
            ]);
        }

        /** @var PoItem $poItem */
        $poItem = PoItem::query()->findOrFail($poItemId);

        if ((int) $poItem->product_id !== (int) $row['product_id']) {
            throw ValidationException::withMessages([
                "items.{$idx}.product_id" => 'Produk tidak match po_item.',
            ]);
        }
        if ((int) $poItem->product_unit_id !== (int) $row['product_unit_id']) {
            throw ValidationException::withMessages([
                "items.{$idx}.product_unit_id" => 'Unit tidak match po_item.',
            ]);
        }

        $cost = (float) ($row['cost_price'] ?? $poItem->unit_net_cost);
        $costOverridden = (float) $cost !== (float) $poItem->unit_net_cost;

        return [$poItem, $poItem->product, $poItem->productUnit, $cost, $costOverridden];
    }

    /**
     * Mode langsung (tanpa PO): produk & unit dari master, wajib milik supplier
     * GRN, cost_price wajib dari input (tidak ada baseline PO → override=false).
     *
     * @param  array<string, mixed>  $row
     * @return array{0: null, 1: Product, 2: ProductUnit, 3: float, 4: bool}
     */
    private function resolveDirectItem(GoodsReceipt $grn, array $row, int $idx): array
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail($row['product_id']);

        if ((int) $product->supplier_id !== (int) $grn->supplier_id) {
            throw ValidationException::withMessages([
                "items.{$idx}.product_id" => 'Produk bukan milik supplier yang dipilih.',
            ]);
        }

        /** @var ProductUnit $unit */
        $unit = ProductUnit::query()
            ->where('product_id', $product->id)
            ->findOrFail($row['product_unit_id']);

        $cost = (float) ($row['cost_price'] ?? 0);

        return [null, $product, $unit, $cost, false];
    }

    private function assertPoOpen(PurchaseOrder $po): void
    {
        if (! in_array($po->status, [
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_PARTIAL_RECEIVED,
        ], true)) {
            throw ValidationException::withMessages([
                'purchase_order_id' => "PO status `{$po->status}` tidak bisa di-GRN — hanya approved/partial_received.",
            ]);
        }
    }
}
