<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
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

        $query = Product::query()
            ->with(['category:id,code,name', 'baseUnit:id,product_id,name'])
            ->leftJoin('stock_balances', 'stock_balances.product_id', '=', 'products.id')
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

        return Inertia::render('Inventory/Stock', [
            'product' => $product,
            'balances' => $batches,
        ]);
    }
}
