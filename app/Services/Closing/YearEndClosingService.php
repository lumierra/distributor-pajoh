<?php

namespace App\Services\Closing;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\NumberingSequence;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\YearEndClosing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Year-End Closing orchestrator.
 *
 *  - preCheck: block kalau ada draft/submitted/in-progress
 *  - execute: carry-over flag, sequence reset (yearly), summary metrics, top lists
 *  - locked: tahun closed = readonly kecuali transaksi carry-over
 */
class YearEndClosingService
{
    /**
     * Pre-check: detect dokumen yang masih draft/submitted/in-progress.
     *
     * @return array{passed:bool, issues:array<int, array{type:string, count:int, message:string}>}
     */
    public function preCheck(int $fiscalYear): array
    {
        $issues = [];

        $poBlocking = PurchaseOrder::query()
            ->whereYear('po_date', $fiscalYear)
            ->where('status', PurchaseOrder::STATUS_DRAFT)
            ->count();
        if ($poBlocking > 0) {
            $issues[] = ['type' => 'purchase_orders', 'count' => $poBlocking, 'message' => "Ada {$poBlocking} PO draft/submitted yang harus di-finalize."];
        }

        $grnBlocking = GoodsReceipt::query()
            ->whereYear('received_date', $fiscalYear)
            ->whereIn('status', [GoodsReceipt::STATUS_DRAFT, GoodsReceipt::STATUS_SUBMITTED])
            ->count();
        if ($grnBlocking > 0) {
            $issues[] = ['type' => 'grn', 'count' => $grnBlocking, 'message' => "Ada {$grnBlocking} GRN draft/submitted."];
        }

        $soBlocking = SalesOrder::query()
            ->whereYear('so_date', $fiscalYear)
            ->whereIn('status', [SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_SUBMITTED, SalesOrder::STATUS_PENDING_CREDIT_REVIEW])
            ->count();
        if ($soBlocking > 0) {
            $issues[] = ['type' => 'sales_orders', 'count' => $soBlocking, 'message' => "Ada {$soBlocking} SO draft/submitted/pending review."];
        }

        // Year already closed?
        $existing = YearEndClosing::query()
            ->where('fiscal_year', $fiscalYear)
            ->where('status', YearEndClosing::STATUS_COMPLETED)
            ->exists();
        if ($existing) {
            $issues[] = ['type' => 'already_closed', 'count' => 1, 'message' => "Fiscal year {$fiscalYear} sudah closed sebelumnya."];
        }

        return [
            'passed' => count($issues) === 0,
            'issues' => $issues,
        ];
    }

