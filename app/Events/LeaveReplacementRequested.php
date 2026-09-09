<?php

namespace App\Events;

use App\Models\LeaveReplacement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveReplacementRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public LeaveReplacement $replacement)
    {
    }
}
