<?php

namespace App\Http\Requests\SupplierReturn;

use Illuminate\Foundation\Http\FormRequest;

class CancelSupplierReturnRequest extends FormRequest
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
            'cancel_reason' => 'required|string|max:500',
        ];
    }
}
