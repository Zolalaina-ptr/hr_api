<?php

namespace App\Listeners;

use App\Events\EmployeeClockOut;
use App\Models\Attendance;
use App\Notifications\AttendanceClockOutNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckOvertime
{
    public function handle(EmployeeClockOut $event): void
    {
        $attendance = $event->attendance;

        if ((float) $attendance->overtime_hours <= 0) {
            return;
        }

        $schedule = $attendance->schedule;
        $threshold = (float) ($schedule?->overtime_threshold ?? 8);

        Log::warning('Overtime detected', [
            'attendance_id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'work_hours' => (float) $attendance->work_hours,
            'overtime_hours' => (float) $attendance->overtime_hours,
            'threshold' => $threshold,
        ]);

        Cache::increment("attendance:daily:{$attendance->date->format('Y-m-d')}:overtime_records");
    }
}