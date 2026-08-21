<?php

namespace App\Http\Requests\Grn;

use App\Models\GoodsReceipt;
use App\Models\GrnItem;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GoodsReceipt::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Mode dari-PO: purchase_order_id diisi. Mode langsung (tanpa PO):
            // purchase_order_id null & supplier_id wajib (dicek di withValidator).
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'supplier_delivery_no' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'discrepancy_notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.po_item_id' => ['nullable', 'integer', 'exists:po_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'items.*.batch_code' => ['required', 'string', 'max:64'],
            'items.*.production_date' => ['nullable', 'date', 'before_or_equal:received_date'],
            'items.*.expired_date' => ['nullable', 'date'],
            'items.*.qty_reguler' => ['nullable', 'integer', 'min:0'],
            'items.*.qty_delivery_note' => ['nullable', 'integer', 'min:0'],
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
            $isDirect = empty($this->input('purchase_order_id'));
            $supplierId = (int) ($this->input('supplier_id') ?? 0);

            // Penerimaan langsung: supplier wajib.
            if ($isDirect && $supplierId <= 0) {
                $validator->errors()->add('supplier_id', 'Supplier wajib dipilih untuk penerimaan langsung tanpa PO.');
            }

            // Produk yang valid untuk supplier ini (mode langsung) — dicek sekali.
            $supplierProductIds = ($isDirect && $supplierId > 0)
                ? Product::query()->where('supplier_id', $supplierId)->pluck('id')->all()
                : [];

            foreach ($this->input('items', []) as $idx => $row) {
                $reg = (int) ($row['qty_reguler'] ?? 0);
                $bon = (int) ($row['qty_bonus'] ?? 0);
                $dmg = (int) ($row['qty_damaged'] ?? 0);

                if (($reg + $bon + $dmg) <= 0) {
                    $validator->errors()->add(
                        "items.{$idx}.qty_reguler",
                        'Minimal 1 qty (reguler/bonus/rusak) harus > 0.',
                    );
                }

                // Mode langsung: tiap produk wajib milik supplier terpilih.
                if ($isDirect && $supplierId > 0) {
                    $productId = (int) ($row['product_id'] ?? 0);
                    if ($productId > 0 && ! in_array($productId, $supplierProductIds, true)) {
                        $validator->errors()->add(
                            "items.{$idx}.product_id",
                            'Produk bukan milik supplier yang dipilih.',
                        );
                    }
                }

                // Mode langsung: qty surat jalan tidak boleh kurang dari qty
                // diterima (yang diterima maksimal sebanyak yang tertulis).
                if ($isDirect && isset($row['qty_delivery_note']) && $row['qty_delivery_note'] !== null) {
                    $sj = (int) $row['qty_delivery_note'];
                    if ($sj > 0 && $sj < $reg) {
                        $validator->errors()->add(
                            "items.{$idx}.qty_delivery_note",
                            'Qty surat jalan tidak boleh kurang dari qty diterima.',
                        );
                    }
                }
            }
        });
    }
}
