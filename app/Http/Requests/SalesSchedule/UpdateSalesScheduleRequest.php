<?php

namespace App\Http\Requests\SalesSchedule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesScheduleRequest extends FormRequest
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
            'sales_id' => 'sometimes|integer|exists:users,id',
            'customer_id' => 'sometimes|integer|exists:customers,id',
            'pattern' => 'sometimes|string|in:recurring,one_time',
            'day_of_week' => 'nullable|integer|between:1,7',
            'visit_date' => 'nullable|date',
            'visit_time' => 'nullable|date_format:H:i',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ];
    }
}
