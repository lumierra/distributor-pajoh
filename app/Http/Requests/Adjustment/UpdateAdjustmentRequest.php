<?php

namespace App\Http\Requests\Adjustment;

use App\Models\ProductBatch;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockAdjustment|null $adjustment */
        $adjustment = $this->route('adjustment');

        return $adjustment instanceof StockAdjustment
            && ($this->user()?->can('update', $adjustment) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'adjustment_date' => ['required', 'date', 'before_or_equal:today'],
            'reason_category' => ['required', Rule::in(StockAdjustment::REASON_CATEGORIES)],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'items.*.batch_id' => ['required', 'integer', 'exists:product_batches,id'],
            'items.*.direction' => ['required', Rule::in(StockAdjustmentItem::DIRECTIONS)],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('items', []) as $idx => $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                $batchId = (int) ($row['batch_id'] ?? 0);
                if ($productId <= 0 || $batchId <= 0) {
                    continue;
                }
                $ok = ProductBatch::query()->where('id', $batchId)->where('product_id', $productId)->exists();
                if (! $ok) {
                    $validator->errors()->add("items.{$idx}.batch_id", 'Batch bukan milik produk ini.');
                }
            }
        });
    }
}
