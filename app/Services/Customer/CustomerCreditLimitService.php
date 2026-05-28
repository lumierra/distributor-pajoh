<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerSupplierCreditLimit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentSupplierAllocation;
use App\Models\Supplier;
use Illuminate\Validation\ValidationException;

/**
 * Credit limit per (customer × supplier).
 *
 * Konsep:
 *  - Outstanding per supplier = SUM(invoice_items.line_subtotal where supplier=X
 *    untuk invoices customer yg masih open/partial_paid/overdue)
 *    - SUM(payment_supplier_allocations.amount untuk customer × supplier).
 *  - Effective limit per supplier = pivot customer_supplier_credit_limits.credit_limit
 *    kalau ada row, else fallback ke customers.credit_limit (legacy global field;
 *    diabaikan kalau 0).
 *  - Available = max(0, limit - outstanding). Kalau limit = 0 → tidak dibatasi.
 *  - Block (hard) saat outstanding + incoming > limit.
 */
class CustomerCreditLimitService
{
    /**
     * @return array<int, array{
     *   supplier_id: int,
     *   limit: float,
     *   outstanding: float,
     *   available: float,
     *   has_limit: bool,
     * }>  keyed by supplier_id
     */
    public function snapshot(Customer $customer): array
    {
        $perSupplierLimit = $this->limitMap($customer);
        $perSupplierOutstanding = $this->outstandingMap($customer);

        $result = [];

        $supplierIds = collect(array_keys($perSupplierLimit))
            ->merge(array_keys($perSupplierOutstanding))
            ->unique();

        foreach ($supplierIds as $sid) {
            $limit = (float) ($perSupplierLimit[$sid] ?? 0.0);
            $outstanding = (float) ($perSupplierOutstanding[$sid] ?? 0.0);
            $hasLimit = $limit > 0;

            $result[$sid] = [
                'supplier_id' => (int) $sid,
                'limit' => $limit,
                'outstanding' => $outstanding,
                'available' => $hasLimit ? max(0.0, $limit - $outstanding) : 0.0,
                'has_limit' => $hasLimit,
            ];
        }

        return $result;
    }

    /**
     * Pastikan incoming per supplier (mis. nilai invoice baru) tidak melebihi
     * limit. Throw ValidationException kalau ada supplier yg exceed.
     *
     * @param  array<int, float>  $incomingPerSupplier  [supplier_id => amount]
     */
    public function assertCanCharge(Customer $customer, array $incomingPerSupplier, string $errorKey = 'items'): void
    {
        if (empty($incomingPerSupplier)) {
            return;
        }

        $limits = $this->limitMap($customer);
        $outstanding = $this->outstandingMap($customer);

        $violations = [];
        foreach ($incomingPerSupplier as $supplierId => $amount) {
            $limit = (float) ($limits[$supplierId] ?? 0.0);
            if ($limit <= 0.0) {
                continue; // no limit set → tidak dibatasi
            }
            $current = (float) ($outstanding[$supplierId] ?? 0.0);
            if (($current + (float) $amount) > $limit) {
                $violations[] = [
                    'supplier_id' => (int) $supplierId,
                    'current' => $current,
                    'incoming' => (float) $amount,
                    'limit' => $limit,
                ];
            }
        }

        if (! empty($violations)) {
            $names = Supplier::query()
                ->whereIn('id', collect($violations)->pluck('supplier_id'))
                ->pluck('name', 'id');

            $messages = collect($violations)->map(function (array $v) use ($names): string {
                $fmt = fn (float $x): string => 'Rp '.number_format($x, 0, ',', '.');

                return sprintf(
                    'Supplier %s: outstanding %s + transaksi baru %s melebihi limit %s.',
                    $names[$v['supplier_id']] ?? "#{$v['supplier_id']}",
                    $fmt($v['current']),
                    $fmt($v['incoming']),
                    $fmt($v['limit']),
                );
            })->implode(' ');

            throw ValidationException::withMessages([
                $errorKey => $messages,
            ]);
        }
    }

    /**
     * Alokasi pembayaran ke per supplier secara prorata berdasar nilai line per
     * supplier di invoice tsb. Dipanggil saat Payment di-record / cleared.
     *
     * @return array<int, float> [supplier_id => allocated_amount]
     */
    public function prorateAllocation(Invoice $invoice, float $paymentAmount): array
    {
        if ($paymentAmount <= 0) {
            return [];
        }

        // Sum per supplier dari line_subtotal invoice (skip bonus).
        $perSupplier = InvoiceItem::query()
            ->where('invoice_id', $invoice->id)
            ->where('is_bonus', false)
            ->whereNotNull('supplier_id')
            ->selectRaw('supplier_id, SUM(line_subtotal) AS total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $totalBase = array_sum($perSupplier);
        if ($totalBase <= 0) {
            return [];
        }

        $allocations = [];
        $allocatedSoFar = 0.0;
        $supplierIds = array_keys($perSupplier);
        $lastSupplier = end($supplierIds);

        foreach ($perSupplier as $supplierId => $supplierTotal) {
            // Last supplier dapat sisa biar gak ada selisih rounding.
            if ($supplierId === $lastSupplier) {
                $amount = round($paymentAmount - $allocatedSoFar, 2);
            } else {
                $amount = round($paymentAmount * ($supplierTotal / $totalBase), 2);
                $allocatedSoFar += $amount;
            }
            $allocations[(int) $supplierId] = max(0.0, $amount);
        }

        return $allocations;
    }

    /**
     * @return array<int, float> [supplier_id => limit]
     */
    private function limitMap(Customer $customer): array
    {
        return CustomerSupplierCreditLimit::query()
            ->where('customer_id', $customer->id)
            ->pluck('credit_limit', 'supplier_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    /**
     * @return array<int, float> [supplier_id => outstanding]
     */
    private function outstandingMap(Customer $customer): array
    {
        // Total invoiced per supplier (dari open invoices)
        $invoiced = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.customer_id', $customer->id)
            ->whereIn('invoices.status', [
                Invoice::STATUS_OPEN,
                Invoice::STATUS_PARTIAL_PAID,
                Invoice::STATUS_OVERDUE,
            ])
            ->whereNull('invoices.deleted_at')
            ->whereNotNull('invoice_items.supplier_id')
            ->where('invoice_items.is_bonus', false)
            ->selectRaw('invoice_items.supplier_id AS sid, SUM(invoice_items.line_subtotal) AS total')
            ->groupBy('invoice_items.supplier_id')
            ->pluck('total', 'sid')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // Total allocated payment per supplier
        $paid = PaymentSupplierAllocation::query()
            ->where('customer_id', $customer->id)
            ->selectRaw('supplier_id, SUM(amount) AS total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $result = [];
        $sids = collect(array_keys($invoiced))
            ->merge(array_keys($paid))
            ->unique();

        foreach ($sids as $sid) {
            $result[(int) $sid] = max(0.0, ((float) ($invoiced[$sid] ?? 0)) - ((float) ($paid[$sid] ?? 0)));
        }

        return $result;
    }
}
