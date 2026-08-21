<?php

namespace App\Http\Requests\Opname;

use App\Models\StockOpname;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockOpname|null $opname */
        $opname = $this->route('opname');

        return $opname instanceof StockOpname
            && ($this->user()?->can('cancel', $opname) ?? false);
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
