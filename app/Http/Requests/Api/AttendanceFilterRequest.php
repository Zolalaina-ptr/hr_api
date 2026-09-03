<?php

namespace App\Http\Requests\Api;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view attendance');
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|exists:employees,id',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'nullable|string|in:'.implode(',', Attendance::STATUSES),
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'status.in' => 'Invalid attendance status.',
        ];
    }

    public function filters(): array
    {
        return array_filter([
            'employee_id' => $this->input('employee_id'),
            'department_id' => $this->input('department_id'),
            'status' => $this->input('status'),
            'date' => $this->input('date'),
            'start_date' => $this->input('start_date'),
            'end_date' => $this->input('end_date'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function perPage(): int
    {
        return (int) ($this->input('per_page', 15));
    }
}