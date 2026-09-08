<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest as LeaveRequestHistory;
use App\Models\LeaveType;
use App\Repositories\LeaveBalanceRepository;
use App\Repositories\LeaveRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveService
{
    public function __construct(
        private LeaveRepository $repository,
        private LeaveBalanceRepository $balanceRepository,
    ) {
    }

    public function createRequest(array $data): Leave
    {
        $this->validateDates($data['start_date'], $data['end_date']);

        $type = LeaveType::findOrFail($data['leave_type_id']);
        $duration = $this->calculateDays($data['start_date'], $data['end_date'], $data['half_day_portion'] ?? 0);

        if ($type->max_days && $duration > $type->max_days) {
            throw new BusinessRuleException("Leave duration ({$duration}) exceeds maximum ({$type->max_days}) for this leave type");
        }

        if ($type->min_days_notice) {
            $notice = (int) $type->min_days_notice;
            $minStart = Carbon::parse($data['start_date'])->subDays($notice);
            if (Carbon::parse($data['start_date'])->lt($minStart)) {
                throw new BusinessRuleException("This leave type requires {$notice} days notice");
            }
        }

        $overlap = $this->checkOverlap($data['employee_id'], $data['start_date'], $data['end_date']);
        if (! empty($overlap)) {
            throw new BusinessRuleException('Leave period overlaps with an existing pending or approved leave');
        }

        $availability = $this->checkAvailability($data['employee_id'], (int) $data['leave_type_id']);
        if ($availability !== null && $availability < $duration) {
            throw new BusinessRuleException("Insufficient balance ({$availability} days remaining, {$duration} requested)");
        }

        $leave = $this->repository->create([
            'employee_id' => $data['employee_id'],
            'leave_type_id' => $data['leave_type_id'],
            'replacement_employee_id' => $data['replacement_employee_id'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration_days' => $duration,
            'half_day_portion' => $data['half_day_portion'] ?? 0,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'attachment_url' => $data['attachment_url'] ?? null,
            'is_urgent' => $data['is_urgent'] ?? false,
        ]);

        $this->recordHistory($leave, 'created', null, 'pending', $data['reason'] ?? null);

        return $leave;
    }

    public function updateRequest(int $id, array $data): Leave
    {
        $leave = Leave::findOrFail($id);

        if ($leave->status !== 'pending') {
            throw new BusinessRuleException('Only pending leaves can be updated');
        }

        if (isset($data['start_date'], $data['end_date'])) {
            $this->validateDates($data['start_date'], $data['end_date']);
        }

        $previousStatus = $leave->status;
        $updated = $this->repository->update($leave, $data);

        $this->recordHistory($updated, 'updated', $previousStatus, $updated->status);

        return $updated;
    }

    public function approveRequest(int $id, ?string $comment = null): Leave
    {
        $leave = Leave::findOrFail($id);

        if ($leave->status !== 'pending') {
            throw new BusinessRuleException('Only pending leaves can be approved');
        }

        $previousStatus = $leave->status;
        $updated = $this->repository->update($leave, [
            'status' => 'approved',
            'approval_comment' => $comment,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->updateBalanceOnApproval($updated);
        $this->recordHistory($updated, 'approved', $previousStatus, 'approved', $comment);

        return $updated;
    }

    public function rejectRequest(int $id, ?string $comment = null): Leave
    {
        $leave = Leave::findOrFail($id);

        if (! in_array($leave->status, ['pending'], true)) {
            throw new BusinessRuleException('Only pending leaves can be rejected');
        }

        $previousStatus = $leave->status;
        $updated = $this->repository->update($leave, [
            'status' => 'rejected',
            'approval_comment' => $comment,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->recordHistory($updated, 'rejected', $previousStatus, 'rejected', $comment);

        return $updated;
    }

    public function cancelRequest(int $id): Leave
    {
        $leave = Leave::findOrFail($id);

        if (in_array($leave->status, ['cancelled', 'rejected'], true)) {
            throw new BusinessRuleException('Leave cannot be cancelled in its current state');
        }

        $previousStatus = $leave->status;
        $updated = $this->repository->update($leave, [
            'status' => 'cancelled',
        ]);

        if ($previousStatus === 'approved') {
            $this->restoreBalanceOnCancellation($updated);
        }

        $this->recordHistory($updated, 'cancelled', $previousStatus, 'cancelled');

        return $updated;
    }

    public function checkAvailability(int $employeeId, int $leaveTypeId): ?float
    {
        $year = (int) now()->format('Y');
        $balance = $this->balanceRepository->findFor($employeeId, $leaveTypeId, $year);

        if (! $balance) {
            return null;
        }

        return (float) $balance->remaining_days;
    }

    public function calculateDays($startDate, $endDate, float $halfDayPortion = 0): float
    {
        // Compare both dates at start of day so the diff is an exact number
        // of days (Carbon 3 diffInDays returns a float).
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        $days = $start->diffInDays($end) + 1;

        if ($halfDayPortion > 0 && $days == 1) {
            return 0.5;
        }

        return (float) $days;
    }

    public function getPendingRequests(array $filters = []): Collection
    {
        return $this->repository->getPending($filters);
    }

    public function getBalance(int $employeeId, ?int $year = null): Collection
    {
        $year ??= (int) now()->format('Y');

        return $this->balanceRepository->getFor($employeeId, $year);
    }

    public function calculateRemainingDays(int $employeeId, int $leaveTypeId, ?int $year = null): float
    {
        $year ??= (int) now()->format('Y');
        $balance = $this->balanceRepository->findFor($employeeId, $leaveTypeId, $year);

        if (! $balance) {
            return 0.0;
        }

        return (float) $balance->remaining_days;
    }

    public function validateDates($startDate, $endDate): void
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($end->lt($start)) {
            throw new BusinessRuleException('End date must be on or after start date');
        }

        if ($start->isPast() && ! $start->isToday()) {
            throw new BusinessRuleException('Start date cannot be in the past');
        }
    }

    public function checkOverlap(int $employeeId, $startDate, $endDate, ?int $excludeId = null): Collection
    {
        return $this->repository->getOverlapping($employeeId, $startDate, $endDate, $excludeId);
    }

    public function generateBalanceReport(int $year): array
    {
        $balances = $this->balanceRepository->getAllForYear($year);

        $byType = [];
        foreach ($balances as $balance) {
            $typeId = $balance->leave_type_id;
            if (! isset($byType[$typeId])) {
                $byType[$typeId] = [
                    'leave_type' => $balance->leaveType?->name,
                    'code' => $balance->leaveType?->code,
                    'employees' => 0,
                    'total_allocated' => 0.0,
                    'total_used' => 0.0,
                    'total_remaining' => 0.0,
                ];
            }

            $byType[$typeId]['employees']++;
            $byType[$typeId]['total_allocated'] += (float) $balance->total_days;
            $byType[$typeId]['total_used'] += (float) $balance->used_days;
            $byType[$typeId]['total_remaining'] += (float) $balance->remaining_days;
        }

        return [
            'year' => $year,
            'total_records' => $balances->count(),
            'by_leave_type' => array_values($byType),
        ];
    }

    public function autoApprove(int $requestId): Leave
    {
        $leave = Leave::findOrFail($requestId);

        if ($leave->status !== 'pending') {
            throw new BusinessRuleException('Only pending leaves can be auto-approved');
        }

        $type = $leave->leaveType;
        if (! $type || ! $type->is_active) {
            throw new BusinessRuleException('Leave type is inactive');
        }

        $available = $this->checkAvailability($leave->employee_id, $leave->leave_type_id);
        if ($available !== null && $available < (float) $leave->duration_days) {
            throw new BusinessRuleException('Auto-approval failed: insufficient balance');
        }

        return $this->approveRequest($requestId, 'Auto-approved by system');
    }

    public function getLeavesPaginated(array $filters = [])
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        unset($filters['per_page']);

        return $this->repository->getPaginated($perPage, $filters);
    }

    public function getLeavesByEmployee(int $employeeId, array $filters = []): Collection
    {
        return $this->repository->getForEmployee($employeeId, $filters);
    }

    public function exportLeaves(array $filters, string $format = 'csv'): StreamedResponse
    {
        $perPage = (int) ($filters['per_page'] ?? 1000);
        unset($filters['per_page']);

        $records = $this->repository->getPaginated($perPage, $filters)->getCollection();

        $filename = 'leaves_'.now()->format('Ymd_His').'.'.$format;
        $headers = [
            'Content-Type' => $format === 'csv' ? 'text/csv' : 'application/json',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($records, $format) {
            $handle = fopen('php://output', 'w');

            if ($format === 'csv') {
                fputcsv($handle, ['ID', 'Employee', 'Type', 'Start', 'End', 'Days', 'Status']);
                foreach ($records as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->employee?->registration_number ?? $row->employee_id,
                        $row->leaveType?->code ?? $row->leave_type_id,
                        $row->start_date?->format('Y-m-d'),
                        $row->end_date?->format('Y-m-d'),
                        $row->duration_days,
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

    public function getStatistics(int $year): array
    {
        $byStatus = Leave::whereYear('start_date', $year)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totals = Leave::whereYear('start_date', $year)
            ->selectRaw('
                COUNT(*) as total_requests,
                SUM(duration_days) as total_days,
                SUM(CASE WHEN status = 'approved' THEN duration_days ELSE 0 END) as approved_days,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
            ')
            ->first();

        return [
            'year' => $year,
            'total_requests' => (int) ($totals->total_requests ?? 0),
            'total_days' => (float) ($totals->total_days ?? 0),
            'approved_days' => (float) ($totals->approved_days ?? 0),
            'pending_count' => (int) ($totals->pending_count ?? 0),
            'by_status' => $byStatus,
        ];
    }

    private function recordHistory(Leave $leave, string $action, ?string $previous, string $new, ?string $comment = null): void
    {
        LeaveRequestHistory::create([
            'leave_id' => $leave->id,
            'employee_id' => $leave->employee_id,
            'leave_type_id' => $leave->leave_type_id,
            'action' => $action,
            'previous_status' => $previous,
            'new_status' => $new,
            'comment' => $comment,
            'performed_by' => auth()->id(),
            'performed_at' => now(),
        ]);
    }

    private function updateBalanceOnApproval(Leave $leave): void
    {
        $year = (int) Carbon::parse($leave->start_date)->format('Y');
        $balance = $this->balanceRepository->findFor($leave->employee_id, $leave->leave_type_id, $year);

        if (! $balance) {
            return;
        }

        $newUsed = (float) $balance->used_days + (float) $leave->duration_days;
        $newRemaining = max(0, (float) $balance->total_days + (float) $balance->carry_over - $newUsed);

        $this->balanceRepository->update($balance, [
            'used_days' => $newUsed,
            'remaining_days' => $newRemaining,
            'pending_days' => max(0, (float) $balance->pending_days - (float) $leave->duration_days),
            'last_calculated_at' => now(),
        ]);
    }

    private function restoreBalanceOnCancellation(Leave $leave): void
    {
        $year = (int) Carbon::parse($leave->start_date)->format('Y');
        $balance = $this->balanceRepository->findFor($leave->employee_id, $leave->leave_type_id, $year);

        if (! $balance) {
            return;
        }

        $newUsed = max(0, (float) $balance->used_days - (float) $leave->duration_days);
        $newRemaining = max(0, (float) $balance->total_days + (float) $balance->carry_over - $newUsed);

        $this->balanceRepository->update($balance, [
            'used_days' => $newUsed,
            'remaining_days' => $newRemaining,
            'last_calculated_at' => now(),
        ]);
    }
}