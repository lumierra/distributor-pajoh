<?php

namespace App\Http\Requests\SalesSchedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesScheduleRequest extends FormRequest
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
            'sales_id' => 'required|integer|exists:users,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'pattern' => 'required|string|in:recurring,one_time',
            'day_of_week' => 'required_if:pattern,recurring|nullable|integer|between:1,7',
            'visit_date' => 'required_if:pattern,one_time|nullable|date',
            'visit_time' => 'nullable|date_format:H:i',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ];
    }
}
