<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_number' => ['required', 'string', 'max:50', 'unique:employees,registration_number'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:employees,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'nationality' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'hiring_date' => ['required', 'date'],
            'contract_type' => ['nullable', Rule::in(['cdi', 'cdd', 'stage', 'alternance'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'on_leave', 'terminated'])],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'hierarchy_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'base_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'social_security_number' => ['nullable', 'string', 'max:50', 'unique:employees,social_security_number'],
            'photo_url' => ['nullable', 'url', 'max:2048'],
            'user_id' => ['nullable', 'exists:users,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'emergency_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_number.unique' => 'This registration number is already taken.',
            'email.unique' => 'An employee with this email already exists.',
            'social_security_number.unique' => 'This social security number is already registered.',
            'hiring_date.required' => 'The hiring date is required.',
            'department_id.exists' => 'The selected department does not exist.',
            'position_id.exists' => 'The selected position does not exist.',
            'manager_id.exists' => 'The selected manager does not exist.',
        ];
    }
}
