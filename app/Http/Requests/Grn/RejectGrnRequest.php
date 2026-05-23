<?php

namespace App\Http\Requests\Grn;

use App\Models\GoodsReceipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectGrnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GoodsReceipt|null $grn */
        $grn = $this->route('goods_receipt');

        return $grn instanceof GoodsReceipt
            && ($this->user()?->can('reject', $grn) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
