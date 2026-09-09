<?php

namespace App\Rules;

use App\Models\Leave;
use Illuminate\Contracts\Validation\Rule;

class NotLeaveEmployee implements Rule
{
    public function __construct(private ?Leave $leave)
    {
    }

    public function passes($attribute, $value): bool
    {
        if (! $this->leave) {
            return true;
        }

        return (int) $value !== (int) $this->leave->employee_id;
    }

    public function message(): string
    {
        return 'The replacement employee must be different from the employee on leave.';
    }
}
