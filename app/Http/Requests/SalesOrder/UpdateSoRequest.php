<?php

namespace App\Http\Requests\SalesOrder;

use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SoItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SalesOrder|null $so */
        $so = $this->route('sales_order');

        return $so instanceof SalesOrder
            && ($this->user()?->can('update', $so) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'so_date' => ['required', 'date'],
            'eta_date' => ['nullable', 'date', 'after_or_equal:so_date'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            'header_discount_type' => ['nullable', Rule::in(SalesOrder::DISCOUNT_TYPES)],
            'header_discount_value' => ['nullable', 'numeric', 'min:0'],
            'cashback' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.discount_type' => ['nullable', Rule::in(SoItem::DISCOUNT_TYPES)],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_bonus' => ['sometimes', 'boolean'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('items', []) as $idx => $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                $unitId = (int) ($row['product_unit_id'] ?? 0);
                if ($productId <= 0 || $unitId <= 0) {
                    continue;
                }
                $unitMatch = ProductUnit::query()
                    ->where('id', $unitId)
                    ->where('product_id', $productId)
                    ->exists();
                if (! $unitMatch) {
                    $validator->errors()->add("items.{$idx}.product_unit_id", 'Unit tidak sesuai dengan produk.');
                }
            }
        });
    }
}
