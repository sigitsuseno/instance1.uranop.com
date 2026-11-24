<?php

namespace Tests\Feature;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OvertimeCalculateFilterTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/attendance/prepare/overtime-employees';

    private const START = '2026-10-12';

    private const END = '2026-10-18';

    private const HITUNG_URL = '/api/v1/attendance/prepare/hitung-lembur';

    private User $user;

    private Employee $budi;

    private Employee $siti;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test HR',
            'email' => 'hr@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->budi = $this->employee('A001', 'BUDI');
        $this->siti = $this->employee('A002', 'SITI');
        $this->employee('A003', 'AGUS'); // tanpa roster

        $this->roster($this->budi, '2026-10-14');
        $this->roster($this->budi, '2026-10-15'); // dua baris, satu karyawan
        $this->roster($this->siti, '2026-10-16');

        $terhapus = $this->employee('A004', 'TERHAPUS');
        $this->roster($terhapus, '2026-10-14')->delete();
    }

    private function employee(string $code, string $name, bool $active = true): Employee
    {
        return Employee::create([
            'employee_code' => $code,
            'name' => $name,
            'gender' => 'P',
            'employment_status' => 'contract',
            'join_date' => '2026-09-01',
            'is_active' => $active,
        ]);
    }

    private function roster(Employee $employee, string $date): EmployeeShiftRoster
    {
        return EmployeeShiftRoster::create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'date' => $date,
            'status' => 'scheduled',
        ]);
    }

    private function url(string $start = self::START, string $end = self::END): string
    {
        return self::URL . '?' . http_build_query([
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    private function codes($response): array
    {
        return collect($response->json('data'))->pluck('employee_code')->all();
    }

    public function test_hanya_pemilik_roster_dalam_rentang_tanpa_duplikat(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson($this->url());

        $response->assertOk();
        $this->assertSame(['A001', 'A002'], $this->codes($response));
    }

    public function test_roster_terhapus_lunak_tidak_ikut(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson($this->url());

        $response->assertOk();
        $this->assertNotContains('A004', $this->codes($response));
    }

    /**
     * Sumber dropdown periode (payroll) harus tahan periode tanpa tanggal:
     * satu baris NULL tidak boleh menjatuhkan seluruh daftar (500).
     */
    public function test_periode_tanpa_tanggal_tidak_merusak_daftar_periode(): void
    {
        \App\Modules\Payroll\Models\PayPeriod::create([
            'name'         => 'Tanpa Tanggal',
            'period_year'  => 2027,
            'period_month' => 1,
            'status'       => 'inactive',
            'start_date'   => null,
            'end_date'     => null,
        ]);

        $response = $this->getJson('/api/v1/payroll/periods');

        $response->assertOk();
        $this->assertContains(
            'Tanpa Tanggal',
            collect($response->json('data'))->pluck('name')->all()
        );
    }

    public function test_karyawan_nonaktif_dengan_roster_tetap_muncul(): void
    {
        $nonaktif = $this->employee('A005', 'NONAKTIF', false);
        $this->roster($nonaktif, '2026-10-15');

        $response = $this->actingAs($this->user, 'sanctum')->getJson($this->url());

        $this->assertContains('A005', $this->codes($response));
    }

    public function test_rentang_di_luar_roster_mengembalikan_kosong(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('2026-10-20', '2026-10-25'));

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_tanggal_wajib_dan_end_harus_setelah_start(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson(self::URL)
            ->assertStatus(422);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('2026-10-18', '2026-10-12'))
            ->assertStatus(422);
    }

    private function prepare(Employee $employee, string $date): AttendancePrepare
    {
        return AttendancePrepare::create([
            'employee_id'   => $employee->id,
            'date'          => $date,
            'periode_start' => '2026-10-01',
            'periode_end'   => '2026-10-31',
            'check_in'      => $date . ' 08:00:00',
            'check_out'     => $date . ' 17:30:00',
            'status'        => 'hadir',
            'is_locked'     => false,
            'lm'            => 999,
            'lm_count'      => 999,
            'overtime'      => 999,
            'overtime_count' => 999,
        ]);
    }

    public function test_hitung_hanya_karyawan_terpilih(): void
    {
        $budiPrepare = $this->prepare($this->budi, '2026-10-14');
        $sitiPrepare = $this->prepare($this->siti, '2026-10-15');

        $response = $this->actingAs($this->user, 'sanctum')->postJson(self::HITUNG_URL, [
            'start_date'   => self::START,
            'end_date'     => self::END,
            'employee_ids' => [$this->budi->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.updated', 1);
        $this->assertNotSame(999, $budiPrepare->fresh()->lm);
        $this->assertSame(999, $sitiPrepare->fresh()->lm);
    }

    public function test_tanpa_employee_ids_semua_dihitung(): void
    {
        $budiPrepare = $this->prepare($this->budi, '2026-10-14');
        $sitiPrepare = $this->prepare($this->siti, '2026-10-15');

        $response = $this->actingAs($this->user, 'sanctum')->postJson(self::HITUNG_URL, [
            'start_date' => self::START,
            'end_date'   => self::END,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.updated', 2);
        $this->assertNotSame(999, $budiPrepare->fresh()->lm);
        $this->assertNotSame(999, $sitiPrepare->fresh()->lm);
    }

    public function test_employee_ids_kosong_ditolak_422(): void
    {
        $budiPrepare = $this->prepare($this->budi, '2026-10-14');

        $this->actingAs($this->user, 'sanctum')->postJson(self::HITUNG_URL, [
            'start_date'   => self::START,
            'end_date'     => self::END,
            'employee_ids' => [],
        ])->assertStatus(422);

        $this->assertSame(999, $budiPrepare->fresh()->lm);
    }

    public function test_employee_ids_bukan_array_ditolak_422(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson(self::HITUNG_URL, [
            'start_date'   => self::START,
            'end_date'     => self::END,
            'employee_ids' => 'A001',
        ])->assertStatus(422);
    }
}
