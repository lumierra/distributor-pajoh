<?php

namespace App\Http\Requests\User;

use App\Models\Role;
use App\Models\User;
use App\Rules\UniqueUsername;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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

        return [
            'name' => ['required', 'string', 'max:128'],
            'username' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_.]+$/', new UniqueUsername($target->id)],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var User $target */
            $target = $this->route('user');
            $newRoleId = (int) $this->input('role_id');
            $newRole = Role::find($newRoleId);
            $actor = $this->user();

            // Tidak boleh demote diri sendiri dari superadmin.
            if ($target->id === $actor?->id
                && $target->role?->code === Role::CODE_SUPERADMIN
                && $newRole?->code !== Role::CODE_SUPERADMIN
            ) {
                $validator->errors()->add('role_id', 'Anda tidak bisa demote diri sendiri dari superadmin.');
            }

            // Hanya superadmin yang boleh menetapkan role superadmin.
            if ($newRole?->code === Role::CODE_SUPERADMIN && ! $actor?->isSuperadmin()) {
                $validator->errors()->add('role_id', 'Hanya superadmin yang boleh menetapkan role superadmin.');
            }

            // Tidak boleh menonaktifkan superadmin terakhir (kalau ada user superadmin tunggal yang aktif).
            if ($this->boolean('is_active') === false
                && $target->role?->code === Role::CODE_SUPERADMIN
            ) {
                $remaining = User::ofRole(Role::CODE_SUPERADMIN)
                    ->active()
                    ->where('id', '!=', $target->id)
                    ->count();
                if ($remaining === 0) {
                    $validator->errors()->add('is_active', 'Minimum 1 superadmin aktif diperlukan.');
                }
            }
        });
    }
}
