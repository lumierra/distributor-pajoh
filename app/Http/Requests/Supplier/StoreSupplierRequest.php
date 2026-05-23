<?php

namespace App\Http\Requests\Supplier;

use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Supplier::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'legal_form' => ['nullable', Rule::in(Supplier::LEGAL_FORMS)],
            'npwp' => ['nullable', 'string', 'max:32', 'regex:/^[\d.\-]+$/'],
            'nib' => ['nullable', 'string', 'max:32'],
            'supplier_category_id' => ['nullable', 'integer', 'exists:supplier_categories,id'],

            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'email' => ['nullable', 'email', 'max:128'],
            'fax' => ['nullable', 'string', 'max:32'],

            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:64'],
            'province' => ['nullable', 'string', 'max:64'],
            'postal_code' => ['nullable', 'string', 'max:16'],

            'contact_person_name' => ['nullable', 'string', 'max:128'],
            'contact_person_role' => ['nullable', 'string', 'max:64'],
            'contact_person_phone' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'contact_person_email' => ['nullable', 'email', 'max:128'],

            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'default_lead_time_days' => ['nullable', 'integer', 'min:0', 'max:180'],

            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:32'],
        ];
    }
}
