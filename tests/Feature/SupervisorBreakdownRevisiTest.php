<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Supervisor\Payroll\Models\SupervisorBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Endpoint inline edit REVISI di /supervisor/payroll (khusus superadmin).
 */
class SupervisorBreakdownRevisiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::create([
            'name'     => 'Test '.$role,
            'email'    => $role.'-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'user_type' => $role === 'superadmin' ? 'superadmin' : 'hrbranch',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeBreakdown(array $overrides = []): SupervisorBreakdown
    {
        $period = PayPeriod::create([
            'name'         => 'Oktober 2026',
            'period_year'  => 2026,
            'period_month' => 10,
            'start_date'   => '2026-10-01',
            'end_date'     => '2026-10-31',
            'is_split'     => false,
        ]);

        $employee = Employee::create([
            'uuid'              => (string) \Illuminate\Support\Str::uuid(),
            'employee_code'     => 'EMP-'.uniqid(),
            'name'              => 'Karyawan Uji',
            'gender'            => 'L',
            'employment_status' => 'permanent',
            'join_date'         => '2025-01-01',
        ]);

        return SupervisorBreakdown::create(array_merge([
            'pay_period_id' => $period->id,
            'employee_id'   => $employee->id,
            'section'       => 'A',
            'group_codes'   => ['GRP-ALLIN'],
            'gaji'          => 3000000,
            'tunjangan'     => 500000,
            'upah_lembur'   => 100000,
            'premi_hadir'   => 200000,
            'tj_masa_kerja' => 150000,
            'revisi'        => -150000,
            'gaji_kotor'    => 3800000,
            'bpjs_tk'       => 0,
            'bpjs_ks'       => 0,
            'bpjs_pen'      => 0,
            'pph'           => 0,
            'cashbon'       => 0,
            'pblt'          => 0,
            'gaji_bersih'   => 3800000,
        ], $overrides));
    }

    public function test_revisi_minus_mengurangi_gaji_kotor_dan_gaji_bersih(): void
    {
        $record = $this->makeBreakdown();
        $user = $this->userWithRole('superadmin');

        // dasar = 3.000.000 + 500.000 + 100.000 + 200.000 + 150.000 = 3.950.000
        $response = $this->actingAs($user)->putJson(
            "/api/v1/supervisor/payroll/breakdown/{$record->id}/revisi",
            ['revisi' => -500000]
        );

        $response->assertOk()->assertJsonPath('data.revisi', -500000);

        $record->refresh();
        $this->assertEquals(-500000, (float) $record->revisi);
        $this->assertEquals(3450000, (float) $record->gaji_kotor);
        $this->assertEquals(3450000, (float) $record->gaji_bersih);
        $this->assertEquals(0, (float) $record->pblt);
    }

    public function test_revisi_positif_menambah_gaji_kotor(): void
    {
        $record = $this->makeBreakdown();
        $user = $this->userWithRole('superadmin');

        $this->actingAs($user)->putJson(
            "/api/v1/supervisor/payroll/breakdown/{$record->id}/revisi",
            ['revisi' => 250000]
        )->assertOk();

        $record->refresh();
        $this->assertEquals(250000, (float) $record->revisi);
        $this->assertEquals(4200000, (float) $record->gaji_kotor);
        $this->assertEquals(4200000, (float) $record->gaji_bersih);
    }

    public function test_pembulatan_100_dipertahankan(): void
    {
        $record = $this->makeBreakdown();
        $user = $this->userWithRole('superadmin');

        // 3.950.000 - 150.000 + revisi? → pakai revisi -123.457 → 3.826.543 → dibulatkan 3.826.600
        $this->actingAs($user)->putJson(
            "/api/v1/supervisor/payroll/breakdown/{$record->id}/revisi",
            ['revisi' => -123457]
        )->assertOk();

        $record->refresh();
        $this->assertEquals(3826543, (float) $record->gaji_kotor);
        $this->assertEquals(3826600, (float) $record->gaji_bersih);
        $this->assertEquals(57, (float) $record->pblt);
    }

    public function test_non_superadmin_ditolak(): void
    {
        $record = $this->makeBreakdown();
        $user = $this->userWithRole('hrbranch');

        $this->actingAs($user)->putJson(
            "/api/v1/supervisor/payroll/breakdown/{$record->id}/revisi",
            ['revisi' => 1000]
        )->assertForbidden();

        $this->assertEquals(-150000, (float) $record->refresh()->revisi);
    }

    public function test_revisi_wajib_diisi(): void
    {
        $record = $this->makeBreakdown();
        $user = $this->userWithRole('superadmin');

        $this->actingAs($user)->putJson(
            "/api/v1/supervisor/payroll/breakdown/{$record->id}/revisi",
            []
        )->assertStatus(422);
    }
}
