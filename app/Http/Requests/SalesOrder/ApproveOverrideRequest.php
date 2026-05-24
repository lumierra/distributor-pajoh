<?php

namespace App\Http\Requests\SalesOrder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya superadmin yang lolos lewat before() di policy.
        return $this->user()?->isSuperadmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
