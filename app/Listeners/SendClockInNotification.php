<?php

namespace App\Listeners;

use App\Events\EmployeeClockIn;
use App\Notifications\AttendanceClockInNotification;
use Illuminate\Support\Facades\Cache;

class SendClockInNotification
{
    public function handle(EmployeeClockIn $event): void
    {
        $attendance = $event->attendance->loadMissing('employee.user');
        $user = $attendance->employee?->user;

        if ($user) {
            $user->notify(new AttendanceClockInNotification($attendance));
        }
    }
}