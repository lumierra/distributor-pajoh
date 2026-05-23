<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * Payload shape (wizard):
     *  - product: name, category_id, brand, description, notes, is_active
     *  - units: [{level, name, qty_to_base, barcode?}, ...] — minimal 1 KCL
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'brand' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],

            'units' => ['required', 'array', 'min:1', 'max:3'],
            'units.*.level' => ['required', Rule::in(ProductUnit::LEVELS)],
            'units.*.name' => ['required', 'string', 'max:32'],
            'units.*.qty_to_base' => ['required', 'integer', 'min:1'],
            'units.*.barcode' => ['nullable', 'string', 'max:64', 'unique:product_units,barcode'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $units = collect($this->input('units', []));
            // KCL wajib
            $kcl = $units->firstWhere('level', ProductUnit::LEVEL_KCL);
            if (! $kcl) {
                $validator->errors()->add('units', 'Unit KCL (base unit) wajib disertakan.');

                return;
            }
            if ((int) $kcl['qty_to_base'] !== 1) {
                $validator->errors()->add('units', 'KCL qty_to_base harus = 1 (base unit).');
            }
            // Level harus unik
            $duplicateLevels = $units->groupBy('level')->filter(fn ($g) => $g->count() > 1)->keys();
            if ($duplicateLevels->isNotEmpty()) {
                $validator->errors()->add('units', 'Setiap level (BSR/TGH/KCL) hanya boleh 1 unit.');
            }
        });
    }
}
