<?php

namespace App\Http\Requests\Opening;

use App\Models\StockOpening;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockOpening|null $opening */
        $opening = $this->route('opening');

        return $opening instanceof StockOpening
            && ($this->user()?->can('cancel', $opening) ?? false);
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
