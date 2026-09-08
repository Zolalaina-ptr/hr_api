<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LeaveApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by the route permission guards
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.string' => 'The comment must be a string.',
            'comment.max' => 'The comment cannot exceed 1000 characters.',
        ];
    }
}
