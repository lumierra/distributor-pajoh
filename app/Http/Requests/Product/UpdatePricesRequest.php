<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * Payload: prices: [{ product_unit_id, price_tier_id, price }, ...]
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prices' => ['required', 'array'],
            'prices.*.product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'prices.*.price_tier_id' => ['required', 'integer', 'exists:price_tiers,id'],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
