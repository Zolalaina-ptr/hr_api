<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EvaluationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        $this->adminUser->syncPermissions(Permission::all());
    }

    public function test_guest_cannot_create_evaluation(): void
    {
        $response = $this->postJson('/api/evaluations', []);

        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_create_evaluation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/evaluations', [
            'employee_id' => $employee->id,
            'evaluation_date' => '2026-09-03',
            'period_start' => '2026-01-01',
            'period_end' => '2026-06-30',
        ]);

        $response->assertStatus(403);
    }

    public function test_score_is_calculated_automatically(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/evaluations', [
            'employee_id' => $employee->id,
            'evaluation_date' => '2026-09-03',
            'period_start' => '2026-01-01',
            'period_end' => '2026-06-30',
            'skills_score' => 4,
            'soft_skills_score' => 3,
            'management_score' => 5,
        ]);

        // (4 + 3 + 5) / 3 = 4.00
        $response->assertCreated()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.overall_score', '4.00');
    }

    public function test_score_ignores_null_scores(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/evaluations', [
            'employee_id' => $employee->id,
            'evaluation_date' => '2026-09-03',
            'period_start' => '2026-01-01',
            'period_end' => '2026-06-30',
            'skills_score' => 5,
            'management_score' => 3,
        ]);

        // (5 + 3) / 2 = 4.00
        $response->assertCreated()
            ->assertJsonPath('data.overall_score', '4.00');
    }

    public function test_invalid_score_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/evaluations', [
            'employee_id' => $employee->id,
            'evaluation_date' => '2026-09-03',
            'period_start' => '2026-01-01',
            'period_end' => '2026-06-30',
            'skills_score' => 6,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('skills_score');
    }

    public function test_period_end_must_be_after_start(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/evaluations', [
            'employee_id' => $employee->id,
            'evaluation_date' => '2026-09-03',
            'period_start' => '2026-06-30',
            'period_end' => '2026-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('period_end');
    }
}
