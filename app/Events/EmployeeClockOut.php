<?php

namespace App\Events;

use App\Models\Attendance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeClockOut
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Attendance $attendance)
    {
    }
}