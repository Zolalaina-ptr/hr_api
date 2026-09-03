<?php

namespace App\Http\Requests\Api;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update attendance');
    }

    public function rules(): array
    {
        return [
            'clock_in' => 'nullable|date',
            'clock_out' => 'nullable|date|after:clock_in',
            'work_hours' => 'nullable|numeric|min:0|max:24',
            'break_hours' => 'nullable|numeric|min:0|max:24',
            'overtime_hours' => 'nullable|numeric|min:0|max:24',
            'late_minutes' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:'.implode(',', Attendance::STATUSES),
            'absence_reason' => 'nullable|string|max:255|required_if:status,absent',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'clock_out.after' => 'The clock-out time must be after the clock-in time.',
            'status.in' => 'Invalid attendance status.',
            'absence_reason.required_if' => 'An absence reason is required when status is absent.',
        ];
    }
}