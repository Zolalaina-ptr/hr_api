<?php

namespace App\Listeners;

use App\Events\EmployeeClockIn;
use Illuminate\Support\Facades\Cache;

class UpdateAttendanceStats
{
    public function handle(EmployeeClockIn $event): void
    {
        $attendance = $event->attendance;
        $date = $attendance->date?->format('Y-m-d') ?? now()->format('Y-m-d');

        Cache::increment("attendance:daily:{$date}:total");
        Cache::increment("attendance:daily:{$date}:status:{$attendance->status}");
        Cache::forget("attendance:monthly:{$attendance->employee_id}");
    }
}