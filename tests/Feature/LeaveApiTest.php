<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $employeeUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        $this->adminUser->syncPermissions(Permission::all());

        // An authenticated user linked to an employee record (self-service flow)
        $this->employeeUser = User::factory()->create();
        $this->employeeUser->syncPermissions(Permission::whereIn('name', [
            'view leaves',
            'create leaves',
        ])->get());

        $this->employee = Employee::factory()->create([
            'user_id' => $this->employeeUser->id,
            'status' => 'active',
        ]);
    }

    private function makeType(array $overrides = []): LeaveType
    {
        static $count = 0;
        $count++;

        return LeaveType::create(array_merge([
            'name' => "Annual Leave {$count}",
            'code' => "ANN{$count}",
            'days_per_year' => 25,
            'is_paid' => true,
            'is_active' => true,
            'min_days_notice' => 0,
        ], $overrides));
    }

    private function makeBalance(Employee $employee, LeaveType $type, array $overrides = []): LeaveBalance
    {
        return LeaveBalance::create(array_merge([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => (int) now()->format('Y'),
            'total_days' => 25,
            'used_days' => 0,
            'pending_days' => 0,
            'remaining_days' => 25,
            'carry_over' => 0,
        ], $overrides));
    }

    private function futureDates(int $offsetDays = 5, int $duration = 2): array
    {
        return [
            'start_date' => now()->addDays($offsetDays)->toDateString(),
            'end_date' => now()->addDays($offsetDays + $duration)->toDateString(),
        ];
    }

    private function createLeave(Employee $employee, LeaveType $type, array $overrides = []): Leave
    {
        return Leave::create(array_merge([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'duration_days' => 2,
            'status' => 'pending',
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------

    public function test_guest_cannot_access_leaves(): void
    {
        $this->getJson('/api/leaves')->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_leaves(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/leaves')->assertStatus(403);
    }

    public function test_user_without_permission_cannot_create_leave(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/leaves', [])->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Leave types
    // -----------------------------------------------------------------

    public function test_can_list_active_leave_types(): void
    {
        $this->makeType(['code' => 'ACT1', 'is_active' => true]);
        $this->makeType(['code' => 'INA1', 'is_active' => false]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/leaves/types');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('ACT1', $response->json('data.0.code'));
    }

    // -----------------------------------------------------------------
    // Creation
    // -----------------------------------------------------------------

    public function test_employee_can_create_leave_request(): void
    {
        $type = $this->makeType();
        $this->makeBalance($this->employee, $type);
        $dates = $this->futureDates(5, 2);

        Sanctum::actingAs($this->employeeUser);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => $type->id,
            'reason' => 'Family event',
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
        ]);
        foreach (\Illuminate\Support\Facades\DB::getQueryLog() as $q) {
            fwrite(STDERR, "DEBUG-CREATE-Q: ".$q['query']." | ".json_encode($q['bindings'])."\n");
        }
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $response->assertCreated()
            ->assertJsonPath('data.employee_id', $this->employee->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.duration_days', '3.00');

        // employee_id is derived from the acting user, no need to pass it
        $this->assertDatabaseHas('leaves', [
            'employee_id' => $this->employee->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('leave_requests', [
            'leave_id' => $response->json('data.id'),
            'action' => 'created',
            'new_status' => 'pending',
        ]);
    }

    public function test_admin_can_create_leave_on_behalf_of_employee(): void
    {
        $type = $this->makeType();
        $this->makeBalance($this->employee, $type);
        $dates = $this->futureDates(5, 1);

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/leaves', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'reason' => 'Planned by HR',
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.employee_id', $this->employee->id);
    }

    public function test_create_leave_requires_reason(): void
    {
        $type = $this->makeType();
        $dates = $this->futureDates();

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => $type->id,
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_create_leave_rejects_past_start_date(): void
    {
        $type = $this->makeType();

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => $type->id,
            'reason' => 'Too late',
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('start_date');
    }

    public function test_create_leave_rejects_end_before_start(): void
    {
        $type = $this->makeType();

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => $type->id,
            'reason' => 'Bad range',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_create_leave_rejects_invalid_type(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => 99999,
            'reason' => 'Nope',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('leave_type_id');
    }

    public function test_create_leave_requires_linked_employee(): void
    {
        // User without an employee record
        $user = User::factory()->create();
        $user->syncPermissions(Permission::whereIn('name', ['create leaves', 'view leaves'])->get());
        Sanctum::actingAs($user);

        $type = $this->makeType();

        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => $type->id,
            'reason' => 'Who am I?',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_create_leave_rejects_insufficient_balance(): void
    {
        $type = $this->makeType();
        $this->makeBalance($this->employee, $type, [
            'total_days' => 2,
            'used_days' => 0,
            'remaining_days' => 2,
        ]);

        // Asking for 3 days with only 2 remaining
        $dates = $this->futureDates(5, 2);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => $type->id,
            'reason' => 'Too long',
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('leaves', 0);
    }

    public function test_create_leave_rejects_overlap_with_existing_leave(): void
    {
        $type = $this->makeType();
        $this->createLeave($this->employee, $type, [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'status' => 'pending',
        ]);

        // Overlapping request
        $dates = [
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
        ];

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => $type->id,
            'reason' => 'Overlap',
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('leaves', 1);
    }

    public function test_create_leave_rejects_insufficient_notice(): void
    {
        $type = $this->makeType(['min_days_notice' => 30]);
        $this->makeBalance($this->employee, $type);

        // Starts in 5 days, needs 30
        $dates = $this->futureDates(5, 1);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => $type->id,
            'reason' => 'No notice',
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('leaves', 0);
    }

    public function test_create_leave_rejects_exceeding_max_days(): void
    {
        $type = $this->makeType(['max_days' => 2]);
        $this->makeBalance($this->employee, $type);

        // 3 days > max 2
        $dates = $this->futureDates(5, 2);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/leaves', [
            'leave_type_id' => $type->id,
            'reason' => 'Too long for this type',
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
        $this->assertDatabaseCount('leaves', 0);
    }

    // -----------------------------------------------------------------
    // Listing
    // -----------------------------------------------------------------

    public function test_can_list_leaves(): void
    {
        $type = $this->makeType();
        $other = Employee::factory()->create();
        $this->createLeave($this->employee, $type);
        $this->createLeave($other, $type, ['status' => 'approved']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/leaves');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'employee_id', 'leave_type_id', 'start_date', 'end_date', 'status'],
                    ],
                    'meta',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.meta.total'));
    }

    public function test_can_filter_leaves_by_status(): void
    {
        $type = $this->makeType();
        $this->createLeave($this->employee, $type, ['status' => 'pending']);
        $this->createLeave($this->employee, $type, ['status' => 'approved']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/leaves?status=pending');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.meta.total'));
        $this->assertEquals('pending', $response->json('data.data.0.status'));
    }

    public function test_can_filter_leaves_by_employee(): void
    {
        $type = $this->makeType();
        $other = Employee::factory()->create();
        $this->createLeave($this->employee, $type);
        $this->createLeave($other, $type);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/leaves?employee_id={$this->employee->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.meta.total'));
        $this->assertEquals($this->employee->id, $response->json('data.data.0.employee_id'));
    }

    public function test_can_get_pending_leaves(): void
    {
        $type = $this->makeType();
        $this->createLeave($this->employee, $type, ['status' => 'pending']);
        $this->createLeave($this->employee, $type, ['status' => 'rejected']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/leaves/pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('pending', $response->json('data.0.status'));
    }

    public function test_can_get_employee_leaves(): void
    {
        $type = $this->makeType();
        $this->createLeave($this->employee, $type);
        $other = Employee::factory()->create();
        $this->createLeave($other, $type);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/leaves/employee/{$this->employee->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->employee->id, $response->json('data.0.employee_id'));
    }

    public function test_can_get_balance(): void
    {
        $type = $this->makeType();
        $this->makeBalance($this->employee, $type);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/leaves/balance?employee_id={$this->employee->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(25.0, (float) $response->json('data.0.remaining_days'));
    }

    public function test_balance_requires_employee_id(): void
    {
        Sanctum::actingAs($this->adminUser);

        $this->getJson('/api/leaves/balance')
            ->assertStatus(422)
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_can_show_leave(): void
    {
        $type = $this->makeType();
        $leave = $this->createLeave($this->employee, $type);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/leaves/{$leave->id}");

        fwrite(STDERR, "DEBUG-SHOW-KEYS: ".json_encode(array_keys($response->json('data')))."\n");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $leave->id)
            ->assertJsonPath('data.employee.id', $this->employee->id)
            ->assertJsonPath('data.leaveType.code', $type->code);
    }

    // -----------------------------------------------------------------
    // Approval workflow
    // -----------------------------------------------------------------

    public function test_can_approve_leave(): void
    {
        $type = $this->makeType();
        $this->makeBalance($this->employee, $type);
        $leave = $this->createLeave($this->employee, $type, [
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
            'duration_days' => 3,
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/leaves/{$leave->id}/approve", [
            'comment' => 'Enjoy your holiday',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approval_comment', 'Enjoy your holiday');

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => 'approved',
            'approved_by' => $this->adminUser->id,
        ]);

        // Balance decreased
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'used_days' => 3.0,
            'remaining_days' => 22.0,
        ]);

        $this->assertDatabaseHas('leave_requests', [
            'leave_id' => $leave->id,
            'action' => 'approved',
            'new_status' => 'approved',
        ]);
    }

    public function test_approve_rejects_non_pending_leave(): void
    {
        $type = $this->makeType();
        $leave = $this->createLeave($this->employee, $type, ['status' => 'approved']);

        Sanctum::actingAs($this->adminUser);

        $this->patchJson("/api/leaves/{$leave->id}/approve")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_can_reject_leave(): void
    {
        $type = $this->makeType();
        $leave = $this->createLeave($this->employee, $type);

        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/leaves/{$leave->id}/reject", [
            'comment' => 'Team needs you',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.approval_comment', 'Team needs you');

        $this->assertDatabaseHas('leave_requests', [
            'leave_id' => $leave->id,
            'action' => 'rejected',
        ]);
    }

    public function test_can_cancel_pending_leave(): void
    {
        $type = $this->makeType();
        $leave = $this->createLeave($this->employee, $type);

        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/leaves/{$leave->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('leave_requests', [
            'leave_id' => $leave->id,
            'action' => 'cancelled',
        ]);
    }

    public function test_cancel_approved_leave_restores_balance(): void
    {
        $type = $this->makeType();
        $this->makeBalance($this->employee, $type);
        $leave = $this->createLeave($this->employee, $type, ['duration_days' => 4]);

        Sanctum::actingAs($this->adminUser);

        $this->patchJson("/api/leaves/{$leave->id}/approve")->assertStatus(200);
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'used_days' => 4.0,
            'remaining_days' => 21.0,
        ]);

        $this->deleteJson("/api/leaves/{$leave->id}")->assertStatus(200);

        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'used_days' => 0.0,
            'remaining_days' => 25.0,
        ]);
    }

    public function test_cancel_rejects_rejected_leave(): void
    {
        $type = $this->makeType();
        $leave = $this->createLeave($this->employee, $type, ['status' => 'rejected']);

        Sanctum::actingAs($this->adminUser);

        $this->deleteJson("/api/leaves/{$leave->id}")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_can_auto_approve_leave(): void
    {
        $type = $this->makeType();
        $this->makeBalance($this->employee, $type);
        $leave = $this->createLeave($this->employee, $type, ['duration_days' => 2]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/leaves/{$leave->id}/auto-approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approval_comment', 'Auto-approved by system');
    }

    public function test_auto_approve_fails_with_insufficient_balance(): void
    {
        $type = $this->makeType();
        $this->makeBalance($this->employee, $type, ['remaining_days' => 1]);
        $leave = $this->createLeave($this->employee, $type, ['duration_days' => 3]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/leaves/{$leave->id}/auto-approve");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('leaves', ['id' => $leave->id, 'status' => 'pending']);
    }

    // -----------------------------------------------------------------
    // Update
    // -----------------------------------------------------------------

    public function test_admin_can_update_pending_leave(): void
    {
        $type = $this->makeType();
        $this->makeBalance($this->employee, $type);
        $leave = $this->createLeave($this->employee, $type);

        $newStart = now()->addDays(20)->toDateString();
        $newEnd = now()->addDays(21)->toDateString();

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/leaves/{$leave->id}", [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'reason' => 'Rescheduled',
            'start_date' => $newStart,
            'end_date' => $newEnd,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.reason', 'Rescheduled');

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'start_date' => $newStart,
            'end_date' => $newEnd,
        ]);
    }

    public function test_update_rejects_non_pending_leave(): void
    {
        $type = $this->makeType();
        $leave = $this->createLeave($this->employee, $type, ['status' => 'approved']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/leaves/{$leave->id}", [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'reason' => 'Too late',
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(21)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // -----------------------------------------------------------------
    // Statistics & export
    // -----------------------------------------------------------------

    public function test_can_get_leave_statistics(): void
    {
        $type = $this->makeType();
        $year = (int) now()->format('Y');

        Leave::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->startOfYear()->addDays(10)->toDateString(),
            'end_date' => now()->startOfYear()->addDays(12)->toDateString(),
            'duration_days' => 3,
            'status' => 'approved',
        ]);
        Leave::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'duration_days' => 2,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/leaves/statistics?year={$year}");

        $response->assertStatus(200)
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.total_requests', 2)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.by_status.approved', 1);
    }

    public function test_can_export_leaves_as_csv(): void
    {
        $type = $this->makeType();
        $this->createLeave($this->employee, $type);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/leaves/export');

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ID', $response->streamedContent());
    }
}
