<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $viewerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        $this->adminUser->syncPermissions(Permission::all());

        $this->viewerUser = User::factory()->create();
        $this->viewerUser->assignRole('rh_manager');
        $this->viewerUser->syncPermissions(Permission::whereIn('name', [
            'view employees',
            'view departments',
            'view positions',
        ])->get());
    }

    // -----------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------

    public function test_guest_cannot_access_employees(): void
    {
        $this->getJson('/api/employees')->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_employees(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/employees')->assertStatus(403);
    }

    public function test_user_without_permission_cannot_create_employee(): void
    {
        Sanctum::actingAs($this->viewerUser);

        $this->postJson('/api/employees', [
            'registration_number' => 'EMP-9999',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test9999@company.com',
            'hiring_date' => '2026-01-01',
        ])->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Listing
    // -----------------------------------------------------------------

    public function test_can_list_employees(): void
    {
        Employee::factory(3)->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/employees');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'registration_number',
                            'first_name',
                            'last_name',
                            'email',
                            'department_id',
                            'position_id',
                        ],
                    ],
                    'meta',
                ],
            ]);

        $this->assertEquals(3, $response->json('data.meta.total'));
    }

    public function test_list_includes_department_and_position(): void
    {
        $department = Department::create(['name' => 'Dev', 'code' => 'DEV']);
        $position = Position::factory()->create(['department_id' => $department->id]);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/employees');

        $this->assertEquals(
            [$department->name, $position->title],
            [$response->json('data.data.0.department.name'), $response->json('data.data.0.position.title')]
        );
    }

    public function test_can_filter_employees_by_status(): void
    {
        Employee::factory(2)->create(['status' => 'active']);
        Employee::factory()->create(['status' => 'inactive']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/employees?status=active');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.meta.total'));
    }

    public function test_can_filter_employees_by_department(): void
    {
        $department = Department::create(['name' => 'Sales', 'code' => 'SLS']);
        Employee::factory(2)->create(['department_id' => $department->id]);
        Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/employees?department_id={$department->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.meta.total'));
    }

    public function test_can_search_employees(): void
    {
        Employee::factory()->create([
            'first_name' => 'Marguerite',
            'last_name' => 'Curie',
            'email' => 'marguerite.curie@company.com',
            'registration_number' => 'EMP-0001',
        ]);
        Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        foreach (['Marguerite', 'CURIE', 'marguerite.curie', 'EMP-0001'] as $term) {
            $response = $this->getJson("/api/employees?search={$term}");
            $response->assertStatus(200);
            $this->assertEquals(
                1,
                $response->json('data.meta.total'),
                "Search for {$term} should return 1 employee"
            );
        }
    }

    public function test_employees_are_paginated(): void
    {
        Employee::factory(25)->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/employees?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data.data'));
        $this->assertEquals(25, $response->json('data.meta.total'));
        $this->assertEquals(3, $response->json('data.meta.last_page'));
    }

    // -----------------------------------------------------------------
    // Creation
    // -----------------------------------------------------------------

    public function test_can_create_employee(): void
    {
        $department = Department::create(['name' => 'Dev', 'code' => 'DEV']);
        $position = Position::factory()->create(['department_id' => $department->id]);

        Sanctum::actingAs($this->adminUser);

        $payload = [
            'registration_number' => 'EMP-1234',
            'first_name' => 'Marie',
            'last_name' => 'Curie',
            'email' => 'marie.curie@company.com',
            'phone' => '+33 6 12 34 56 78',
            'gender' => 'female',
            'hiring_date' => '2026-01-15',
            'contract_type' => 'cdi',
            'status' => 'active',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'base_salary' => 45000,
        ];

        $response = $this->postJson('/api/employees', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.registration_number', 'EMP-1234')
            ->assertJsonPath('data.first_name', 'Marie')
            ->assertJsonPath('data.department.id', $department->id)
            ->assertJsonPath('data.position.id', $position->id);

        $this->assertDatabaseHas('employees', [
            'registration_number' => 'EMP-1234',
            'email' => 'marie.curie@company.com',
        ]);
    }

    public function test_create_employee_validation_fails_without_required_fields(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/employees', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['registration_number', 'first_name', 'last_name', 'email', 'hiring_date']);
    }

    public function test_create_employee_rejects_duplicate_email(): void
    {
        Employee::factory()->create(['email' => 'taken@company.com']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/employees', [
            'registration_number' => 'EMP-5555',
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'taken@company.com',
            'hiring_date' => '2026-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_create_employee_rejects_duplicate_registration_number(): void
    {
        Employee::factory()->create(['registration_number' => 'EMP-7777']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/employees', [
            'registration_number' => 'EMP-7777',
            'first_name' => 'Copy',
            'last_name' => 'Cat',
            'email' => 'copycat@company.com',
            'hiring_date' => '2026-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('registration_number');
    }

    public function test_create_employee_rejects_invalid_department(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/employees', [
            'registration_number' => 'EMP-8888',
            'first_name' => 'Nope',
            'last_name' => 'Dept',
            'email' => 'nopedept@company.com',
            'hiring_date' => '2026-01-01',
            'department_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('department_id');
    }

    // -----------------------------------------------------------------
    // Show
    // -----------------------------------------------------------------

    public function test_can_show_employee(): void
    {
        $employee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/employees/{$employee->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.email', $employee->email);
    }

    public function test_show_employee_includes_relations(): void
    {
        $department = Department::create(['name' => 'Dev', 'code' => 'DEV']);
        $position = Position::factory()->create(['department_id' => $department->id]);
        $manager = Employee::factory()->create();
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => $manager->id,
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/employees/{$employee->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.department.name', $department->name)
            ->assertJsonPath('data.position.title', $position->title)
            ->assertJsonPath('data.manager.id', $manager->id);
    }

    // -----------------------------------------------------------------
    // Update
    // -----------------------------------------------------------------

    public function test_can_update_employee(): void
    {
        $employee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'first_name' => 'Updated',
            'phone' => '+33 6 99 88 77 66',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.first_name', 'Updated')
            ->assertJsonPath('data.phone', '+33 6 99 88 77 66');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Updated',
        ]);
    }

    public function test_update_rejects_self_manager(): void
    {
        $employee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'manager_id' => $employee->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('manager_id');
    }

    public function test_update_records_history_on_position_change(): void
    {
        $department = Department::create(['name' => 'Dev', 'code' => 'DEV']);
        $positionA = Position::factory()->create(['department_id' => $department->id, 'title' => 'Test Position A']);
        $positionB = Position::factory()->create(['department_id' => $department->id, 'title' => 'Test Position B']);
        $employee = Employee::factory()->create([
            'position_id' => $positionA->id,
            'base_salary' => 40000,
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'position_id' => $positionB->id,
            'change_reason' => 'Promotion',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employee_histories', [
            'employee_id' => $employee->id,
            'previous_position' => $positionA->title,
            'new_position' => $positionB->title,
            'previous_salary' => '40000.00',
            'new_salary' => '40000.00',
            'change_reason' => 'Promotion',
            'changed_by' => $this->adminUser->id,
        ]);
    }

    public function test_update_records_history_on_salary_change(): void
    {
        $employee = Employee::factory()->create(['base_salary' => 40000]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'base_salary' => 44000,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employee_histories', [
            'employee_id' => $employee->id,
            'previous_salary' => '40000.00',
            'new_salary' => '44000.00',
        ]);
    }

    public function test_update_creates_no_history_on_unrelated_change(): void
    {
        $employee = Employee::factory()->create(['base_salary' => 40000]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'phone' => '+33 6 00 00 00 00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('employee_histories', 0);
    }

    // -----------------------------------------------------------------
    // Deletion (soft)
    // -----------------------------------------------------------------

    public function test_can_delete_employee(): void
    {
        $employee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_deleted_employee_returns_404(): void
    {
        $employee = Employee::factory()->create();

        Sanctum::actingAs($this->adminUser);

        $this->deleteJson("/api/employees/{$employee->id}")->assertStatus(200);

        $this->getJson("/api/employees/{$employee->id}")->assertStatus(404);
    }

    // -----------------------------------------------------------------
    // History
    // -----------------------------------------------------------------

    public function test_can_get_employee_history(): void
    {
        $department = Department::create(['name' => 'Dev', 'code' => 'DEV']);
        $positionA = Position::factory()->create(['department_id' => $department->id, 'title' => 'History Position A']);
        $positionB = Position::factory()->create(['department_id' => $department->id, 'title' => 'History Position B']);
        $employee = Employee::factory()->create([
            'position_id' => $positionA->id,
            'base_salary' => 40000,
        ]);

        Sanctum::actingAs($this->adminUser);

        $this->putJson("/api/employees/{$employee->id}", [
            'position_id' => $positionB->id,
            'base_salary' => 42000,
            'change_reason' => 'Promotion',
        ])->assertStatus(200);

        $response = $this->getJson("/api/employees/{$employee->id}/history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'effective_date',
                            'previous_position',
                            'new_position',
                            'previous_salary',
                            'new_salary',
                            'change_reason',
                        ],
                    ],
                    'meta',
                ],
            ]);

        $this->assertEquals(1, $response->json('data.meta.total'));
        $this->assertEquals($positionB->title, $response->json('data.data.0.new_position'));
    }
}
