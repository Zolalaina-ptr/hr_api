<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveReplacement;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\User;
use App\Events\LeaveReplacementRequested;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveReplacementApiTest extends TestCase
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

    private function createLeave(array $overrides = []): Leave
    {
        return Leave::create(array_merge([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->makeType()->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'duration_days' => 5,
            'status' => 'approved',
        ], $overrides));
    }

    private function createReplacement(Leave $leave, ?Employee $replacementEmployee = null, array $overrides = []): LeaveReplacement
    {
        return LeaveReplacement::create(array_merge([
            'leave_id' => $leave->id,
            'original_employee_id' => $leave->employee_id,
            'replacement_employee_id' => ($replacementEmployee ?? Employee::factory()->create())->id,
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
            'status' => 'pending',
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------

    public function test_guest_cannot_access_leave_replacements(): void
    {
        $leave = $this->createLeave();

        $this->getJson("/api/leaves/{$leave->id}/replacements")->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_replacements(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $leave = $this->createLeave();

        $this->getJson("/api/leaves/{$leave->id}/replacements")->assertStatus(403);
    }

    public function test_user_without_permission_cannot_create_replacement(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $leave = $this->createLeave();

        $this->postJson("/api/leaves/{$leave->id}/replacements", [])
            ->assertStatus(403);
    }

    public function test_user_without_permission_cannot_accept_replacement(): void
    {
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave);

        // Has view/create leaves but not update leaves
        Sanctum::actingAs($this->employeeUser);

        $this->patchJson("/api/replacements/{$replacement->id}/accept")
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Listing & show
    // -----------------------------------------------------------------

    public function test_can_list_replacements_for_a_leave(): void
    {
        $leave = $this->createLeave();
        $this->createReplacement($leave);
        $this->createReplacement($leave);

        $otherLeave = $this->createLeave();
        $this->createReplacement($otherLeave);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/leaves/{$leave->id}/replacements");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'leave_id', 'original_employee_id', 'replacement_employee_id', 'start_date', 'end_date', 'status'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals($leave->id, $response->json('data.0.leave_id'));
    }

    public function test_can_filter_replacements_by_status(): void
    {
        $leave = $this->createLeave();
        $this->createReplacement($leave, null, ['status' => 'pending']);
        $this->createReplacement($leave, null, ['status' => 'accepted']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/leaves/{$leave->id}/replacements?status=accepted");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('accepted', $response->json('data.0.status'));
    }

    public function test_can_show_replacement(): void
    {
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/replacements/{$replacement->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $replacement->id)
            ->assertJsonPath('data.status', 'pending')
            // Laravel serializes relation keys in snake_case
            ->assertJsonPath('data.leave.id', $leave->id)
            ->assertJsonPath('data.original_employee.id', $this->employee->id)
            ->assertJsonPath('data.replacement_employee.id', $replacement->replacement_employee_id);
    }

    // -----------------------------------------------------------------
    // Creation
    // -----------------------------------------------------------------

    public function test_can_create_replacement_with_leave_period_defaults(): void
    {
        $leave = $this->createLeave();
        $replacementEmployee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $replacementEmployee->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.original_employee_id', $this->employee->id)
            ->assertJsonPath('data.replacement_employee_id', $replacementEmployee->id)
            ->assertJsonPath('data.start_date', $leave->start_date->format('Y-m-d').'T00:00:00.000000Z')
            ->assertJsonPath('data.end_date', $leave->end_date->format('Y-m-d').'T00:00:00.000000Z');

        $this->assertDatabaseHas('leave_replacements', [
            'leave_id' => $leave->id,
            'original_employee_id' => $this->employee->id,
            'replacement_employee_id' => $replacementEmployee->id,
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
            'status' => 'pending',
            'requested_by' => $this->adminUser->id,
        ]);
    }

    public function test_can_create_replacement_with_custom_period_and_responsibilities(): void
    {
        $leave = $this->createLeave();
        $replacementEmployee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $replacementEmployee->id,
            'start_date' => now()->addDays(11)->toDateString(),
            'end_date' => now()->addDays(13)->toDateString(),
            'responsibilities' => 'Handle daily standups and approvals',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.responsibilities', 'Handle daily standups and approvals');

        $this->assertDatabaseHas('leave_replacements', [
            'leave_id' => $leave->id,
            'start_date' => now()->addDays(11)->toDateString(),
            'end_date' => now()->addDays(13)->toDateString(),
        ]);
    }

    public function test_create_requires_replacement_employee_id(): void
    {
        $leave = $this->createLeave();

        Sanctum::actingAs($this->adminUser);

        $this->postJson("/api/leaves/{$leave->id}/replacements", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('replacement_employee_id');
    }

    public function test_create_rejects_unknown_replacement_employee(): void
    {
        $leave = $this->createLeave();

        Sanctum::actingAs($this->adminUser);

        $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => 99999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('replacement_employee_id');
    }

    public function test_create_rejects_replacement_employee_equal_to_leave_employee(): void
    {
        $leave = $this->createLeave();

        Sanctum::actingAs($this->adminUser);

        $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $this->employee->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('replacement_employee_id');
    }

    public function test_create_rejects_end_date_before_start_date(): void
    {
        $leave = $this->createLeave();
        $replacementEmployee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $replacementEmployee->id,
            'start_date' => now()->addDays(13)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_create_rejects_responsibilities_exceeding_2000_characters(): void
    {
        $leave = $this->createLeave();
        $replacementEmployee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $replacementEmployee->id,
            'responsibilities' => str_repeat('a', 2001),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('responsibilities');
    }

    public function test_create_rejects_when_accepted_replacement_already_exists(): void
    {
        $leave = $this->createLeave();
        $this->createReplacement($leave, null, ['status' => 'accepted']);
        $replacementEmployee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $replacementEmployee->id,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('leave_replacements', 1);
    }

    public function test_create_allows_multiple_pending_replacements(): void
    {
        $leave = $this->createLeave();
        $this->createReplacement($leave, null, ['status' => 'pending']);
        $this->createReplacement($leave, null, ['status' => 'declined']);
        $replacementEmployee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $replacementEmployee->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('leave_replacements', 3);
    }

    // -----------------------------------------------------------------
    // Workflow (state machine)
    // -----------------------------------------------------------------

    public function test_can_accept_pending_replacement(): void
    {
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave);

        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/replacements/{$replacement->id}/accept");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('leave_replacements', [
            'id' => $replacement->id,
            'status' => 'accepted',
            'approved_by' => $this->adminUser->id,
        ]);

        $this->assertDatabaseMissing('leave_replacements', [
            'id' => $replacement->id,
            'approved_at' => null,
        ]);
    }

    public function test_accept_rejects_non_pending_replacement(): void
    {
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave, null, ['status' => 'declined']);

        Sanctum::actingAs($this->adminUser);

        $this->patchJson("/api/replacements/{$replacement->id}/accept")
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('leave_replacements', [
            'id' => $replacement->id,
            'status' => 'declined',
        ]);
    }

    public function test_can_decline_pending_replacement(): void
    {
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave);

        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/replacements/{$replacement->id}/decline", [
            'reason' => 'Already covering another absence',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.rejection_reason', 'Already covering another absence');

        $this->assertDatabaseHas('leave_replacements', [
            'id' => $replacement->id,
            'status' => 'declined',
            'rejection_reason' => 'Already covering another absence',
            'approved_by' => $this->adminUser->id,
        ]);
    }

    public function test_decline_rejects_non_pending_replacement(): void
    {
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave, null, ['status' => 'accepted']);

        Sanctum::actingAs($this->adminUser);

        $this->patchJson("/api/replacements/{$replacement->id}/decline")
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('leave_replacements', [
            'id' => $replacement->id,
            'status' => 'accepted',
        ]);
    }

    public function test_can_cancel_pending_replacement(): void
    {
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave);

        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/replacements/{$replacement->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('leave_replacements', [
            'id' => $replacement->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_can_cancel_accepted_replacement(): void
    {
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave, null, ['status' => 'accepted']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/replacements/{$replacement->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('leave_replacements', [
            'id' => $replacement->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_rejects_terminal_replacement(): void
    {
        $leave = $this->createLeave();

        $declined = $this->createReplacement($leave, null, ['status' => 'declined']);
        $cancelled = $this->createReplacement($leave, null, ['status' => 'cancelled']);

        Sanctum::actingAs($this->adminUser);

        $this->deleteJson("/api/replacements/{$declined->id}")
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->deleteJson("/api/replacements/{$cancelled->id}")
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('leave_replacements', [
            'id' => $declined->id,
            'status' => 'declined',
        ]);
        $this->assertDatabaseHas('leave_replacements', [
            'id' => $cancelled->id,
            'status' => 'cancelled',
        ]);
    }

    // -----------------------------------------------------------------
    // Notifications
    // -----------------------------------------------------------------

    public function test_leave_replacement_requested_event_is_dispatched_on_creation(): void
    {
        Event::fake([LeaveReplacementRequested::class]);

        $leave = $this->createLeave();
        $replacementEmployee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $replacementEmployee->id,
        ])->assertCreated();

        Event::assertDispatched(LeaveReplacementRequested::class, fn ($e) => $e->replacement->leave_id === $leave->id
            && $e->replacement->replacement_employee_id === $replacementEmployee->id);
    }

    public function test_requesting_replacement_notifies_replacement_employee_user(): void
    {
        $leave = $this->createLeave();
        $replacementUser = User::factory()->create();
        $replacementEmployee = Employee::factory()->create([
            'user_id' => $replacementUser->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->adminUser);

        $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $replacementEmployee->id,
            'responsibilities' => 'Cover the support line',
        ])->assertCreated();

        $this->assertDatabaseCount('notifications', 1);

        $notification = $replacementUser->notifications()->first();

        $this->assertEquals('leave_replacement_requested', $notification->data['type']);
        $this->assertEquals($leave->id, $notification->data['leave_id']);
        $this->assertEquals($replacementEmployee->id, $notification->data['replacement_employee_id']);
        $this->assertEquals('Cover the support line', $notification->data['responsibilities']);
    }

    public function test_requesting_replacement_without_linked_user_creates_no_notification(): void
    {
        $leave = $this->createLeave();
        $replacementEmployee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $this->postJson("/api/leaves/{$leave->id}/replacements", [
            'replacement_employee_id' => $replacementEmployee->id,
        ])->assertCreated();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_accepting_replacement_notifies_requester(): void
    {
        $replacementUser = User::factory()->create();
        $replacementEmployee = Employee::factory()->create([
            'user_id' => $replacementUser->id,
            'status' => 'active',
        ]);
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave, $replacementEmployee, [
            'requested_by' => $this->employeeUser->id,
        ]);

        Sanctum::actingAs($this->adminUser);

        $this->patchJson("/api/replacements/{$replacement->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseCount('notifications', 1);

        $notification = $this->employeeUser->notifications()->first();

        $this->assertEquals('leave_replacement_accepted', $notification->data['type']);
        $this->assertEquals('pending', $notification->data['previous_status']);
        $this->assertEquals('accepted', $notification->data['status']);
    }

    public function test_declining_replacement_notifies_requester_with_reason(): void
    {
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave, null, [
            'requested_by' => $this->employeeUser->id,
        ]);

        Sanctum::actingAs($this->adminUser);

        $this->patchJson("/api/replacements/{$replacement->id}/decline", [
            'reason' => 'Out of office that week',
        ])->assertOk();

        $this->assertDatabaseCount('notifications', 1);

        $notification = $this->employeeUser->notifications()->first();

        $this->assertEquals('leave_replacement_declined', $notification->data['type']);
        $this->assertEquals('declined', $notification->data['status']);
        $this->assertEquals('Out of office that week', $notification->data['rejection_reason']);
    }

    public function test_cancelling_replacement_notifies_replacement_employee_user(): void
    {
        $replacementUser = User::factory()->create();
        $replacementEmployee = Employee::factory()->create([
            'user_id' => $replacementUser->id,
            'status' => 'active',
        ]);
        $leave = $this->createLeave();
        $replacement = $this->createReplacement($leave, $replacementEmployee);

        Sanctum::actingAs($this->adminUser);

        $this->deleteJson("/api/replacements/{$replacement->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseCount('notifications', 1);

        $notification = $replacementUser->notifications()->first();

        $this->assertEquals('leave_replacement_cancelled', $notification->data['type']);
        $this->assertEquals('pending', $notification->data['previous_status']);
        $this->assertEquals('cancelled', $notification->data['status']);
    }
}
