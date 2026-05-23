<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canView('inventory.ledger'), 403);

        $query = StockLedger::query()
            ->with(['product:id,sku,name', 'batch:id,batch_code', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($batchId = $request->input('batch_id')) {
            $query->where('batch_id', $batchId);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($refType = $request->input('ref_type')) {
            $query->where('ref_type', $refType);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return Inertia::render('Inventory/Ledger', [
            'ledger' => $query->paginate(50)->withQueryString(),
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'sku', 'name']),
            'types' => StockLedger::TYPES,
            'filters' => [
                'product_id' => $request->input('product_id'),
                'batch_id' => $request->input('batch_id'),
                'type' => $request->input('type'),
                'ref_type' => $request->input('ref_type'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
        ]);
    }
}
