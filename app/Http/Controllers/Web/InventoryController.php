<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * Overview stok per produk: sum semua batch.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canView('inventory.stock'), 403);

        // Pending per produk = barang yang belum datang, dikonversi ke BASE UNIT
        // (× qty_to_base) supaya satuannya konsisten dgn stok. BUKAN stok —
        // catatan "barang menyusul" saja. Dua sumber:
        //  (a) Dari PO: sisa PO approved/partial_received (qty_ordered − qty_received).
        //  (b) Penerimaan langsung: dari surat jalan GRN posted tanpa PO
        //      (qty_delivery_note − qty_reguler).
        $poPendingSub = DB::table('po_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'po_items.purchase_order_id')
            ->join('product_units', 'product_units.id', '=', 'po_items.product_unit_id')
            ->whereIn('purchase_orders.status', [
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_PARTIAL_RECEIVED,
            ])
            ->whereNull('purchase_orders.deleted_at')
            ->groupBy('po_items.product_id')
            ->select([
                'po_items.product_id',
                DB::raw('COALESCE(SUM(GREATEST(po_items.qty_ordered - po_items.qty_received, 0) * product_units.qty_to_base), 0) AS pending_qty'),
                DB::raw('COALESCE(SUM(GREATEST(po_items.bonus_qty - po_items.bonus_qty_received, 0) * product_units.qty_to_base), 0) AS pending_bonus_qty'),
            ]);

        // Pending penerimaan langsung: GRN posted tanpa PO (po_item_id null),
        // sisa surat jalan = qty_delivery_note − qty_reguler.
        $directPendingSub = DB::table('grn_items')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'grn_items.goods_receipt_id')
            ->join('product_units', 'product_units.id', '=', 'grn_items.product_unit_id')
            ->whereNull('grn_items.po_item_id')
            ->whereNull('goods_receipts.purchase_order_id')
            ->where('goods_receipts.status', GoodsReceipt::STATUS_POSTED)
            ->whereNull('goods_receipts.deleted_at')
            ->whereNull('grn_items.pending_settled_at') // item yg pending-nya sudah ditandai selesai tak dihitung
            ->groupBy('grn_items.product_id')
            ->select([
                'grn_items.product_id',
                DB::raw('COALESCE(SUM(GREATEST(grn_items.qty_delivery_note - grn_items.qty_reguler, 0) * product_units.qty_to_base), 0) AS pending_qty'),
            ]);

        $query = Product::query()
            ->with([
                'category:id,code,name',
                'baseUnit:id,product_id,name',
                'units:id,product_id,name,qty_to_base',
            ])
            ->leftJoin('stock_balances', 'stock_balances.product_id', '=', 'products.id')
            ->leftJoinSub($poPendingSub, 'po_pending', 'po_pending.product_id', '=', 'products.id')
            ->leftJoinSub($directPendingSub, 'direct_pending', 'direct_pending.product_id', '=', 'products.id')
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.category_id',
                'products.base_unit_id',
                'products.is_active',
                DB::raw('COALESCE(SUM(stock_balances.qty_on_hand), 0) AS total_on_hand'),
                DB::raw('COALESCE(SUM(stock_balances.qty_bonus_pool), 0) AS total_bonus_pool'),
                DB::raw('COALESCE(SUM(stock_balances.qty_reserved), 0) AS total_reserved'),
                DB::raw('COUNT(DISTINCT stock_balances.batch_id) AS batch_count'),
                DB::raw('(COALESCE(MAX(po_pending.pending_qty), 0) + COALESCE(MAX(direct_pending.pending_qty), 0)) AS pending_qty'),
                DB::raw('COALESCE(MAX(po_pending.pending_bonus_qty), 0) AS pending_bonus_qty'),
            ])
            ->groupBy(
                'products.id',
                'products.sku',
                'products.name',
                'products.category_id',
                'products.base_unit_id',
                'products.is_active',
            )
            ->orderBy('products.name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('products.category_id', $categoryId);
        }

        if ($request->input('stock_status') === 'out') {
            $query->havingRaw('COALESCE(SUM(stock_balances.qty_on_hand), 0) <= 0');
        } elseif ($request->input('stock_status') === 'low') {
            $query->havingRaw('COALESCE(SUM(stock_balances.qty_on_hand), 0) > 0')
                ->havingRaw('COALESCE(SUM(stock_balances.qty_on_hand), 0) <= 10');
        } elseif ($request->input('stock_status') === 'in') {
            $query->havingRaw('COALESCE(SUM(stock_balances.qty_on_hand), 0) > 0');
        }

        return Inertia::render('Inventory/Stocks', [
            'products' => $query->paginate(25)->withQueryString(),
            'categories' => ProductCategory::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'category_id' => $request->input('category_id'),
                'stock_status' => $request->input('stock_status'),
            ],
        ]);
    }

    /**
     * Detail stok per batch untuk produk tertentu.
     */
    public function show(Request $request, Product $product): Response
    {
        abort_unless($request->user()?->canView('inventory.stock'), 403);

        $product->load(['category:id,code,name', 'baseUnit', 'units']);

        $batches = StockBalance::query()
            ->where('product_id', $product->id)
            ->with(['batch:id,batch_code,supplier_id,production_date,expired_date,is_active', 'batch.supplier:id,code,name'])
            ->orderByRaw('(SELECT expired_date FROM product_batches WHERE id = stock_balances.batch_id) ASC')
            ->get();

        // Ringkasan stok produk ini (base unit). Stok real = on_hand − bonus.
        $totalOnHand = (int) $batches->sum('qty_on_hand');
        $totalBonus = (int) $batches->sum('qty_bonus_pool');
        $totalReserved = (int) $batches->sum('qty_reserved');

        // Riwayat mutasi terakhir produk ini (semua masuk/keluar tercatat di ledger).
        $ledger = StockLedger::query()
            ->where('product_id', $product->id)
            ->with([
                'batch:id,batch_code',
                'creator:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return Inertia::render('Inventory/Stock', [
            'product' => $product,
            'balances' => $batches,
            'summary' => [
                'stock_real' => $totalOnHand - $totalBonus,
                'bonus' => $totalBonus,
                'reserved' => $totalReserved,
                'available' => $totalOnHand - $totalReserved,
                'pending' => $this->pendingBaseQtyForProduct($product->id),
            ],
            'ledger' => $ledger,
        ]);
    }

    /**
     * Total pending 1 produk (base unit) = pending PO (approved/partial) +
     * pending penerimaan langsung (surat jalan GRN posted tanpa PO). Sama
     * definisi dgn kolom Pending di halaman list stok.
     */
    private function pendingBaseQtyForProduct(int $productId): int
    {
        $poPending = (int) DB::table('po_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'po_items.purchase_order_id')
            ->join('product_units', 'product_units.id', '=', 'po_items.product_unit_id')
            ->where('po_items.product_id', $productId)
            ->whereIn('purchase_orders.status', [
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_PARTIAL_RECEIVED,
            ])
            ->whereNull('purchase_orders.deleted_at')
            ->sum(DB::raw('GREATEST(po_items.qty_ordered - po_items.qty_received, 0) * product_units.qty_to_base'));

        $directPending = (int) DB::table('grn_items')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'grn_items.goods_receipt_id')
            ->join('product_units', 'product_units.id', '=', 'grn_items.product_unit_id')
            ->where('grn_items.product_id', $productId)
            ->whereNull('grn_items.po_item_id')
            ->whereNull('goods_receipts.purchase_order_id')
            ->where('goods_receipts.status', GoodsReceipt::STATUS_POSTED)
            ->whereNull('goods_receipts.deleted_at')
            ->whereNull('grn_items.pending_settled_at') // item yg pending-nya sudah ditandai selesai tak dihitung
            ->sum(DB::raw('GREATEST(grn_items.qty_delivery_note - grn_items.qty_reguler, 0) * product_units.qty_to_base'));

        return $poPending + $directPending;
    }
}
