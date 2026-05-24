<?php

namespace App\Http\Requests\Payment;

use App\Models\PaymentRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectPaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PaymentRequest|null $req */
        $req = $this->route('payment_request');

        return $req instanceof PaymentRequest
            && ($this->user()?->can('reject', $req) ?? false);
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
