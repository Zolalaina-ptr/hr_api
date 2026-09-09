<?php

namespace App\Notifications;

use App\Models\LeaveReplacement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class LeaveReplacementRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public LeaveReplacement $replacement)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        $replacement = $this->replacement->loadMissing(['originalEmployee', 'replacementEmployee']);
        $original = $replacement->originalEmployee;

        return new DatabaseMessage([
            'replacement_id' => $replacement->id,
            'leave_id' => $replacement->leave_id,
            'original_employee_id' => $replacement->original_employee_id,
            'original_employee_name' => $original ? trim($original->first_name.' '.$original->last_name) : null,
            'replacement_employee_id' => $replacement->replacement_employee_id,
            'start_date' => $replacement->start_date?->format('Y-m-d'),
            'end_date' => $replacement->end_date?->format('Y-m-d'),
            'responsibilities' => $replacement->responsibilities,
            'status' => $replacement->status,
            'type' => 'leave_replacement_requested',
        ]);
    }
}
