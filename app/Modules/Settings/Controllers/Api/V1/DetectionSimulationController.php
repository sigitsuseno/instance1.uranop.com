<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Attendance\Services\DetectionWindowService;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Simulasi Deteksi Kehadiran.
 *
 * Membaca att_raw_logs dan mencocokkannya dengan roster karyawan berdasarkan
 * aturan window di DetectionWindowService. Window waktu diatur di halaman
 * (offcanvas) dan disimpan di system_settings.
 */
class DetectionSimulationController extends Controller
{
    public function __construct(private DetectionWindowService $windows)
    {
    }

    /**
     * GET /api/v1/settings/detection-simulation/settings
     */
    public function getSettings()
    {
        return response()->json(['data' => $this->windows->settings()]);
    }

    /**
     * PUT /api/v1/settings/detection-simulation/settings
     */
    public function updateSettings(Request $request)
    {
        $rules = [];
        foreach (array_keys(DetectionWindowService::DEFAULTS) as $key) {
            $rules[$key] = ['required', 'date_format:H:i'];
        }

        $validated = $request->validate($rules);

        foreach ($validated as $key => $value) {
            $setting = SystemSetting::firstOrNew(['key' => $key]);

            if (! $setting->exists) {
                $setting->uuid = (string) Str::uuid();
            }

            $setting->group      = DetectionWindowService::GROUP;
            $setting->value      = $value;
            $setting->type       = 'string';
            $setting->updated_by = auth()->id();

            $setting->save();
        }

        return response()->json([
            'message' => 'Pengaturan simulasi deteksi disimpan.',
            'data'    => $this->windows->settings(),
        ]);
    }

