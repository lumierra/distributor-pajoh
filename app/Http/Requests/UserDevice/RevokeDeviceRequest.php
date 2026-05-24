<?php

namespace App\Http\Requests\UserDevice;

use Illuminate\Foundation\Http\FormRequest;

class RevokeDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revoke_reason' => 'required|string|max:500',
        ];
    }
}
