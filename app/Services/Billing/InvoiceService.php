<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DoItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Customer\CustomerCreditLimitService;
use App\Services\Customer\CustomerOutstandingService;
use App\Services\Numbering\NumberingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invoice life cycle:
 *  - generateFromDeliveredDo (idempotent — 1 DO = 1 invoice via unique index)
 *  - recomputeTotals (subtotal, total, outstanding)
 *  - applyPayment / applyCreditNote (di-call dari T14/T15)
 *  - markOverdue (daily job)
 *  - resolveStatus (transition logic terpusat)
 */
class InvoiceService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly CustomerOutstandingService $outstanding,
        private readonly CustomerCreditLimitService $creditLimits,
    ) {}

    /**
     * Auto-generate invoice dari DO delivered. Idempotent — kalau sudah ada
     * invoice untuk DO ini (unique index `invoices_do_unique`), return yang
     * existing.
     */
    public function generateFromDeliveredDo(DeliveryOrder $do, ?User $by = null): Invoice
    {
        $do->loadMissing(['salesOrder.customer', 'salesOrder.sales', 'items.soItem', 'driver', 'vehicle']);

        if (! in_array($do->status, [
            DeliveryOrder::STATUS_DELIVERED,
            DeliveryOrder::STATUS_PARTIAL_RETURNED,
        ], true)) {
            throw ValidationException::withMessages([
                'delivery_order_id' => "DO status `{$do->status}` belum delivered.",
            ]);
        }

        // Idempotency check
        $existing = Invoice::query()->where('delivery_order_id', $do->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($do, $by): Invoice {
            /** @var SalesOrder $so */
            $so = $do->salesOrder;
            /** @var Customer $customer */
            $customer = $so->customer;

            // Hard block: cek credit limit per supplier sebelum invoice create.
            // Incoming dihitung dari nilai DO yang akan jadi invoice (per supplier).
            $incomingPerSupplier = $this->computeIncomingPerSupplier($do);
            if (! empty($incomingPerSupplier)) {
                $this->creditLimits->assertCanCharge($customer, $incomingPerSupplier, 'credit_limit');
            }

            $invoiceDate = $do->delivered_at instanceof CarbonInterface
                ? Carbon::parse($do->delivered_at)->startOfDay()
                : now()->startOfDay();

            $paymentTerm = (int) ($so->payment_term_days ?? $customer->payment_term_days ?? 0);
            $dueDate = $invoiceDate->copy()->addDays($paymentTerm);

            $invoice = new Invoice([
                'sales_order_id' => $so->id,
                'delivery_order_id' => $do->id,
                'customer_id' => $customer->id,
                'customer_snapshot' => $so->customer_snapshot ?? $this->buildCustomerSnapshot($customer),
                'sales_id' => $so->sales_id,
                'sales_name_snapshot' => $so->sales?->name,
                'driver_name_snapshot' => $do->driver?->name ?? ($do->driver_snapshot['name'] ?? null),
                'vehicle_plate_snapshot' => $do->vehicle?->plate_number ?? ($do->vehicle_snapshot['plate'] ?? null),
                'invoice_date' => $invoiceDate,
                'payment_term_days' => $paymentTerm,
                'due_date' => $dueDate,
                'original_due_date' => $dueDate,
                'is_cash' => $paymentTerm === 0,
                'status' => Invoice::STATUS_OPEN,
                'fiscal_year' => (int) $invoiceDate->format('Y'),
                'header_discount_amount' => $this->prorateHeaderDiscount($so, $do),
                'cashback_amount' => $this->prorateSoAmount($so, $do, (float) $so->cashback),
                'delivery_notes_snapshot' => $do->receiver_notes,
                'created_by' => $by?->id,
            ]);

            $invoice->invoice_number = $this->numbering->next('invoice', ['cust' => $customer->id]);
            $invoice->save();

            $this->cloneItems($invoice, $do);
            $this->recomputeTotals($invoice);

            // Invalidate customer outstanding cache supaya credit limit check
            // di SO berikutnya pakai angka up-to-date.
            $this->outstanding->invalidate($customer);

            return $invoice->refresh();
        });
    }

    /**
     * Hitung nilai per supplier dari DO yang akan jadi invoice.
     * Pakai qty_effective (delivered - returned) × unit_net_price dari SO item.
     * Skip bonus & supplier_id null.
     *
     * @return array<int, float> [supplier_id => amount]
     */
    private function computeIncomingPerSupplier(DeliveryOrder $do): array
    {
        $do->loadMissing('items.soItem');
        $result = [];
        foreach ($do->items as $item) {
            if ($item->is_bonus) {
                continue;
            }
            $supplierId = $item->supplier_id ?? $item->soItem?->supplier_id;
            if (! $supplierId) {
                continue;
            }
            $qtyEffective = max(0, (int) $item->qty_delivered - (int) $item->qty_returned);
            if ($qtyEffective <= 0) {
                continue;
            }
            $netPrice = (float) ($item->soItem->unit_net_price ?? 0);
            $result[(int) $supplierId] = ($result[(int) $supplierId] ?? 0.0) + ($netPrice * $qtyEffective);
        }

        return $result;
    }

    /**
     * Header discount SO → prorata ke invoice ini.
     */
    private function prorateHeaderDiscount(SalesOrder $so, DeliveryOrder $do): float
    {
        return $this->prorateSoAmount($so, $do, (float) $so->header_discount_amount);
    }

    /**
     * Distribusikan sebuah nilai potongan tingkat-SO (header discount / cashback)
     * ke invoice per-DO secara proporsional: berdasar nilai item DO ini relatif
     * terhadap subtotal SO.
     */
    private function prorateSoAmount(SalesOrder $so, DeliveryOrder $do, float $soAmount): float
    {
        if ($soAmount <= 0) {
            return 0.0;
        }

        $soSubtotal = (float) $so->subtotal;
        if ($soSubtotal <= 0) {
            return 0.0;
        }

        $doSubtotal = 0.0;
        foreach ($do->items as $item) {
            if ($item->is_bonus) {
                continue;
            }
            $qtyEffective = max(0, (int) $item->qty_delivered - (int) $item->qty_returned);
            $netPrice = (float) ($item->soItem->unit_net_price ?? 0);
            $doSubtotal += $netPrice * $qtyEffective;
        }

        if ($doSubtotal <= 0) {
            return 0.0;
        }

        return round($soAmount * $doSubtotal / $soSubtotal, 2);
    }

    private function cloneItems(Invoice $invoice, DeliveryOrder $do): void
    {
        $do->loadMissing(['items.soItem', 'items.batch']);

        $sortOrder = 0;
        foreach ($do->items as $doItem) {
            /** @var DoItem $doItem */
            $qtyEffective = max(0, (int) $doItem->qty_delivered - (int) $doItem->qty_returned);

            // Skip kalau qty effective 0 dan bukan bonus
            if ($qtyEffective <= 0 && ! $doItem->is_bonus) {
                continue;
            }

            $soItem = $doItem->soItem;
            $unitPrice = (float) ($soItem->unit_price ?? 0);
            $isBonus = (bool) $doItem->is_bonus;

            $unitNet = $isBonus
                ? 0.0
                : (float) ($soItem->unit_net_price ?? 0);

            // Bonus items keep qty walau qty_effective = 0 (mis. semua di-return
            // → tetap muncul di faktur dengan qty 0 untuk audit trail).
            $qtyForLine = $isBonus ? max($qtyEffective, (int) $doItem->qty_delivered) : $qtyEffective;
            $lineSubtotal = $isBonus ? 0.0 : round($unitNet * $qtyForLine, 2);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'sales_order_item_id' => $soItem->id,
                'do_item_id' => $doItem->id,
                'product_id' => $doItem->product_id,
                'supplier_id' => $doItem->supplier_id ?? $soItem->supplier_id,
                'product_unit_id' => $doItem->product_unit_id,
                'product_name_snapshot' => $doItem->product_name_snapshot,
                'product_sku_snapshot' => $doItem->product_sku_snapshot,
                'product_unit_name_snapshot' => $doItem->product_unit_name_snapshot,
                'batch_code_snapshot' => $doItem->batch_code_snapshot,
                'qty' => $qtyForLine,
                'unit_price' => $unitPrice,
                'discount_z1_pct' => 0,
                'discount_z2_pct' => 0,
                'discount_type' => $isBonus ? null : $soItem->discount_type,
                'discount_value' => $isBonus ? 0 : (float) ($soItem->discount_value ?? 0),
                'unit_net_price' => $unitNet,
                'line_subtotal' => $lineSubtotal,
                'is_bonus' => $isBonus,
                'notes' => $doItem->notes,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    public function recomputeTotals(Invoice $invoice): Invoice
    {
        $subtotal = (float) $invoice->items()->sum('line_subtotal');
        $total = max(0, $subtotal - (float) $invoice->header_discount_amount - (float) $invoice->cashback_amount);

        $invoice->update([
            'subtotal' => $subtotal,
            'total' => $total,
            'outstanding' => max(0, $total - (float) $invoice->paid_amount),
        ]);

        return $invoice->refresh();
    }

    /**
     * Apply payment amount ke invoice. Dipanggil dari T14 PaymentService.
     */
    public function applyPayment(Invoice $invoice, float $amount, ?User $by = null): Invoice
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount payment harus > 0.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $amount, $by): Invoice {
            $invoice->refresh();
            $newPaid = (float) $invoice->paid_amount + $amount;
            $newOutstanding = max(0, (float) $invoice->total - $newPaid);

            $invoice->update([
                'paid_amount' => $newPaid,
                'outstanding' => $newOutstanding,
                'updated_by' => $by?->id,
            ]);

            $this->resolveStatus($invoice);
            $this->outstanding->invalidate($invoice->customer()->first());

            return $invoice->refresh();
        });
    }

    /**
     * Apply credit note ke invoice. Sama pattern dengan payment.
     */
    public function applyCreditNote(Invoice $invoice, float $amountApplied, ?User $by = null): Invoice
    {
        return $this->applyPayment($invoice, $amountApplied, $by);
    }

    /**
     * Reverse payment (saat payment di-cancel/refund). Decrement paid_amount.
     */
    public function reversePayment(Invoice $invoice, float $amount, ?User $by = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $amount, $by): Invoice {
            $invoice->refresh();
            $newPaid = max(0, (float) $invoice->paid_amount - $amount);
            $newOutstanding = max(0, (float) $invoice->total - $newPaid);

            $invoice->update([
                'paid_amount' => $newPaid,
                'outstanding' => $newOutstanding,
                'paid_at' => $newOutstanding > 0 ? null : $invoice->paid_at,
                'updated_by' => $by?->id,
            ]);

            $this->resolveStatus($invoice);
            $this->outstanding->invalidate($invoice->customer()->first());

            return $invoice->refresh();
        });
    }

    /**
     * Resolve status based on outstanding, due_date, paid_amount.
     */
    public function resolveStatus(Invoice $invoice): Invoice
    {
        $invoice->refresh();

        $outstanding = (float) $invoice->outstanding;
        $paid = (float) $invoice->paid_amount;
        $overdue = $invoice->due_date !== null && $invoice->due_date->isPast();

        if ($outstanding <= 0.0001 /* epsilon utk floating */) {
            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => $invoice->paid_at ?? now(),
            ]);
        } elseif ($overdue) {
            $invoice->update([
                'status' => Invoice::STATUS_OVERDUE,
                'overdue_set_at' => $invoice->overdue_set_at ?? now(),
            ]);
        } elseif ($paid > 0) {
            $invoice->update(['status' => Invoice::STATUS_PARTIAL_PAID]);
        } else {
            $invoice->update(['status' => Invoice::STATUS_OPEN]);
        }

        return $invoice->refresh();
    }

    /**
     * Daily job: tandai semua open/partial_paid yang due_date < today &
     * masih ada outstanding sebagai overdue.
     */
    public function markOverdueDue(): int
    {
        $count = 0;
        Invoice::query()->overdueCandidates()->chunkById(100, function ($chunk) use (&$count): void {
            foreach ($chunk as $inv) {
                $inv->update([
                    'status' => Invoice::STATUS_OVERDUE,
                    'overdue_set_at' => $inv->overdue_set_at ?? now(),
                ]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerSnapshot(Customer $c): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'owner_name' => $c->owner_name,
            'phone' => $c->phone,
            'whatsapp' => $c->whatsapp,
            'email' => $c->email,
            'address' => $c->address,
            'city' => $c->city,
            'province' => $c->province,
            'npwp' => $c->npwp,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }
}
