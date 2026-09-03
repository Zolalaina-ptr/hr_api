<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update positions');
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255|unique:positions,title,' . $this->position->id,
            'code' => 'required|string|max:50|unique:positions,code,' . $this->position->id,
            'description' => 'nullable|string|max:1000',
            'level' => 'required|string|in:junior,mid,senior,lead,manager,director',
            'department_id' => 'required|exists:departments,id',
            'min_salary' => 'required|numeric|min:0',
            'max_salary' => 'required|numeric|min:0|gte:min_salary',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The position title is required.',
            'title.unique' => 'A position with this title already exists.',
            'code.required' => 'The position code is required.',
            'code.unique' => 'A position with this code already exists.',
            'level.required' => 'The position level is required.',
            'level.in' => 'The position level must be one of: junior, mid, senior, lead, manager, director.',
            'department_id.required' => 'The department is required.',
            'department_id.exists' => 'The selected department does not exist.',
            'min_salary.required' => 'The minimum salary is required.',
            'max_salary.required' => 'The maximum salary is required.',
            'max_salary.gte' => 'The maximum salary must be greater than or equal to the minimum salary.',
        ];
    }
}
