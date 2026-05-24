<?php

namespace App\Http\Requests\SupplierReturn;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierReturnRequest extends FormRequest
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
            'reason_code' => 'sometimes|string|in:damaged,expired,quality,wrong_item,other',
            'reason_notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.source_type' => 'required_with:items|string|in:grn_damaged,customer_return_bs,stock,adjustment',
            'items.*.product_id' => 'required_with:items|integer|exists:products,id',
            'items.*.product_unit_id' => 'required_with:items|integer|exists:product_units,id',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.cost_price' => 'required_with:items|numeric|min:0',
        ];
    }
}
