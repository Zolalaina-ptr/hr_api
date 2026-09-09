<?php

namespace App\Http\Requests\Api;

use App\Rules\NotLeaveEmployee;
use Illuminate\Foundation\Http\FormRequest;

class LeaveReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by the route permission guards
        return true;
    }

    public function prepareForValidation(): void
    {
        $leave = $this->route('leave');

        // The replacement period defaults to the leave period
        $this->merge([
            'start_date' => $this->filled('start_date') ? $this->input('start_date') : $leave?->start_date?->toDateString(),
            'end_date' => $this->filled('end_date') ? $this->input('end_date') : $leave?->end_date?->toDateString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'replacement_employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
                new NotLeaveEmployee($this->route('leave')),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'responsibilities' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'replacement_employee_id.required' => 'The replacement employee is required.',
            'replacement_employee_id.integer' => 'The replacement employee must be an integer.',
            'replacement_employee_id.exists' => 'The selected replacement employee does not exist.',
            'replacement_employee_id.NotLeaveEmployee' => 'The replacement employee must be different from the employee on leave.',
            'start_date.required' => 'The start date is required.',
            'start_date.date' => 'The start date must be a valid date.',
            'end_date.required' => 'The end date is required.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'responsibilities.string' => 'The responsibilities must be a string.',
            'responsibilities.max' => 'The responsibilities cannot exceed 2000 characters.',
        ];
    }
}
