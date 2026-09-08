<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
        $this->user->syncPermissions(Permission::all());
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->getJson('/api/dashboard/statistics')->assertStatus(401);
    }

    public function test_statistics_returns_headcount(): void
    {
        Employee::factory(3)->create(['status' => 'active']);
        Employee::factory()->create(['status' => 'inactive']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/dashboard/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'headcount',
                    'active_headcount',
                    'attendance_rate',
                    'pending_leaves',
                    'monthly_salary_mass',
                    'expiring_contracts',
                ],
            ])
            ->assertJsonPath('data.headcount', 4)
            ->assertJsonPath('data.active_headcount', 3)
            ->assertJsonPath('data.pending_leaves', 0);
    }

    public function test_headcount_endpoint(): void
    {
        Employee::factory(2)->create(['status' => 'active']);
        Employee::factory()->create(['status' => 'on_leave']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/dashboard/headcount');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.active', 2);
    }

    public function test_attendance_rate(): void
    {
        $employee = Employee::factory()->create(['status' => 'active']);

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => today(),
            'status' => 'present',
        ]);

        Sanctum::actingAs($this->user);

        // statistics() is cached for 5 minutes — clear it so the rate is recomputed
        Cache::flush();

        $response = $this->getJson('/api/dashboard/attendance');

        $response->assertStatus(200);
        // JSON drops the ".0" of whole-number floats, so compare numerically
        $this->assertSame(100.0, (float) $response->json('data.rate'));
    }

    public function test_attendance_rate_is_zero_without_attendance(): void
    {
        Employee::factory(2)->create(['status' => 'active']);

        Sanctum::actingAs($this->user);

        Cache::flush();

        $response = $this->getJson('/api/dashboard/attendance');

        $response->assertStatus(200)
            ->assertJsonPath('data.rate', 0);
    }

    public function test_demographics(): void
    {
        Employee::factory(2)->create(['gender' => 'male', 'contract_type' => 'cdi', 'status' => 'active']);
        Employee::factory()->create(['gender' => 'female', 'contract_type' => 'cdd', 'status' => 'inactive']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/dashboard/demographics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'by_gender',
                    'by_contract_type',
                    'by_status',
                ],
            ])
            ->assertJsonPath('data.by_gender.male', 2)
            ->assertJsonPath('data.by_gender.female', 1)
            ->assertJsonPath('data.by_contract_type.cdi', 2)
            ->assertJsonPath('data.by_status.active', 2)
            ->assertJsonPath('data.by_status.inactive', 1);
    }
}