    /**
     * Execute closing process. Idempotent untuk year yang sama (return existing
     * kalau sudah completed, retry kalau status failed/in_progress).
     */
    public function execute(int $fiscalYear, User $by): YearEndClosing
    {
        $existing = YearEndClosing::query()->where('fiscal_year', $fiscalYear)->first();
        if ($existing !== null && $existing->status === YearEndClosing::STATUS_COMPLETED) {
            return $existing;
        }

        $check = $this->preCheck($fiscalYear);
        if (! $check['passed']) {
            throw ValidationException::withMessages([
                'pre_check' => 'Pre-check failed: '.collect($check['issues'])->pluck('message')->implode(' '),
            ]);
        }

        return DB::transaction(function () use ($fiscalYear, $by, $existing): YearEndClosing {
            $closing = $existing ?? new YearEndClosing([
                'fiscal_year' => $fiscalYear,
                'closing_date' => now()->toDateString(),
                'status' => YearEndClosing::STATUS_IN_PROGRESS,
                'closed_by' => $by->id,
            ]);
            $closing->fill([
                'status' => YearEndClosing::STATUS_IN_PROGRESS,
                'closed_by' => $by->id,
                'closing_date' => now()->toDateString(),
                'pre_check_passed_at' => now(),
                'error_message' => null,
            ])->save();

            try {
                $this->applyCarryOver($fiscalYear, $closing);
                $closing->update(['carry_over_done_at' => now()]);

                $this->resetYearlySequences($fiscalYear);
                $closing->update(['sequence_reset_done_at' => now()]);

                $this->generateSummary($fiscalYear, $closing);
                $closing->update(['summary_generated_at' => now()]);

                $closing->update([
                    'status' => YearEndClosing::STATUS_COMPLETED,
                    'closed_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $closing->update([
                    'status' => YearEndClosing::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
                throw $e;
            }

            return $closing->refresh();
        });
    }

    /**
     * Carry-over: flag open PO/SO/Invoice/CN supaya tetap muncul di tahun aktif.
     */
    private function applyCarryOver(int $fiscalYear, YearEndClosing $closing): void
    {
        // PO open (draft sudah blocked di pre-check, jadi yg open = approved/partial)
        $poOpenIds = PurchaseOrder::query()
            ->whereYear('po_date', $fiscalYear)
            ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL_RECEIVED])
            ->pluck('id')
            ->all();
        if (! empty($poOpenIds)) {
            PurchaseOrder::query()->whereIn('id', $poOpenIds)->update(['is_carry_over' => true]);
        }

        $soOpenIds = SalesOrder::query()
            ->whereYear('so_date', $fiscalYear)
            ->whereIn('status', [SalesOrder::STATUS_APPROVED, SalesOrder::STATUS_PARTIALLY_DELIVERED])
            ->pluck('id')
            ->all();
        if (! empty($soOpenIds)) {
            SalesOrder::query()->whereIn('id', $soOpenIds)->update(['is_carry_over' => true]);
        }

        $invoiceOpenIds = Invoice::query()
            ->whereYear('invoice_date', $fiscalYear)
            ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIAL_PAID, Invoice::STATUS_OVERDUE])
            ->pluck('id')
            ->all();
        if (! empty($invoiceOpenIds)) {
            Invoice::query()->whereIn('id', $invoiceOpenIds)->update(['is_carry_over' => true]);
        }

        $cnOpenIds = CreditNote::query()
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('status', [CreditNote::STATUS_OPEN, CreditNote::STATUS_APPLIED])
            ->pluck('id')
            ->all();
        if (! empty($cnOpenIds)) {
            CreditNote::query()->whereIn('id', $cnOpenIds)->update(['is_carry_over' => true]);
        }

        $totalOutstanding = Invoice::query()->whereIn('id', $invoiceOpenIds)->sum('outstanding');

        $closing->update([
            'carry_over_po_ids' => $poOpenIds,
            'carry_over_so_ids' => $soOpenIds,
            'carry_over_invoice_ids' => $invoiceOpenIds,
            'carry_over_cn_ids' => $cnOpenIds,
            'carry_over_summary' => [
                'po_count' => count($poOpenIds),
                'so_count' => count($soOpenIds),
                'invoice_count' => count($invoiceOpenIds),
                'cn_count' => count($cnOpenIds),
                'total_outstanding_value' => (float) $totalOutstanding,
            ],
            'total_outstanding_carry_over' => (float) $totalOutstanding,
        ]);
    }

    /**
     * Reset numbering sequences yang `reset_period=yearly` untuk year yang baru closed.
     */
    private function resetYearlySequences(int $fiscalYear): void
    {
        NumberingSequence::query()
            ->where('period_year', $fiscalYear)
            ->update(['last_number' => 0]);
    }

    /**
     * Generate summary: revenue, cost, margin, top customers/products/sales.
     */
    private function generateSummary(int $fiscalYear, YearEndClosing $closing): void
    {
        // Revenue + cost dari invoice_items + ledger (sederhana: sum dari invoice)
        $revenueRow = Invoice::query()
            ->whereYear('invoice_date', $fiscalYear)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('COUNT(*) AS invoice_count, COALESCE(SUM(total), 0) AS revenue, COALESCE(SUM(paid_amount), 0) AS paid')
            ->first();

        $purchaseRow = GoodsReceipt::query()
            ->whereYear('received_date', $fiscalYear)
            ->where('status', GoodsReceipt::STATUS_POSTED)
            ->join('grn_items', 'grn_items.goods_receipt_id', '=', 'goods_receipts.id')
            ->selectRaw('COALESCE(SUM(grn_items.cost_price * (grn_items.qty_reguler + grn_items.qty_bonus)), 0) AS total_purchases')
            ->first();

        $paymentRow = Payment::query()
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('status', [Payment::STATUS_POSTED, Payment::STATUS_CLEARED])
            ->selectRaw('COALESCE(SUM(applied_amount), 0) AS total_payments')
            ->first();

        $stockValue = StockBalance::query()
            ->join('stock_ledger', function ($join): void {
                $join->on('stock_ledger.product_id', '=', 'stock_balances.product_id')
                    ->on('stock_ledger.batch_id', '=', 'stock_balances.batch_id');
            })
            ->where('stock_balances.qty_on_hand', '>', 0)
            ->groupBy('stock_balances.id', 'stock_balances.qty_on_hand')
            ->selectRaw('stock_balances.qty_on_hand * AVG(stock_ledger.cost_price) AS line_value')
            ->get()
            ->sum('line_value');

        // Top 5 customers by revenue
        $top5Customers = DB::table('invoices')
            ->whereYear('invoice_date', $fiscalYear)
            ->whereNotIn('status', ['cancelled'])
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->select('customers.id', 'customers.code', 'customers.name')
            ->selectRaw('COUNT(invoices.id) AS invoice_count, SUM(invoices.total) AS revenue')
            ->groupBy('customers.id', 'customers.code', 'customers.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->toArray();

        // Top 5 products by qty sold
        $top5Products = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->whereYear('invoices.invoice_date', $fiscalYear)
            ->select('products.id', 'products.sku', 'products.name')
            ->selectRaw('SUM(invoice_items.qty) AS qty_sold, SUM(invoice_items.line_subtotal) AS revenue')
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->toArray();

        // Top 3 sales
        $top3Sales = DB::table('invoices')
            ->whereYear('invoice_date', $fiscalYear)
            ->whereNotNull('sales_id')
            ->join('users', 'users.id', '=', 'invoices.sales_id')
            ->select('users.id', 'users.name')
            ->selectRaw('COUNT(invoices.id) AS invoice_count, SUM(invoices.total) AS revenue')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('revenue')
            ->limit(3)
            ->get()
            ->toArray();

        $customerCount = Customer::query()->where('is_active', true)->count();
        $productSold = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereYear('invoices.invoice_date', $fiscalYear)
            ->distinct('invoice_items.product_id')
            ->count('invoice_items.product_id');

        $revenue = (float) $revenueRow->revenue;
        $cost = (float) $purchaseRow->total_purchases;
        $margin = $revenue - $cost;
        $marginPct = $revenue > 0 ? round(($margin / $revenue) * 100, 2) : 0;

        $closing->update([
            'total_revenue' => $revenue,
            'total_cost' => $cost,
            'total_margin' => $margin,
            'margin_percent' => $marginPct,
            'total_purchases' => $cost,
            'total_payments_received' => (float) $paymentRow->total_payments,
            'total_stock_value_closing' => round((float) $stockValue, 2),
            'invoice_count' => (int) $revenueRow->invoice_count,
            'customer_count_active' => $customerCount,
            'product_count_sold' => $productSold,
            'top_5_customers' => $top5Customers,
            'top_5_products' => $top5Products,
            'top_3_sales' => $top3Sales,
        ]);
    }
}
