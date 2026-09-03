<?php

namespace App\Repositories;

use App\Models\LeaveBalance;
use Illuminate\Database\Eloquent\Collection;

class LeaveBalanceRepository
{
    public function findFor(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance
    {
        return LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();
    }

    public function getFor(int $employeeId, int $year): Collection
    {
        return LeaveBalance::with('leaveType')
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get();
    }

    public function getAllForYear(int $year): Collection
    {
        return LeaveBalance::with(['employee', 'leaveType'])
            ->where('year', $year)
            ->get();
    }

    public function create(array $data): LeaveBalance
    {
        return LeaveBalance::create($data);
    }

    public function update(LeaveBalance $balance, array $data): LeaveBalance
    {
        $balance->update($data);

        return $balance->fresh(['employee', 'leaveType']);
    }

    public function upsertFor(int $employeeId, int $leaveTypeId, int $year, array $data): LeaveBalance
    {
        $balance = $this->findFor($employeeId, $leaveTypeId, $year);

        if ($balance) {
            return $this->update($balance, $data);
        }

        $data = array_merge([
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveTypeId,
            'year' => $year,
        ], $data);

        return $this->create($data);
    }
}