<?php

namespace App\Http\Requests\SalesOrder;

use App\Models\SalesOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelSoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SalesOrder|null $so */
        $so = $this->route('sales_order');

        return $so instanceof SalesOrder
            && ($this->user()?->can('cancel', $so) ?? false);
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
