<?php

namespace App\Events;

use App\Models\Attendance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeClockIn
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Attendance $attendance)
    {
    }
}