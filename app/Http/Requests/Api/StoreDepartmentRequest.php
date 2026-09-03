<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create departments');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:departments',
            'code' => 'required|string|max:50|unique:departments',
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:employees,id',
            'budget' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The department name is required.',
            'name.unique' => 'A department with this name already exists.',
            'code.required' => 'The department code is required.',
            'code.unique' => 'A department with this code already exists.',
            'parent_id.exists' => 'The selected parent department does not exist.',
            'manager_id.exists' => 'The selected manager does not exist.',
            'budget.numeric' => 'The budget must be a valid number.',
        ];
    }
}
