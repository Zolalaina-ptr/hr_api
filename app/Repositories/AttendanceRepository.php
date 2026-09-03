<?php

namespace App\Repositories;

use App\Models\Attendance;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AttendanceRepository
{
    public function getPaginated(int $perPage = 15, array $filters = []): Paginator
    {
        $query = Attendance::with(['employee', 'schedule', 'approver']);

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date'])) {
            $query->where('date', $filters['date']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('date', [$filters['start_date'], $filters['end_date']]);
        } elseif (isset($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        } elseif (isset($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        if (isset($filters['department_id'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        return $query->orderByDesc('date')->paginate($perPage);
    }

    public function findById(int $id): ?Attendance
    {
        return Attendance::with(['employee', 'schedule', 'approver'])->find($id);
    }

    public function findByEmployeeAndDate(int $employeeId, string $date): ?Attendance
    {
        return Attendance::where('employee_id', $employeeId)
            ->where('date', $date)
            ->first();
    }

    public function getForMonth(int $employeeId, int $year, int $month): Collection
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return Attendance::with(['schedule', 'approver'])
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('date')
            ->get();
    }

    public function getToday(): Collection
    {
        return Attendance::with(['employee', 'schedule'])
            ->where('date', now()->format('Y-m-d'))
            ->get();
    }

    public function getApprovedCount(int $employeeId, int $year, int $month): int
    {
        return Attendance::where('employee_id', $employeeId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereNotNull('approved_at')
            ->count();
    }

    public function bulkInsert(array $rows): void
    {
        if (! empty($rows)) {
            Attendance::insert($rows);
        }
    }

    public function create(array $data): Attendance
    {
        return Attendance::create($data);
    }

    public function update(Attendance $attendance, array $data): Attendance
    {
        $attendance->update($data);

        return $attendance->fresh(['employee', 'schedule', 'approver']);
    }

    public function delete(Attendance $attendance): bool
    {
        return (bool) $attendance->delete();
    }
}