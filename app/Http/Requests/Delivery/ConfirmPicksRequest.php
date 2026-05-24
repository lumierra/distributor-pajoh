<?php

namespace App\Http\Requests\Delivery;

use App\Models\DeliveryOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmPicksRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DeliveryOrder|null $do */
        $do = $this->route('delivery_order');

        return $do instanceof DeliveryOrder
            && ($this->user()?->can('confirmPick', $do) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'picks' => ['required', 'array', 'min:1'],
            'picks.*.item_id' => ['required', 'integer', 'exists:do_items,id'],
            'picks.*.qty_picked' => ['required', 'integer', 'min:0'],
        ];
    }
}
