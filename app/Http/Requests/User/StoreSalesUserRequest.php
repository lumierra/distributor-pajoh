<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Rules\PasswordPolicy;
use App\Rules\UniqueUsername;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $username = $this->input('username');

        return [
            // Identitas dasar
            'name' => ['required', 'string', 'max:128'],
            'username' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_.]+$/', new UniqueUsername],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:32'],

            // Password (opsional, default 12345678 kalau kosong)
            'password' => ['nullable', 'string', new PasswordPolicy(is_string($username) ? $username : null)],
            'password_confirmation' => ['nullable', 'same:password'],

            // Status
            'is_active' => ['sometimes', 'boolean'],

            // Device pre-register (opsional — kalau admin sudah pegang HP-nya)
            'device_uuid' => ['nullable', 'string', 'max:128', 'required_with:device_name,mac_address'],
            'device_name' => ['nullable', 'string', 'max:128'],
            'mac_address' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function prepareForValidation(): void
    {
        $username = $this->input('username');
        if (is_string($username)) {
            $this->merge(['username' => strtolower(trim($username))]);
        }
    }
}
