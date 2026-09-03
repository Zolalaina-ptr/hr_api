<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AttendanceFilterRequest;
use App\Http\Requests\Api\AttendanceUpdateRequest;
use App\Http\Requests\Api\ClockInRequest;
use App\Http\Requests\Api\ClockOutRequest;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController
{
    use ApiResponseTrait;

    public function __construct(private AttendanceService $service)
    {
    }

    public function index(AttendanceFilterRequest $request): JsonResponse
    {
        $attendances = $this->service->getAttendanceReport($request->filters());

        return $this->success($attendances, 'Attendances retrieved successfully');
    }

    public function clockIn(ClockInRequest $request): JsonResponse
    {
        $attendance = $this->service->clockIn(
            (int) $request->get('employee_id'),
            $request->only(['schedule_id', 'latitude', 'longitude', 'notes'])
        );

        return $this->success($attendance, 'Clock-in recorded successfully', 201);
    }

    public function clockOut(Attendance $attendance): JsonResponse
    {
        $updated = $this->service->clockOut($attendance->id);

        return $this->success($updated, 'Clock-out recorded successfully');
    }

    public function show(Attendance $attendance): JsonResponse
    {
        $attendance->load(['employee', 'schedule', 'approver']);

        return $this->success($attendance, 'Attendance retrieved successfully');
    }

    public function update(AttendanceUpdateRequest $request, Attendance $attendance): JsonResponse
    {
        $updated = $this->service->updateAttendance($attendance, $request->validated());

        return $this->success($updated, 'Attendance updated successfully');
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->service->deleteAttendance($attendance);

        return $this->success(message: 'Attendance deleted successfully');
    }

    public function today(): JsonResponse
    {
        return $this->success($this->service->getTodayAttendance(), 'Today attendances retrieved successfully');
    }

    public function monthly(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $records = $this->service->getMonthlyAttendance(
            (int) $request->get('employee_id'),
            (int) $request->get('month'),
            $request->filled('year') ? (int) $request->get('year') : null
        );

        return $this->success($records, 'Monthly attendances retrieved successfully');
    }

    public function employee(Request $request, int $employeeId): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $records = $this->service->getEmployeeAttendances($employeeId, [
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'status' => $request->get('status'),
        ]);

        return $this->success($records, 'Employee attendances retrieved successfully');
    }

    public function approve(Attendance $attendance): JsonResponse
    {
        $updated = $this->service->approveAttendance($attendance->id);

        return $this->success($updated, 'Attendance approved successfully');
    }

    public function reject(Request $request, Attendance $attendance): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $updated = $this->service->rejectAttendance($attendance->id, $request->get('reason'));

        return $this->success($updated, 'Attendance rejected successfully');
    }

    public function export(AttendanceFilterRequest $request): StreamedResponse|JsonResponse
    {
        $format = $request->get('format', 'csv');

        return $this->service->exportAttendance($request->filters(), $format);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|exists:attendances,id',
            'updates.*.status' => 'nullable|string|in:'.implode(',', Attendance::STATUSES),
            'updates.*.notes' => 'nullable|string|max:1000',
        ]);

        $count = $this->service->bulkUpdate($data['updates']);

        return $this->success(['updated' => $count], "{$count} attendances updated successfully");
    }

    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $stats = $this->service->getStatistics([
            'employee_id' => $request->get('employee_id'),
            'month' => $request->get('month'),
            'year' => $request->get('year'),
        ]);

        return $this->success($stats, 'Attendance statistics retrieved successfully');
    }
}