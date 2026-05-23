<?php

namespace App\Http\Requests\Vehicle;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Vehicle|null $vehicle */
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle
            && ($this->user()?->can('uploadDocument', $vehicle) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(VehicleDocument::TYPES)],
            'title' => ['required', 'string', 'max:128'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'issued_date' => ['nullable', 'date'],
            'expires_date' => ['nullable', 'date', 'after_or_equal:issued_date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
