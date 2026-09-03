<?php

namespace App\Listeners;

use App\Events\EmployeeClockOut;
use App\Notifications\AttendanceClockOutNotification;

class SendClockOutNotification
{
    public function handle(EmployeeClockOut $event): void
    {
        $attendance = $event->attendance->loadMissing('employee.user');
        $user = $attendance->employee?->user;

        if ($user) {
            $user->notify(new AttendanceClockOutNotification($attendance));
        }
    }
}