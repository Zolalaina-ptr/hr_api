<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->employee->id;

        return [
            'registration_number' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('employees', 'registration_number')->ignore($id)],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'birth_place' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'marital_status' => ['sometimes', 'nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'children_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'hiring_date' => ['sometimes', 'nullable', 'date'],
            'contract_type' => ['sometimes', 'nullable', Rule::in(['cdi', 'cdd', 'stage', 'alternance'])],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive', 'on_leave', 'terminated'])],
            'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
            'position_id' => ['sometimes', 'nullable', 'exists:positions,id'],
            'hierarchy_level' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'base_salary' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'hourly_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'iban' => ['sometimes', 'nullable', 'string', 'max:34'],
            'bic' => ['sometimes', 'nullable', 'string', 'max:11'],
            'social_security_number' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('employees', 'social_security_number')->ignore($id)],
            'photo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'manager_id' => ['sometimes', 'nullable', 'exists:employees,id'],
            'emergency_contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
