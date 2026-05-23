<?php

namespace App\Http\Requests\Vehicle;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Vehicle|null $vehicle */
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle
            && ($this->user()?->can('setMaintenance', $vehicle) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
