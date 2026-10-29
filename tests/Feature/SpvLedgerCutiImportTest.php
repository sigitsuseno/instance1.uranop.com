<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Endpoint "Get Data" di /supervisor/leave/generate — menyalin
 * employee_leaves ke spv_ledger_cuti (khusus superadmin).
 */
class SpvLedgerCutiImportTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::create([
            'name'      => 'Test '.$role,
            'email'     => $role.'-'.uniqid().'@example.test',
            'password'  => Hash::make('password'),
            'user_type' => $role === 'superadmin' ? 'superadmin' : 'hrbranch',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeEmployee(string $name): Employee
    {
        return Employee::create([
            'employee_code'     => 'EMP-'.uniqid(),
            'name'              => $name,
            'gender'            => 'L',
            'employment_status' => 'permanent',
            'join_date'         => '2025-01-01',
        ]);
    }

    private function makePeriod(string $name): LeavePeriod
    {
        return LeavePeriod::create([
            'name'       => $name,
            'start_date' => '2026-01-01',
            'end_date'   => '2026-12-31',
            'status'     => 'active',
        ]);
    }

    private function makeLeaveType(): LeaveType
    {
        return LeaveType::create([
            'code'         => 'CT-'.uniqid(),
            'name'         => 'Cuti Tahunan',
            'category'     => 'leave',
            'balance_type' => 'increment',
        ]);
    }

    private function makeLedger(Employee $employee, LeaveType $type, LeavePeriod $period, array $overrides = []): EmployeeLeave
    {
        return EmployeeLeave::create(array_merge([
            'employee_id'      => $employee->id,
            'leave_type_id'    => $type->id,
            'leave_period_id'  => $period->id,
            'reference_id'     => 0,
            'transaction_type' => 'increment',
            'amount'           => 12,
            'description'      => 'Generate Kuota Cuti Tahunan',
        ], $overrides));
    }

    public function test_superadmin_copies_ledger_only_for_selected_employees_and_period(): void
    {
        $superadmin = $this->userWithRole('superadmin');
        $type = $this->makeLeaveType();
        $period = $this->makePeriod('Periode 2026');
        $otherPeriod = $this->makePeriod('Periode 2027');
        $selected = $this->makeEmployee('Karyawan Terpilih');
        $other = $this->makeEmployee('Karyawan Lain');

        $this->makeLedger($selected, $type, $period);
        $this->makeLedger($other, $type, $period);        // karyawan tidak dipilih
        $this->makeLedger($selected, $type, $otherPeriod); // periode lain

        $response = $this->actingAs($superadmin)->postJson(
            '/api/v1/supervisor/leave/spv-ledger-cuti/import',
            [
                'employee_ids'    => [$selected->id],
                'leave_period_id' => $period->id,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('skipped', 0);

        $this->assertDatabaseCount('spv_ledger_cuti', 1);
        $this->assertDatabaseHas('spv_ledger_cuti', [
            'employee_id'      => $selected->id,
            'leave_type_id'    => $type->id,
            'leave_period_id'  => $period->id,
            'reference_id'     => 0,
            'transaction_type' => 'increment',
            'amount'           => 12,
            'description'      => 'Generate Kuota Cuti Tahunan',
        ]);
    }

    public function test_import_is_idempotent_and_skips_existing_rows(): void
    {
        $superadmin = $this->userWithRole('superadmin');
        $type = $this->makeLeaveType();
        $period = $this->makePeriod('Periode 2026');
        $employee = $this->makeEmployee('Karyawan Uji');
        $this->makeLedger($employee, $type, $period);

        $payload = [
            'employee_ids'    => [$employee->id],
            'leave_period_id' => $period->id,
        ];

        $this->actingAs($superadmin)
            ->postJson('/api/v1/supervisor/leave/spv-ledger-cuti/import', $payload)
            ->assertOk()
            ->assertJsonPath('created', 1);

        $this->actingAs($superadmin)
            ->postJson('/api/v1/supervisor/leave/spv-ledger-cuti/import', $payload)
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 1);

        $this->assertDatabaseCount('spv_ledger_cuti', 1);
    }

    public function test_non_superadmin_is_forbidden(): void
    {
        $hrbranch = $this->userWithRole('hrbranch');
        $type = $this->makeLeaveType();
        $period = $this->makePeriod('Periode 2026');
        $employee = $this->makeEmployee('Karyawan Uji');
        $this->makeLedger($employee, $type, $period);

        $this->actingAs($hrbranch)->postJson(
            '/api/v1/supervisor/leave/spv-ledger-cuti/import',
            [
                'employee_ids'    => [$employee->id],
                'leave_period_id' => $period->id,
            ]
        )->assertForbidden();

        $this->assertDatabaseCount('spv_ledger_cuti', 0);
    }

    public function test_employee_ids_and_period_are_required(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        $this->actingAs($superadmin)
            ->postJson('/api/v1/supervisor/leave/spv-ledger-cuti/import', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_ids', 'leave_period_id']);
    }
}
