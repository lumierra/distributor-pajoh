<?php

namespace App\Http\Requests\Payment;

use App\Models\InvoiceExtensionLog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequestExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InvoiceExtensionLog::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_due_date' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
