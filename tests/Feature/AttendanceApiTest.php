<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Department $department;

    private Position $position;

    private Employee $employee;

    private AttendanceSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        $this->adminUser->syncPermissions(\App\Models\Permission::all());

        $this->department = Department::create([
            'name' => 'IT',
            'code' => 'IT',
            'budget' => 100000,
        ]);

        $this->position = Position::create([
            'title' => 'Developer',
            'code' => 'DEV',
            'level' => 'mid',
            'department_id' => $this->department->id,
            'min_salary' => 30000,
            'max_salary' => 50000,
        ]);

        $employeeUser = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'status' => 'active',
        ]);

        $this->schedule = AttendanceSchedule::create([
            'name' => 'Standard',
            'code' => 'STD',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'overtime_threshold' => 8.0,
            'late_tolerance_minutes' => 10.0,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    // -----------------------------------------------------------------
    // Clock-in / Clock-out
    // -----------------------------------------------------------------

    public function test_guest_cannot_clock_in(): void
    {
        $response = $this->postJson('/api/attendances/clock-in', [
            'employee_id' => $this->employee->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_can_clock_in(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/attendances/clock-in', [
            'employee_id' => $this->employee->id,
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'notes' => 'On site',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'employee_id',
                    'date',
                    'clock_in',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
        ]);
    }

    public function test_clock_in_validates_employee_id(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/attendances/clock-in', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_clock_in_validates_latitude_range(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/attendances/clock-in', [
            'employee_id' => $this->employee->id,
            'latitude' => 200,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_duplicate_clock_in_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        $this->postJson('/api/attendances/clock-in', [
            'employee_id' => $this->employee->id,
        ])->assertStatus(201);

        $response = $this->postJson('/api/attendances/clock-in', [
            'employee_id' => $this->employee->id,
        ]);

        $response->assertStatus(500);

        $this->assertSame(
            1,
            Attendance::where('employee_id', $this->employee->id)
                ->where('date', now()->format('Y-m-d'))
                ->count()
        );
    }

    public function test_admin_can_clock_out(): void
    {
        Sanctum::actingAs($this->adminUser);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'schedule_id' => $this->schedule->id,
            'date' => now()->format('Y-m-d'),
            'clock_in' => now()->subHours(8),
            'status' => 'present',
        ]);

        $response = $this->patchJson("/api/attendances/{$attendance->id}/clock-out");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $attendance->id);

        $attendance->refresh();
        $this->assertNotNull($attendance->clock_out);
        $this->assertGreaterThan(0, (float) $attendance->work_hours);
    }

    public function test_duplicate_clock_out_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'work_hours' => 8,
            'status' => 'present',
        ]);

        $this->patchJson("/api/attendances/{$attendance->id}/clock-out")
            ->assertStatus(500);
    }

    // -----------------------------------------------------------------
    // Work-hours & overtime calculation
    // -----------------------------------------------------------------

    public function test_work_hours_calculated_correctly(): void
    {
        $service = app(\App\Services\AttendanceService::class);

        $hours = $service->calculateWorkHours(
            Carbon::parse('2026-09-01 09:00'),
            Carbon::parse('2026-09-01 17:30')
        );

        $this->assertEquals(8.5, $hours);
    }

    public function test_overtime_calculated_above_threshold(): void
    {
        $service = app(\App\Services\AttendanceService::class);

        $overtime = $service->calculateOvertime(10.0, 8.0);

        $this->assertEquals(2.0, $overtime);
    }

    public function test_no_overtime_when_within_threshold(): void
    {
        $service = app(\App\Services\AttendanceService::class);

        $overtime = $service->calculateOvertime(8.0, 8.0);

        $this->assertEquals(0.0, $overtime);
    }

    // -----------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------

    public function test_can_list_attendances(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->getJson('/api/attendances');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'employee_id', 'date', 'status'],
                    ],
                    'meta',
                ],
            ]);
    }

    public function test_filter_attendances_by_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'status' => 'absent',
        ]);
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->getJson('/api/attendances?status=present');

        $response->assertStatus(200);
        foreach ($response->json('data.data') as $row) {
            $this->assertEquals('present', $row['status']);
        }
    }

    public function test_filter_attendances_by_employee(): void
    {
        Sanctum::actingAs($this->adminUser);

        $other = Employee::factory()->create([
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'status' => 'active',
        ]);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);
        Attendance::create([
            'employee_id' => $other->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->getJson("/api/attendances?employee_id={$this->employee->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_filter_attendances_by_date_range(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-08-01',
            'status' => 'present',
        ]);
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-01',
            'status' => 'present',
        ]);

        $response = $this->getJson('/api/attendances?start_date=2026-09-01&end_date=2026-09-30');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_show_attendance(): void
    {
        Sanctum::actingAs($this->adminUser);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->getJson("/api/attendances/{$attendance->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $attendance->id);
    }

    public function test_can_update_attendance(): void
    {
        Sanctum::actingAs($this->adminUser);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->putJson("/api/attendances/{$attendance->id}", [
            'notes' => 'Corrected entry',
            'status' => 'remote',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'remote',
            'notes' => 'Corrected entry',
        ]);
    }

    public function test_update_validates_clock_out_after_clock_in(): void
    {
        Sanctum::actingAs($this->adminUser);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'clock_in' => '2026-09-01 17:00:00',
            'status' => 'present',
        ]);

        $response = $this->putJson("/api/attendances/{$attendance->id}", [
            'clock_in' => '2026-09-01 17:00:00',
            'clock_out' => '2026-09-01 09:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('clock_out');
    }

    public function test_can_delete_attendance(): void
    {
        Sanctum::actingAs($this->adminUser);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $this->deleteJson("/api/attendances/{$attendance->id}")->assertStatus(200);

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    // -----------------------------------------------------------------
    // Today / monthly / employee / statistics
    // -----------------------------------------------------------------

    public function test_today_endpoint_returns_today_records(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->getJson('/api/attendances/today');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_monthly_endpoint_returns_records_for_month(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-08-15',
            'status' => 'present',
        ]);
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-15',
            'status' => 'present',
        ]);

        $response = $this->getJson("/api/attendances/monthly?employee_id={$this->employee->id}&month=9&year=2026");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_employee_endpoint_returns_records(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-01',
            'status' => 'present',
        ]);
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-02',
            'status' => 'absent',
        ]);

        $response = $this->getJson("/api/attendances/employee/{$this->employee->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_statistics_endpoint(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-01',
            'status' => 'present',
            'work_hours' => 8,
            'overtime_hours' => 1,
            'late_minutes' => 5,
        ]);
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-02',
            'status' => 'absent',
            'absence_reason' => 'Sick',
            'approved_at' => now(),
            'approved_by' => $this->adminUser->id,
        ]);

        $response = $this->getJson("/api/attendances/statistics?employee_id={$this->employee->id}&month=9&year=2026");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'employee_id',
                    'total_records',
                    'approved_count',
                    'total_work_hours',
                    'total_overtime_hours',
                    'total_late_minutes',
                    'by_status',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.total_records'));
        $this->assertEquals(1, $response->json('data.approved_count'));
        $this->assertEquals(8.0, $response->json('data.total_work_hours'));
        $this->assertEquals(1.0, $response->json('data.total_overtime_hours'));
    }

    // -----------------------------------------------------------------
    // Approvals
    // -----------------------------------------------------------------

    public function test_can_approve_attendance(): void
    {
        Sanctum::actingAs($this->adminUser);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->patchJson("/api/attendances/{$attendance->id}/approve");

        $response->assertStatus(200);

        $attendance->refresh();
        $this->assertNotNull($attendance->approved_at);
        $this->assertEquals($this->adminUser->id, $attendance->approved_by);
    }

    public function test_can_reject_attendance_with_reason(): void
    {
        Sanctum::actingAs($this->adminUser);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->patchJson("/api/attendances/{$attendance->id}/reject", [
            'reason' => 'Invalid clock-in location',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'absent',
            'absence_reason' => 'Invalid clock-in location',
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        Sanctum::actingAs($this->adminUser);

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->patchJson("/api/attendances/{$attendance->id}/reject", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    // -----------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------

    public function test_export_csv(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-01',
            'status' => 'present',
            'work_hours' => 8,
            'overtime_hours' => 0,
        ]);

        $response = $this->getJson('/api/attendances/export?format=csv&start_date=2026-09-01&end_date=2026-09-30');

        $response->assertStatus(200);
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_export_json(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
        ]);

        $response = $this->getJson('/api/attendances/export?format=json');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));
    }

    // -----------------------------------------------------------------
    // Bulk operations & reports
    // -----------------------------------------------------------------

    public function test_bulk_update_attendances(): void
    {
        Sanctum::actingAs($this->adminUser);

        $a1 = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-01',
            'status' => 'present',
        ]);
        $a2 = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-02',
            'status' => 'present',
        ]);

        $response = $this->postJson('/api/attendances/bulk', [
            'updates' => [
                ['id' => $a1->id, 'status' => 'remote', 'notes' => 'WFH'],
                ['id' => $a2->id, 'status' => 'absent', 'absence_reason' => 'Sick'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.updated', 2);

        $this->assertDatabaseHas('attendances', ['id' => $a1->id, 'status' => 'remote']);
        $this->assertDatabaseHas('attendances', ['id' => $a2->id, 'status' => 'absent']);
    }

    public function test_bulk_update_validates_ids(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/attendances/bulk', [
            'updates' => [
                ['id' => 99999, 'status' => 'remote'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('updates.0.id');
    }

    public function test_monthly_summary_aggregates_correctly(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-01',
            'status' => 'present',
            'work_hours' => 8,
            'overtime_hours' => 1,
            'late_minutes' => 5,
            'approved_at' => now(),
            'approved_by' => $this->adminUser->id,
        ]);
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-02',
            'status' => 'remote',
            'work_hours' => 8,
        ]);
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-03',
            'status' => 'absent',
        ]);

        $service = app(\App\Services\AttendanceService::class);
        $summary = $service->generateMonthlySummary($this->employee->id, 9, 2026);

        $this->assertEquals(3, $summary['total_days']);
        $this->assertEquals(1, $summary['present_days']);
        $this->assertEquals(1, $summary['remote_days']);
        $this->assertEquals(1, $summary['absent_days']);
        $this->assertEquals(16.0, $summary['total_work_hours']);
        $this->assertEquals(1.0, $summary['total_overtime_hours']);
        $this->assertEquals(1, $summary['approved_count']);
    }

    public function test_check_duplicate_attendance(): void
    {
        $service = app(\App\Services\AttendanceService::class);

        $today = now()->format('Y-m-d');
        $this->assertFalse($service->checkDuplicateAttendance($this->employee->id, $today));

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => $today,
            'status' => 'present',
        ]);

        $this->assertTrue($service->checkDuplicateAttendance($this->employee->id, $today));
    }
}