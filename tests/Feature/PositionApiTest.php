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

class PositionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Department $department;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        $this->adminUser->syncPermissions(\App\Models\Permission::all());

        $this->department = Department::create([
            'name' => 'IT Department',
            'code' => 'IT',
            'budget' => 300000,
        ]);

        $this->position = Position::create([
            'title' => 'Software Developer',
            'code' => 'DEV',
            'description' => 'Full stack developer',
            'level' => 'mid',
            'department_id' => $this->department->id,
            'min_salary' => 40000,
            'max_salary' => 60000,
        ]);
    }

    // -----------------------------------------------------------------
    // CRUD tests
    // -----------------------------------------------------------------

    public function test_guest_cannot_access_positions(): void
    {
        $response = $this->getJson('/api/positions');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_positions(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/positions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'code',
                            'level',
                            'min_salary',
                            'max_salary',
                        ],
                    ],
                    'meta',
                ],
            ]);
    }

    public function test_can_filter_positions_by_level(): void
    {
        Sanctum::actingAs($this->adminUser);

        Position::create([
            'title' => 'Senior Dev',
            'code' => 'SR',
            'level' => 'senior',
            'department_id' => $this->department->id,
            'min_salary' => 60000,
            'max_salary' => 85000,
        ]);

        $response = $this->getJson('/api/positions?level=mid');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'meta',
                ],
            ]);

        foreach ($response->json('data.data') as $position) {
            $this->assertEquals('mid', $position['level']);
        }
    }

    public function test_can_search_positions(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/positions?search=Developer');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data.data')));
    }

    public function test_admin_can_create_position(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'title' => 'Product Manager',
            'code' => 'PM',
            'description' => 'Product management role',
            'level' => 'manager',
            'department_id' => $this->department->id,
            'min_salary' => 60000,
            'max_salary' => 90000,
        ];

        $response = $this->postJson('/api/positions', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'code',
                ],
            ])
            ->assertJson([
                'data' => [
                    'title' => 'Product Manager',
                    'code' => 'PM',
                    'level' => 'manager',
                ],
            ]);

        $this->assertDatabaseHas('positions', [
            'title' => 'Product Manager',
            'code' => 'PM',
        ]);
    }

    public function test_create_position_validation_fails_without_required_fields(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/positions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'code', 'level', 'department_id', 'min_salary', 'max_salary']);
    }

    public function test_create_position_rejects_invalid_level(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/positions', [
            'title' => 'Test',
            'code' => 'TST',
            'level' => 'intern',
            'department_id' => $this->department->id,
            'min_salary' => 10000,
            'max_salary' => 15000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('level');
    }

    public function test_admin_can_update_position(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'title' => 'Senior Developer',
            'code' => $this->position->code,
            'level' => 'senior',
            'department_id' => $this->department->id,
            'min_salary' => 60000,
            'max_salary' => 85000,
        ];

        $response = $this->putJson("/api/positions/{$this->position->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'title' => 'Senior Developer',
                    'level' => 'senior',
                ],
            ]);

        $this->assertDatabaseHas('positions', [
            'id' => $this->position->id,
            'title' => 'Senior Developer',
        ]);
    }

    public function test_admin_can_delete_position_without_employees(): void
    {
        Sanctum::actingAs($this->adminUser);

        $position = Position::create([
            'title' => 'Vacant Position',
            'code' => 'VAC',
            'level' => 'junior',
            'department_id' => $this->department->id,
            'min_salary' => 25000,
            'max_salary' => 35000,
        ]);

        $response = $this->deleteJson("/api/positions/{$position->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('positions', [
            'id' => $position->id,
        ]);
    }

    // -----------------------------------------------------------------
    // Hierarchy / relations tests
    // -----------------------------------------------------------------

    public function test_assign_position_to_department(): void
    {
        Sanctum::actingAs($this->adminUser);

        $otherDept = Department::create([
            'name' => 'Sales',
            'code' => 'SALES',
        ]);

        $response = $this->patchJson(
            "/api/positions/{$this->position->id}/department",
            ['department_id' => $otherDept->id]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('positions', [
            'id' => $this->position->id,
            'department_id' => $otherDept->id,
        ]);
    }

    public function test_assign_position_to_invalid_department_fails(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson(
            "/api/positions/{$this->position->id}/department",
            ['department_id' => 99999]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('department_id');
    }

    public function test_can_get_position_requirements(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/positions/{$this->position->id}/requirements");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    public function test_get_positions_by_department(): void
    {
        Sanctum::actingAs($this->adminUser);

        $otherDept = Department::create([
            'name' => 'Other',
            'code' => 'OTH',
        ]);
        Position::create([
            'title' => 'Other Position',
            'code' => 'OP',
            'level' => 'junior',
            'department_id' => $otherDept->id,
            'min_salary' => 25000,
            'max_salary' => 35000,
        ]);

        $response = $this->getJson("/api/positions?department_id={$this->department->id}");

        $response->assertStatus(200);
        foreach ($response->json('data.data') as $position) {
            $this->assertEquals($this->department->id, $position['department_id']);
        }
    }

    // -----------------------------------------------------------------
    // Salary range / budget-style tests
    // -----------------------------------------------------------------

    public function test_can_get_positions_by_salary_range(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/positions/salary-range?min_salary=35000&max_salary=70000');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    public function test_salary_range_validation(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/positions/salary-range?min_salary=70000&max_salary=35000');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('max_salary');
    }

    public function test_get_level_salary_stats(): void
    {
        Sanctum::actingAs($this->adminUser);

        Position::create([
            'title' => 'Another Mid',
            'code' => 'MID2',
            'level' => 'mid',
            'department_id' => $this->department->id,
            'min_salary' => 45000,
            'max_salary' => 65000,
        ]);

        $response = $this->getJson('/api/positions/level/mid');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'level',
                    'count',
                    'min_salary',
                    'max_salary',
                    'average_min_salary',
                    'average_max_salary',
                ],
            ]);

        $this->assertEquals('mid', $response->json('data.level'));
        $this->assertEquals(2, $response->json('data.count'));
    }

    public function test_get_all_salary_ranges(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/positions/salary-ranges');

        $response->assertStatus(200);
        $this->assertCount(6, $response->json('data'));
    }

    public function test_get_available_positions_excludes_filled(): void
    {
        Sanctum::actingAs($this->adminUser);

        $available = Position::create([
            'title' => 'Available',
            'code' => 'AVL',
            'level' => 'junior',
            'department_id' => $this->department->id,
            'min_salary' => 25000,
            'max_salary' => 35000,
        ]);

        $filled = Position::create([
            'title' => 'Filled',
            'code' => 'FIL',
            'level' => 'junior',
            'department_id' => $this->department->id,
            'min_salary' => 25000,
            'max_salary' => 35000,
        ]);

        Employee::factory()->create([
            'department_id' => $this->department->id,
            'position_id' => $filled->id,
        ]);

        $response = $this->getJson('/api/positions/available');

        $response->assertStatus(200);
        $codes = array_column($response->json('data'), 'code');
        $this->assertContains('AVL', $codes);
        $this->assertNotContains('FIL', $codes);
    }

    public function test_get_position_statistics(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory(2)->create([
            'position_id' => $this->position->id,
            'department_id' => $this->department->id,
        ]);

        $response = $this->getJson("/api/positions/{$this->position->id}/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'code',
                    'level',
                    'department',
                    'salary_range',
                    'assigned_employees',
                    'employee_names',
                    'requirements_count',
                    'is_active',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.assigned_employees'));
    }

    // -----------------------------------------------------------------
    // Cascade / relation tests
    // -----------------------------------------------------------------

    public function test_cannot_delete_position_with_assigned_employees(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory()->create([
            'position_id' => $this->position->id,
            'department_id' => $this->department->id,
        ]);

        $response = $this->deleteJson("/api/positions/{$this->position->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('positions', [
            'id' => $this->position->id,
        ]);
    }

    public function test_can_get_position_employees(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory(2)->create([
            'position_id' => $this->position->id,
            'department_id' => $this->department->id,
        ]);

        $response = $this->getJson("/api/positions/{$this->position->id}/employees");

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

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_can_filter_position_employees_by_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory(2)->create([
            'position_id' => $this->position->id,
            'department_id' => $this->department->id,
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'position_id' => $this->position->id,
            'department_id' => $this->department->id,
            'status' => 'inactive',
        ]);

        $response = $this->getJson("/api/positions/{$this->position->id}/employees?status=active");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_unique_constraint_on_position_title(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'title' => $this->position->title,
            'code' => 'DUP',
            'level' => 'mid',
            'department_id' => $this->department->id,
            'min_salary' => 40000,
            'max_salary' => 60000,
        ];

        $response = $this->postJson('/api/positions', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_max_salary_must_be_gte_min_salary(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'title' => 'Test Position',
            'code' => 'TST',
            'level' => 'mid',
            'department_id' => $this->department->id,
            'min_salary' => 60000,
            'max_salary' => 40000,
        ];

        $response = $this->postJson('/api/positions', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('max_salary');
    }
}