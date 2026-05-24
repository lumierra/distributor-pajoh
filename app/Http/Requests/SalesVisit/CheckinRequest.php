<?php

namespace App\Http\Requests\SalesVisit;

use Illuminate\Foundation\Http\FormRequest;

class CheckinRequest extends FormRequest
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
            'customer_id' => 'required|integer|exists:customers,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy_meter' => 'nullable|integer|min:0',
            'is_mock_location' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
            'photo' => 'nullable|file|image|max:5120',
        ];
    }
}
