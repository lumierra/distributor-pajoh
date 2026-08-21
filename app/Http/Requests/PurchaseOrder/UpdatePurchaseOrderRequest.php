<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PurchaseOrder|null $po */
        $po = $this->route('purchase_order');

        return $po instanceof PurchaseOrder
            && $po->canBeEdited()
            && ($this->user()?->can('update', $po) ?? false);
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
            foreach ($this->input('items', []) as $idx => $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $exists = Product::query()
                    ->where('id', $productId)
                    ->where('supplier_id', $supplierId)
                    ->exists();
                if (! $exists) {
                    $validator->errors()->add(
                        "items.{$idx}.product_id",
                        'Produk bukan milik supplier ini.',
                    );
                }
            }
        });
    }
}
