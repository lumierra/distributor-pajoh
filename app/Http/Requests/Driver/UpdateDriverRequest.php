<?php

namespace App\Http\Requests\Driver;

use App\Models\Driver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Driver|null $driver */
        $driver = $this->route('driver');

        return $driver instanceof Driver
            && ($this->user()?->can('update', $driver) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'nik' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:64'],

            'license_no' => ['nullable', 'string', 'max:32'],
            'license_type' => ['nullable', Rule::in(Driver::LICENSE_TYPES)],
            'license_expired_date' => ['nullable', 'date'],

            'emergency_contact_name' => ['nullable', 'string', 'max:128'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\-\+\(\)]+$/'],

            'hire_date' => ['nullable', 'date'],
            'default_vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],

            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
