<?php

namespace App\Http\Requests\Opname;

use App\Models\StockOpname;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockOpname|null $opname */
        $opname = $this->route('opname');

        return $opname instanceof StockOpname
            && ($this->user()?->can('update', $opname) ?? false);
    }

    /**
     * items: [{ id, counted_qty }] — hasil hitung fisik per item existing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opname_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
            'items' => ['present', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:stock_opname_items,id'],
            'items.*.counted_qty' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
