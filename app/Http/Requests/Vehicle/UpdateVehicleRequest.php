<?php

namespace App\Http\Requests\Vehicle;

use App\Models\Vehicle;
use App\Services\Fleet\VehicleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Vehicle|null $vehicle */
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle
            && ($this->user()?->can('update', $vehicle) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('plate_number')) {
            $this->merge([
                'plate_number' => VehicleService::normalizePlate((string) $this->input('plate_number')),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return [
            'plate_number' => [
                'required',
                'string',
                'max:16',
                'regex:/^[A-Z]{1,2}\s\d{1,4}\s[A-Z]{1,3}$/',
                Rule::unique('vehicles', 'plate_number')->ignore($vehicle->id),
            ],
            'type' => ['required', Rule::in(Vehicle::TYPES)],
            'brand' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:64'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'color' => ['nullable', 'string', 'max:32'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0'],
            'capacity_kubik' => ['nullable', 'numeric', 'min:0'],
            'last_service_date' => ['nullable', 'date'],
            'next_service_date' => ['nullable', 'date', 'after_or_equal:last_service_date'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
