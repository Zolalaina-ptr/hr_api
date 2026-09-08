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

class PayrollApiTest extends TestCase
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

    public function test_guest_cannot_generate_payroll(): void
    {
        $response = $this->postJson('/api/payrolls', []);

        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_generate_payroll(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/payrolls', [
            'employee_id' => $employee->id,
            'period_month' => 9,
            'period_year' => 2026,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_generate_payroll(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employee = Employee::factory()->create(['base_salary' => 30000]);

        $response = $this->postJson('/api/payrolls', [
            'employee_id' => $employee->id,
            'period_month' => 9,
            'period_year' => 2026,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.net_pay', 28200)
            ->assertJsonPath('data.status', 'generated');

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee->id,
            'period_month' => 9,
            'period_year' => 2026,
        ]);
    }

    public function test_payroll_includes_overtime_and_bonuses(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employee = Employee::factory()->create(['base_salary' => 10000]);

        $response = $this->postJson('/api/payrolls', [
            'employee_id' => $employee->id,
            'period_month' => 3,
            'period_year' => 2026,
            'overtime_pay' => 1000,
            'bonuses' => 500,
        ]);

        // gross = 10000 + 1000 + 500 = 11500
        // social security = 115.00 (1%), taxes = 575.00 (5%)
        // net = 11500 - 115 - 575 = 10810
        $response->assertCreated()
            ->assertJsonPath('data.gross_pay', 11500)
            ->assertJsonPath('data.net_pay', 10810);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/payrolls', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id', 'period_month', 'period_year']);
    }

    public function test_duplicate_period_is_rejected_with_validation_error(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employee = Employee::factory()->create();

        $data = [
            'employee_id' => $employee->id,
            'period_month' => 1,
            'period_year' => 2026,
        ];

        $this->postJson('/api/payrolls', $data)->assertCreated();

        $response = $this->postJson('/api/payrolls', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('period_month');

        $this->assertDatabaseCount('payrolls', 1);
    }
}
