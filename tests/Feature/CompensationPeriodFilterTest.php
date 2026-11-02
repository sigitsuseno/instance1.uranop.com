<?php

namespace Tests\Feature;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompensationPeriodFilterTest extends TestCase
{
    use RefreshDatabase;

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'employee_code'      => '50923',
            'name'               => 'ASNA NUR AINI',
            'gender'             => 'P',
            'employment_status'  => 'contract',
            'join_date'          => '2026-09-01',
            // Sisa dari masa kerja sebelumnya (karyawan di-rehire).
            'resign_date'        => '2026-06-24',
            'is_active'          => true,
        ], $attributes));
    }

    private function contract(Employee $employee, array $attributes = []): EmployeeContract
    {
        return EmployeeContract::create(array_merge([
            'employee_id'     => $employee->id,
            'contract_number' => 'CTR-TEST-'.uniqid(),
            'contract_type'   => 'pkwt',
            'start_date'      => '2026-10-02',
            'end_date'        => '2026-11-02',
            'status'          => 'active',
            'is_latest'       => true,
        ], $attributes));
    }

    public function test_contract_is_included_when_end_date_is_inside_period_even_if_resign_date_is_earlier(): void
    {
        $this->contract($this->employee());

        $this->assertSame(
            1,
            EmployeeContract::dueInCompensationPeriod('2026-10-25', '2026-11-24')->count()
        );
    }

    public function test_contract_is_excluded_when_end_date_is_outside_period_even_if_resign_date_is_inside(): void
    {
        $this->contract($this->employee(['resign_date' => '2026-11-10']), [
            'start_date' => '2026-10-02',
            'end_date'   => '2026-12-02',
        ]);

        $this->assertSame(
            0,
            EmployeeContract::dueInCompensationPeriod('2026-10-25', '2026-11-24')->count()
        );
    }

    public function test_period_boundaries_are_inclusive(): void
    {
        $this->contract($this->employee(['resign_date' => null]), ['start_date' => '2026-09-25', 'end_date' => '2026-10-25']);
        $this->contract($this->employee(['employee_code' => '50924', 'resign_date' => null]), ['start_date' => '2026-09-25', 'end_date' => '2026-11-24']);

        $this->assertSame(
            2,
            EmployeeContract::dueInCompensationPeriod('2026-10-25', '2026-11-24')->count()
        );
    }

    public function test_compensation_months_uses_contract_dates_and_ignores_resign_date(): void
    {
        $contract = $this->contract($this->employee());

        $this->assertSame(1, $contract->compensationMonths());
    }
}
