<?php

namespace App\Http\Requests\Adjustment;

use App\Models\StockAdjustment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockAdjustment|null $adjustment */
        $adjustment = $this->route('adjustment');

        return $adjustment instanceof StockAdjustment
            && ($this->user()?->can('cancel', $adjustment) ?? false);
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
