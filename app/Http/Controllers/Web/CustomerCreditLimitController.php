<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSupplierCreditLimit;
use App\Models\Supplier;
use App\Services\Customer\CustomerCreditLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerCreditLimitController extends Controller
{
    public function __construct(
        private readonly CustomerCreditLimitService $service,
    ) {}

    /**
     * Return snapshot per supplier: existing limit, outstanding, available.
     * Dipanggil saat modal CreditLimit dibuka.
     */
    public function index(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $snapshot = $this->service->snapshot($customer);

        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $existingLimits = CustomerSupplierCreditLimit::query()
            ->where('customer_id', $customer->id)
            ->pluck('credit_limit', 'supplier_id');

        $rows = $suppliers->map(function (Supplier $s) use ($existingLimits, $snapshot): array {
            $info = $snapshot[$s->id] ?? null;

            return [
                'supplier_id' => $s->id,
                'supplier_code' => $s->code,
                'supplier_name' => $s->name,
                'credit_limit' => isset($existingLimits[$s->id]) ? (float) $existingLimits[$s->id] : null,
                'outstanding' => (float) ($info['outstanding'] ?? 0),
                'available' => (float) ($info['available'] ?? 0),
            ];
        });

        return response()->json([
            'rows' => $rows,
        ]);
    }

    /**
     * Sync semua limit untuk customer ini. Input: rows => [{supplier_id, credit_limit}, ...]
     * Row dengan credit_limit null/0/empty → row di-delete.
     */
    public function sync(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'rows.*.credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $customer, $request): void {
            foreach ($data['rows'] as $row) {
                $supplierId = (int) $row['supplier_id'];
                $limit = $row['credit_limit'];

                if ($limit === null || $limit === '' || (float) $limit <= 0) {
                    CustomerSupplierCreditLimit::query()
                        ->where('customer_id', $customer->id)
                        ->where('supplier_id', $supplierId)
                        ->delete();

                    continue;
                }

                CustomerSupplierCreditLimit::updateOrCreate(
                    ['customer_id' => $customer->id, 'supplier_id' => $supplierId],
                    [
                        'credit_limit' => $limit,
                        'created_by' => $request->user()?->id,
                    ],
                );
            }
        });

        return back()->with('flash.success', 'Credit limit per supplier diperbarui.');
    }
}
