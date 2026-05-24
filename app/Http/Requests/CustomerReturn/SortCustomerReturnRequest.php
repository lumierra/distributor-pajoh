<?php

namespace App\Http\Requests\CustomerReturn;

use Illuminate\Foundation\Http\FormRequest;

class SortCustomerReturnRequest extends FormRequest
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
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:customer_return_items,id',
            'items.*.qty_good' => 'required|integer|min:0',
            'items.*.qty_bs' => 'required|integer|min:0',
        ];
    }
}
