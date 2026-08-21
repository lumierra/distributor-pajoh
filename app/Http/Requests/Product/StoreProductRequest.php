<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * Payload shape:
     *  - product: supplier_id, sku, name, category_id, description, notes, is_active
     *  - units: [{unit_id, qty_to_base, barcode?}, ...] — minimal 1; tepat 1 dgn qty_to_base=1 (base unit)
     *  - packages: [{name, items:[{unit_index, cost_price, sell_price}]}, ...] — minimal 1 paket harga
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],

            'units' => ['required', 'array', 'min:1'],
            'units.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'units.*.qty_to_base' => ['required', 'integer', 'min:1'],
            'units.*.barcode' => ['nullable', 'string', 'max:64', 'unique:product_units,barcode'],

            'packages' => ['required', 'array', 'min:1'],
            'packages.*.name' => ['required', 'string', 'max:128'],
            'packages.*.items' => ['required', 'array', 'min:1'],
            'packages.*.items.*.unit_index' => ['required', 'integer', 'min:0'],
            'packages.*.items.*.cost_price' => ['required', 'numeric', 'min:0'],
            'packages.*.items.*.sell_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $units = collect($this->input('units', []));
            $unitCount = $units->count();

            // Tepat 1 base unit (qty_to_base=1)
            $baseCount = $units->filter(fn ($u) => (int) ($u['qty_to_base'] ?? 0) === 1)->count();
            if ($baseCount === 0) {
                $validator->errors()->add('units', 'Wajib ada 1 satuan dgn qty=1 (base unit).');
            } elseif ($baseCount > 1) {
                $validator->errors()->add('units', 'Hanya boleh ada 1 satuan dgn qty=1 (base unit).');
            }

            // unit_id tidak duplikat
            $duplicateUnitIds = $units
                ->pluck('unit_id')
                ->filter()
                ->countBy()
                ->filter(fn ($c) => $c > 1);
            if ($duplicateUnitIds->isNotEmpty()) {
                $validator->errors()->add('units', 'Tiap satuan master hanya boleh dipakai sekali per produk.');
            }

            // Tiap paket: unit_index valid & tidak duplikat dalam paket
            foreach ($this->input('packages', []) as $pi => $pkg) {
                $seen = [];
                foreach ($pkg['items'] ?? [] as $ii => $item) {
                    $idx = $item['unit_index'] ?? null;
                    if ($idx === null || $idx >= $unitCount) {
                        $validator->errors()->add("packages.{$pi}.items.{$ii}.unit_index", 'Satuan tidak valid.');
                    } elseif (in_array($idx, $seen, true)) {
                        $validator->errors()->add("packages.{$pi}.items.{$ii}.unit_index", 'Satuan duplikat dalam paket.');
                    } else {
                        $seen[] = $idx;
                    }
                }
            }
        });
    }
}
