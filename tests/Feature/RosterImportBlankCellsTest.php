<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Settings\Models\EmployeeGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import roster Excel (POST /api/schedule/roster/import).
 *
 * Periode uji Oktober 2026 = 2026-09-25 s/d 2026-10-24 (30 hari).
 * Fokus: sel tanggal kosong tetap membuat baris sch_employee_shift_rosters,
 * dan enrollment Siklus Payroll PAY-2026-10 ikut disamakan dengan isi file.
 */
class RosterImportBlankCellsTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 2026;

    private const MONTH = 10;

    private const START = '2026-09-25';

    private const END = '2026-10-24';

    private const PERIOD_CODE = 'PAY-2026-10';

    private const URL = '/api/schedule/roster/import';

    private User $user;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->shift = Shift::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'PAGI',
            'name' => 'Pagi',
            'external_code' => 'P',
            'work_hour_start' => '08:00:00',
            'work_hour_end' => '17:00:00',
            'is_active' => true,
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  Sel kosong
    // ════════════════════════════════════════════════════════════════

    public function test_sel_kosong_tetap_membuat_roster_untuk_semua_tanggal_periode(): void
    {
        $employee = $this->makeEmployee('1001', 'KARYAWAN A');

        // Hanya dua kolom tanggal yang ada di file; sisanya tidak punya kolom.
        $this->import($this->workbook([
            ['nip' => '1001', 'name' => 'KARYAWAN A', 'cells' => ['25' => 'P']],
        ], ['25', '26']));

        $this->assertSame(30, $this->rosterQuery($employee)->count());

        $blank = $this->roster($employee->id, self::END);
        $this->assertNotNull($blank);
        $this->assertNull($blank->shift_id);
        $this->assertSame('-', $blank->external_code);
        $this->assertSame('scheduled', $blank->status);

        // Sel yang ada isinya tetap jadi shift seperti biasa.
        $shifted = $this->roster($employee->id, self::START);
        $this->assertSame($this->shift->id, $shifted->shift_id);
        $this->assertSame('P', $shifted->external_code);
    }

    public function test_sel_kosong_di_hari_libur_ditandai_libur(): void
    {
        $employee = $this->makeEmployee('1002', 'KARYAWAN B');

        Holiday::create([
            'uuid' => (string) Str::uuid(),
            'date' => '2026-10-05',
            'name' => 'Libur Nasional',
        ]);

        $this->import($this->workbook([
            ['nip' => '1002', 'name' => 'KARYAWAN B', 'cells' => []],
        ], ['25', '5']));

        $holiday = $this->roster($employee->id, '2026-10-05');
        $this->assertNotNull($holiday);
        $this->assertNull($holiday->shift_id);
        $this->assertSame('L', $holiday->external_code);
        $this->assertSame('holiday', $holiday->status);
        $this->assertTrue((bool) $holiday->is_holiday);

        // Tanggal biasa tetap scheduled.
        $this->assertSame('scheduled', $this->roster($employee->id, '2026-10-06')->status);
    }

    public function test_sel_kosong_tidak_menimpa_roster_yang_sudah_ada(): void
    {
        $employee = $this->makeEmployee('1003', 'KARYAWAN C');

        DB::table('sch_employee_shift_rosters')->insert([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'shift_id' => $this->shift->id,
            'date' => '2026-10-06',
            'shift_code' => 'PAGI',
            'external_code' => 'P',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->import($this->workbook([
            ['nip' => '1003', 'name' => 'KARYAWAN C', 'cells' => []],
        ], ['25']));

        $existing = $this->roster($employee->id, '2026-10-06');
        $this->assertSame($this->shift->id, $existing->shift_id);
        $this->assertSame('P', $existing->external_code);
    }

    // ════════════════════════════════════════════════════════════════
    //  Enrollment siklus payroll
    // ════════════════════════════════════════════════════════════════

    public function test_enrollment_periode_diganti_dengan_isi_file_tanpa_grp_jkt(): void
    {
        $enrolledInFile = $this->makeEmployee('2001', 'KARYAWAN D');
        $jakarta = $this->makeEmployee('2002', 'KARYAWAN E');
        $stale = $this->makeEmployee('2003', 'KARYAWAN F');

        $this->group($jakarta, 'GRP-JKT');
        $this->group($stale, self::PERIOD_CODE);
        $this->group($enrolledInFile, self::PERIOD_CODE);

        $this->import($this->workbook([
            ['nip' => '2001', 'name' => 'KARYAWAN D', 'cells' => []],
            ['nip' => '2002', 'name' => 'KARYAWAN E', 'cells' => []],
        ], ['25']));

        $enrolled = EmployeeGroup::where('reference_code', self::PERIOD_CODE)
            ->pluck('employee_id')
            ->all();

        $this->assertContains($enrolledInFile->id, $enrolled);
        $this->assertNotContains($jakarta->id, $enrolled);
        $this->assertNotContains($stale->id, $enrolled);
    }

    // ════════════════════════════════════════════════════════════════
    //  Fixture
    // ════════════════════════════════════════════════════════════════

    private function makeEmployee(string $nip, string $name): Employee
    {
        return Employee::create([
            'uuid' => (string) Str::uuid(),
            'employee_code' => 'EMP-'.$nip,
            'nip' => $nip,
            'name' => $name,
            'gender' => 'L',
            'employment_status' => 'permanent',
            'join_date' => '2020-01-01',
            'is_active' => true,
        ]);
    }

    private function group(Employee $employee, string $code): EmployeeGroup
    {
        return EmployeeGroup::create([
            'employee_id' => $employee->id,
            'reference_code' => $code,
        ]);
    }

    /**
     * Workbook roster: baris 1 judul, baris 2 heading, baris 3+ data.
     *
     * @param  array<int, array{nip: string, name: string, cells: array<string, string>}>  $rows
     * @param  array<int, string>  $dayColumns  Nomor tanggal yang punya kolom di file.
     */
    private function workbook(array $rows, array $dayColumns): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue([1, 1], 'Jadwal Oktober 2026');

        $headings = ['NIP', 'NAMA LENGKAP', 'WP', ...$dayColumns];
        foreach ($headings as $index => $heading) {
            $sheet->setCellValue([$index + 1, 2], $heading);
        }

        $rowNumber = 3;
        foreach ($rows as $row) {
            $sheet->setCellValueExplicit([1, $rowNumber], $row['nip'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([2, $rowNumber], $row['name']);
            $sheet->setCellValue([3, $rowNumber], '');

            foreach ($dayColumns as $index => $day) {
                $sheet->setCellValue([4 + $index, $rowNumber], $row['cells'][$day] ?? '');
            }

            $rowNumber++;
        }

        $path = tempnam(sys_get_temp_dir(), 'jadwal').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function import(string $path): array
    {
        // UploadedFile::move() memindahkan berkas sumber — pakai salinan baru.
        $copy = tempnam(sys_get_temp_dir(), 'upload').'.xlsx';
        copy($path, $copy);

        $response = $this->actingAs($this->user, 'sanctum')->post(self::URL, [
            'month' => self::MONTH,
            'year' => self::YEAR,
            'file' => new UploadedFile($copy, 'jadwal.xlsx', null, null, true),
        ]);

        $response->assertStatus(200);

        return $response->json();
    }

    private function rosterQuery(Employee $employee)
    {
        // whereDate: cast 'date' model menyimpan "Y-m-d H:i:s" di sqlite,
        // sedangkan MySQL memotongnya jadi DATE.
        return DB::table('sch_employee_shift_rosters')
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', self::START)
            ->whereDate('date', '<=', self::END);
    }

    private function roster(int $employeeId, string $date): ?object
    {
        return DB::table('sch_employee_shift_rosters')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->first();
    }
}
