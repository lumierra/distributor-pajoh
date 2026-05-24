<?php

namespace App\Http\Requests\Delivery;

use App\Models\DeliveryOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkPackedRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DeliveryOrder|null $do */
        $do = $this->route('delivery_order');

        return $do instanceof DeliveryOrder
            && ($this->user()?->can('markPacked', $do) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
        ];
    }
}
