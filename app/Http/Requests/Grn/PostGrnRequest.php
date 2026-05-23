<?php

namespace App\Http\Requests\Grn;

use App\Models\GoodsReceipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PostGrnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GoodsReceipt|null $grn */
        $grn = $this->route('goods_receipt');

        return $grn instanceof GoodsReceipt
            && ($this->user()?->can('post', $grn) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'approve_over_receive' => ['sometimes', 'boolean'],
        ];
    }
}