    /**
     * GET /api/v1/settings/detection-simulation?period_id=X&page=1&per_page=50&search=
     */
    public function fetch(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'page'      => 'nullable|integer|min:1',
            'per_page'  => 'nullable|integer|min:10|max:200',
            'search'    => 'nullable|string',
        ]);

        $period   = PayPeriod::findOrFail($request->period_id);
        $settings = $this->windows->settings();
        $perPage  = (int) $request->input('per_page', 50);
        $search   = trim((string) $request->input('search', ''));

        $startDate = $period->start_date->toDateString();
        $endDate   = $period->end_date->toDateString();

        $query = EmployeeShiftRoster::with([
                'employee:id,employee_code,nip,name',
                'shift:id,code,name,external_code',
                'workPattern:id,code,name,employee_type',
            ])
            ->whereBetween('date', [$startDate, $endDate]);

        if ($search !== '') {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $rosters = $query->orderBy('date')->orderBy('employee_id')->paginate($perPage);

        // Ambil scan log untuk karyawan di halaman ini. Rentang diperlebar
        // sehari di kedua sisi agar window yang melewati tengah malam tetap tercakup.
        // att_raw_logs.employee_code menyimpan PIN mesin (biasanya = NIP), jadi
        // cocokkan lewat NIP lebih dulu lalu fallback ke employee_code.
        $logKeys = $rosters->getCollection()
            ->flatMap(fn (EmployeeShiftRoster $roster) => [
                $roster->employee->nip ?? null,
                $roster->employee->employee_code ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        $logsByKey = collect();

        if ($logKeys->isNotEmpty()) {
            $logsByKey = RawLog::whereIn('employee_code', $logKeys)
                ->whereBetween('scan_datetime', [
                    Carbon::parse($startDate)->subDay()->startOfDay(),
                    Carbon::parse($endDate)->addDay()->endOfDay(),
                ])
                ->orderBy('scan_datetime')
                ->get(['id', 'employee_code', 'scan_datetime', 'machine_name'])
                ->groupBy('employee_code');
        }

        $rows = $rosters->getCollection()->map(
            fn (EmployeeShiftRoster $roster) => $this->buildRow($roster, $logsByKey, $settings)
        )->values();

        return response()->json([
            'data' => [
                'period'   => [
                    'id'         => $period->id,
                    'name'       => $period->name,
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                ],
                'settings' => $settings,
                'windows'  => $this->windowsPayload($settings),
                'rows'     => $rows,
            ],
            'meta' => [
                'current_page' => $rosters->currentPage(),
                'last_page'    => $rosters->lastPage(),
                'per_page'     => $rosters->perPage(),
                'total'        => $rosters->total(),
                'detected'     => $rows->filter(fn ($r) => $r['detected'] === true)->count(),
                'not_detected' => $rows->filter(fn ($r) => $r['detected'] === false)->count(),
                'no_rule'      => $rows->filter(fn ($r) => $r['detected'] === null)->count(),
            ],
        ]);
    }

    /**
     * Bentuk satu baris hasil simulasi untuk satu roster.
     */
    private function buildRow(EmployeeShiftRoster $roster, $logsByKey, array $settings): array
    {
        $dateStr      = $roster->date->toDateString();
        $employeeCode = $roster->employee->employee_code ?? null;
        $externalCode = $this->resolveExternalCode($roster);
        $group        = DetectionWindowService::resolveGroup($externalCode);

        $nip = $roster->employee->nip ?? null;

        if ($nip && $logsByKey->has($nip)) {
            $employeeLogs = $logsByKey->get($nip);
        } elseif ($employeeCode && $logsByKey->has($employeeCode)) {
            $employeeLogs = $logsByKey->get($employeeCode);
        } else {
            $employeeLogs = collect();
        }

        // Scan log mentah: seluruh log pada tanggal yang tercatat di att_raw_logs,
        // tanpa penyaringan window apa pun.
        $dateLogs = $employeeLogs->filter(function ($log) use ($dateStr) {
            return Carbon::parse($log->scan_datetime)->toDateString() === $dateStr;
        })->values();

        // Scan global: log yang jatuh di dalam Global Time Window.
        [$globalStart, $globalEnd] = $this->windows->windowRange(
            $dateStr,
            $settings['global_start_time'],
            $settings['global_end_time']
        );

        $globalLogs = $employeeLogs->filter(function ($log) use ($globalStart, $globalEnd) {
            $scan = Carbon::parse($log->scan_datetime);
            return $scan->greaterThanOrEqualTo($globalStart) && $scan->lessThanOrEqualTo($globalEnd);
        })->values();

        // Aturan deteksi mengikuti window shift / hari libur.
        $rule        = $this->windows->resolveRule($roster, $settings);
        $matchedLogs = $this->windows->matchedLogs($employeeLogs, $dateStr, $rule);

        return [
            'id'                 => $roster->id,
            'date'               => $dateStr,
            'employee_code'      => $employeeCode,
            'nip'                => $roster->employee->nip ?? '-',
            'name'               => $roster->employee->name ?? '-',
            'external_code'      => $externalCode ?: null,
            'shift_code'         => $roster->shift->code ?? $roster->shift_code,
            'shift_name'         => $roster->shift->name ?? null,
            'shift_group'        => $group,
            'shift_group_label'  => $group ? DetectionWindowService::GROUP_LABELS[$group] : null,
            'detection_rule'     => $rule['rule'],
            'rule_label'         => $rule['label'],
            'window_start'       => $rule['window_start'],
            'window_end'         => $rule['window_end'],
            'scan_log'           => $dateLogs
                ->map(fn ($log) => Carbon::parse($log->scan_datetime)->format('H:i:s'))
                ->implode(', '),
            'scan_count'         => $dateLogs->count(),
            'global_log'         => $globalLogs
                ->map(fn ($log) => Carbon::parse($log->scan_datetime)->format('H:i:s'))
                ->implode(', '),
            'global_count'       => $globalLogs->count(),
            'matched_log'        => $matchedLogs
                ->map(fn ($log) => Carbon::parse($log->scan_datetime)->format('H:i:s'))
                ->implode(', '),
            'matched_count'      => $matchedLogs->count(),
            'first_matched_at'   => $matchedLogs->isNotEmpty()
                ? Carbon::parse($matchedLogs->first()->scan_datetime)->format('H:i:s')
                : null,
            // null = tidak ada aturan deteksi untuk baris ini
            'detected'           => $rule['rule'] === 'none' ? null : $matchedLogs->isNotEmpty(),
        ];
    }

    /**
     * external_code dari shift roster, fallback ke kolom roster.
     */
    private function resolveExternalCode(EmployeeShiftRoster $roster): string
    {
        $code = $roster->shift->external_code ?? $roster->external_code ?? '';

        return strtoupper(trim((string) $code));
    }

    /**
     * Ringkasan window untuk ditampilkan di halaman.
     */
    private function windowsPayload(array $settings): array
    {
        return [
            'global' => [
                'label' => 'Global Time Window',
                'start' => $settings['global_start_time'],
                'end'   => $settings['global_end_time'],
            ],
            'pagi' => [
                'label' => 'Shift Pagi (P)',
                'start' => $settings['shift_pagi_start_time'],
                'end'   => $settings['shift_pagi_end_time'],
            ],
            'siang' => [
                'label' => 'Shift Siang (S)',
                'start' => $settings['shift_siang_start_time'],
                'end'   => $settings['shift_siang_end_time'],
            ],
            'malam' => [
                'label' => 'Shift Malam (ML)',
                'start' => $settings['shift_malam_start_time'],
                'end'   => $settings['shift_malam_end_time'],
            ],
        ];
    }
}
