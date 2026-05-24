<?php

namespace App\Http\Requests\Payment;

use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BounceGiroRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Payment|null $payment */
        $payment = $this->route('payment');

        return $payment instanceof Payment
            && ($this->user()?->can('bounceGiro', $payment) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bounce_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
