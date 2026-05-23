<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'owner_name' => ['nullable', 'string', 'max:128'],
            'customer_type_id' => ['nullable', 'integer', 'exists:customer_types,id'],
            'price_tier_id' => ['required', 'integer', 'exists:price_tiers,id'],
            'npwp' => ['nullable', 'string', 'max:32', 'regex:/^[\d.\-]+$/'],

            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'email' => ['nullable', 'email', 'max:128'],

            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:64'],
            'province' => ['nullable', 'string', 'max:64'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'area' => ['nullable', 'string', 'max:128'],

            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],

            'assigned_sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:32'],
        ];
    }
}
