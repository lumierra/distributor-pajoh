<?php

namespace App\Http\Requests\Payment;

use App\Models\PaymentRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PaymentRequest::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(PaymentRequest::METHODS)],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:64'],
            'giro_due_date' => ['nullable', 'date', 'required_if:method,giro'],
            'paid_at' => ['required', 'date'],
            'proof_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
