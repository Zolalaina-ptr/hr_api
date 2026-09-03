<?php

namespace App\Repositories;

use App\Models\Leave;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

class LeaveRepository
{
    public function getPaginated(int $perPage = 15, array $filters = []): Paginator
    {
        $query = Leave::with(['employee', 'leaveType', 'replacement', 'approver']);

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereBetween('start_date', [$filters['start_date'], $filters['end_date']])
                  ->orWhereBetween('end_date', [$filters['start_date'], $filters['end_date']]);
            });
        }

        if (isset($filters['is_urgent'])) {
            $query->where('is_urgent', $filters['is_urgent']);
        }

        if (isset($filters['department_id'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        return $query->orderByDesc('start_date')->paginate($perPage);
    }

    public function findById(int $id): ?Leave
    {
        return Leave::with(['employee', 'leaveType', 'replacement', 'approver', 'histories'])->find($id);
    }

    public function getPending(array $filters = []): Collection
    {
        $query = Leave::with(['employee', 'leaveType'])
            ->where('status', 'pending');

        if (isset($filters['department_id'])) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        return $query->orderBy('start_date')->get();
    }

    public function getOverlapping(int $employeeId, $startDate, $endDate, ?int $excludeId = null): Collection
    {
        return Leave::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get();
    }

    public function getForEmployee(int $employeeId, array $filters = []): Collection
    {
        $query = Leave::with(['leaveType', 'approver'])
            ->where('employee_id', $employeeId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['year'])) {
            $query->where(function ($q) use ($filters) {
                $year = (int) $filters['year'];
                $q->whereYear('start_date', $year)
                  ->orWhereYear('end_date', $year);
            });
        }

        return $query->orderByDesc('start_date')->get();
    }

    public function create(array $data): Leave
    {
        return Leave::create($data);
    }

    public function update(Leave $leave, array $data): Leave
    {
        $leave->update($data);

        return $leave->fresh(['employee', 'leaveType', 'replacement', 'approver']);
    }

    public function delete(Leave $leave): bool
    {
        return (bool) $leave->delete();
    }
}