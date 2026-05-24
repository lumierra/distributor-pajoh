<?php

namespace App\Http\Requests\CustomerReturn;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerReturnRequest extends FormRequest
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
            'customer_id' => 'required|integer|exists:customers,id',
            'sales_id' => 'nullable|integer|exists:users,id',
            'return_date' => 'required|date|before_or_equal:today',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'delivery_order_id' => 'nullable|integer|exists:delivery_orders,id',
            'brand_tag' => 'nullable|string|max:64',
            'reason_code' => 'nullable|string|in:expired,damaged,wrong_item,customer_request,quality_issue,other',
            'reason_notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'proof_photo' => 'nullable|file|image|max:5120',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_unit_id' => 'required|integer|exists:product_units,id',
            'items.*.qty_total' => 'required|integer|min:1',
            'items.*.qty_good' => 'nullable|integer|min:0',
            'items.*.qty_bs' => 'nullable|integer|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.batch_id' => 'nullable|integer|exists:product_batches,id',
            'items.*.invoice_item_id' => 'nullable|integer|exists:invoice_items,id',
            'items.*.do_item_id' => 'nullable|integer|exists:do_items,id',
            'items.*.so_item_id' => 'nullable|integer|exists:so_items,id',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
