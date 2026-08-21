<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        // Penjualan bulan ini (SO tidak dibatalkan/ditolak).
        $salesMtd = (float) SalesOrder::query()
            ->whereBetween('so_date', [$monthStart, $today->copy()->endOfDay()])
            ->whereNotIn('status', [SalesOrder::STATUS_CANCELLED, SalesOrder::STATUS_REJECTED])
            ->sum('total');

        // Outstanding invoice (yang belum lunas).
        $outstanding = (float) Invoice::query()
            ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIAL_PAID, Invoice::STATUS_OVERDUE])
            ->sum('outstanding');

        $stats = [
            // Baris atas — operasional harian.
            'sales_mtd' => $salesMtd,
            'outstanding' => $outstanding,
            'so_pending' => SalesOrder::query()
                ->whereIn('status', [SalesOrder::STATUS_SUBMITTED, SalesOrder::STATUS_PENDING_CREDIT_REVIEW])
                ->count(),
            'invoice_overdue' => Invoice::query()->where('status', Invoice::STATUS_OVERDUE)->count(),

            // Baris master — kondisi data.
            'products_out_of_stock' => $this->productsOutOfStock(),
            'customers_active' => Customer::query()->where('is_active', true)->count(),
            'suppliers_active' => Supplier::query()->where('is_active', true)->count(),
            'po_open' => PurchaseOrder::query()
                ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL_RECEIVED])
                ->count(),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'salesTrend' => $this->salesTrend(),
            'recentSalesOrders' => $this->recentSalesOrders(),
            'lowStock' => $this->lowStockProducts(),
        ]);
    }

    /**
     * Jumlah produk aktif yang total stoknya 0 (habis).
     */
    private function productsOutOfStock(): int
    {
        return (int) DB::table('products')
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('stock_balances')
                    ->whereColumn('stock_balances.product_id', 'products.id')
                    ->where('stock_balances.qty_on_hand', '>', 0);
            })
            ->count();
    }

    /**
     * Tren penjualan 6 bulan terakhir (total SO valid per bulan).
     *
     * @return array<int, array{label: string, total: float}>
     */
    private function salesTrend(): array
    {
        $start = Carbon::today()->startOfMonth()->subMonths(5);

        $rows = SalesOrder::query()
            ->where('so_date', '>=', $start)
            ->whereNotIn('status', [SalesOrder::STATUS_CANCELLED, SalesOrder::STATUS_REJECTED])
            ->selectRaw("DATE_FORMAT(so_date, '%Y-%m') AS ym, SUM(total) AS total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $start->copy()->addMonths($i);
            $months[] = [
                'label' => $m->translatedFormat('M'),
                'total' => (float) ($rows[$m->format('Y-m')] ?? 0),
            ];
        }

        return $months;
    }

    /**
     * 5 SO terbaru untuk panel aktivitas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentSalesOrders(): array
    {
        return SalesOrder::query()
            ->with('customer:id,name')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'so_number', 'customer_id', 'total', 'status', 'so_date'])
            ->map(fn (SalesOrder $so) => [
                'id' => $so->id,
                'so_number' => $so->so_number,
                'customer_name' => $so->customer?->name ?? '—',
                'total' => (float) $so->total,
                'status' => $so->status,
                'so_date' => $so->so_date?->toDateString(),
            ])
            ->all();
    }

    /**
     * 5 produk aktif dengan stok terendah (real = on_hand − bonus).
     *
     * @return array<int, array<string, mixed>>
     */
    private function lowStockProducts(): array
    {
        return DB::table('products')
            ->leftJoin('stock_balances', 'stock_balances.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->selectRaw('products.id, products.sku, products.name, COALESCE(SUM(stock_balances.qty_on_hand) - SUM(stock_balances.qty_bonus_pool), 0) AS stock_real')
            ->orderBy('stock_real')
            ->orderBy('products.name')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'sku' => $r->sku,
                'name' => $r->name,
                'stock_real' => (int) $r->stock_real,
            ])
            ->all();
    }
}
