<?php

namespace App\Http\Requests\CustomerReturn;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'return_date' => 'sometimes|date|before_or_equal:today',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'delivery_order_id' => 'nullable|integer|exists:delivery_orders,id',
            'brand_tag' => 'nullable|string|max:64',
            'reason_code' => 'nullable|string|in:expired,damaged,wrong_item,customer_request,quality_issue,other',
            'reason_notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|integer|exists:products,id',
            'items.*.product_unit_id' => 'required_with:items|integer|exists:product_units,id',
            'items.*.qty_total' => 'required_with:items|integer|min:1',
            'items.*.qty_good' => 'nullable|integer|min:0',
            'items.*.qty_bs' => 'nullable|integer|min:0',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.batch_id' => 'nullable|integer|exists:product_batches,id',
        ];
    }
}
