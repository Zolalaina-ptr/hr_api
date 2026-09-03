<?php

namespace App\Services;

use App\Events\EmployeeClockIn;
use App\Events\EmployeeClockOut;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Employee;
use App\Repositories\AttendanceRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceService
{
    public function __construct(private AttendanceRepository $repository)
    {
    }

    public function clockIn(int $employeeId, array $data = []): Attendance
    {
        $employee = Employee::findOrFail($employeeId);
        $now = now();
        $today = $now->format('Y-m-d');

        $existing = $this->repository->findByEmployeeAndDate($employeeId, $today);

        if ($existing && $existing->clock_in) {
            throw new \RuntimeException('Employee already clocked in today');
        }

        $schedule = $this->resolveSchedule($employee, $data);

        $lateMinutes = $schedule
            ? max(0, $this->calculateLateMinutes($schedule, $now))
            : 0;

        $payload = array_merge([
            'employee_id' => $employeeId,
            'schedule_id' => $schedule?->id,
            'date' => $today,
            'clock_in' => $now,
            'status' => $lateMinutes > 0 ? 'late' : 'present',
            'late_minutes' => $lateMinutes,
            'ip_address' => $data['ip_address'] ?? request()?->ip(),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $existing ? [] : []);

        if ($existing) {
            $updated = $this->repository->update($existing, $payload);
            EmployeeClockIn::dispatch($updated);

            return $updated;
        }

        $created = $this->repository->create($payload);
        EmployeeClockIn::dispatch($created);

        return $created;
    }

    public function clockOut(int $attendanceId): Attendance
    {
        $attendance = Attendance::findOrFail($attendanceId);

        if ($attendance->clock_out) {
            throw new \RuntimeException('Employee already clocked out');
        }

        $now = now();
        $workHours = $this->calculateWorkHours($attendance->clock_in, $now);
        $schedule = $attendance->schedule;
        $overtimeHours = $schedule
            ? $this->calculateOvertime($workHours, $schedule->overtime_threshold)
            : 0;

        $updated = $this->repository->update($attendance, [
            'clock_out' => $now,
            'work_hours' => $workHours,
            'overtime_hours' => $overtimeHours,
        ]);

        EmployeeClockOut::dispatch($updated);

        return $updated;
    }

    public function calculateWorkHours($clockIn, $clockOut): float
    {
        if (! $clockIn || ! $clockOut) {
            return 0.0;
        }

        $start = $clockIn instanceof Carbon ? $clockIn : Carbon::parse($clockIn);
        $end = $clockOut instanceof Carbon ? $clockOut : Carbon::parse($clockOut);
        $minutes = max(0, $start->diffInMinutes($end));

        return round($minutes / 60, 2);
    }

    public function calculateOvertime(float $workHours, float $threshold): float
    {
        if ($workHours <= $threshold) {
            return 0.0;
        }

        return round($workHours - $threshold, 2);
    }

    public function calculateLateMinutes(AttendanceSchedule $schedule, Carbon $clockIn): float
    {
        $start = Carbon::parse($schedule->start_time)->setDateFrom($clockIn);
        $diff = $start->diffInMinutes($clockIn, false);

        return $diff > 0 ? (float) $diff : 0.0;
    }

    public function getTodayAttendance(): Collection
    {
        return $this->repository->getToday();
    }

    public function getMonthlyAttendance(int $employeeId, int $month, ?int $year = null): Collection
    {
        $year ??= (int) now()->format('Y');

        return $this->repository->getForMonth($employeeId, $year, $month);
    }

    public function getAttendanceReport(array $filters = [])
    {
        $perPage = $filters['per_page'] ?? 15;

        return $this->repository->getPaginated($perPage, $filters);
    }

    public function approveAttendance(int $id, ?int $approverId = null): Attendance
    {
        $attendance = Attendance::findOrFail($id);

        return $this->repository->update($attendance, [
            'approved_by' => $approverId ?? auth()->id(),
            'approved_at' => now(),
        ]);
    }

    public function generateMonthlySummary(int $employeeId, int $month, ?int $year = null): array
    {
        $year ??= (int) now()->format('Y');
        $records = $this->repository->getForMonth($employeeId, $year, $month);

        $summary = [
            'employee_id' => $employeeId,
            'year' => $year,
            'month' => $month,
            'total_days' => $records->count(),
            'present_days' => 0,
            'absent_days' => 0,
            'leave_days' => 0,
            'remote_days' => 0,
            'training_days' => 0,
            'late_days' => 0,
            'total_work_hours' => 0.0,
            'total_overtime_hours' => 0.0,
            'total_late_minutes' => 0.0,
            'approved_count' => 0,
        ];

        foreach ($records as $record) {
            $summary['total_work_hours'] += (float) $record->work_hours;
            $summary['total_overtime_hours'] += (float) $record->overtime_hours;
            $summary['total_late_minutes'] += (float) $record->late_minutes;

            if ($record->approved_at) {
                $summary['approved_count']++;
            }

            match ($record->status) {
                'present' => $summary['present_days']++,
                'absent' => $summary['absent_days']++,
                'on_leave' => $summary['leave_days']++,
                'remote' => $summary['remote_days']++,
                'training' => $summary['training_days']++,
                'late' => $summary['late_days']++,
                default => null,
            };
        }

        return $summary;
    }

    public function checkDuplicateAttendance(int $employeeId, string $date): bool
    {
        return $this->repository
            ->findByEmployeeAndDate($employeeId, $date) !== null;
    }

    public function exportAttendance(array $filters, string $format = 'csv'): StreamedResponse
    {
        $records = $this->repository->getPaginated(1000, $filters)->getCollection();

        $filename = 'attendances_'.now()->format('Ymd_His').'.'.$format;
        $headers = [
            'Content-Type' => $format === 'csv' ? 'text/csv' : 'application/json',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($records, $format) {
            $handle = fopen('php://output', 'w');

            if ($format === 'csv') {
                fputcsv($handle, ['ID', 'Employee', 'Date', 'Clock In', 'Clock Out', 'Work Hours', 'Overtime', 'Status']);
                foreach ($records as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->employee?->registration_number ?? $row->employee_id,
                        $row->date?->format('Y-m-d'),
                        $row->clock_in?->format('Y-m-d H:i'),
                        $row->clock_out?->format('Y-m-d H:i'),
                        $row->work_hours,
                        $row->overtime_hours,
                        $row->status,
                    ]);
                }
            } else {
                echo json_encode($records->toArray());
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkUpdate(array $updates): int
    {
        $count = 0;
        foreach ($updates as $payload) {
            $id = $payload['id'] ?? null;
            if (! $id) {
                continue;
            }
            $attendance = Attendance::find($id);
            if (! $attendance) {
                continue;
            }
            unset($payload['id']);
            $this->repository->update($attendance, $payload);
            $count++;
        }

        return $count;
    }

    public function updateAttendance(Attendance $attendance, array $data): Attendance
    {
        return $this->repository->update($attendance, $data);
    }

    public function deleteAttendance(Attendance $attendance): bool
    {
        return $this->repository->delete($attendance);
    }

    public function rejectAttendance(int $id, string $reason): Attendance
    {
        $attendance = Attendance::findOrFail($id);

        return $this->repository->update($attendance, [
            'status' => 'absent',
            'absence_reason' => $reason,
        ]);
    }

    public function getEmployeeAttendances(int $employeeId, array $filters = []): Collection
    {
        $query = Attendance::with(['schedule', 'approver'])
            ->where('employee_id', $employeeId);

        if (! empty($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('date')->get();
    }

    public function getStatistics(array $filters = []): array
    {
        $month = (int) ($filters['month'] ?? now()->format('n'));
        $year = (int) ($filters['year'] ?? now()->format('Y'));
        $employeeId = $filters['employee_id'] ?? null;

        $query = Attendance::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totals = (clone $query)
            ->selectRaw('
                COALESCE(SUM(work_hours), 0) as total_work_hours,
                COALESCE(SUM(overtime_hours), 0) as total_overtime_hours,
                COALESCE(SUM(late_minutes), 0) as total_late_minutes,
                COUNT(*) as total_records,
                SUM(CASE WHEN approved_at IS NOT NULL THEN 1 ELSE 0 END) as approved_count
            ')
            ->first();

        return [
            'period' => ['year' => $year, 'month' => $month],
            'employee_id' => $employeeId,
            'total_records' => (int) ($totals->total_records ?? 0),
            'approved_count' => (int) ($totals->approved_count ?? 0),
            'total_work_hours' => (float) ($totals->total_work_hours ?? 0),
            'total_overtime_hours' => (float) ($totals->total_overtime_hours ?? 0),
            'total_late_minutes' => (float) ($totals->total_late_minutes ?? 0),
            'by_status' => $byStatus,
        ];
    }

    private function resolveSchedule(Employee $employee, array $data = []): ?AttendanceSchedule
    {
        if (isset($data['schedule_id'])) {
            return AttendanceSchedule::find($data['schedule_id']);
        }

        return AttendanceSchedule::active()->default()->first();
    }
}