<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Rules\PasswordPolicy;
use App\Rules\UniqueUsername;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $username = $this->input('username');

        return [
            'name' => ['required', 'string', 'max:128'],
            'username' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_.]+$/', new UniqueUsername($target->id)],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],

            // Password (opsional → kalau kosong, tidak diubah)
            'password' => ['nullable', 'string', new PasswordPolicy(is_string($username) ? $username : null)],
            'password_confirmation' => ['nullable', 'same:password'],

            // Device (opsional → kalau UUID diisi, akan create/update device aktif)
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
