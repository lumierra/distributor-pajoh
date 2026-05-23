<?php

namespace App\Http\Requests\User;

use App\Models\Role;
use App\Models\User;
use App\Rules\PasswordPolicy;
use App\Rules\UniqueUsername;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:128'],
            'username' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_.]+$/', new UniqueUsername],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => ['required', 'string', new PasswordPolicy(is_string($username) ? $username : null)],
            'password_confirmation' => ['required', 'same:password'],
            'is_active' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:64'],
            'nik' => ['nullable', 'string', 'max:32'],
            'emergency_contact_name' => ['nullable', 'string', 'max:128'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'hire_date' => ['nullable', 'date'],
            'default_area' => ['nullable', 'string', 'max:128'],
            'monthly_target' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function prepareForValidation(): void
    {
        $username = $this->input('username');
        if (is_string($username)) {
            $this->merge(['username' => strtolower(trim($username))]);
        }
    }

    /**
     * Additional rule: hanya superadmin yang boleh membuat user role superadmin.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $roleId = $this->input('role_id');
            if (! $roleId) {
                return;
            }
            $role = Role::find($roleId);
            if ($role?->code === Role::CODE_SUPERADMIN && ! $this->user()?->isSuperadmin()) {
                $validator->errors()->add('role_id', 'Hanya superadmin yang boleh membuat user superadmin.');
            }
        });
    }
}
