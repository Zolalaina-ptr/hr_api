<?php

namespace App\Listeners;

use App\Events\LeaveReplacementStatusChanged;
use App\Notifications\LeaveReplacementStatusNotification;

class SendLeaveReplacementStatusNotification
{
    public function handle(LeaveReplacementStatusChanged $event): void
    {
        $replacement = $event->replacement->loadMissing(['replacementEmployee.user', 'requester']);

        // The requester learns about accept/decline decisions;
        // the replacement learns the request is cancelled.
        $recipient = $event->replacement->status === 'cancelled'
            ? $replacement->replacementEmployee?->user
            : $replacement->requester;

        if ($recipient) {
            $recipient->notify(new LeaveReplacementStatusNotification($replacement, $event->previousStatus));
        }
    }
}
