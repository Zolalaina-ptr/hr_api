<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Leave;

class NoLeaveOverlap implements Rule
{
    protected $employeeId;
    protected $startDate;
    protected $endDate;

    public function __construct(int $employeeId, $startDate, $endDate)
    {
        $this->employeeId = $employeeId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function passes($attribute, $value)
    {
        return ! $this->hasOverlappingLeave();
    }

    public function fails($attribute, $value)
    {
        return $this->hasOverlappingLeave();
    }

    private function hasOverlappingLeave(): bool
    {
        return Leave::where('employee_id', $this->employeeId)
            ->where(function ($q) {
                $q->where('start_date', '<=', $this->endDate)
                    ->where('end_date', '>=', $this->startDate);
            })
            ->exists();
    }
}