<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create attendance');
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'schedule_id' => 'nullable|exists:attendance_schedules,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'ip_address' => 'nullable|ip',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'The employee is required.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'latitude.between' => 'The latitude must be between -90 and 90.',
            'longitude.between' => 'The longitude must be between -180 and 180.',
            'ip_address.ip' => 'The IP address must be a valid IP.',
        ];
    }

    public function ipAddress(): ?string
    {
        return $this->input('ip_address') ?? request()->ip();
    }
}