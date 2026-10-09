<?php

namespace Tests\Feature;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\FileManager\Support\FileManagerPathGuard;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Input Data Lama — pemetaan berkas absen_core (dari File Manager) ke
 * att_prepares, leave_requests, dan att_consecutive_days.
 *
 * Periode uji sengaja pendek (2026-04-25 s/d 2026-04-30) supaya fixture kecil:
 *   Sab 25, Min 26, Sen 27, Sel 28, Rab 29, Kam 30.
 */
class LegacyAttendanceInputTest extends TestCase
{
    use RefreshDatabase;

    private const START = '2026-04-25';

    private const END = '2026-04-30';

    private User $user;

    private PayPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        // Rute legacy-input dibatasi role:superadmin karena sumbernya disk File Manager.
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->user = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->assignRole('superadmin');

        Storage::fake(FileManagerPathGuard::DISK);

        $this->period = PayPeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'April 2026',
            'period_year' => 2026,
            'period_month' => 4,
            'start_date' => self::START,
            'end_date' => self::END,
            'status' => 'active',
        ]);

        LeavePeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Periode Uji',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => 'active',
        ]);

        foreach ([
            ['CT', 'Cuti Tahunan', 'decrement'],
            ['SKT', 'Sakit', 'increment'],
            ['ITM', 'Izin Tidak Masuk', 'none'],
            ['CKM', 'Cuti Keluarga Meninggal', 'none'],
            ['CM', 'Cuti Menikah', 'none'],
        ] as [$code, $name, $balance]) {
            LeaveType::create([
                'uuid' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'category' => 'leave',
                'balance_type' => $balance,
                'is_paid' => true,
                'is_active' => true,
            ]);
        }

        $this->seedRoster();
    }

    // ════════════════════════════════════════════════════════════════
    //  Helper fixture
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

    private function seedRoster(): void
    {
        $shift = Shift::firstOrCreate(
            ['code' => 'P-R'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'OFFICE STAFF',
                'external_code' => 'P',
                'work_hour_start' => '08:00:00',
                'work_hour_end' => '17:00:00',
                'is_active' => true,
            ],
        );

        // Sengaja lewat query builder, bukan model: cast 'date' pada model menulis
        // "2026-04-30 00:00:00", sedangkan MySQL menyimpan kolom DATE sebagai
        // "2026-04-30". Fixture harus menyerupai MySQL, kalau tidak perbandingan
        // rentang tanggal di SQLite ikut membuang hari terakhir.
        $employeeIds = Employee::pluck('id')->all();

        if ($employeeIds === []) {
            return;
        }

        $existing = DB::table('sch_employee_shift_rosters')
            ->whereIn('employee_id', $employeeIds)
            ->get(['employee_id', 'date'])
            ->map(fn ($row) => $row->employee_id.'|'.substr((string) $row->date, 0, 10))
            ->all();

        $dates = [];
        for ($date = self::START; $date <= self::END; $date = date('Y-m-d', strtotime("$date +1 day"))) {
            $dates[] = $date;
        }

        foreach ($employeeIds as $employeeId) {
            foreach ($dates as $date) {
                if (in_array($employeeId.'|'.$date, $existing, true)) {
                    continue;
                }

                DB::table('sch_employee_shift_rosters')->insert([
                    'uuid' => (string) Str::uuid(),
                    'employee_id' => $employeeId,
                    'shift_id' => $shift->id,
                    'date' => $date,
                    'is_sun' => date('N', strtotime($date)) == 7,
                    'status' => 'scheduled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Bangun berkas absen_core: baris 1 tanggal, baris 2 nama hari, baris 3+ data.
     * $rows = [nip => [kolom-hari-0 => [kode, jam], ...]]
     */
    private function makeWorkbook(array $rows, ?array $days = null): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if ($days === null) {
            $days = [];
            for ($date = self::START; $date <= self::END; $date = date('Y-m-d', strtotime("$date +1 day"))) {
                $days[] = $date;
            }
        }

        $sheet->setCellValue('A1', 'NO');
        $sheet->setCellValue('B1', 'NAMA');

        $column = 3;
        foreach ($days as $date) {
            $sheet->setCellValueByColumnAndRow($column, 1, ExcelDate::PHPToExcel(new \DateTime($date)));
            $sheet->setCellValueByColumnAndRow($column, 2, date('l', strtotime($date)));
            $column += 2;
        }

        $rowNumber = 3;
        foreach ($rows as $nip => $cells) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, $nip);
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, 'NAMA '.$nip);

            $column = 3;
            foreach ($days as $index => $date) {
                [$code, $hours] = $cells[$index] ?? [null, null];
                if ($code !== null && $code !== '') {
                    $sheet->setCellValueByColumnAndRow($column, $rowNumber, $code);
                }
                if ($hours !== null) {
                    $sheet->setCellValueByColumnAndRow($column + 1, $rowNumber, $hours);
                }
                $column += 2;
            }

            $rowNumber++;
        }

        $path = tempnam(sys_get_temp_dir(), 'absen').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * Letakkan workbook ke disk File Manager dan kembalikan path relatifnya —
     * halaman Input Data Lama mengambil berkas dari sana, bukan dari unggahan.
     */
    private function sourceFile(array $rows, ?array $days = null): string
    {
        $local = $this->makeWorkbook($rows, $days);
        $name = 'absen_core_'.Str::random(6).'.xlsx';

        Storage::disk(FileManagerPathGuard::DISK)->put($name, file_get_contents($local));
        @unlink($local);

        return $name;
    }

    private function rawRow(string $date): ?object
    {
        return DB::table('att_prepares')->where('date', $date)->first();
    }

    /**
     * Jam & menit harus persis; detiknya sengaja acak 01-29.
     */
    private function assertClockIs(string $expectedPrefix, ?string $actual): void
    {
        $this->assertNotNull($actual, 'Waktu tidak boleh kosong.');
        $this->assertStringStartsWith($expectedPrefix, $actual);

        $seconds = (int) substr($actual, -2);
        $this->assertGreaterThanOrEqual(1, $seconds, "Detik {$seconds} di luar rentang 01-29.");
        $this->assertLessThanOrEqual(29, $seconds, "Detik {$seconds} di luar rentang 01-29.");
    }

    private function preview(string $path): array
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/v1/attendance/legacy-input/preview', [
                'path' => $path,
                'pay_period_id' => $this->period->id,
            ]);

        $response->assertStatus(200);

        return $response->json('data');
    }

    private function store(string $path): array
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/v1/attendance/legacy-input/store', [
                'path' => $path,
                'pay_period_id' => $this->period->id,
            ]);

        $response->assertStatus(200);

        return $response->json('data');
    }

    // ════════════════════════════════════════════════════════════════
    //  Parser
    // ════════════════════════════════════════════════════════════════

    public function test_parser_membaca_pasangan_kolom_ganjil_genap(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        // Sab: H tanpa lembur, Sen: H dengan lembur 2 jam
        $path = $this->sourceFile([
            '1001' => [0 => ['H', null], 2 => ['H', 2]],
        ]);

        $data = $this->preview($path);

        $this->assertSame(1, $data['summary']['file']['rows']);
        $this->assertSame(1, $data['summary']['file']['unique_nip']);
        $this->assertSame(6, $data['summary']['file']['days']);
        $this->assertSame(self::START, $data['summary']['file']['first_date']);
        $this->assertSame(self::END, $data['summary']['file']['last_date']);
    }

    // ════════════════════════════════════════════════════════════════
    //  Pemetaan kode
    // ════════════════════════════════════════════════════════════════

    public function test_pemetaan_kode_ke_status(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $path = $this->sourceFile([
            '1001' => [
                0 => ['H', null],        // Sab 25 -> hadir
                1 => ['SAKIT', null],    // Min 26 -> skt
                2 => ['CUTI', null],     // Sen 27 -> ct
                3 => ['IZIN', null],     // Sel 28 -> itm
                4 => ['CMD', null],      // Rab 29 -> ckm
                5 => ['CTN', null],      // Kam 30 -> cm
            ],
        ]);

        $this->store($path);

        $statuses = AttendancePrepare::orderBy('date')->pluck('status', 'date')->all();

        $this->assertSame('hadir', $statuses['2026-04-25']);
        $this->assertSame('skt', $statuses['2026-04-26']);
        $this->assertSame('ct', $statuses['2026-04-27']);
        $this->assertSame('itm', $statuses['2026-04-28']);
        $this->assertSame('ckm', $statuses['2026-04-29']);
        $this->assertSame('cm', $statuses['2026-04-30']);
    }

    public function test_sel_kosong_dan_kode_out_alfa_jadi_absent(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $path = $this->sourceFile([
            '1001' => [
                0 => ['OUT', null],
                1 => ['ALFA', null],
                2 => [null, null],
            ],
        ]);

        $this->store($path);

        $statuses = AttendancePrepare::orderBy('date')->pluck('status', 'date')->all();

        $this->assertSame('absent', $statuses['2026-04-25']);
        $this->assertSame('absent', $statuses['2026-04-26']);
        $this->assertSame('absent', $statuses['2026-04-27']);
    }

    public function test_kode_off_jadi_off(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $path = $this->sourceFile(['1001' => [0 => ['OFF', null]]]);
        $this->store($path);

        $this->assertSame('off', AttendancePrepare::whereDate('date', '2026-04-25')->value('status'));
    }

    // ════════════════════════════════════════════════════════════════
    //  Lembur & LM
    // ════════════════════════════════════════════════════════════════

    public function test_lembur_hari_kerja_jam_kali_60_dan_check_out_ditambah_lembur(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        // Senin 27 April, lembur 2 jam
        $path = $this->sourceFile(['1001' => [2 => ['H', 2]]]);
        $this->store($path);

        $row = $this->rawRow('2026-04-27');

        $this->assertSame(120, (int) $row->overtime);
        $this->assertSame(0, (int) $row->lm);
        $this->assertSame('08:00:00', $row->schedule_in);
        $this->assertSame('17:00:00', $row->schedule_out);
        $this->assertClockIs('2026-04-27 08:00:', $row->check_in);
        // check_out = jam pulang jadwal (17:00) + 2 jam lembur
        $this->assertClockIs('2026-04-27 19:00:', $row->check_out);
    }

    public function test_kode_l_menjadi_lm_480_dan_jam_tetap(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        // Minggu 26 April berkode L
        $path = $this->sourceFile(['1001' => [1 => ['L', null]]]);
        $this->store($path);

        $row = $this->rawRow('2026-04-26');

        $this->assertSame('hadir', $row->status);
        $this->assertSame(480, (int) $row->lm);
        $this->assertSame(0, (int) $row->overtime);
        $this->assertSame('08:00:00', $row->schedule_in);
        $this->assertSame('16:00:00', $row->schedule_out);
        $this->assertClockIs('2026-04-26 08:00:', $row->check_in);
        $this->assertClockIs('2026-04-26 16:00:', $row->check_out);
    }

    public function test_minggu_tanpa_kode_dengan_jam_lembur_jadi_lm(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        // Minggu 26 April, kode kosong, lembur 3,5 jam
        $path = $this->sourceFile(['1001' => [1 => [null, 3.5]]]);
        $this->store($path);

        $row = $this->rawRow('2026-04-26');

        $this->assertSame('hadir', $row->status);
        $this->assertSame(210, (int) $row->lm);
        $this->assertSame(0, (int) $row->overtime);
        $this->assertSame('08:00:00', $row->schedule_in);
        $this->assertSame('16:00:00', $row->schedule_out);
        $this->assertClockIs('2026-04-26 08:00:', $row->check_in);
        // check_out = 08:00 + 3,5 jam
        $this->assertClockIs('2026-04-26 11:30:', $row->check_out);
    }

    public function test_detik_check_in_dan_check_out_selalu_antara_01_dan_29(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        // Campur semua bentuk hari yang punya jam: H (dengan & tanpa lembur),
        // Minggu berlembur, dan kode L.
        $path = $this->sourceFile([
            '1001' => [
                0 => ['H', 1],
                1 => [null, 3.5],
                2 => ['H', 2],
                3 => ['L', null],
                4 => ['H', null],
                5 => ['H', 4],
            ],
        ]);

        $this->store($path);

        $checked = 0;

        foreach (DB::table('att_prepares')->get(['check_in', 'check_out']) as $row) {
            foreach ([$row->check_in, $row->check_out] as $value) {
                if ($value === null) {
                    continue;
                }

                $seconds = (int) substr($value, -2);
                $this->assertGreaterThanOrEqual(1, $seconds, "Detik {$seconds} pada {$value} di bawah 01.");
                $this->assertLessThanOrEqual(29, $seconds, "Detik {$seconds} pada {$value} di atas 29.");
                $checked++;
            }
        }

        $this->assertSame(12, $checked, 'Enam hari berlalu: tiap hari harus punya check_in dan check_out.');
    }

    // ════════════════════════════════════════════════════════════════
    //  CSF & consecutive
    // ════════════════════════════════════════════════════════════════

    public function test_csf_menulis_consecutive_day_tanpa_jam(): void
    {
        $employee = $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $path = $this->sourceFile([
            '1001' => [0 => ['CSF', null], 2 => ['CSF', null]],
        ]);

        $this->store($path);

        $rows = AttendancePrepare::orderBy('date')->get();
        $this->assertSame('hadir', $rows[0]->status);
        $this->assertSame(AttendancePrepare::REVIEW_CSF, $rows[0]->review_status);
        $this->assertNull($rows[0]->check_in);
        $this->assertNull($rows[0]->check_out);
        $this->assertNull($rows[0]->schedule_in);

        // Satu baris att_consecutive_days per sel CSF
        $this->assertSame(2, DB::table('att_consecutive_days')->where('employee_id', $employee->id)->count());
        $consecutive = DB::table('att_consecutive_days')->where('employee_id', $employee->id)
            ->where('start_date', '2026-04-27')->first();
        $this->assertSame(1, $consecutive->total_days);
        $this->assertSame('worked', $consecutive->type);
        $this->assertSame('CONSECUTIVE', $consecutive->notes);
    }

    // ════════════════════════════════════════════════════════════════
    //  Leave request
    // ════════════════════════════════════════════════════════════════

    public function test_cuti_berurutan_digabung_jadi_satu_request_dan_saldo_terpotong(): void
    {
        $employee = $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $ct = LeaveType::where('code', 'CT')->first();
        EmployeeLeave::create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'leave_type_id' => $ct->id,
            'leave_period_id' => LeavePeriod::first()->id,
            'transaction_type' => 'increment',
            'amount' => 12,
            'created_by' => $this->user->id,
        ]);

        // CUTI Sen 27 & Sel 28 (berurutan) + CUTI Kam 30 (terpisah)
        $path = $this->sourceFile([
            '1001' => [2 => ['CUTI', null], 3 => ['CUTI', null], 5 => ['CUTI', null]],
        ]);

        $this->store($path);

        $this->assertSame(2, DB::table('leave_requests')->count());

        $merged = DB::table('leave_requests')->where('start_date', '2026-04-27')->first();
        $this->assertSame('2026-04-28', $merged->end_date);
        $this->assertSame(2, $merged->days_requested);
        $this->assertSame('approved', $merged->status);
        // Saldo 12 - 2 hari untuk rentang pertama
        $this->assertSame(10, $merged->sisa_cuti);

        // Saldo CT terpotong total 3 hari (2 + 1)
        $balance = DB::table('employee_leaves')->where('employee_id', $employee->id)->value('amount');
        $this->assertSame('9.00', number_format((float) $balance, 2, '.', ''));
    }

    public function test_tanggal_cuti_yang_sudah_punya_leave_request_approved_dilewati(): void
    {
        $employee = $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $ct = LeaveType::where('code', 'CT')->first();
        LeaveRequest::create([
            'employee_id'     => $employee->id,
            'leave_type_id'   => $ct->id,
            'leave_period_id' => LeavePeriod::first()->id,
            'start_date'      => '2026-04-28',
            'end_date'        => '2026-04-28',
            'days_requested'  => 1,
            'status'          => 'approved',
        ]);

        // CUTI 27, 28, 29 — 28 sudah terisi, jadi rentangnya terpecah jadi 27 dan 29.
        $path = $this->sourceFile([
            '1001' => [2 => ['CUTI', null], 3 => ['CUTI', null], 4 => ['CUTI', null]],
        ]);

        $data = $this->store($path);

        $this->assertCount(1, $data['summary']['leave_requests']['skipped']);
        $this->assertSame('2026-04-28', $data['summary']['leave_requests']['skipped'][0]['date']);
        $this->assertSame(2, $data['summary']['leave_requests']['total']);

        // 1 record lama + 2 rentang baru
        $this->assertSame(3, DB::table('leave_requests')->count());
        $this->assertSame(1, DB::table('leave_requests')->where('start_date', '2026-04-27')->where('end_date', '2026-04-27')->count());
        $this->assertSame(1, DB::table('leave_requests')->where('start_date', '2026-04-29')->where('end_date', '2026-04-29')->count());
    }

    public function test_leave_request_pending_tidak_menghalangi_pengisian(): void
    {
        $employee = $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $ct = LeaveType::where('code', 'CT')->first();
        LeaveRequest::create([
            'employee_id'     => $employee->id,
            'leave_type_id'   => $ct->id,
            'leave_period_id' => LeavePeriod::first()->id,
            'start_date'      => '2026-04-28',
            'end_date'        => '2026-04-28',
            'days_requested'  => 1,
            'status'          => 'pending',
        ]);

        // CUTI 27, 28 — yang pending tidak menempati tanggal, jadi tetap satu rentang penuh.
        $path = $this->sourceFile([
            '1001' => [2 => ['CUTI', null], 3 => ['CUTI', null]],
        ]);

        $data = $this->store($path);

        $this->assertCount(0, $data['summary']['leave_requests']['skipped']);
        $this->assertSame(1, $data['summary']['leave_requests']['total']);

        $created = DB::table('leave_requests')->where('status', 'approved')->first();
        $this->assertSame('2026-04-27', $created->start_date);
        $this->assertSame('2026-04-28', $created->end_date);
        $this->assertSame(2, $created->days_requested);
    }

    // ════════════════════════════════════════════════════════════════
    //  GRP-JKT skeleton
    // ════════════════════════════════════════════════════════════════

    public function test_karyawan_grp_jkt_dibuatkan_record_penuh_dengan_kolom_kosong(): void
    {
        $jkt = $this->makeEmployee('2001', 'JAKARTA ORANG');
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        DB::table('employee_group_masters')->insert([
            'uuid' => (string) Str::uuid(),
            'group_label' => 'Imported Shift/Group',
            'name' => 'JKT',
            'code' => 'GRP-JKT',
            'is_active' => true,
        ]);
        DB::table('employee_groups')->insert([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $jkt->id,
            'reference_code' => 'GRP-JKT',
        ]);

        // '2001' sengaja TIDAK ada di file supaya diisi sebagai skeleton GRP-JKT.
        $path = $this->sourceFile([
            '1001' => [0 => ['H', null]],
        ]);

        $data = $this->store($path);

        $this->assertSame(1, $data['summary']['employees']['grp_jkt_skeleton']);

        $rows = AttendancePrepare::where('employee_id', $jkt->id)->orderBy('date')->get();
        $this->assertCount(6, $rows);
        $this->assertSame(AttendancePrepare::REVIEW_CEK, $rows[0]->review_status);
        $this->assertNull($rows[0]->check_in);
        $this->assertNull($rows[0]->schedule_in);
        $this->assertSame(0, $rows[0]->overtime);
        $this->assertSame(0, $rows[0]->lm);
        // 2026-04-26 adalah Minggu -> off, sisanya absent
        $this->assertSame('off', DB::table('att_prepares')
            ->where('employee_id', $jkt->id)->where('date', '2026-04-26')->value('status'));
        $this->assertSame('absent', DB::table('att_prepares')
            ->where('employee_id', $jkt->id)->where('date', '2026-04-25')->value('status'));
    }

    // ════════════════════════════════════════════════════════════════
    //  Validasi & idempotensi
    // ════════════════════════════════════════════════════════════════

    public function test_menolak_berkas_yang_tanggalnya_tidak_sesuai_periode(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        // Berkas hanya berisi 2 hari, sedangkan periode 6 hari
        $path = $this->sourceFile(
            ['1001' => [0 => ['H', null]]],
            ['2026-04-25', '2026-04-26'],
        );

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/v1/attendance/legacy-input/preview', [
                'path' => $path,
                'pay_period_id' => $this->period->id,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('tidak sama dengan periode', $response->json('message'));
    }

    public function test_menolak_bila_roster_belum_ada(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        // sengaja tidak seedRoster()

        $path = $this->sourceFile(['1001' => [0 => ['H', null]]]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/v1/attendance/legacy-input/preview', [
                'path' => $path,
                'pay_period_id' => $this->period->id,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('belum ada', $response->json('message'));
        $this->assertSame(0, AttendancePrepare::count());
    }

    public function test_menolak_path_yang_menunjuk_folder(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        Storage::disk(FileManagerPathGuard::DISK)->makeDirectory('arsip');

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/v1/attendance/legacy-input/preview', [
                'path' => 'arsip',
                'pay_period_id' => $this->period->id,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('bukan sebuah berkas', $response->json('message'));
    }

    public function test_menolak_berkas_yang_bukan_spreadsheet(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        Storage::disk(FileManagerPathGuard::DISK)->put('catatan.txt', 'bukan spreadsheet');

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/v1/attendance/legacy-input/preview', [
                'path' => 'catatan.txt',
                'pay_period_id' => $this->period->id,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('spreadsheet', $response->json('message'));
    }

    public function test_menolak_path_yang_keluar_dari_root_file_manager(): void
    {
        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/v1/attendance/legacy-input/preview', [
                'path' => '../../.env',
                'pay_period_id' => $this->period->id,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Path tidak valid', $response->json('message'));
    }

    public function test_hrmanager_tidak_bisa_mengakses(): void
    {
        Role::create(['name' => 'hrmanager', 'guard_name' => 'web']);

        $hr = User::create([
            'name' => 'HR Manager',
            'email' => 'hr@test.com',
            'password' => bcrypt('password'),
        ]);
        $hr->assignRole('hrmanager');

        $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $path = $this->sourceFile(['1001' => [0 => ['H', null]]]);

        $this->actingAs($hr, 'sanctum')
            ->post('/api/v1/attendance/legacy-input/preview', [
                'path' => $path,
                'pay_period_id' => $this->period->id,
            ])
            ->assertStatus(403);
    }

    public function test_menjalankan_dua_kali_tidak_menduplikasi_data(): void
    {
        $employee = $this->makeEmployee('1001', 'BUDI');
        $this->seedRoster();

        $cells = [2 => ['CUTI', null], 3 => ['CSF', null]];

        // Berkas terpisah tiap request supaya yang diuji benar-benar penulisan ulang.
        $this->store($this->sourceFile(['1001' => $cells]));
        $second = $this->store($this->sourceFile(['1001' => $cells]));

        $this->assertSame(0, $second['result']['att_prepare']['inserted']);
        $this->assertSame(6, $second['result']['att_prepare']['updated']);
        $this->assertSame(0, $second['result']['leave_requests']);
        $this->assertSame(0, $second['result']['consecutive']);

        $this->assertSame(6, AttendancePrepare::where('employee_id', $employee->id)->count());
        $this->assertSame(1, DB::table('leave_requests')->count());
        $this->assertSame(1, DB::table('att_consecutive_days')->count());
    }
}
