<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\SupplierProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Create PO superadmin only (PurchaseOrderPolicy::create = false; before() lolos superadmin).
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'po_date' => ['required', 'date'],
            'eta_date' => ['nullable', 'date', 'after_or_equal:po_date'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            'header_discount_type' => ['nullable', Rule::in(PurchaseOrder::DISCOUNT_TYPES)],
            'header_discount_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'items.*.qty_ordered' => ['required', 'integer', 'min:1'],
            'items.*.bonus_qty' => ['nullable', 'integer', 'min:0'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_z1_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_z2_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $supplierId = (int) $this->input('supplier_id');
            $items = $this->input('items', []);

            // Verify each product is in supplier_products pivot.
            foreach ($items as $idx => $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $exists = SupplierProduct::query()
                    ->where('supplier_id', $supplierId)
                    ->where('product_id', $productId)
                    ->where('is_active', true)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add(
                        "items.{$idx}.product_id",
                        'Produk tidak tertaut ke supplier ini.',
                    );
                }
            }
        });
    }
}
