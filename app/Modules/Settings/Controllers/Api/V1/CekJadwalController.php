<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\WorkPattern;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Attendance\Models\RawLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CekJadwalController extends Controller
{
    /**
     * GET /api/v1/settings/cek-jadwal?period_id=X&page=1&per_page=50&search=
     * Ambil semua roster dalam periode, lengkap dengan scan log dan opsi dropdown.
     */
    public function index(Request $request)
    {
        $request->validate(['period_id' => 'required|exists:pay_periods,id']);

        $period = PayPeriod::findOrFail($request->period_id);
        $startDate = $period->start_date->toDateString();
        $endDate   = $period->end_date->toDateString();
        $perPage   = min((int) $request->input('per_page', 50), 200);
        $search    = $request->input('search', '');

        // Ambil semua work patterns untuk dropdown WP
        $workPatterns = WorkPattern::active()->orderBy('code')->get(['id', 'code', 'name']);

        // Base query
        $query = EmployeeShiftRoster::with(['employee', 'shift', 'workPattern'])
            ->whereBetween('date', [$startDate, $endDate]);

        // Search filter
        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $rosters = $query->orderBy('date')->orderBy('id')->paginate($perPage);

        // Kumpulkan semua employee_code untuk batch query scan log
        $employeeCodes = $rosters->pluck('employee.employee_code')->unique()->filter()->values();

        // Ambil scan log dalam rentang periode + jam 05:30-23:55
        $rawLogs = RawLog::whereIn('employee_code', $employeeCodes)
            ->whereBetween('scan_datetime', [
                $startDate . ' 05:30:00',
                $endDate . ' 23:55:00',
            ])
            ->orderBy('scan_datetime')
            ->get()
            ->groupBy(function ($log) {
                return $log->employee_code . '|' . Carbon::parse($log->scan_datetime)->toDateString();
            });

        // Untuk setiap roster, kumpulkan shifts dari work_pattern terkait (buat dropdown kode)
        $workPatternIds = $rosters->pluck('work_pattern_id')->unique()->filter()->values();
        $allShifts = Shift::whereIn('work_pattern_id', $workPatternIds)
            ->where('is_active', true)
            ->get()
            ->groupBy('work_pattern_id');

        $data = $rosters->map(function ($roster) use ($rawLogs, $allShifts) {
            $employeeCode = $roster->employee->employee_code ?? null;
            $dateStr      = $roster->date->toDateString();
            $key          = $employeeCode . '|' . $dateStr;

            // Scan logs untuk employee+date ini
            $logs = $rawLogs->get($key, collect([]));

            // Scan log dalam format string (jam saja)
            $scanLogTimes = $logs->map(function ($log) {
                return Carbon::parse($log->scan_datetime)->format('H:i');
            })->implode(', ');

            // Shifts untuk work_pattern roster ini (dropdown kode)
            $wpShifts = $allShifts->get($roster->work_pattern_id, collect([]));
            $shiftOptions = $wpShifts->map(function ($s) {
                return [
                    'id'            => $s->id,
                    'code'          => $s->code,
                    'external_code' => $s->external_code,
                ];
            })->values()->toArray();

            // Deteksi check_in: apakah ada scan log dalam range shift->check_in_start - check_in_end?
            $hasCheckIn = false;
            $checkInStart = null;
            $checkInEnd   = null;
            if ($roster->shift) {
                $checkInStart = $roster->shift->check_in_start;
                $checkInEnd   = $roster->shift->check_in_end;
                foreach ($logs as $log) {
                    $scanTime = Carbon::parse($log->scan_datetime)->format('H:i:s');
                    if ($scanTime >= $checkInStart && $scanTime <= $checkInEnd) {
                        $hasCheckIn = true;
                        break;
                    }
                }
            }

            // Tentukan nilai kode default
            $isSunday = Carbon::parse($dateStr)->isSunday();
            $defaultKode = 'None';
            if ($isSunday) {
                $defaultKode = 'M';
            } elseif ($hasCheckIn) {
                $defaultKode = $roster->external_code ?: ($roster->shift->external_code ?? 'None');
            }

            // Jadwal: None → work_hour_start (merah), else → kode
            $jadwal = ($defaultKode === 'None' || $defaultKode === null)
                ? ($roster->shift->work_hour_start ?? '-')
                : $defaultKode;
            $jadwalIsRed = ($defaultKode === 'None' || $defaultKode === null);

            return [
                'id'                => $roster->id,
                'date'              => $dateStr,
                'is_sunday'         => $isSunday,
                'nip'               => $roster->employee->nip ?? '-',
                'nama'              => $roster->employee->name ?? '-',
                'scan_log'          => $scanLogTimes ?: '-',
                'work_pattern_id'   => $roster->work_pattern_id,
                'work_pattern_code' => $roster->workPattern->code ?? '-',
                'shift_id'          => $roster->shift_id,
                'shift_code'        => $roster->shift_code,
                'external_code'     => $roster->external_code,
                'kode'              => $defaultKode,
                'jadwal'            => $jadwal,
                'jadwal_is_red'     => $jadwalIsRed,
                'has_check_in'      => $hasCheckIn,
                'shift_options'     => $shiftOptions,
                'check_in_start'    => $checkInStart,
                'check_in_end'      => $checkInEnd,
            ];
        });

        return response()->json([
            'data' => [
                'period'        => $period,
                'work_patterns' => $workPatterns,
                'rosters'       => $data->values(),
            ],
            'meta' => [
                'current_page' => $rosters->currentPage(),
                'last_page'    => $rosters->lastPage(),
                'per_page'     => $rosters->perPage(),
                'total'        => $rosters->total(),
            ],
        ]);
    }

    /**
     * PUT /api/v1/settings/cek-jadwal/{id}/work-pattern
     * Update work_pattern_id roster, auto-update shift ke shift pertama WP baru.
     */
    public function updateWorkPattern(Request $request, $id)
    {
        $request->validate(['work_pattern_id' => 'required|exists:sch_work_patterns,id']);

        $roster = EmployeeShiftRoster::findOrFail($id);
        $newWorkPatternId = $request->work_pattern_id;

        // Cari shift pertama yang aktif dari WP baru
        $firstShift = Shift::where('work_pattern_id', $newWorkPatternId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $roster->update([
            'work_pattern_id' => $newWorkPatternId,
            'shift_id'        => $firstShift?->id,
            'shift_code'      => $firstShift?->code,
            'external_code'   => $firstShift?->external_code,
            'updated_by'      => auth()->id() ?? 1,
        ]);

        $roster->load(['shift', 'workPattern']);

        return response()->json([
            'message' => 'Work Pattern berhasil diupdate',
            'data'    => $this->formatRosterResponse($roster),
        ]);
    }

    /**
     * PUT /api/v1/settings/cek-jadwal/{id}/kode
     * Update shift_code dan external_code roster berdasarkan kode yang dipilih.
     * Kode "None" akan mengosongkan, "M" untuk Minggu tetap simpan "M".
     */
    public function updateKode(Request $request, $id)
    {
        $request->validate(['kode' => 'required|string']);

        $roster = EmployeeShiftRoster::findOrFail($id);
        $kode   = $request->kode;

        if ($kode === 'None') {
            $roster->update([
                'external_code' => null,
                'updated_by'    => auth()->id() ?? 1,
            ]);
        } else {
            // Cari shift dengan external_code yang sesuai di WP roster saat ini
            $shift = Shift::where('work_pattern_id', $roster->work_pattern_id)
                ->where('external_code', $kode)
                ->where('is_active', true)
                ->first();

            $roster->update([
                'external_code' => $kode,
                'shift_code'    => $shift?->code ?? $roster->shift_code,
                'shift_id'      => $shift?->id ?? $roster->shift_id,
                'updated_by'    => auth()->id() ?? 1,
            ]);
        }

        $roster->load(['shift', 'workPattern']);

        return response()->json([
            'message' => 'Kode berhasil diupdate',
            'data'    => $this->formatRosterResponse($roster),
        ]);
    }

    /**
     * Format response untuk satu roster.
     */
    private function formatRosterResponse($roster)
    {
        $wpShifts = Shift::where('work_pattern_id', $roster->work_pattern_id)
            ->where('is_active', true)
            ->get();

        return [
            'id'                => $roster->id,
            'work_pattern_id'   => $roster->work_pattern_id,
            'work_pattern_code' => $roster->workPattern->code ?? '-',
            'shift_id'          => $roster->shift_id,
            'shift_code'        => $roster->shift_code,
            'external_code'     => $roster->external_code,
            'kode'              => $roster->external_code ?: 'None',
            'shift_options'     => $wpShifts->map(fn($s) => [
                'id'            => $s->id,
                'code'          => $s->code,
                'external_code' => $s->external_code,
            ])->values()->toArray(),
        ];
    }
}
