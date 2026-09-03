<?php

namespace Tests\Feature;

use App\Events\EmployeeClockIn;
use App\Events\EmployeeClockOut;
use App\Listeners\CalculateHoursOnClockOut;
use App\Listeners\CheckOvertime;
use App\Listeners\SendClockInNotification;
use App\Listeners\SendClockOutNotification;
use App\Listeners\UpdateAttendanceStats;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AttendanceEventsTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
        $position = Position::create([
            'title' => 'Dev',
            'code' => 'DEV',
            'level' => 'mid',
            'department_id' => $dept->id,
            'min_salary' => 30000,
            'max_salary' => 50000,
        ]);

        $this->employee = Employee::factory()->create([
            'department_id' => $dept->id,
            'position_id' => $position->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_employee_clock_in_event_is_dispatched_with_attendance(): void
    {
        Event::fake([EmployeeClockIn::class]);

        AttendanceSchedule::create([
            'name' => 'Standard',
            'code' => 'STD',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_default' => true,
            'is_active' => true,
        ]);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'clock_in' => now(),
            'status' => 'present',
        ]);

        event(new EmployeeClockIn($attendance));

        Event::assertDispatched(EmployeeClockIn::class, fn ($e) => $e->attendance->id === $attendance->id);
    }

    public function test_send_clock_in_notification_listener_creates_notification(): void
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'clock_in' => now(),
            'status' => 'present',
        ]);
        $attendance->load('employee.user');

        $listener = new SendClockInNotification;
        $listener->handle(new EmployeeClockIn($attendance));

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_update_attendance_stats_listener_increments_cache(): void
    {
        $date = now()->format('Y-m-d');
        Cache::forget("attendance:daily:{$date}:total");

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => $date,
            'clock_in' => now(),
            'status' => 'present',
        ]);

        (new UpdateAttendanceStats)->handle(new EmployeeClockIn($attendance));

        $this->assertEquals(1, Cache::get("attendance:daily:{$date}:total"));
        $this->assertEquals(1, Cache::get("attendance:daily:{$date}:status:present"));
    }

    public function test_check_overtime_listener_logs_warning_when_overtime(): void
    {
        Log::spy();

        $schedule = AttendanceSchedule::create([
            'name' => 'Standard',
            'code' => 'STD-OT',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'overtime_threshold' => 8,
            'is_default' => true,
            'is_active' => true,
        ]);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'schedule_id' => $schedule->id,
            'date' => now()->format('Y-m-d'),
            'clock_in' => now()->subHours(10),
            'clock_out' => now(),
            'work_hours' => 10,
            'overtime_hours' => 2,
            'status' => 'present',
        ]);
        $attendance->setRelation('schedule', $schedule);

        (new CheckOvertime)->handle(new EmployeeClockOut($attendance));

        Log::shouldHaveReceived('warning')->once();
        $this->assertEquals(1, Cache::get("attendance:daily:{$attendance->date->format('Y-m-d')}:overtime_records"));
    }

    public function test_check_overtime_listener_skips_when_no_overtime(): void
    {
        Log::spy();

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'work_hours' => 8,
            'overtime_hours' => 0,
            'status' => 'present',
        ]);

        (new CheckOvertime)->handle(new EmployeeClockOut($attendance));

        Log::shouldNotHaveReceived('warning');
    }

    public function test_calculate_hours_listener_logs_on_clock_out(): void
    {
        Log::spy();

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'work_hours' => 8,
            'overtime_hours' => 0,
            'status' => 'present',
        ]);

        (new CalculateHoursOnClockOut)->handle(new EmployeeClockOut($attendance));

        Log::shouldHaveReceived('info')->once();
    }

    public function test_send_clock_out_notification_listener_creates_notification(): void
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'work_hours' => 8,
            'status' => 'present',
        ]);
        $attendance->load('employee.user');

        (new SendClockOutNotification)->handle(new EmployeeClockOut($attendance));

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_service_dispatches_events_when_clocking_in_and_out(): void
    {
        Event::fake([EmployeeClockIn::class, EmployeeClockOut::class]);

        AttendanceSchedule::create([
            'name' => 'Standard',
            'code' => 'STD-SVC',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_default' => true,
            'is_active' => true,
        ]);

        $service = app(\App\Services\AttendanceService::class);

        $attendance = $service->clockIn($this->employee->id, []);

        Event::assertDispatched(EmployeeClockIn::class);

        $service->clockOut($attendance->id);

        Event::assertDispatched(EmployeeClockOut::class);
    }
}