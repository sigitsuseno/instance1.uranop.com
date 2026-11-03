<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Supervisor\Payroll\Exports\SupervisorPayrollExport;
use App\Modules\Supervisor\Payroll\Models\SupervisorBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kolom REVISI pada Export Excel & Print PDF /supervisor/payroll.
 */
class SupervisorPayrollExportPrintRevisiTest extends TestCase
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

    private function makeBreakdown(float $revisi = -500000): array
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
            'nip'               => 'NIP-'.uniqid(),
            'name'              => 'Karyawan Uji',
            'gender'            => 'L',
            'employment_status' => 'permanent',
            'join_date'         => '2025-01-01',
        ]);

        SupervisorBreakdown::create([
            'pay_period_id' => $period->id,
            'employee_id'   => $employee->id,
            'section'       => 'A',
            'group_codes'   => ['GRP-ALLIN'],
            'employee_code' => 'EMP-1',
            'employee_name' => 'Karyawan Uji',
            'gender'        => 'L',
            'department_name' => 'Produksi',
            'position_name' => 'Operator',
            'gaji_pokok'    => 3000000,
            'premi'         => 100000,
            'tj_masa_kerja' => 150000,
            'tunjangan'     => 500000,
            'hari_kerja'    => 25,
            'lm'            => 16,
            'lembur_count'  => 3,
            'gaji'          => 3000000,
            'upah_lembur'   => 100000,
            'premi_hadir'   => 200000,
            'revisi'        => $revisi,
            'gaji_kotor'    => 3450000,
            'bpjs_tk'       => 0,
            'bpjs_ks'       => 0,
            'bpjs_pen'      => 0,
            'pph'           => 0,
            'cashbon'       => 0,
            'pblt'          => 0,
            'gaji_bersih'   => 3450000,
        ]);

        return [$period, $employee];
    }

    private function loadSheet(string $contents)
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_revisi_');
        file_put_contents($path, $contents);

        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            @unlink($path);
        }
    }

    private function exportTotals(float $revisi): array
    {
        return [
            'gaji_pokok'    => 3000000,
            'premi'         => 100000,
            'tj_masa_kerja' => 150000,
            'upah_lembur'   => 100000,
            'gaji'          => 3000000,
            'revisi'        => $revisi,
            'tunjangan'     => 500000,
            'premi_hadir'   => 200000,
            'bpjs_tk'       => 0,
            'bpjs_ks'       => 0,
            'bpjs_pen'      => 0,
            'pph'           => 0,
            'cashbon'       => 0,
            'pblt'          => 0,
            'gaji_bersih'   => 3450000,
        ];
    }

    private function exportRow(float $revisi): array
    {
        return [
            'employee_code' => 'EMP-1',
            'name'          => 'Karyawan Uji',
            'department'    => 'Produksi',
            'position'      => 'Operator',
            'gender'        => 'L',
            'masa_kerja'    => 10,
            'join_date_raw' => '01/01/2025',
            'ptkp'          => 'TK/0',
            'gaji_pokok'    => 3000000,
            'premi'         => 100000,
            'tj_masa_kerja' => 150000,
            'hari_kerja'    => 25,
            'lm'            => 2,
            'lembur_count'  => 3,
            'upah_lembur'   => 100000,
            'gaji'          => 3000000,
            'revisi'        => $revisi,
            'tunjangan'     => 500000,
            'premi_hadir'   => 200000,
            'bpjs_tk'       => 0,
            'bpjs_ks'       => 0,
            'bpjs_pen'      => 0,
            'pph'           => 0,
            'cashbon'       => 0,
            'pblt'          => 0,
            'gaji_bersih'   => 3450000,
        ];
    }

    public function test_export_excel_menempatkan_kolom_revisi_di_antara_gaji_dan_tunjangan(): void
    {
        $export = new SupervisorPayrollExport(
            [$this->exportRow(-500000)],
            'Oktober 2026',
            $this->exportTotals(-500000),
        );

        $sheet = $this->loadSheet(Excel::raw($export, ExcelFormat::XLSX));

        // Header (row 3): GAJI | Revisi | Tunjangan
        $this->assertSame('GAJI', $sheet->getCell('P3')->getValue());
        $this->assertSame('Revisi', $sheet->getCell('Q3')->getValue());
        $this->assertSame('Tunjangan', $sheet->getCell('R3')->getValue());

        // Data (row 4) dan grand total (row 5)
        $this->assertEquals(-500000, (float) $sheet->getCell('Q4')->getValue());
        $this->assertEquals(-500000, (float) $sheet->getCell('Q5')->getValue());
    }

    public function test_endpoint_export_menyertakan_nilai_revisi(): void
    {
        [$period] = $this->makeBreakdown(-500000);
        $user = $this->userWithRole('superadmin');

        $response = $this->actingAs($user)
            ->get("/api/v1/supervisor/payroll/breakdown/export?period_id={$period->id}");

        $response->assertOk();

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        $this->assertSame('Revisi', $sheet->getCell('Q3')->getValue());
        $this->assertEquals(-500000, (float) $sheet->getCell('Q4')->getValue());
        $this->assertEquals(-500000, (float) $sheet->getCell('Q5')->getValue());
    }

    public function test_endpoint_print_meneruskan_nilai_revisi_ke_view(): void
    {
        [$period] = $this->makeBreakdown(-500000);
        $user = $this->userWithRole('superadmin');

        $viewData = null;
        View::composer('supervisor.payroll.salary-breakdown-pdf', function ($view) use (&$viewData) {
            $viewData = $view->getData();
        });

        $response = $this->actingAs($user)
            ->get("/api/v1/supervisor/payroll/breakdown/print?period_id={$period->id}");

        $response->assertOk();
        $this->assertNotNull($viewData);
        $this->assertEquals(-500000, (float) $viewData['data'][0]['revisi']);
        $this->assertEquals(-500000, (float) $viewData['totals']['revisi']);
    }

    public function test_print_pdf_menggunakan_kertas_f4_landscape(): void
    {
        [$period] = $this->makeBreakdown();
        $user = $this->userWithRole('superadmin');

        $response = $this->actingAs($user)
            ->get("/api/v1/supervisor/payroll/breakdown/print?period_id={$period->id}");

        $response->assertOk();

        $pdf = $response->getContent();
        $this->assertSame('%PDF', substr($pdf, 0, 4));

        preg_match('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $m);
        $this->assertNotEmpty($m, 'MediaBox tidak ditemukan pada PDF.');

        // F4 landscape: 330 mm × 215 mm → ±935.43 pt × ±609.45 pt
        $this->assertEqualsWithDelta(935.43, (float) $m[1], 1.0);
        $this->assertEqualsWithDelta(609.45, (float) $m[2], 1.0);
    }

    public function test_view_pdf_menampilkan_kolom_revisi(): void
    {
        $html = view('supervisor.payroll.salary-breakdown-pdf', [
            'data' => [[
                'no'             => 1,
                'employee_code'  => 'EMP-1',
                'name'           => 'Karyawan Uji',
                'bagian_jabatan' => 'Produksi / Operator',
                'gender'         => 'L',
                'masa_kerja'     => 10,
                'join_date'      => '01/01/2025',
                'ptkp'           => 'TK/0',
                'gaji_pokok'     => 3000000,
                'premi'          => 100000,
                'tj_masa_kerja'  => 150000,
                'hari_kerja'     => 25,
                'lm'             => 2,
                'lembur_count'   => 3,
                'upah_lembur'    => 100000,
                'gaji'           => 3000000,
                'revisi'         => -500000,
                'tunjangan'      => 500000,
                'premi_hadir'    => 200000,
                'bpjs_tk'        => 0,
                'bpjs_ks'        => 0,
                'bpjs_pen'       => 0,
                'pph'            => 0,
                'cashbon'        => 0,
                'pblt'           => 0,
                'gaji_bersih'    => 3450000,
            ]],
            'totals' => [
                'gaji_pokok'    => 3000000,
                'premi'         => 100000,
                'tj_masa_kerja' => 150000,
                'upah_lembur'   => 100000,
                'gaji'          => 3000000,
                'revisi'        => -500000,
                'tunjangan'     => 500000,
                'premi_hadir'   => 200000,
                'bpjs_tk'       => 0,
                'bpjs_ks'       => 0,
                'bpjs_pen'      => 0,
                'pph'           => 0,
                'cashbon'       => 0,
                'pblt'          => 0,
                'gaji_bersih'   => 3450000,
            ],
            'periodLabel' => 'Oktober 2026',
            'companyName' => 'PT. TEST',
        ])->render();

        $this->assertStringContainsString('>Revisi<', $html);
        $this->assertStringContainsString('-500.000', $html);
    }
}
