<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class PayPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_can_list_pay_periods()
    {
        PayPeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Januari 2026',
            'period_year' => 2026,
            'period_month' => 1,
            'start_date' => '2025-12-25',
            'end_date' => '2026-01-24',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/payroll/periods');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'start_date', 'end_date', 'is_split', 'status']
            ]
        ]);
    }

    public function test_can_create_pay_period()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payroll/periods', [
                'name' => 'Juli 2026',
                'start_date' => '2026-06-25',
                'end_date' => '2026-07-24',
                'is_split' => false,
                'status' => 'active',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Pay period created successfully',
        ]);
        $this->assertDatabaseHas('pay_periods', [
            'name' => 'Juli 2026',
            'period_month' => 7,
        ]);
    }

    public function test_can_show_pay_period()
    {
        $period = PayPeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Januari 2026',
            'period_year' => 2026,
            'period_month' => 1,
            'start_date' => '2025-12-25',
            'end_date' => '2026-01-24',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/payroll/periods/{$period->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Januari 2026');
    }

    public function test_can_delete_pay_period()
    {
        $period = PayPeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Januari 2026',
            'period_year' => 2026,
            'period_month' => 1,
            'start_date' => '2025-12-25',
            'end_date' => '2026-01-24',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/payroll/periods/{$period->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($period);
    }
}
