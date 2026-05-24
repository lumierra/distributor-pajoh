<?php

namespace App\Http\Requests\Delivery;

use App\Models\DeliveryOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkDeliveredRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DeliveryOrder|null $do */
        $do = $this->route('delivery_order');

        return $do instanceof DeliveryOrder
            && ($this->user()?->can('markDelivered', $do) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receiver_name' => ['required', 'string', 'max:128'],
            'receiver_notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'proof_photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'digital_signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:1024'],

            'item_quantities' => ['required', 'array', 'min:1'],
            'item_quantities.*.item_id' => ['required', 'integer', 'exists:do_items,id'],
            'item_quantities.*.qty_delivered' => ['required', 'integer', 'min:0'],
            'item_quantities.*.qty_returned' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
