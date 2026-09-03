<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LeaveApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update leaves');
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'The status is required.',
            'status.in' => 'The status must be either approved or rejected.',
            'comment.required' => 'A comment is required when rejecting a leave request.',
            'comment.string' => 'The comment must be a string.',
            'comment.max' => 'The comment cannot exceed 1000 characters.',
        ];
    }
}