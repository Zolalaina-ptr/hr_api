<?php

namespace App\Http\Requests\Api;

use App\Rules\NoLeaveOverlap;
use App\Rules\SufficientNotice;
use App\Rules\ValidLeaveBalance;
use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by the route permission guards
        return true;
    }

    public function prepareForValidation(): void
    {
        // Self-service by default: the acting user's employee record.
        // HR/admins may override by passing an explicit employee_id.
        if (! $this->filled('employee_id')) {
            $this->merge(['employee_id' => $this->user()->employee?->id]);
        }
    }

    public function rules(): array
    {
        $employeeId = (int) $this->input('employee_id');
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');
        $leaveTypeId = (int) $this->input('leave_type_id');

        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_date' => [
                'required',
                'date',
                'after:today',
                new NoLeaveOverlap($employeeId, $startDate, $endDate),
                new ValidLeaveBalance($employeeId, $leaveTypeId, $startDate, $endDate),
                new SufficientNotice($employeeId, $leaveTypeId, $startDate),
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                new NoLeaveOverlap($employeeId, $startDate, $endDate),
            ],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'An employee is required for this leave request.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'start_date.required' => 'The start date is required.',
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.after' => 'The start date must be after today.',
            'start_date.NoLeaveOverlap' => 'These dates overlap with an existing pending or approved leave.',
            'start_date.ValidLeaveBalance' => 'Insufficient leave balance for the requested period.',
            'start_date.SufficientNotice' => 'This leave type requires more advance notice.',
            'end_date.required' => 'The end date is required.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'leave_type_id.required' => 'The leave type is required.',
            'leave_type_id.exists' => 'The selected leave type does not exist.',
            'reason.required' => 'The reason is required.',
            'reason.string' => 'The reason must be a string.',
            'reason.max' => 'The reason cannot exceed 1000 characters.',
        ];
    }
}
