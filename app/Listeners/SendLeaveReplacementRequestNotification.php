<?php

namespace App\Listeners;

use App\Events\LeaveReplacementRequested;
use App\Notifications\LeaveReplacementRequestNotification;

class SendLeaveReplacementRequestNotification
{
    public function handle(LeaveReplacementRequested $event): void
    {
        $replacement = $event->replacement->loadMissing('replacementEmployee.user');
        $user = $replacement->replacementEmployee?->user;

        if ($user) {
            $user->notify(new LeaveReplacementRequestNotification($replacement));
        }
    }
}
