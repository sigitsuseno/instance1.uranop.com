<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Modules\Auth\Models\User;

class BpjsTest extends TestCase
{
    public function test_create_bpjs()
    {
        $user = User::first() ?? User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/settings/bpjs-configs', [
            'effective_date' => '2026-06-15',
            'jht_employer' => 3.70,
            'jht_employee' => 2.00,
            'jkk' => 0.24,
            'jkm' => 0.30,
            'jp_employer' => 2.00,
            'jp_employee' => 1.00,
            'kesehatan_employer' => 4.00,
            'kesehatan_employee' => 1.00,
            'max_wage_cap' => 12000000,
            'description' => '',
        ]);

        $response->dump();
    }
}
