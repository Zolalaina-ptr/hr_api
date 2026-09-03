<?php

namespace App\Listeners;

use App\Events\EmployeeClockOut;
use App\Models\Attendance;
use App\Notifications\AttendanceClockOutNotification;
use Illuminate\Support\Facades\Log;

class CalculateHoursOnClockOut
{
    public function handle(EmployeeClockOut $event): void
    {
        $attendance = $event->attendance;
        $attendance->loadMissing('employee.user');

        Log::info('Employee clocked out', [
            'attendance_id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'work_hours' => (float) $attendance->work_hours,
            'overtime_hours' => (float) $attendance->overtime_hours,
            'late_minutes' => (float) $attendance->late_minutes,
        ]);
    }
}