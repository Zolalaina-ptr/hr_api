<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractApiTest extends TestCase
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

    // -----------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------

    public function test_guest_cannot_access_contracts(): void
    {
        $this->getJson('/api/contracts')->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_contracts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/contracts')->assertStatus(403);
    }

    public function test_user_without_permission_cannot_create_contract(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/contracts', [])->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Listing
    // -----------------------------------------------------------------

    public function test_can_list_contracts(): void
    {
        $this->makeContract();
        $this->makeContract(['status' => 'terminated']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/contracts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'employee_id', 'type', 'start_date', 'gross_annual_salary', 'status'],
                    ],
                    'meta',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.meta.total'));
    }

    public function test_can_filter_contracts_by_status(): void
    {
        $this->makeContract(['status' => 'active']);
        $this->makeContract(['status' => 'terminated']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/contracts?status=active');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.meta.total'));
        $this->assertEquals('active', $response->json('data.data.0.status'));
    }

    public function test_can_filter_contracts_by_employee(): void
    {
        $other = Employee::factory()->create();
        $this->makeContract();
        $this->makeContract(['employee_id' => $other->id]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/contracts?employee_id={$this->employee->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.meta.total'));
        $this->assertEquals($this->employee->id, $response->json('data.data.0.employee_id'));
    }

    public function test_can_get_employee_contracts(): void
    {
        $other = Employee::factory()->create();
        $this->makeContract();
        $this->makeContract(['employee_id' => $other->id]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/contracts/employee/{$this->employee->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_get_expiring_contracts(): void
    {
        // Expires in 10 days (within the 30-day window)
        $this->makeContract(['end_date' => now()->addDays(10)->toDateString(), 'status' => 'active']);
        // Expires in 60 days (outside the window)
        $this->makeContract(['end_date' => now()->addDays(60)->toDateString(), 'status' => 'active']);
        // Already terminated
        $this->makeContract(['end_date' => now()->addDays(5)->toDateString(), 'status' => 'terminated']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/contracts/expiring?days=30');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        // date cast serializes as ISO datetime — compare the date part only
        $this->assertStringStartsWith(
            now()->addDays(10)->toDateString(),
            $response->json('data.0.end_date')
        );
    }

    // -----------------------------------------------------------------
    // Creation
    // -----------------------------------------------------------------

    public function test_can_create_contract(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/contracts', [
            'employee_id' => $this->employee->id,
            'type' => 'cdi',
            'start_date' => '2026-01-01',
            'end_date' => null,
            'gross_annual_salary' => 52000,
            'fixed_bonus' => 2000,
            'variable_bonus' => 1000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.employee_id', $this->employee->id)
            ->assertJsonPath('data.type', 'cdi')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('contracts', [
            'employee_id' => $this->employee->id,
            'type' => 'cdi',
            'gross_annual_salary' => 52000,
        ]);
    }

    public function test_create_contract_validation_fails_without_required_fields(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/contracts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id', 'type', 'start_date', 'gross_annual_salary']);
    }

    public function test_create_contract_rejects_invalid_type(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/contracts', [
            'employee_id' => $this->employee->id,
            'type' => 'permanent',
            'start_date' => '2026-01-01',
            'gross_annual_salary' => 40000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_create_contract_rejects_end_before_start(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/contracts', [
            'employee_id' => $this->employee->id,
            'type' => 'cdd',
            'start_date' => '2026-06-01',
            'end_date' => '2026-01-01',
            'gross_annual_salary' => 40000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    // -----------------------------------------------------------------
    // Show / update / terminate
    // -----------------------------------------------------------------

    public function test_can_show_contract(): void
    {
        $contract = $this->makeContract();

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/contracts/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $contract->id)
            ->assertJsonPath('data.employee.id', $this->employee->id);
    }

    public function test_can_update_contract(): void
    {
        $contract = $this->makeContract(['gross_annual_salary' => 40000]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/contracts/{$contract->id}", [
            'employee_id' => $this->employee->id,
            'type' => 'cdd',
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'gross_annual_salary' => 46000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.type', 'cdd')
            ->assertJsonPath('data.gross_annual_salary', '46000.00');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'type' => 'cdd',
            'gross_annual_salary' => 46000,
        ]);
    }

    public function test_destroy_terminates_contract(): void
    {
        $contract = $this->makeContract(['status' => 'active']);

        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/contracts/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'terminated');

        // Terminated, not deleted
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => 'terminated',
        ]);
        $this->assertNotNull($contract->fresh()->termination_date);
    }

    private function makeContract(array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'employee_id' => $this->employee->id,
            'type' => 'cdi',
            'start_date' => '2026-01-01',
            'end_date' => null,
            'gross_annual_salary' => 45000,
            'status' => 'active',
        ], $overrides));
    }
}
