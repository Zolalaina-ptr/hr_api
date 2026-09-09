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

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        $this->adminUser->syncPermissions(Permission::all());

        $this->employee = Employee::factory()->create();
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

    // -----------------------------------------------------------------
    // Listing / show
    // -----------------------------------------------------------------

    public function test_can_list_payrolls(): void
    {
        $this->makePayroll();
        $this->makePayroll(['period_month' => 8]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/payrolls');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'employee_id', 'period_month', 'period_year', 'gross_pay', 'net_pay', 'status'],
                    ],
                    'meta',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.meta.total'));
    }

    public function test_can_filter_payrolls_by_employee(): void
    {
        $other = Employee::factory()->create();
        $this->makePayroll();
        $this->makePayroll(['employee_id' => $other->id]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/payrolls?employee_id={$this->employee->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.meta.total'));
        $this->assertEquals($this->employee->id, $response->json('data.data.0.employee_id'));
    }

    public function test_can_get_employee_payrolls(): void
    {
        $other = Employee::factory()->create();
        $this->makePayroll();
        $this->makePayroll(['employee_id' => $other->id]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/payrolls/employee/{$this->employee->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.meta.total'));
    }

    public function test_can_show_payroll(): void
    {
        $payroll = $this->makePayroll();

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/payrolls/{$payroll->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $payroll->id)
            ->assertJsonPath('data.employee.id', $this->employee->id);
    }

    // -----------------------------------------------------------------
    // Workflow: validate / pay
    // -----------------------------------------------------------------

    public function test_can_validate_payroll(): void
    {
        $payroll = $this->makePayroll();

        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/payrolls/{$payroll->id}/validate");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'validated');

        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'status' => 'validated']);
    }

    public function test_can_pay_payroll(): void
    {
        $payroll = $this->makePayroll();

        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/payrolls/{$payroll->id}/pay", [
            'payment_reference' => 'REF-2026-0901',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_reference', 'REF-2026-0901');

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => 'paid',
            'payment_reference' => 'REF-2026-0901',
        ]);
    }

    public function test_user_without_permission_cannot_validate_payroll(): void
    {
        $user = User::factory()->create();
        $payroll = $this->makePayroll();

        Sanctum::actingAs($user);

        $this->patchJson("/api/payrolls/{$payroll->id}/validate")->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------

    public function test_can_export_payrolls_as_csv(): void
    {
        $this->makePayroll();

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/payrolls/export');

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('employee_id', $response->streamedContent());
    }

    private function makePayroll(array $overrides = []): \App\Models\Payroll
    {
        return \App\Models\Payroll::create(array_merge([
            'employee_id' => $this->employee->id,
            'period_month' => 9,
            'period_year' => 2026,
            'base_salary' => 30000,
            'overtime_pay' => 0,
            'bonuses' => 0,
            'benefits_in_kind' => 0,
            'social_security_employee' => 300,
            'taxes' => 1500,
            'gross_pay' => 30000,
            'net_pay' => 28200,
            'status' => 'generated',
        ], $overrides));
    }
}
