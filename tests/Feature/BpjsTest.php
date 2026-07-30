<?php

namespace Tests\Feature;

use App\Modules\Settings\Models\BpjsConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BpjsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bpjs_config_creation()
    {
        $config = BpjsConfig::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
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
        ]);

        $this->assertNotNull($config->id);
        $this->assertEquals(3.70, $config->jht_employer);
        $this->assertEquals(12000000, $config->max_wage_cap);
    }
}
