<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && ($this->user()?->can('update', $customer) ?? false);
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

            'assigned_sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:32'],
        ];
    }

    /**
     * Field-level guard — kalau user kirim field restricted padahal tidak
     * berhak, throw 403 (rule §7).
     */
    protected function prepareForValidation(): void
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');
        $user = $this->user();

        if ($user === null) {
            return;
        }

        $original = [
            'credit_limit' => (float) $customer->credit_limit,
            'price_tier_id' => (int) $customer->price_tier_id,
            'assigned_sales_id' => $customer->assigned_sales_id,
        ];

        $checks = [
            'credit_limit' => 'updateCreditLimit',
            'price_tier_id' => 'updatePriceTier',
            'assigned_sales_id' => 'updateAssignedSales',
        ];

        foreach ($checks as $field => $ability) {
            if (! $this->has($field)) {
                continue;
            }

            $incoming = $this->input($field);
            $same = match ($field) {
                'credit_limit' => (float) $incoming === $original[$field],
                'price_tier_id' => (int) $incoming === $original[$field],
                default => $incoming == $original[$field],
            };

            if (! $same && ! $user->can($ability, $customer)) {
                throw new AuthorizationException("Tidak berhak mengubah {$field}.");
            }
        }
    }
}
