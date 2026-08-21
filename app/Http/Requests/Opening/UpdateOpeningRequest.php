<?php

namespace App\Http\Requests\Opening;

use App\Models\StockOpening;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockOpening|null $opening */
        $opening = $this->route('opening');

        return $opening instanceof StockOpening
            && ($this->user()?->can('update', $opening) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opening_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'items.*.supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'items.*.batch_code' => ['nullable', 'string', 'max:64'],
            'items.*.expired_date' => ['nullable', 'date'],
            'items.*.qty' => ['nullable', 'integer', 'min:0'],
            'items.*.qty_bonus' => ['nullable', 'integer', 'min:0'],
            'items.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
