<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Schedule\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Import scanlog fingerprint ke attendance_autologs lewat
 * POST /api/v1/supervisor/attendance/import.
 *
 * Periode uji 2026-10-12 s/d 2026-10-18: Sen, Sel, Rab, Kam, Jum, Sab, Min —
 * cukup untuk menguji hari kerja, Sabtu, dan Minggu (jalur lm).
 */
class AttendanceScanImportTest extends TestCase
{
    use RefreshDatabase;

    private const START = '2026-10-12';

    private const END = '2026-10-18';

    private const URL = '/api/v1/supervisor/attendance/import';

    private User $user;

    private PayPeriod $period;

    private Shift $shift;

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

        $this->period = PayPeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Oktober 2026',
            'period_year' => 2026,
            'period_month' => 10,
            'start_date' => self::START,
            'end_date' => self::END,
            'status' => 'active',
        ]);

        $this->shift = Shift::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'P-R',
            'name' => 'OFFICE STAFF',
            'external_code' => 'P',
            'work_hour_start' => '08:00:00',
            'work_hour_end' => '17:00:00',
            'is_active' => true,
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  Fixture
    // ════════════════════════════════════════════════════════════════

    private function makeEmployee(string $nip, string $name = 'KARYAWAN'): Employee
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

    /**
     * Roster lewat query builder supaya kolom DATE tersimpan seperti MySQL
     * ("2026-10-12"), bukan "2026-10-12 00:00:00" seperti cast model.
     *
     * @param  array<int, string>  $dates
     */
    private function seedRoster(Employee $employee, array $dates, ?Shift $shift = null): void
    {
        foreach ($dates as $date) {
            DB::table('sch_employee_shift_rosters')->insert([
                'uuid' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'shift_id' => ($shift ?? $this->shift)->id,
                'date' => $date,
                'is_sun' => date('N', strtotime($date)) == 7,
                'is_sat' => date('N', strtotime($date)) == 6,
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Daftarkan karyawan ke grup periode — inilah yang menentukan scope import.
     */
    private function enrollInGroup(Employee ...$employees): void
    {
        foreach ($employees as $employee) {
            DB::table('supervisor_employee_groups')->insert([
                'uuid' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'group_name' => 'Oktober 2026',
                'group_code' => 'Oktober 2026',
                'period_start' => self::START,
                'period_end' => self::END,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Karyawan yang siap diimport: masuk grup periode DAN punya roster.
     *
     * @param  array<int, string>  $dates
     */
    private function enroll(Employee $employee, array $dates): void
    {
        $this->enrollInGroup($employee);
        $this->seedRoster($employee, $dates);
    }

    /**
     * Workbook format RAW (1 baris per scan).
     *
     * @param  array<int, array{pin: string, date: string, time: string}>  $rows
     */
    private function rawWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Tanggal scan', 'Tanggal', 'Jam', 'PIN', 'NIP', 'Nama', 'Jabatan',
            'Departemen', 'Kantor', 'Verifikasi', 'I/O', 'Workcode', 'SN', 'Mesin',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 2], $header);
        }

        $rowNumber = 3;
        foreach ($rows as $row) {
            $sheet->setCellValue([1, $rowNumber], $row['date'].' '.$row['time']);
            $sheet->setCellValue([2, $rowNumber], $row['date']);
            $sheet->setCellValue([3, $rowNumber], $row['time']);
            $sheet->setCellValue([4, $rowNumber], $row['pin']);
            $sheet->setCellValue([5, $rowNumber], $row['pin']);
            $sheet->setCellValue([6, $rowNumber], 'KARYAWAN');
            $sheet->setCellValue([11, $rowNumber], '0');
            $sheet->setCellValue([13, $rowNumber], '13061016030118');
            $sheet->setCellValue([14, $rowNumber], 'Mesin 1');
            $rowNumber++;
        }

        return $this->saveWorkbook($spreadsheet);
    }

    /**
     * Workbook format PIVOT (1 baris per karyawan-hari).
     *
     * @param  array<int, array{pin: string, date: string, scans: array<int, string>}>  $rows
     */
    private function pivotWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'PIN', 'NIP', 'Nama', 'Jabatan', 'Departemen', 'Kantor', 'Tanggal',
            'Scan 1', 'Scan 2', 'Scan 3', 'Scan 4',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 2], $header);
        }

        $rowNumber = 3;
        foreach ($rows as $row) {
            $sheet->setCellValue([1, $rowNumber], $row['pin']);
            $sheet->setCellValue([2, $rowNumber], $row['pin']);
            $sheet->setCellValue([3, $rowNumber], 'KARYAWAN');
            $sheet->setCellValue([7, $rowNumber], $row['date']);

            foreach (array_values($row['scans']) as $index => $time) {
                $sheet->setCellValue([8 + $index, $rowNumber], $time);
            }

            $rowNumber++;
        }

        return $this->saveWorkbook($spreadsheet);
    }

    private function saveWorkbook(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'scanlog').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function import(string $path, string $name = 'scanlog.xlsx', ?int $periodId = null): array
    {
        // UploadedFile::move() memindahkan berkas sumber, jadi tiap panggilan
        // harus memakai salinan baru supaya import bisa dijalankan berulang.
        $copy = tempnam(sys_get_temp_dir(), 'upload').'.xlsx';
        copy($path, $copy);

        $response = $this->actingAs($this->user, 'sanctum')->post(self::URL, [
            'payroll_period_id' => $periodId ?? $this->period->id,
            'file' => new UploadedFile($copy, $name, null, null, true),
        ]);

        $response->assertStatus(200);

        return $response->json('data');
    }

    private function autolog(int $employeeId, string $date): ?object
    {
        return DB::table('attendance_autologs')
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->first();
    }

    // ════════════════════════════════════════════════════════════════
    //  Format RAW
    // ════════════════════════════════════════════════════════════════

    public function test_raw_menulis_satu_baris_per_karyawan_hari(): void
    {
        $employee = $this->makeEmployee('1', 'SATYAHADI');
        $this->enroll($employee, [self::START, '2026-10-13']);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:24:23'],
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '18:14:16'],
            ['pin' => '1', 'date' => '13-10-2026', 'time' => '08:21:44'],
            ['pin' => '1', 'date' => '13-10-2026', 'time' => '19:00:00'],
        ]);

        $result = $this->import($path);

        $this->assertSame('raw', $result['format']);
        $this->assertSame(2, $result['inserted']);
        $this->assertSame(0, $result['updated']);

        $row = $this->autolog($employee->id, self::START);

        $this->assertNotNull($row);
        $this->assertSame('2026-10-12 08:24:23', $row->check_in);
        $this->assertSame('2026-10-12 18:14:16', $row->check_out);
        $this->assertSame('2026-10-12 08:00:00', $row->actual_in);
        $this->assertSame('2026-10-12 17:00:00', $row->actual_out);
        $this->assertSame('present', $row->status);
        $this->assertSame(2, (int) $row->scan_count);
        // 18:14:16 − 17:00:00 = 74 menit → dibulatkan jadi 60
        $this->assertSame(60, (int) $row->lembur);
        $this->assertSame(1.0, (float) $row->lembur_calc);
        $this->assertSame(0, (int) $row->lm);
        $this->assertSame(0, (int) $row->is_sun);
        $this->assertSame($employee->id, (int) $row->employee_id);
        $this->assertNotNull($row->employee_shift_roster_id);
        $this->assertNull($row->deleted_at);
        $this->assertStringStartsWith('SCAN_', $row->import_batch);

        // 13-10 19:00 → 120 menit, sudah kelipatan 30
        $this->assertSame(120, (int) $this->autolog($employee->id, '2026-10-13')->lembur);
    }

    /**
     * Pembulatan lembur hari kerja: kelipatan 30, toleransi 5 menit di batas
     * atas, dan di bawah 30 menit jadi 0.
     *
     * @return array<string, array{0: int, 1: int}> [menit check_out setelah 17:00, lembur diharapkan]
     */
    public static function pembulatanLemburProvider(): array
    {
        return [
            '29 menit → 0' => [29, 0],
            '30 menit → 30' => [30, 30],
            '54 menit → 30' => [54, 30],
            '55 menit → 60 (toleransi)' => [55, 60],
            '74 menit → 60' => [74, 60],
            '84 menit → 60' => [84, 60],
            '85 menit → 90 (toleransi)' => [85, 90],
            '114 menit → 90' => [114, 90],
            '115 menit → 120 (toleransi)' => [115, 120],
        ];
    }

    /**
     * @dataProvider pembulatanLemburProvider
     */
    #[DataProvider('pembulatanLemburProvider')]
    public function test_pembulatan_lembur_hari_kerja(int $minutes, int $expected): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        $checkOut = (new \DateTimeImmutable('2026-10-12 17:00:00'))->modify("+{$minutes} minutes");

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '12-10-2026', 'time' => $checkOut->format('H:i:s')],
        ]);

        $this->import($path);

        $row = $this->autolog($employee->id, self::START);
        $this->assertSame($expected, (int) $row->lembur);
        $this->assertSame(round($expected / 60, 2), (float) $row->lembur_calc);
    }

    public function test_lm_tidak_dibulatkan(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, ['2026-10-18']); // Minggu, is_sun = 1

        // 17:00 + 74 menit = 18:14 → lm mentah 74, bukan 60
        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '18-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '18-10-2026', 'time' => '18:14:00'],
        ]);

        $this->import($path);

        $row = $this->autolog($employee->id, '2026-10-18');
        $this->assertSame(0, (int) $row->lembur);
        $this->assertSame(74, (int) $row->lm);
        $this->assertSame(round(74 / 60, 2), (float) $row->lm_calc);
    }

    public function test_pivot_ikut_terbaca(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        $path = $this->pivotWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'scans' => ['08:00:00', '18:00:00']],
        ]);

        $result = $this->import($path, 'scanlog.xlsx');

        $this->assertSame('pivot', $result['format']);
        $this->assertSame(1, $result['inserted']);

        $row = $this->autolog($employee->id, self::START);
        $this->assertSame('2026-10-12 08:00:00', $row->check_in);
        $this->assertSame('2026-10-12 18:00:00', $row->check_out);
        $this->assertSame(60, (int) $row->lembur);
    }

    public function test_scan_di_luar_urutan_tetap_dinormalkan(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        // Scan pulang ditulis lebih dulu di file
        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '18:00:00'],
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '07:55:00'],
        ]);

        $this->import($path);

        $row = $this->autolog($employee->id, self::START);
        $this->assertSame('2026-10-12 07:55:00', $row->check_in);
        $this->assertSame('2026-10-12 18:00:00', $row->check_out);
    }

    // ════════════════════════════════════════════════════════════════
    //  Lembur & lm
    // ════════════════════════════════════════════════════════════════

    public function test_satu_scan_saja_check_out_null_dan_lembur_nol(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
        ]);

        $result = $this->import($path);

        $this->assertSame(1, $result['inserted']);

        $row = $this->autolog($employee->id, self::START);
        $this->assertSame('2026-10-12 08:00:00', $row->check_in);
        $this->assertNull($row->check_out);
        $this->assertSame(1, (int) $row->scan_count);
        $this->assertSame(0, (int) $row->lembur);
        $this->assertSame(0, (int) $row->lm);
    }

    public function test_pulang_lebih_awal_dari_jadwal_lembur_nol(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '16:00:00'],
        ]);

        $this->import($path);

        $this->assertSame(0, (int) $this->autolog($employee->id, self::START)->lembur);
    }

    public function test_hari_minggu_masuk_lm_bukan_lembur(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, ['2026-10-18']); // Minggu, is_sun = 1

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '18-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '18-10-2026', 'time' => '19:00:00'],
        ]);

        $this->import($path);

        $row = $this->autolog($employee->id, '2026-10-18');
        $this->assertSame(1, (int) $row->is_sun);
        $this->assertSame(0, (int) $row->lembur);
        $this->assertSame(120, (int) $row->lm);
    }

    public function test_hari_libur_tabel_holiday_masuk_lm(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        Holiday::create([
            'uuid' => (string) Str::uuid(),
            'date' => self::START,
            'name' => 'Libur Nasional',
            'is_national_holiday' => true,
        ]);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '18:00:00'],
        ]);

        $this->import($path);

        $row = $this->autolog($employee->id, self::START);
        $this->assertSame(1, (int) $row->is_holiday);
        $this->assertSame(0, (int) $row->lembur);
        $this->assertSame(60, (int) $row->lm);
    }

    public function test_tanpa_shift_jadwal_actual_kosong_dan_lembur_nol(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        // Shift tanpa jam kerja (mis. kode 'M' / 'L')
        DB::table('sch_shifts')->where('id', $this->shift->id)->update([
            'work_hour_start' => null,
            'work_hour_end' => null,
        ]);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '18:00:00'],
        ]);

        $this->import($path);

        $row = $this->autolog($employee->id, self::START);
        $this->assertNull($row->actual_in);
        $this->assertNull($row->actual_out);
        $this->assertSame(0, (int) $row->lembur);
        $this->assertSame(0, (int) $row->lm);
    }

    // ════════════════════════════════════════════════════════════════
    //  Scope
    // ════════════════════════════════════════════════════════════════

    public function test_tanggal_di_luar_periode_diabaikan(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '19-10-2026', 'time' => '08:00:00'],
        ]);

        $result = $this->import($path);

        $this->assertSame(1, $result['inserted']);
        $this->assertNull($this->autolog($employee->id, '2026-10-19'));
    }

    public function test_pin_tidak_dikenal_diabaikan(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '9999', 'date' => '12-10-2026', 'time' => '08:00:00'],
        ]);

        $result = $this->import($path);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(['9999'], $result['unmatched_pins']);
    }

    public function test_karyawan_di_luar_grup_periode_diabaikan(): void
    {
        $inGroup = $this->makeEmployee('1');
        $outsideGroup = $this->makeEmployee('2');
        $this->enroll($inGroup, [self::START]);
        // Karyawan 2 punya roster, tapi tidak terdaftar di grup periode
        $this->seedRoster($outsideGroup, [self::START]);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '2', 'date' => '12-10-2026', 'time' => '08:00:00'],
        ]);

        $result = $this->import($path);

        $this->assertSame(1, $result['inserted']);
        $this->assertNull($this->autolog($outsideGroup->id, self::START));
    }

    public function test_karyawan_di_grup_tanpa_roster_tetap_diimport(): void
    {
        $employee = $this->makeEmployee('1');
        // Masuk grup periode, tapi tidak punya roster sama sekali
        $this->enrollInGroup($employee);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '18:00:00'],
        ]);

        $result = $this->import($path);

        $this->assertSame(1, $result['inserted']);

        $row = $this->autolog($employee->id, self::START);
        $this->assertSame('2026-10-12 08:00:00', $row->check_in);
        $this->assertSame('2026-10-12 18:00:00', $row->check_out);
        $this->assertNull($row->actual_in);
        $this->assertNull($row->actual_out);
        $this->assertNull($row->employee_shift_roster_id);
        $this->assertSame(0, (int) $row->lembur);
    }

    public function test_baris_di_luar_file_dibiarkan(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START, '2026-10-13']);

        // Baris lama yang tanggalnya tidak ada di file
        DB::table('attendance_autologs')->insert([
            'employee_id' => $employee->id,
            'date' => '2026-10-13',
            'status' => 'absent',
            'check_in' => null,
            'import_batch' => 'IMP_LAIN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
        ]);

        $this->import($path);

        $untouched = $this->autolog($employee->id, '2026-10-13');
        $this->assertSame('absent', $untouched->status);
        $this->assertSame('IMP_LAIN', $untouched->import_batch);
    }

    // ════════════════════════════════════════════════════════════════
    //  Upsert
    // ════════════════════════════════════════════════════════════════

    public function test_import_dua_kali_idempoten(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START, '2026-10-13']);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '18:00:00'],
            ['pin' => '1', 'date' => '13-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '13-10-2026', 'time' => '18:00:00'],
        ]);

        $first = $this->import($path);
        $this->assertSame(2, $first['inserted']);
        $this->assertSame(0, $first['updated']);

        $second = $this->import($path);
        $this->assertSame(0, $second['inserted']);
        $this->assertSame(2, $second['updated']);

        $this->assertSame(2, DB::table('attendance_autologs')->count());
    }

    /**
     * Sekaligus menguji tiga hal: baris soft-deleted dihidupkan kembali,
     * kolom basi (lm/lm_calc/is_sun) direset, dan unique index tidak ditabrak.
     */
    public function test_baris_soft_deleted_dihidupkan_dan_kolom_basi_direset(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, ['2026-10-13']); // Selasa, hari kerja

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '13-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '13-10-2026', 'time' => '18:00:00'],
        ]);

        $this->import($path);

        DB::table('attendance_autologs')
            ->where('employee_id', $employee->id)
            ->where('date', '2026-10-13')
            ->update([
                'deleted_at' => now(),
                'lm' => 99,
                'lm_calc' => 1.65,
                'is_sun' => 1,
                'notes' => 'diedit manual',
            ]);

        $result = $this->import($path);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['updated']);

        $row = $this->autolog($employee->id, '2026-10-13');

        $this->assertNull($row->deleted_at, 'Baris soft-deleted harus dihidupkan kembali.');
        $this->assertSame(0, (int) $row->lm);
        // decimal(5,2) diformat beda oleh SQLite vs MySQL — bandingkan sebagai angka
        $this->assertSame(0.0, (float) $row->lm_calc);
        $this->assertSame(0, (int) $row->is_sun);
        $this->assertStringStartsWith('SCAN_', $row->import_batch);
        $this->assertSame(1, DB::table('attendance_autologs')->count());
    }

    public function test_baris_terkunci_dilewati(): void
    {
        $employee = $this->makeEmployee('1');
        $this->enroll($employee, [self::START]);

        DB::table('attendance_autologs')->insert([
            'employee_id' => $employee->id,
            'date' => self::START,
            'status' => 'present',
            'check_in' => '2026-10-12 07:00:00',
            'is_locked' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = $this->rawWorkbook([
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '08:00:00'],
            ['pin' => '1', 'date' => '12-10-2026', 'time' => '18:00:00'],
        ]);

        $result = $this->import($path);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['locked']);
        $this->assertSame('2026-10-12 07:00:00', $this->autolog($employee->id, self::START)->check_in);
    }

    // ════════════════════════════════════════════════════════════════
    //  Validasi berkas
    // ════════════════════════════════════════════════════════════════

    public function test_ekstensi_tidak_didukung_ditolak(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scanlog').'.txt';
        file_put_contents($path, 'bukan excel');

        $response = $this->actingAs($this->user, 'sanctum')->post(self::URL, [
            'payroll_period_id' => $this->period->id,
            'file' => new UploadedFile($path, 'scanlog.txt', null, null, true),
        ]);

        $response->assertStatus(422);
    }

    public function test_excel_bukan_scanlog_ditolak(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Bukan');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Scanlog');
        $path = $this->saveWorkbook($spreadsheet);

        $response = $this->actingAs($this->user, 'sanctum')->post(self::URL, [
            'payroll_period_id' => $this->period->id,
            'file' => new UploadedFile($path, 'lain.xlsx', null, null, true),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Format file tidak dikenali', $response->json('message'));
    }

    public function test_berkas_wajib_diupload(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->post(self::URL, [
            'payroll_period_id' => $this->period->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    /**
     * Jalur .bin/.dat tidak berubah: berkasnya diterima dan tetap menjalankan
     * AttendanceImportService (generate dari roster), bukan parser scanlog.
     *
     * Tanpa fixture karyawan, service lama berhenti lebih awal dan melaporkan
     * 0/0/0 — cukup untuk membuktikan berkasnya tidak ditolak dan tidak dibaca
     * sebagai Excel.
     */
    public function test_berkas_bin_tetap_jalur_lama(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fingerprint').'.bin';
        file_put_contents($path, "PIN\tTanggal\tJam\n1\t12/10/2026\t08:00:00\n");

        $response = $this->actingAs($this->user, 'sanctum')->post(self::URL, [
            'payroll_period_id' => $this->period->id,
            'file' => new UploadedFile($path, 'fingerprint.bin', null, null, true),
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.inserted', 0);
        $response->assertJsonPath('data.updated', 0);
        $this->assertStringContainsString('Jangan lupa jalankan Perhitungan Lembur!', $response->json('message'));
        $this->assertStringNotContainsString('scanlog', $response->json('message'));
        $this->assertSame(0, DB::table('attendance_autologs')->count());
    }
}
