<?php

namespace App\Services\Purchasing;

use App\Models\PoItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly PoPdfRenderer $pdf,
    ) {}

    /**
     * Create draft PO with items.
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function createDraft(array $headerData, array $itemsData, User $by): PurchaseOrder
    {
        return DB::transaction(function () use ($headerData, $itemsData, $by): PurchaseOrder {
            /** @var Supplier $supplier */
            $supplier = Supplier::query()->findOrFail($headerData['supplier_id']);

            $poDate = $headerData['po_date'] instanceof CarbonInterface
                ? $headerData['po_date']
                : Carbon::parse($headerData['po_date']);

            $po = new PurchaseOrder([
                'supplier_id' => $supplier->id,
                'po_date' => $poDate,
                'eta_date' => $headerData['eta_date'] ?? null,
                'payment_term_days' => $headerData['payment_term_days'] ?? $supplier->payment_term_days,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'fiscal_year' => (int) $poDate->format('Y'),
                'is_carry_over' => false,
                'header_discount_type' => $headerData['header_discount_type'] ?? null,
                'header_discount_value' => $headerData['header_discount_value'] ?? 0,
                'notes' => $headerData['notes'] ?? null,
                'created_by' => $by->id,
            ]);

            $po->po_number = $this->numbering->next('po');
            $po->save();

            $this->syncItems($po, $itemsData);
            $this->recomputeTotals($po);

            return $po->refresh();
        });
    }

    /**
     * Update draft PO header + items.
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function updateDraft(PurchaseOrder $po, array $headerData, array $itemsData, User $by): PurchaseOrder
    {
        if (! $po->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'PO sudah tidak bisa diedit.']);
        }

        return DB::transaction(function () use ($po, $headerData, $itemsData, $by): PurchaseOrder {
            $poDate = $headerData['po_date'] instanceof CarbonInterface
                ? $headerData['po_date']
                : Carbon::parse($headerData['po_date']);

            $po->fill([
                'supplier_id' => $headerData['supplier_id'],
                'po_date' => $poDate,
                'eta_date' => $headerData['eta_date'] ?? null,
                'payment_term_days' => $headerData['payment_term_days'] ?? null,
                'header_discount_type' => $headerData['header_discount_type'] ?? null,
                'header_discount_value' => $headerData['header_discount_value'] ?? 0,
                'notes' => $headerData['notes'] ?? null,
                'fiscal_year' => (int) $poDate->format('Y'),
                'updated_by' => $by->id,
            ]);
            $po->save();

            $this->syncItems($po, $itemsData);
            $this->recomputeTotals($po);

            return $po->refresh();
        });
    }

    public function approve(PurchaseOrder $po, User $by): PurchaseOrder
    {
        if (! $po->canBeApproved()) {
            throw ValidationException::withMessages(['status' => 'PO tidak bisa di-approve (status atau items kosong).']);
        }

        return DB::transaction(function () use ($po, $by): PurchaseOrder {
            $po->update([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => $by->id,
                'approved_at' => now(),
                'supplier_snapshot' => $this->buildSupplierSnapshot($po->supplier()->first()),
            ]);

            // Generate PDF (idempotent).
            $this->pdf->generate($po->refresh());

            return $po->refresh();
        });
    }

    public function cancel(PurchaseOrder $po, string $reason, User $by): PurchaseOrder
    {
        if (! $po->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'PO tidak bisa di-cancel pada status saat ini.']);
        }

        $hasReceived = $po->items()
            ->where(fn ($q) => $q->where('qty_received', '>', 0)->orWhere('bonus_qty_received', '>', 0))
            ->exists();
        if ($hasReceived) {
            throw ValidationException::withMessages(['status' => 'PO sudah ada penerimaan (GRN posted). Tidak bisa di-cancel.']);
        }

        $po->update([
            'status' => PurchaseOrder::STATUS_CANCELLED,
            'cancelled_by' => $by->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $po->refresh();
    }

    public function close(PurchaseOrder $po, string $reason, User $by): PurchaseOrder
    {
        if (! $po->canBeClosed()) {
            throw ValidationException::withMessages(['status' => 'PO tidak bisa di-close pada status saat ini.']);
        }

        $po->update([
            'status' => PurchaseOrder::STATUS_CLOSED,
            'closed_by' => $by->id,
            'closed_at' => now(),
            'close_reason' => $reason,
        ]);

        return $po->refresh();
    }

    public function regeneratePdf(PurchaseOrder $po): PurchaseOrder
    {
        $this->pdf->generate($po);

        return $po->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    private function syncItems(PurchaseOrder $po, array $itemsData): void
    {
        $po->items()->delete();

        foreach ($itemsData as $idx => $row) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($row['product_id']);
            /** @var ProductUnit $unit */
            $unit = ProductUnit::query()->where('product_id', $product->id)->findOrFail($row['product_unit_id']);

            $cost = (float) $row['cost_price'];
            $z1 = (float) ($row['discount_z1_pct'] ?? 0);
            $z2 = (float) ($row['discount_z2_pct'] ?? 0);
            $netCost = PoItem::computeUnitNetCost($cost, $z1, $z2);

            PoItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'product_unit_name_snapshot' => $unit->name,
                'qty_ordered' => (int) $row['qty_ordered'],
                'bonus_qty' => (int) ($row['bonus_qty'] ?? 0),
                'cost_price' => $cost,
                'discount_z1_pct' => $z1,
                'discount_z2_pct' => $z2,
                'unit_net_cost' => $netCost,
                'line_subtotal' => round($netCost * (int) $row['qty_ordered'], 2),
                'notes' => $row['notes'] ?? null,
                'sort_order' => $idx,
            ]);
        }
    }

    public function recomputeTotals(PurchaseOrder $po): void
    {
        $subtotal = (float) $po->items()->sum('line_subtotal');

        $headerDiscAmount = 0.0;
        if ($po->header_discount_type === 'rp') {
            $headerDiscAmount = min($subtotal, (float) $po->header_discount_value);
        } elseif ($po->header_discount_type === 'percent') {
            $headerDiscAmount = round($subtotal * (float) $po->header_discount_value / 100, 2);
        }

        $po->update([
            'subtotal' => $subtotal,
            'header_discount_amount' => $headerDiscAmount,
            'total' => max(0, $subtotal - $headerDiscAmount),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSupplierSnapshot(Supplier $s): array
    {
        return [
            'id' => $s->id,
            'code' => $s->code,
            'name' => $s->name,
            'legal_form' => $s->legal_form,
            'npwp' => $s->npwp,
            'phone' => $s->phone,
            'email' => $s->email,
            'address' => $s->address,
            'city' => $s->city,
            'province' => $s->province,
            'contact_person_name' => $s->contact_person_name,
            'contact_person_phone' => $s->contact_person_phone,
            'payment_term_days' => $s->payment_term_days,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }
}
