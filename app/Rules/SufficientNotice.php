<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\LeaveType;
use Carbon\Carbon;

class SufficientNotice implements Rule
{
    protected $employeeId;
    protected $leaveTypeId;
    protected $startDate;

    public function __construct(int $employeeId, int $leaveTypeId, $startDate)
    {
        $this->employeeId = $employeeId;
        $this->leaveTypeId = $leaveTypeId;
        $this->startDate = $startDate;
    }

    public function passes($attribute, $value)
    {
        $leaveType = LeaveType::find($this->leaveTypeId);
        if (! $leaveType) {
            return false;
        }

        $minNotice = (int) $leaveType->min_days_notice;
        $start = Carbon::parse($this->startDate);
        $diffDays = $start->diffInDays(now());

        return $diffDays >= $minNotice;
    }

    public function fails($attribute, $value)
    {
        return ! $this->passes($attribute, $value);
    }
}