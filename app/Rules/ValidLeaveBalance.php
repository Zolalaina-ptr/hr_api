<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Carbon\Carbon;
use App\Models\LeaveBalance;

class ValidLeaveBalance implements Rule
{
    protected $employeeId;
    protected $leaveTypeId;
    protected $startDate;
    protected $endDate;

    public function __construct(int $employeeId, int $leaveTypeId, $startDate, $endDate)
    {
        $this->employeeId = $employeeId;
        $this->leaveTypeId = $leaveTypeId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function passes($attribute, $value)
    {
        return $this->hasSufficientBalance();
    }

    public function message(): string
    {
        return 'Insufficient leave balance for the requested period.';
    }

    public function fails($attribute, $value)
    {
        return !$this->hasSufficientBalance();
    }

    private function hasSufficientBalance(): bool
    {
        if (! $this->startDate || ! $this->endDate || $this->employeeId <= 0 || $this->leaveTypeId <= 0) {
            // Other rules report the missing values
            return true;
        }

        $duration = $this->calculateDays($this->startDate, $this->endDate);
        $balance = LeaveBalance::where('employee_id', $this->employeeId)
            ->where('leave_type_id', $this->leaveTypeId)
            ->where('year', date('Y'))
            ->first();

        // No balance configured for this leave type -> no limitation
        // (same semantics as LeaveService::checkAvailability())
        if (! $balance) {
            return true;
        }

        return $balance->remaining_days >= $duration;
    }

    private function calculateDays(string $startDate, string $endDate): float
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        return $start->diffInDays($end) + 1; // include both days
    }
}