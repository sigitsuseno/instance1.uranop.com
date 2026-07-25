<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use App\Modules\KaryawanTitipan\Models\KaryawanTitipan;
use App\Modules\KaryawanTitipan\Models\KaryawanTitipanRoster;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    /**
     * Laporan Kehadiran — API Matrix Roster Harian.
     */
    public function index(Request $request)
    {
        $data = $this->buildData($request);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/v1/laporan/kehadiran/export
     * Export matrix kehadiran ke Excel.
     */
    public function export(Request $request)
    {
        $data = $this->buildData($request);

        $period = PayPeriod::find($request->integer('period_id'));
        $label = $period ? $period->name : 'Laporan_Kehadiran';
        $filename = 'Laporan_Kehadiran_' . str_replace(' ', '_', $label) . '.xlsx';

        // Group into 3 sections (same as frontend)
        $kelompok = [
            'A. JAKARTA'  => ['GRP-JKT'],
            'B. ALL IN'   => ['GRP-ALLIN', 'GRP-GD', 'GRP-SPR', 'GRP-SPC'],
            'C. PRINTING' => ['GRP-PS1', 'GRP-SS'],
        ];

        $sections = [];
        foreach ($kelompok as $labelSection => $codes) {
            $sectionData = $data['records']->filter(fn($r) =>
                !empty($r['group_codes']) && array_intersect($r['group_codes'], $codes)
            )->values()->toArray();

            $sections[] = [
                'label' => $labelSection,
                'data'  => $sectionData,
            ];
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Reports\Exports\AttendanceMatrixExport(
                $sections,
                $data['dates'],
                $label
            ),
            $filename
        );
    }

    // ─── Shared Data Builder ────────────────────────────────────

    private function buildData(Request $request): array
    {
        $periodId = $request->integer('period_id');
        $groups   = $request->input('groups', []);

        // ── 1. Daftar periode
        $periods = PayPeriod::orderBy('start_date', 'desc')->get()
            ->map(fn(PayPeriod $p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'start_date' => $p->start_date->format('Y-m-d'),
                'end_date'   => $p->end_date->format('Y-m-d'),
            ])->values();

        // ── 2. Periode terpilih
        $period = $periodId
            ? PayPeriod::find($periodId)
            : PayPeriod::orderBy('start_date', 'desc')->first();

        if (! $period) {
            return [
                'periods' => $periods,
                'dates'   => [],
                'records' => collect(),
                'filters' => ['groups' => $groups, 'period_id' => $periodId],
            ];
        }

        $startDate = Carbon::parse($period->start_date);
        $endDate   = Carbon::parse($period->end_date);

        $today = Carbon::today();
        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        // ── 3. Generate daftar tanggal
        $dates = [];
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            $dates[] = [
                'date'       => $d->format('Y-m-d'),
                'day'        => $d->format('d'),
                'day_name'   => $d->translatedFormat('D'),
                'is_weekend' => $d->isSunday(),
            ];
            $d->addDay();
        }

        $dateStrings = array_column($dates, 'date');

        // ── 4. Query att_prepares
        $prepares = AttendancePrepare::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()->groupBy('employee_id');

        // ── 5. Query karyawan
        $employees = Employee::query()
            ->activeInPeriod($startDate, $endDate)
            ->when(! empty($groups), fn($q) => $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $groups)))
            ->with(['groups'])
            ->orderBy('name')
            ->get();

        // ── 6. Query hari libur
        $holidays = Holiday::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()->keyBy(fn(Holiday $h) => $h->date->format('Y-m-d'));

        // ── 7. Bangun matrix records
        $records = $employees->map(function (Employee $employee) use ($prepares, $holidays, $dateStrings) {
            $empPrepares = $prepares->get($employee->id, collect())
                ->keyBy(fn(AttendancePrepare $p) => $p->date->format('Y-m-d'));

            $totalUangMakan = 0;
            $attendance = [];
            foreach ($dateStrings as $dateStr) {
                $prep      = $empPrepares->get($dateStr);
                $holiday   = $holidays->get($dateStr);
                $carbon    = Carbon::parse($dateStr);
                $isWeekend = $carbon->isSunday();
                $isHoliday = $isWeekend || $holiday !== null;
                $dayOfWeek = $carbon->dayOfWeek;

                if ($prep) {
                    $status = $this->mapStatus($prep->status, $isHoliday);
                } else {
                    $status = ($isWeekend || $holiday) ? 'Off' : '-';
                }

                // Hitung uang_makan per hari (JKT default rate 15rb)
                $lemburHours = $prep ? (($prep->lm ?? 0) + ($prep->overtime ?? 0)) / 60 : 0;
                $uangMakan   = 0;

                if ($lemburHours >= 2) {
                    if ($dayOfWeek === 0 || $isHoliday) {
                        // Sunday / Holiday
                        if ($lemburHours >= 8) {
                            $uangMakan = 0; // minggu_full — default 0
                        } elseif ($lemburHours >= 4) {
                            $uangMakan = 0; // minggu_half — default 0
                        }
                    } elseif ($dayOfWeek === 6) {
                        // Saturday
                        if ($lemburHours >= 4) {
                            $uangMakan = 0; // sabtu_full — default 0
                        } elseif ($lemburHours >= 2) {
                            $uangMakan = 0; // sabtu_dua — default 0
                        }
                    } else {
                        // Weekday
                        $uangMakan = 15000;
                    }
                }

                $totalUangMakan += $uangMakan;

                $attendance[$dateStr] = [
                    'status'       => $status,
                    'is_holiday'   => $isHoliday,
                    'holiday_name' => $holiday?->name,
                    'lm'           => $prep?->lm ?? null,
                    'overtime'     => $prep?->overtime ?? null,
                    'uang_makan'   => $uangMakan,
                ];
            }

            return [
                'id'               => $employee->id,
                'employee_code'    => $employee->employee_code,
                'name'             => $employee->name,
                'group_codes'      => $employee->groups->pluck('reference_code')->toArray(),
                'attendance'       => $attendance,
                'total_uang_makan' => $totalUangMakan,
            ];
        });

        // ── 8. Karyawan Titipan → Section A. JAKARTA
        $titipanEmployees = KaryawanTitipan::where('status', 'aktif')->get();

        $titipanRecords = $titipanEmployees->map(function ($employee) use ($dateStrings, $startDate, $endDate) {
            $rosterRecords = KaryawanTitipanRoster::where('karyawan_titipan_id', $employee->id)
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->keyBy(fn($r) => $r->date->format('Y-m-d'));

            $uangMakanRate = (int) ($employee->component['uang_makan'] ?? 0);
            $totalUangMakan = 0;
            $attendance = [];

            foreach ($dateStrings as $dateStr) {
                $roster = $rosterRecords->get($dateStr);
                $status = $roster?->status ?? '-';

                $uangMakanDaily = ($status === 'H') ? $uangMakanRate : 0;
                $totalUangMakan += $uangMakanDaily;

                $attendance[$dateStr] = [
                    'status'     => $status,
                    'is_holiday' => false,
                    'uang_makan' => $uangMakanDaily,
                ];
            }

            return [
                'id'               => 'titipan_' . $employee->id,
                'employee_code'    => $employee->employee_code,
                'name'             => $employee->nama,
                'group_codes'      => ['GRP-JKT'],
                'attendance'       => $attendance,
                'total_uang_makan' => $totalUangMakan,
            ];
        });

        // Gabungkan records reguler + titipan
        $records = $records->concat($titipanRecords);

        return [
            'periods' => $periods,
            'dates'   => $dates,
            'records' => $records,
            'filters' => [
                'groups'    => $groups,
                'period_id' => $period->id,
            ],
        ];
    }

    // ─── Status Mapping ──────────────────────────────────────────

    private function mapStatus(string $statusRaw, bool $isHoliday): string
    {
        return match (true) {
            $statusRaw === 'hadir' && $isHoliday => 'L',
            $statusRaw === 'hadir'                => 'H',
            $statusRaw === 'absent'               => 'A',
            $statusRaw === 'libur',
            $statusRaw === 'off'                  => 'Off',
            $statusRaw === 'skt'                  => 'S',
            str_starts_with($statusRaw, 'c')      => 'C',
            $statusRaw === 'imt',
            $statusRaw === 'ipa'                  => $isHoliday ? 'L' : 'H',
            str_starts_with($statusRaw, 'i')      => 'I',
            default                               => '-',
        };
    }
}
