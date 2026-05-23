<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('resetPassword', $target) ?? false);
    }

    /**
     * Admin reset bypass full password policy (T02 §5.2): minimal 8 char saja,
     * lalu user dipaksa ganti via flow `force_password_change` saat login.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_password' => ['required', 'string', 'min:8', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
