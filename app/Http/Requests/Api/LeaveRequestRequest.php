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
        return $this->user()->can('create leaves');
    }

    public function rules(): array
    {
        $employeeId = $this->user()->id;
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');
        $leaveTypeId = $this->input('leave_type_id');

        return [
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
            'leave_type_id' => 'required|exists:leave_types,id',
            'reason' => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'The start date is required.',
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.after' => 'The start date must be after today.',
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