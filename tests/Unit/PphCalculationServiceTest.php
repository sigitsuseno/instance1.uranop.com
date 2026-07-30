<?php

namespace Tests\Unit;

use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\EmployeePph;
use App\Modules\Payroll\Services\PphCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class PphCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PphCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PphCalculationService::class);
    }

    private function createEmployee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Employee',
            'employee_code' => 'EMP-' . Str::random(6),
            'gender' => 'L',
            'employment_status' => 'contract',
            'payroll_cycle' => 'monthly',
            'ptkp' => 'TK/0',
            'has_npwp' => true,
            'join_date' => '2024-01-01',
            'is_active' => true,
        ], $overrides));
    }

    private function createPayPeriod(array $overrides = []): PayPeriod
    {
        return PayPeriod::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Januari 2026',
            'period_year' => 2026,
            'period_month' => 1,
            'is_split' => false,
            'status' => 'active',
            'start_date' => '2025-12-25',
            'end_date' => '2026-01-24',
        ], $overrides));
    }

    public function test_returns_zero_for_part_1()
    {
        $employee = $this->createEmployee();
        $period = $this->createPayPeriod(['is_split' => true]);

        $result = $this->service->calculate(
            $employee, $period,
            5_000_000, 500_000, 200_000, 100_000, 4_500_000,
            true // isPart1
        );

        $this->assertEquals(0, $result);
    }

    public function test_returns_existing_employee_pph_record_value()
    {
        $employee = $this->createEmployee();
        $period = $this->createPayPeriod(['is_split' => false]);

        EmployeePph::create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'pay_period_id' => $period->id,
            'pph_deducted' => 150_000,
            'pph_amount' => 150_000,
            'npwp' => '12.345.678.9-012.345',
            'has_npwp' => true,
            'ptkp_status' => 'TK/0',
            'gaji_pokok' => 5_000_000,
            'gross_income' => 7_000_000,
            'netto_income' => 6_500_000,
            'annualized_income' => 78_000_000,
            'pkp' => 24_000_000,
            'pph_rate' => 0.25,
            'calculation_method' => 'ter',
            'pph_method' => 'gross',
        ]);

        $result = $this->service->calculate(
            $employee, $period,
            5_000_000, 500_000, 200_000, 100_000, 5_000_000,
            false
        );

        $this->assertEquals(150_000, $result);
    }

    public function test_calculates_pph_using_ter_method()
    {
        $employee = $this->createEmployee(['ptkp' => 'TK/0']);
        $period = $this->createPayPeriod(['is_split' => false]);

        // Gross: 7_000_000 → Category A → TER 1.25% → 87_500
        $result = $this->service->calculate(
            $employee, $period,
            5_000_000, 1_000_000, 500_000, 500_000, 5_000_000,
            false
        );

        $this->assertEquals(87_500, $result);
    }

    public function test_applies_non_npwp_penalty()
    {
        $employee = $this->createEmployee(['ptkp' => 'TK/0', 'has_npwp' => false]);
        $period = $this->createPayPeriod(['is_split' => false]);

        // Gross: 7_000_000 → TER A 1.25% → 87_500 × 1.2 = 105_000
        $result = $this->service->calculate(
            $employee, $period,
            5_000_000, 1_000_000, 500_000, 500_000, 5_000_000,
            false
        );

        $this->assertEquals(105_000, $result);
    }

    public function test_returns_zero_for_zero_income()
    {
        $employee = $this->createEmployee();
        $period = $this->createPayPeriod();

        $result = $this->service->calculate(
            $employee, $period,
            0, 0, 0, 0, 0,
            false
        );

        $this->assertEquals(0, $result);
    }

    public function test_splits_pph_for_split_period()
    {
        $employee = $this->createEmployee(['ptkp' => 'K/3']);
        $period = $this->createPayPeriod(['is_split' => true]);

        // Category C, gross 12_000_000 → TER C 1.75% → 210_000 / 2 = 105_000
        $result = $this->service->calculate(
            $employee, $period,
            8_000_000, 2_000_000, 1_000_000, 1_000_000, 8_000_000,
            false
        );

        $this->assertEquals(105_000, $result);
    }

    public function test_handles_different_ptkp_categories()
    {
        $period = $this->createPayPeriod(['is_split' => false]);

        $testCases = [
            ['ptkp' => 'TK/0', 'code' => 'EMP-A'],
            ['ptkp' => 'K/0',  'code' => 'EMP-B'],
            ['ptkp' => 'TK/2', 'code' => 'EMP-C'],
            ['ptkp' => 'K/2',  'code' => 'EMP-D'],
            ['ptkp' => 'K/3',  'code' => 'EMP-E'],
        ];

        foreach ($testCases as $tc) {
            $employee = $this->createEmployee([
                'employee_code' => $tc['code'],
                'ptkp' => $tc['ptkp'],
                'has_npwp' => true,
            ]);

            $result = $this->service->calculate(
                $employee, $period,
                7_000_000, 2_000_000, 500_000, 500_000,
                7_000_000,
                false
            );

            $this->assertGreaterThan(0, $result, "PTKP {$tc['ptkp']} should produce non-zero PPH");
        }
    }
}
