<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierProductRequest extends FormRequest
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
            'supplier_id' => [
                'required',
                'integer',
                'exists:suppliers,id',
                Rule::unique('supplier_products')
                    ->where('product_id', $product->id),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:64'],
            'default_cost_price' => ['nullable', 'numeric', 'min:0'],
            'moq' => ['nullable', 'integer', 'min:1'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
