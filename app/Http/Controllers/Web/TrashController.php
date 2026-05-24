<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TrashController extends Controller
{
    /**
     * Whitelist of soft-deletable models yang muncul di Trash UI.
     * Key = short slug, value = [model class, label, menu permission for restore].
     *
     * @var array<string, array{class:class-string, label:string, menu:string}>
     */
    private array $models = [
        'customers' => ['class' => Customer::class, 'label' => 'Customers', 'menu' => 'master.customer'],
        'suppliers' => ['class' => Supplier::class, 'label' => 'Suppliers', 'menu' => 'master.supplier'],
        'products' => ['class' => Product::class, 'label' => 'Products', 'menu' => 'master.product'],
        'drivers' => ['class' => Driver::class, 'label' => 'Drivers', 'menu' => 'master.driver'],
        'vehicles' => ['class' => Vehicle::class, 'label' => 'Vehicles', 'menu' => 'master.vehicle'],
        'sales-orders' => ['class' => SalesOrder::class, 'label' => 'Sales Orders', 'menu' => 'sales.so'],
        'invoices' => ['class' => Invoice::class, 'label' => 'Invoices', 'menu' => 'sales.invoice'],
        'delivery-orders' => ['class' => DeliveryOrder::class, 'label' => 'Delivery Orders', 'menu' => 'sales.do'],
        'purchase-orders' => ['class' => PurchaseOrder::class, 'label' => 'Purchase Orders', 'menu' => 'purchasing.po'],
        'goods-receipts' => ['class' => GoodsReceipt::class, 'label' => 'GRN', 'menu' => 'purchasing.grn'],
        'customer-returns' => ['class' => CustomerReturn::class, 'label' => 'Customer Returns', 'menu' => 'returns.customer'],
        'supplier-returns' => ['class' => SupplierReturn::class, 'label' => 'Supplier Returns', 'menu' => 'returns.supplier'],
    ];

    public function index(Request $request): InertiaResponse
    {
        $this->ensureSuperadmin($request);

        $type = $request->input('type', 'customers');
        if (! isset($this->models[$type])) {
            abort(404);
        }

        $config = $this->models[$type];
        $modelClass = $config['class'];

        $query = $modelClass::query()->onlyTrashed()->orderByDesc('deleted_at');

        if ($search = trim((string) $request->input('q'))) {
            // Best-effort: search common label fields.
            $query->where(function ($q) use ($search): void {
                foreach (['name', 'code', 'invoice_number', 'po_number', 'do_number', 'return_number'] as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        $items = $query->paginate(25)->withQueryString();

        // Counts per type untuk tab badge
        $counts = [];
        foreach ($this->models as $slug => $cfg) {
            $counts[$slug] = $cfg['class']::onlyTrashed()->count();
        }

        return Inertia::render('Trash/Index', [
            'type' => $type,
            'label' => $config['label'],
            'items' => $items,
            'counts' => $counts,
            'models' => collect($this->models)
                ->map(fn ($c, $slug) => ['slug' => $slug, 'label' => $c['label']])
                ->values(),
            'filters' => [
                'q' => $request->input('q'),
                'type' => $type,
            ],
        ]);
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        if (! isset($this->models[$type])) {
            abort(404);
        }

        $config = $this->models[$type];
        $modelClass = $config['class'];

        $item = $modelClass::onlyTrashed()->findOrFail($id);
        $item->restore();

        return back()->with('flash.success', "{$config['label']} #{$id} di-restore.");
    }

    private function ensureSuperadmin(Request $request): void
    {
        $user = $request->user();
        if ($user === null || ! $user->isSuperadmin()) {
            abort(403);
        }
    }
}
