<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * Form edit mengirim seluruh struktur (supplier, sku, satuan, paket harga)
     * sama seperti create. units & packages nullable supaya update kolom-scalar
     * sederhana (mis. dari flow lain) tetap bisa tanpa mengirim struktur penuh.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],

            'units' => ['sometimes', 'array', 'min:1'],
            'units.*.unit_id' => ['required_with:units', 'integer', 'exists:units,id'],
            'units.*.qty_to_base' => ['required_with:units', 'integer', 'min:1'],
            // Barcode unik, tapi abaikan yang sudah milik satuan produk ini
            // (karena satuan lama dihapus lalu dibuat ulang saat replace).
            'units.*.barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('product_units', 'barcode')->where(
                    fn ($q) => $productId ? $q->where('product_id', '!=', $productId) : $q
                ),
            ],

            'packages' => ['sometimes', 'array', 'min:1'],
            'packages.*.name' => ['required_with:packages', 'string', 'max:128'],
            'packages.*.items' => ['required_with:packages', 'array', 'min:1'],
            'packages.*.items.*.unit_index' => ['required_with:packages', 'integer', 'min:0'],
            'packages.*.items.*.cost_price' => ['required_with:packages', 'numeric', 'min:0'],
            'packages.*.items.*.sell_price' => ['required_with:packages', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->has('units')) {
                return;
            }

            $units = collect($this->input('units', []));
            $unitCount = $units->count();

            $baseCount = $units->filter(fn ($u) => (int) ($u['qty_to_base'] ?? 0) === 1)->count();
            if ($baseCount === 0) {
                $validator->errors()->add('units', 'Wajib ada 1 satuan dgn qty=1 (base unit).');
            } elseif ($baseCount > 1) {
                $validator->errors()->add('units', 'Hanya boleh ada 1 satuan dgn qty=1 (base unit).');
            }

            $duplicateUnitIds = $units->pluck('unit_id')->filter()->countBy()->filter(fn ($c) => $c > 1);
            if ($duplicateUnitIds->isNotEmpty()) {
                $validator->errors()->add('units', 'Tiap satuan master hanya boleh dipakai sekali per produk.');
            }

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
