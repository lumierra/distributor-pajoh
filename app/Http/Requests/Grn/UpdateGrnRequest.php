<?php

namespace App\Http\Requests\Grn;

use App\Models\GoodsReceipt;
use App\Models\GrnItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GoodsReceipt|null $grn */
        $grn = $this->route('goods_receipt');

        return $grn instanceof GoodsReceipt
            && ($this->user()?->can('update', $grn) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'supplier_delivery_no' => ['nullable', 'string', 'max:64'],
            'supplier_vehicle_info' => ['nullable', 'string', 'max:128'],
            'supplier_driver_name' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string'],
            'discrepancy_notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.po_item_id' => ['required', 'integer', 'exists:po_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'items.*.batch_code' => ['required', 'string', 'max:64'],
            'items.*.production_date' => ['nullable', 'date', 'before_or_equal:received_date'],
            'items.*.expired_date' => ['nullable', 'date'],
            'items.*.qty_reguler' => ['nullable', 'integer', 'min:0'],
            'items.*.qty_bonus' => ['nullable', 'integer', 'min:0'],
            'items.*.qty_damaged' => ['nullable', 'integer', 'min:0'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0'],
            'items.*.cost_override_reason' => ['nullable', 'string', 'max:255'],
            'items.*.condition' => ['nullable', Rule::in(GrnItem::CONDITIONS)],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('items', []) as $idx => $row) {
                $reg = (int) ($row['qty_reguler'] ?? 0);
                $bon = (int) ($row['qty_bonus'] ?? 0);
                $dmg = (int) ($row['qty_damaged'] ?? 0);

                if (($reg + $bon + $dmg) <= 0) {
                    $validator->errors()->add(
                        "items.{$idx}.qty_reguler",
                        'Minimal 1 qty (reguler/bonus/damaged) harus > 0.',
                    );
                }
            }
        });
    }
}
