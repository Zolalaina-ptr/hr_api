<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $rhUser;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->rhUser = User::factory()->create();

        $this->adminUser->assignRole('admin');
        $this->rhUser->assignRole('rh_manager');

        $this->adminUser->syncPermissions(\App\Models\Permission::all());

        $rhPermissions = \App\Models\Permission::whereIn('name', [
            'view employees',
            'view departments',
            'view positions',
        ])->get();
        $this->rhUser->syncPermissions($rhPermissions);

        $this->department = Department::create([
            'name' => 'Test Department',
            'code' => 'TST',
            'description' => 'Test Description',
            'budget' => 100000,
            'is_active' => true,
        ]);
    }

    // -----------------------------------------------------------------
    // CRUD tests
    // -----------------------------------------------------------------

    public function test_guest_cannot_access_departments(): void
    {
        $response = $this->getJson('/api/departments');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_departments(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/departments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'code',
                        ],
                    ],
                    'meta',
                ],
            ]);
    }

    public function test_admin_can_create_department(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'name' => 'New Department',
            'code' => 'NEW',
            'description' => 'New department description',
            'budget' => 150000,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/departments', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'code',
                ],
            ])
            ->assertJson([
                'data' => [
                    'name' => 'New Department',
                    'code' => 'NEW',
                ],
            ]);

        $this->assertDatabaseHas('departments', [
            'name' => 'New Department',
            'code' => 'NEW',
        ]);
    }

    public function test_create_department_validation_fails_without_required_fields(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/departments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_admin_can_update_department(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'name' => 'Updated Department',
            'code' => $this->department->code,
            'budget' => 200000,
            'is_active' => false,
        ];

        $response = $this->putJson("/api/departments/{$this->department->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Department',
                    'budget' => '200000.00',
                ],
            ]);

        $this->assertDatabaseHas('departments', [
            'id' => $this->department->id,
            'name' => 'Updated Department',
        ]);
    }

    public function test_admin_can_delete_department_without_employees(): void
    {
        Sanctum::actingAs($this->adminUser);

        $department = Department::create([
            'name' => 'Empty Department',
            'code' => 'EMPTY',
            'budget' => 50000,
        ]);

        $response = $this->deleteJson("/api/departments/{$department->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_rh_user_without_create_permission_cannot_create_department(): void
    {
        Sanctum::actingAs($this->rhUser);

        $response = $this->postJson('/api/departments', [
            'name' => 'Forbidden',
            'code' => 'FRB',
        ]);

        $response->assertStatus(403);
    }

    public function test_filter_departments_by_is_active(): void
    {
        Sanctum::actingAs($this->adminUser);

        Department::create([
            'name' => 'Inactive',
            'code' => 'INA',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/departments?is_active=1');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_search_departments_by_name(): void
    {
        Sanctum::actingAs($this->adminUser);

        Department::create([
            'name' => 'Marketing Department',
            'code' => 'MKT',
        ]);

        $response = $this->getJson('/api/departments?search=Marketing');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    // -----------------------------------------------------------------
    // Hierarchy tests
    // -----------------------------------------------------------------

    public function test_can_get_department_hierarchy(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/departments/hierarchy');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'children',
                    ],
                ],
            ]);
    }

    public function test_hierarchy_includes_deeply_nested_children(): void
    {
        Sanctum::actingAs($this->adminUser);

        $root = Department::create(['name' => 'Root', 'code' => 'ROOT']);
        $mid = Department::create(['name' => 'Mid', 'code' => 'MID', 'parent_id' => $root->id]);
        $leaf = Department::create(['name' => 'Leaf', 'code' => 'LEAF', 'parent_id' => $mid->id]);

        $response = $this->getJson('/api/departments/hierarchy');

        $response->assertStatus(200);

        $tree = $response->json('data');
        $this->assertNotEmpty($tree);
        $found = false;
        foreach ($tree as $node) {
            if ($node['code'] === 'ROOT') {
                $found = true;
                $this->assertCount(1, $node['children']);
                $this->assertEquals('MID', $node['children'][0]['code']);
                $this->assertCount(1, $node['children'][0]['children']);
                $this->assertEquals('LEAF', $node['children'][0]['children'][0]['code']);
            }
        }
        $this->assertTrue($found, 'Root department not found in hierarchy');
    }

    public function test_hierarchy_includes_manager_information(): void
    {
        Sanctum::actingAs($this->adminUser);

        $manager = Employee::factory()->create();
        $dept = Department::create([
            'name' => 'Has Manager',
            'code' => 'HMGR',
            'manager_id' => $manager->id,
        ]);

        $response = $this->getJson('/api/departments/hierarchy');

        $response->assertStatus(200);

        $tree = $response->json('data');
        $found = null;
        foreach ($tree as $node) {
            if ($node['code'] === 'HMGR') {
                $found = $node;
            }
        }
        $this->assertNotNull($found);
        $this->assertNotNull($found['manager']);
        $this->assertEquals($manager->id, $found['manager']['id']);
    }

    public function test_can_assign_manager_to_department(): void
    {
        Sanctum::actingAs($this->adminUser);

        $manager = Employee::factory()->create();

        $response = $this->patchJson(
            "/api/departments/{$this->department->id}/manager",
            ['manager_id' => $manager->id]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('departments', [
            'id' => $this->department->id,
            'manager_id' => $manager->id,
        ]);
    }

    public function test_assign_manager_records_history(): void
    {
        Sanctum::actingAs($this->adminUser);

        $firstManager = Employee::factory()->create();
        $secondManager = Employee::factory()->create();

        $this->department->update(['manager_id' => $firstManager->id]);

        $this->patchJson(
            "/api/departments/{$this->department->id}/manager",
            ['manager_id' => $secondManager->id]
        )->assertStatus(200);

        $this->assertDatabaseHas('department_histories', [
            'department_id' => $this->department->id,
            'previous_manager_id' => $firstManager->id,
            'new_manager_id' => $secondManager->id,
        ]);
    }

    // -----------------------------------------------------------------
    // Budget tests
    // -----------------------------------------------------------------

    public function test_can_get_budget_overview(): void
    {
        Sanctum::actingAs($this->adminUser);

        $child = Department::create([
            'name' => 'Child',
            'code' => 'CHILD',
            'parent_id' => $this->department->id,
            'budget' => 50000,
        ]);

        $response = $this->getJson("/api/departments/{$this->department->id}/budget-overview");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'department_id',
                    'allocated_budget',
                    'total_budget',
                    'subtotal_budget',
                    'departments',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(100000.0, $data['allocated_budget']);
        $this->assertEquals(150000.0, $data['total_budget']);
    }

    public function test_budget_overview_handles_null_budget(): void
    {
        Sanctum::actingAs($this->adminUser);

        $dept = Department::create([
            'name' => 'No Budget',
            'code' => 'NB',
        ]);

        $response = $this->getJson("/api/departments/{$dept->id}/budget-overview");

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.allocated_budget'));
    }

    // -----------------------------------------------------------------
    // Cascade / relation tests
    // -----------------------------------------------------------------

    public function test_cannot_delete_department_with_active_employees(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory()->create([
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/departments/{$this->department->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('departments', [
            'id' => $this->department->id,
        ]);
    }

    public function test_can_delete_department_with_only_inactive_employees(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory()->create([
            'department_id' => $this->department->id,
            'status' => 'inactive',
        ]);

        $response = $this->deleteJson("/api/departments/{$this->department->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('departments', [
            'id' => $this->department->id,
        ]);
    }

    public function test_cannot_delete_department_with_child_departments(): void
    {
        Sanctum::actingAs($this->adminUser);

        Department::create([
            'name' => 'Sub',
            'code' => 'SUB',
            'parent_id' => $this->department->id,
        ]);

        $response = $this->deleteJson("/api/departments/{$this->department->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('departments', [
            'id' => $this->department->id,
        ]);
    }

    public function test_can_get_department_employees(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory(3)->create([
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/departments/{$this->department->id}/employees");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'first_name',
                            'last_name',
                        ],
                    ],
                    'meta',
                ],
            ]);

        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_can_filter_department_employees_by_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory(2)->create([
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'department_id' => $this->department->id,
            'status' => 'inactive',
        ]);

        $response = $this->getJson("/api/departments/{$this->department->id}/employees?status=active");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_can_get_department_statistics(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory(2)->create([
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);
        Position::factory()->create([
            'department_id' => $this->department->id,
        ]);

        $response = $this->getJson("/api/departments/{$this->department->id}/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'code',
                    'headcount',
                    'total_headcount',
                    'budget',
                    'positions_count',
                    'child_departments_count',
                    'is_active',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.headcount'));
        $this->assertEquals(1, $response->json('data.positions_count'));
    }

    public function test_headcount_includes_child_departments(): void
    {
        Sanctum::actingAs($this->adminUser);

        $child = Department::create([
            'name' => 'Child',
            'code' => 'CHILD2',
            'parent_id' => $this->department->id,
        ]);

        Employee::factory(2)->create([
            'department_id' => $child->id,
            'status' => 'active',
        ]);
        Employee::factory(1)->create([
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/departments/{$this->department->id}/headcount");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.total_headcount'));
    }

    public function test_unique_constraint_on_department_name(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'name' => $this->department->name,
            'code' => 'DUP',
            'budget' => 100000,
        ];

        $response = $this->postJson('/api/departments', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}