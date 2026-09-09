<?php

namespace App\Notifications;

use App\Models\LeaveReplacement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class LeaveReplacementStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveReplacement $replacement,
        public string $previousStatus,
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        $replacement = $this->replacement->loadMissing('replacementEmployee');
        $employee = $replacement->replacementEmployee;

        return new DatabaseMessage([
            'replacement_id' => $replacement->id,
            'leave_id' => $replacement->leave_id,
            'replacement_employee_id' => $replacement->replacement_employee_id,
            'replacement_employee_name' => $employee ? trim($employee->first_name.' '.$employee->last_name) : null,
            'previous_status' => $this->previousStatus,
            'status' => $replacement->status,
            'rejection_reason' => $replacement->rejection_reason,
            'start_date' => $replacement->start_date?->format('Y-m-d'),
            'end_date' => $replacement->end_date?->format('Y-m-d'),
            'type' => 'leave_replacement_'.$replacement->status,
        ]);
    }
}
