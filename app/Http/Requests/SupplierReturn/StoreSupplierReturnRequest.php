<?php

namespace App\Http\Requests\SupplierReturn;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierReturnRequest extends FormRequest
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
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'return_date' => 'required|date|before_or_equal:today',
            'reason_code' => 'required|string|in:damaged,expired,quality,wrong_item,other',
            'reason_notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'proof_photo' => 'nullable|file|image|max:5120',
            'items' => 'required|array|min:1',
            'items.*.source_type' => 'required|string|in:grn_damaged,customer_return_bs,stock,adjustment',
            'items.*.source_id' => 'nullable|integer',
            'items.*.grn_item_id' => 'nullable|integer|exists:grn_items,id',
            'items.*.customer_return_item_id' => 'nullable|integer|exists:customer_return_items,id',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_unit_id' => 'required|integer|exists:product_units,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.cost_price' => 'required|numeric|min:0',
            'items.*.batch_id' => 'nullable|integer|exists:product_batches,id',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
