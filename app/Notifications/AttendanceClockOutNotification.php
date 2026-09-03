<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class AttendanceClockOutNotification extends Notification
{
    use Queueable;

    public function __construct(public Attendance $attendance)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        $employee = $this->attendance->employee;

        return new DatabaseMessage([
            'attendance_id' => $this->attendance->id,
            'employee_id' => $this->attendance->employee_id,
            'employee_name' => $employee ? trim($employee->first_name.' '.$employee->last_name) : null,
            'date' => $this->attendance->date?->format('Y-m-d'),
            'clock_out' => $this->attendance->clock_out?->format('H:i'),
            'work_hours' => (float) $this->attendance->work_hours,
            'overtime_hours' => (float) $this->attendance->overtime_hours,
            'type' => 'clock_out',
        ]);
    }
}