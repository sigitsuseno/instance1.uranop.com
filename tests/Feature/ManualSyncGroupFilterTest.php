<?php

namespace Tests\Feature;

use App\Modules\Attendance\Models\ManualDetect;
use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Settings\Models\EmployeeGroup;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualSyncGroupFilterTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/attendance/manual-sync/data';

    private User $user;
    private PayPeriod $period;
    private Employee $groupA;
    private Employee $groupB;
    private Employee $noGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test HR',
            'email' => 'hr@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->period = PayPeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Oktober 2026',
            'period_year' => 2026,
            'period_month' => 10,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'status' => 'active',
        ]);

        EmployeeGroupMaster::create([
            'group_label' => 'Imported Shift/Group',
            'name' => 'Group A',
            'code' => 'GRP-A',
            'is_active' => true,
        ]);
        EmployeeGroupMaster::create([
            'group_label' => 'Imported Shift/Group',
            'name' => 'Group B',
            'code' => 'GRP-B',
            'is_active' => true,
        ]);

        $this->groupA = $this->employee('A001', 'KARYAWAN A', 'GRP-A');
        $this->groupB = $this->employee('B001', 'KARYAWAN B', 'GRP-B');
        $this->noGroup = $this->employee('C001', 'KARYAWAN C');
    }

    private function employee(string $code, string $name, ?string $groupCode = null): Employee
    {
        $employee = Employee::create([
            'employee_code' => $code,
            'name' => $name,
            'gender' => 'P',
            'employment_status' => 'contract',
            'join_date' => '2026-09-01',
            'is_active' => true,
        ]);

        if ($groupCode) {
            EmployeeGroup::create([
                'employee_id' => $employee->id,
                'reference_code' => $groupCode,
            ]);
        }

        ManualDetect::create([
            'employee_id' => $employee->id,
            'date' => '2026-10-14',
            'status' => ManualDetect::STATUS_DRAFT,
        ]);

        return $employee;
    }

    private function names($response): array
    {
        return collect($response->json('data'))->pluck('employee.name')->unique()->sort()->values()->all();
    }

    private function params(array $query = []): array
    {
        return array_merge([
            'period_id' => $this->period->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'mode' => 'display',
            'per_page' => 10,
        ], $query);
    }

    private function url(array $query = []): string
    {
        return self::URL . '?' . http_build_query($this->params($query));
    }

    public function test_semua_tanpa_group_codes_memuat_karyawan_tanpa_group(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson($this->url());

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 3);
        $this->assertSame(['KARYAWAN A', 'KARYAWAN B', 'KARYAWAN C'], $this->names($response));
    }

    public function test_satu_group_code_hanya_memuat_anggota_group_itu(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url(['group_codes' => ['GRP-A']]));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 1);
        $this->assertSame(['KARYAWAN A'], $this->names($response));
    }

    public function test_beberapa_group_code_memuat_gabungan_anggota(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url(['group_codes' => ['GRP-A', 'GRP-B']]));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 2);
        $this->assertSame(['KARYAWAN A', 'KARYAWAN B'], $this->names($response));
    }

    public function test_group_codes_kosong_sama_dengan_semua(): void
    {
        // http_build_query() membuang array kosong, jadi parameter dikirim lewat
        // call() supaya `group_codes` benar-benar hadir sebagai array kosong —
        // bukan hilang dari query seperti pada test "tanpa group_codes".
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Accept' => 'application/json'])
            ->call('GET', self::URL, $this->params(['group_codes' => []]));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 3);
    }

    public function test_kode_group_tak_dikenal_mengembalikan_nol(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url(['group_codes' => ['GRP-TIDAK-ADA']]));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 0);
        $this->assertSame([], $this->names($response));
    }

    public function test_group_codes_harus_array(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url(['group_codes' => 'GRP-A']))
            ->assertStatus(422);
    }
}
