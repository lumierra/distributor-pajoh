<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerPhoto::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(CustomerPhoto::TYPES)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:3072'],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Customer $customer */
            $customer = $this->route('customer');

            if ($customer->photos()->count() >= 10) {
                $validator->errors()->add('file', 'Limit foto tercapai (max 10 per customer).');
            }
        });
    }
}
