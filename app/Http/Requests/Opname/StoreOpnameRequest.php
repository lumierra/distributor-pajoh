<?php

namespace App\Http\Requests\Opname;

use App\Models\StockOpname;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockOpname::class) ?? false;
    }

    /**
     * Item di-auto-generate dari batch berstok, jadi store hanya header.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opname_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
