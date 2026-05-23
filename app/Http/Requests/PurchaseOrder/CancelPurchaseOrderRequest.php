<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PurchaseOrder|null $po */
        $po = $this->route('purchase_order');

        return $po instanceof PurchaseOrder
            && ($this->user()?->can('cancel', $po) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
