<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Unique;

class PayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Prevent duplicate payroll for the same employee and period.
        // Backed by the DB unique constraint, but returns a clean 422.
        $uniquePeriod = new Unique(
            'payrolls',
            'period_month',
            null,
            null,
            'employee_id',
            $this->input('employee_id')
        );
        $uniquePeriod = $uniquePeriod->where('period_year', $this->input('period_year'));

        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'period_month' => ['required', 'integer', 'between:1,12', $uniquePeriod],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'overtime_pay' => ['nullable', 'numeric', 'min:0'],
            'bonuses' => ['nullable', 'numeric', 'min:0'],
            'benefits_in_kind' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_month.unique' => 'A payroll already exists for this employee and period.',
        ];
    }
}
