<?php

namespace App\Http\Requests\SupplierReturn;

use Illuminate\Foundation\Http\FormRequest;

class MarkSentSupplierReturnRequest extends FormRequest
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
            'sent_date' => 'nullable|date|before_or_equal:today',
        ];
    }
}
