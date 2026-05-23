<?php

namespace App\Http\Requests\Supplier;

use App\Models\SupplierBankAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bank = $this->route('bank_account');
        if (! $bank instanceof SupplierBankAccount) {
            return false;
        }

        return $this->user()?->can('update', $bank->supplier) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:64'],
            'bank_code' => ['nullable', 'string', 'max:16'],
            'account_number' => ['required', 'string', 'max:32', 'regex:/^\d{5,30}$/'],
            'account_holder' => ['required', 'string', 'max:128'],
            'branch' => ['nullable', 'string', 'max:128'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
