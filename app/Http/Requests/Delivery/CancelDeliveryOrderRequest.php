<?php

namespace App\Http\Requests\Delivery;

use App\Models\DeliveryOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelDeliveryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DeliveryOrder|null $do */
        $do = $this->route('delivery_order');

        return $do instanceof DeliveryOrder
            && ($this->user()?->can('cancel', $do) ?? false);
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
