<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'level' => [
                'required',
                Rule::in(ProductUnit::LEVELS),
                Rule::unique('product_units', 'level')
                    ->where('product_id', $product->id)
                    ->whereNull('deleted_at'),
            ],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:32'],
            'qty_to_base' => ['required', 'integer', 'min:1'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:product_units,barcode'],
        ];
    }
}
