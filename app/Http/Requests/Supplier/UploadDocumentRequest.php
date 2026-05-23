<?php

namespace App\Http\Requests\Supplier;

use App\Models\Supplier;
use App\Models\SupplierDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier && ($this->user()?->can('update', $supplier) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(SupplierDocument::TYPES)],
            'title' => ['required', 'string', 'max:128'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5 MB
            'issued_date' => ['nullable', 'date'],
            'expires_date' => ['nullable', 'date', 'after:issued_date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
