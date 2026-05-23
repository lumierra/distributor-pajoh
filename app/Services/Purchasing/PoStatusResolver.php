<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseOrder;

/**
 * Hook untuk T08 (GRN) — saat GRN posted, panggil resolver ini untuk
 * meng-update status PO ke partial_received/closed berdasarkan qty_received.
 */
class PoStatusResolver
{
    public function resolveAfterGrnChange(PurchaseOrder $po): PurchaseOrder
    {
        $po->load('items');

        $allReceived = $po->items->every(
            fn ($item) => $item->qty_received >= $item->qty_ordered,
        );
        $anyReceived = $po->items->contains(
            fn ($item) => $item->qty_received > 0 || $item->bonus_qty_received > 0,
        );

        if (in_array($po->status, [PurchaseOrder::STATUS_CANCELLED, PurchaseOrder::STATUS_CLOSED], true)) {
            return $po;
        }

        if ($allReceived) {
            $po->update(['status' => PurchaseOrder::STATUS_CLOSED]);
        } elseif ($anyReceived) {
            $po->update(['status' => PurchaseOrder::STATUS_PARTIAL_RECEIVED]);
        }

        return $po->refresh();
    }
}
