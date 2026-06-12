<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    /**
     * Laporan Kehadiran — API Matrix Roster Harian.
     * Dipanggil via GET /api/v1/laporan/kehadiran
     *
     * Query params:
     *   - period_id  (int)    ID periode payroll
     *   - groups[]   (array)  Kode group (GRP-PS1, GRP-ALLIN, dsb)
     */
    public function index(Request $request)
    {
        $periodId = $request->integer('period_id');
        $groups   = $request->input('groups', []);

        // ── 1. Daftar periode ─────────────────────────────────
        $periods = PayPeriod::orderBy('start_date', 'desc')->get()
            ->map(fn(PayPeriod $p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'start_date' => $p->start_date->format('Y-m-d'),
                'end_date'   => $p->end_date->format('Y-m-d'),
            ])
            ->values();

        // ── 2. Periode terpilih (default: terbaru) ────────────
        $period = $periodId
            ? PayPeriod::find($periodId)
            : PayPeriod::orderBy('start_date', 'desc')->first();

        if (! $period) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'periods' => $periods,
                    'dates'   => [],
                    'records' => [],
                    'filters' => [
                        'groups'    => $groups,
                        'period_id' => $periodId,
                    ],
                ],
            ]);
        }

        $startDate = Carbon::parse($period->start_date);
        $endDate   = Carbon::parse($period->end_date);

        // Jangan lewati hari ini
        $today = Carbon::today();
        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        // ── 3. Generate daftar tanggal ────────────────────────
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

        // ── 4. Query data kehadiran (att_prepares) ────────────
        $prepares = AttendancePrepare::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->groupBy('employee_id');

        // ── 5. Query karyawan (filter group + aktif dalam periode) ──
        $employees = Employee::query()
            ->activeInPeriod($startDate, $endDate)
            ->when(! empty($groups), fn($q) => $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $groups)))
            ->with(['groups'])
            ->orderBy('name')
            ->get();

        // ── 6. Query hari libur ───────────────────────────────
        $holidays = Holiday::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(fn(Holiday $h) => $h->date->format('Y-m-d'));

        // ── 7. Bangun matrix records ──────────────────────────
        $records = $employees->map(function (Employee $employee) use ($prepares, $holidays, $dateStrings) {
            $empPrepares = $prepares->get($employee->id, collect())
                ->keyBy(fn(AttendancePrepare $p) => $p->date->format('Y-m-d'));

            $attendance = [];
            foreach ($dateStrings as $dateStr) {
                $prep      = $empPrepares->get($dateStr);
                $holiday   = $holidays->get($dateStr);
                $carbon    = Carbon::parse($dateStr);
                $isWeekend = $carbon->isSunday();
                $isHoliday = $isWeekend || $holiday !== null;

                if ($prep) {
                    $status = $this->mapStatus($prep->status, $isHoliday);
                } else {
                    // Tidak ada record kehadiran
                    if ($isWeekend || $holiday) {
                        $status = 'Off';
                    } else {
                        $status = '-';
                    }
                }

                $attendance[$dateStr] = [
                    'status'       => $status,
                    'is_holiday'   => $isHoliday,
                    'holiday_name' => $holiday?->name,
                ];
            }

            return [
                'id'            => $employee->id,
                'employee_code' => $employee->employee_code,
                'name'          => $employee->name,
                'attendance'    => $attendance,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'periods' => $periods,
                'dates'   => $dates,
                'records' => $records,
                'filters' => [
                    'groups'    => $groups,
                    'period_id' => $period->id,
                ],
            ],
        ]);
    }

    // ─── Status Mapping ──────────────────────────────────────────────

    /**
     * Konversi status mentah dari att_prepares ke kode tampilan.
     *
     * @param  string $statusRaw  Status dari kolom att_prepares.status
     * @param  bool   $isHoliday  Apakah tanggal ini libur (weekend / holiday)
     * @return string Kode tampilan: H|A|Off|L|C|I|S|-
     */
    private function mapStatus(string $statusRaw, bool $isHoliday): string
    {
        return match (true) {
            // Libur Masuk: hadir di hari libur
            $statusRaw === 'hadir' && $isHoliday => 'L',

            // Hadir biasa
            $statusRaw === 'hadir'                => 'H',

            // Absen
            $statusRaw === 'absent'               => 'A',

            // Libur / Off
            $statusRaw === 'libur',
            $statusRaw === 'off'                  => 'Off',

            // Sakit
            $statusRaw === 'skt'                  => 'S',

            // Cuti (prefix 'c': ct, cm, cl, dll)
            str_starts_with($statusRaw, 'c')      => 'C',

            // Izin Masuk Terlambat / Izin Pulang Awal → tetap Hadir
            $statusRaw === 'imt',
            $statusRaw === 'ipa'                  => $isHoliday ? 'L' : 'H',

            // Izin lainnya (prefix 'i': itm, izn, dll)
            str_starts_with($statusRaw, 'i')      => 'I',

            // Fallback
            default                               => '-',
        };
    }
}
