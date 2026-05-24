<?php

namespace App\Http\Requests\Payment;

use App\Models\InvoiceExtensionLog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var InvoiceExtensionLog|null $log */
        $log = $this->route('invoice_extension_log');

        return $log instanceof InvoiceExtensionLog
            && ($this->user()?->can('review', $log) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
