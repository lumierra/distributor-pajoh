<?php

namespace App\Http\Requests\SalesVisit;

use Illuminate\Foundation\Http\FormRequest;

class RequestBypassRequest extends FormRequest
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
            'reason' => 'required|string|max:500',
            'requested_lat' => 'nullable|numeric|between:-90,90',
            'requested_lng' => 'nullable|numeric|between:-180,180',
            'distance_meter' => 'nullable|integer|min:0',
        ];
    }
}
