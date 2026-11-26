<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RekapPphKompensasiContractFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PayPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test HR',
            'email' => 'hr@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->period = PayPeriod::create([
            'name'         => 'Oktober 2026',
            'period_year'  => 2026,
            'period_month' => 10,
            'status'       => 'active',
            'start_date'   => '2026-09-25',
            'end_date'     => '2026-10-24',
        ]);
    }

    private function employee(string $code, string $name): Employee
    {
        $employee = Employee::create([
            'employee_code'     => $code,
            'name'              => $name,
            'gender'            => 'L',
            'employment_status' => 'permanent',
            'join_date'         => '2026-01-01',
            'is_active'         => true,
        ]);

        EmployeeShiftRoster::create([
            'uuid'        => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'date'        => '2026-10-01',
            'status'      => 'scheduled',
        ]);

        return $employee;
    }

    private function contract(Employee $employee, ?string $endDate): EmployeeContract
    {
        return EmployeeContract::create([
            'employee_id'     => $employee->id,
            'contract_number' => 'CTR-TEST-'.uniqid(),
            'contract_type'   => $endDate === null ? 'pkwtt' : 'pkwt',
            'start_date'      => '2026-09-09',
            'end_date'        => $endDate,
            'status'          => 'active',
            'is_latest'       => true,
        ]);
    }

    private function names(): array
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson(
            '/api/v1/reports/rekap-pph-kompensasi?'.http_build_query([
                'period_id' => $this->period->id,
                'extra_ids' => '',
            ])
        );

        $response->assertOk();

        return collect($response->json('pph'))->pluck('name')->all();
    }

    public function test_kontrak_tanpa_tanggal_akhir_ikut_di_rekap_pph(): void
    {
        $this->contract($this->employee('A001', 'AGUS GUNARDI'), null);

        $this->assertContains('AGUS GUNARDI', $this->names());
    }

    public function test_kontrak_berakhir_sebelum_periode_tidak_ikut(): void
    {
        $this->contract($this->employee('A002', 'KONTRAK LEWAT'), '2026-09-08');

        $this->assertNotContains('KONTRAK LEWAT', $this->names());
    }

    public function test_karyawan_tanpa_kontrak_tetap_ikut(): void
    {
        $this->employee('A003', 'TANPA KONTRAK');

        $this->assertContains('TANPA KONTRAK', $this->names());
    }
}
