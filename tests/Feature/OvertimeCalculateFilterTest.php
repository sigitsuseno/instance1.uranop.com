<?php

namespace Tests\Feature;

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

        $this->assertNotContains('A004', $this->codes($response));
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
}
