<?php

namespace App\Http\Requests\SupplierReturn;

use Illuminate\Foundation\Http\FormRequest;

class SettleSupplierReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settled_amount' => 'required|numeric|min:0',
            'settlement_notes' => 'nullable|string|max:500',
        ];
    }
}
