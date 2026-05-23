<?php

namespace App\Http\Requests\Driver;

use App\Models\Driver;
use App\Models\DriverDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Driver|null $driver */
        $driver = $this->route('driver');

        return $driver instanceof Driver
            && ($this->user()?->can('uploadDocument', $driver) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(DriverDocument::TYPES)],
            'title' => ['required', 'string', 'max:128'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'issued_date' => ['nullable', 'date'],
            'expires_date' => ['nullable', 'date', 'after_or_equal:issued_date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
