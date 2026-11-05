<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Notifikasi consecutive day di lonceng layout Supervisor.
 *
 * GET /api/v1/supervisor/attendance/consecutive-notifications
 *
 * Aturan: pada Sabtu terakhir yang punya data, karyawan yang hadir penuh
 * Senin–Sabtu (6 hari berturut-turut) muncul di notifikasi.
 */
class SupervisorConsecutiveNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/supervisor/attendance/consecutive-notifications';

    private const SATURDAY = '2026-10-24';

    private const PERIOD_START = '2026-09-25';

    private const PERIOD_END = '2026-10-24';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->user = User::create([
            'name' => 'Test Supervisor',
            'email' => 'supervisor@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->assignRole('superadmin');
    }

    public function test_karyawan_hadir_penuh_enam_hari_muncul_di_notifikasi(): void
    {
        $employee = $this->makeEmployee('EMP-001', 'BUDI');

        $this->enrollInGroup($employee);
        $this->seedAutologs($employee, [
            '2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(self::URL.'?date='.self::SATURDAY);

        $response->assertOk()
            ->assertJsonPath('saturday', self::SATURDAY)
            ->assertJsonPath('start_date', '2026-10-19')
            ->assertJsonPath('end_date', self::SATURDAY)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('notifications.0.employee_code', 'EMP-001')
            ->assertJsonPath('notifications.0.employee_name', 'BUDI')
            ->assertJsonPath('notifications.0.message', 'Karyawan BUDI - EMP-001 sudah masuk selama 6 hari');
    }

    public function test_karyawan_dengan_satu_hari_cuti_tidak_muncul_di_notifikasi(): void
    {
        $employee = $this->makeEmployee('EMP-002', 'SITI');

        $this->enrollInGroup($employee);
        $this->seedAutologs($employee, [
            '2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23',
        ]);
        $this->seedAutologs($employee, ['2026-10-24'], 'leave');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(self::URL.'?date='.self::SATURDAY);

        $response->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonCount(0, 'notifications');
    }

    public function test_tanpa_parameter_tanggal_dipakai_sabtu_terakhir_yang_punya_data(): void
    {
        Carbon::setTestNow('2026-11-05 08:00:00');

        try {
            $employee = $this->makeEmployee('EMP-003', 'ANDI');

            $this->enrollInGroup($employee);
            $this->seedAutologs($employee, [
                '2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17',
                '2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24',
            ]);

            $response = $this->actingAs($this->user, 'sanctum')->getJson(self::URL);

            $response->assertOk()
                ->assertJsonPath('saturday', self::SATURDAY)
                ->assertJsonPath('count', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_karyawan_dengan_hari_libur_nasional_di_tengah_tidak_muncul_di_notifikasi(): void
    {
        $employee = $this->makeEmployee('EMP-004', 'RINA');

        $this->enrollInGroup($employee);
        $this->seedAutologs($employee, [
            '2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23',
        ]);
        $this->seedAutologs($employee, ['2026-10-24'], 'present', true);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(self::URL.'?date='.self::SATURDAY);

        $response->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonCount(0, 'notifications');
    }

    private function makeEmployee(string $code, string $name): Employee
    {
        return Employee::create([
            'uuid' => (string) Str::uuid(),
            'employee_code' => $code,
            'nip' => $code,
            'name' => $name,
            'gender' => 'L',
            'employment_status' => 'permanent',
            'join_date' => '2020-01-01',
            'is_active' => true,
        ]);
    }

    private function enrollInGroup(Employee $employee): void
    {
        DB::table('supervisor_employee_groups')->insert([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'group_name' => 'Oktober 2026',
            'group_code' => 'Oktober 2026',
            'period_start' => self::PERIOD_START,
            'period_end' => self::PERIOD_END,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $dates
     */
    private function seedAutologs(Employee $employee, array $dates, string $status = 'present', bool $isHoliday = false): void
    {
        foreach ($dates as $date) {
            DB::table('attendance_autologs')->insert([
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => $status,
                'is_holiday' => $isHoliday,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
