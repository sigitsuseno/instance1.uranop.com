<?php

namespace App\Modules\Attendance\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\ConsecutiveDay;
use App\Modules\Attendance\Models\ManualDetect;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Attendance\Services\AttendanceSyncService;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ManualSyncController extends Controller
{
    protected AttendanceSyncService $syncService;

    public function __construct(AttendanceSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * GET /api/v1/attendance/manual-sync/data
     *
     * Query params:
     *   - period_id  (required) → pay_periods.id
     *   - start_date (optional) → override rentang dari periode
     *   - end_date   (optional) → override rentang dari periode
     *   - mode       (optional) → "fetch" (auto-detect + upsert) | "display" (hanya baca DB)
     *   - page       (optional) → halaman (default: 1)
     *   - per_page   (optional) → row per halaman (default: 200, max: 500)
     *   - employee_id (optional) → filter karyawan tertentu
     *   - search     (optional) → cari nama/nip
     */
    public function getData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id'   => 'required|integer|exists:pay_periods,id',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
            'mode'        => 'nullable|in:fetch,display',
            'page'        => 'nullable|integer|min:1',
            'per_page'    => 'nullable|integer|min:1|max:500',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'search'      => 'nullable|string|max:100',
        ]);

        $period    = PayPeriod::findOrFail($validated['period_id']);
        $startDate = $validated['start_date'] ?? $period->start_date->toDateString();
        $endDate   = $validated['end_date']   ?? $period->end_date->toDateString();
        $mode      = $validated['mode'] ?? 'fetch';
        $page      = (int) ($validated['page'] ?? 1);
        $perPage   = (int) ($validated['per_page'] ?? 10);

        Log::info('ManualSync: getData', [
            'period_id' => $period->id,
            'start'     => $startDate,
            'end'       => $endDate,
            'mode'      => $mode,
            'page'      => $page,
        ]);

        // ── Mode: display → langsung return dari DB ───────────
        if ($mode === 'display') {
            return $this->returnRecords($startDate, $endDate, $page, $perPage, $validated);
        }

        // ── Mode: fetch → auto-detect + upsert ────────────────

        // 1. Ambil semua roster dalam rentang
        $rosters = EmployeeShiftRoster::with(['employee', 'shift', 'workPattern'])
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        if ($rosters->isEmpty()) {
            return response()->json([
                'data'       => [],
                'pagination' => ['current_page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 0],
                'message'    => 'Tidak ada data roster untuk rentang ini.',
            ]);
        }

        // 2. Preload existing ManualDetect → key: "employee_id|date"
        $existingMap = ManualDetect::whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($item) => $item->employee_id . '|' . $item->date->toDateString());

        // 3. Single RawLog query untuk SELURUH rentang (hindari N query)
        $windowStart = Carbon::parse($startDate)->toDateString() . ' 05:50:00';
        $windowEnd   = Carbon::parse($endDate)->addDay()->toDateString() . ' 08:00:00';

        $allRawLogs = RawLog::whereBetween('scan_datetime', [$windowStart, $windowEnd])
            ->orderBy('scan_datetime')
            ->get();

        // Index logs by (date, employee_code) untuk O(1) lookup
        // Logs < 08:00 juga di-assign ke hari sebelumnya (overnight window)
        $logsIndex = [];
        foreach ($allRawLogs as $log) {
            $scanDate = $log->scan_datetime->toDateString();
            $scanTime = $log->scan_datetime->format('H:i:s');
            $empCode  = $log->employee_code;

            // Primary assignment
            if (!isset($logsIndex[$scanDate])) {
                $logsIndex[$scanDate] = [];
            }
            $logsIndex[$scanDate][$empCode][] = $log;

            // Overnight: logs before 08:00 also belong to previous day
            if ($scanTime < '08:00:00') {
                $prevDate = $log->scan_datetime->copy()->subDay()->toDateString();
                if (!isset($logsIndex[$prevDate])) {
                    $logsIndex[$prevDate] = [];
                }
                $logsIndex[$prevDate][$empCode][] = $log;
            }
        }

        // 4. Preload holidays
        $holidayDates = Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->toArray();
        $holidaySet = array_flip($holidayDates);

        $userId     = Auth::id();
        $inserts    = [];
        $updateCount = 0;
        $skipCount  = 0;

        $dateGroups = $rosters->groupBy(fn ($r) => $r->date->toDateString());

        foreach ($dateGroups as $dateStr => $dayRosters) {
            $isSunday  = Carbon::parse($dateStr)->isSunday();
            $isHoliday = isset($holidaySet[$dateStr]);

            // Ambil logs untuk tanggal ini dari index (O(1))
            $dayLogsIndex = $logsIndex[$dateStr] ?? [];

            foreach ($dayRosters as $roster) {
                $employee = $roster->employee;
                if (!$employee) continue;

                $key = $employee->id . '|' . $dateStr;
                $existing = $existingMap->get($key);

                // SKIP: sudah lengkap → gak perlu diproses ulang
                if ($existing && $existing->status === ManualDetect::STATUS_LENGKAP) {
                    $skipCount++;
                    continue;
                }

                $shift           = $roster->shift;
                $workPattern     = $roster->workPattern;
                $workPatternType = $roster->work_pattern_type ?? 'FIXED';
                $empCode         = $employee->nip ?? $employee->employee_code;
                $empLogs         = collect($dayLogsIndex[$empCode] ?? []);

                // Format logs untuk time_scan_result
                $allLogsFormatted = $empLogs->map(fn (RawLog $log) => [
                    'raw_log_id'    => $log->id,
                    'scan_datetime' => $log->scan_datetime->toDateTimeString(),
                    'scan_time'     => $log->scan_datetime->format('H:i:s'),
                    'machine_name'  => $log->machine_name,
                ])->values()->toArray();

                // Auto-detect
                $autoDetect     = null;
                $detectCheckIn  = null;
                $detectCheckOut = null;

                if ($empLogs->isNotEmpty() && $shift) {
                    try {
                        $detectResult = $this->syncService->detectForManualSync(
                            $empLogs, $shift, $dateStr, $isHoliday, $isSunday,
                            $workPatternType, $roster->external_code
                        );

                        $ci = $detectResult['check_in'] ?? null;
                        $co = $detectResult['check_out'] ?? null;

                        $detectCheckIn  = $ci instanceof Carbon ? $ci : null;
                        $detectCheckOut = $co instanceof Carbon ? $co : null;

                        $autoDetect = [
                            'check_in_log_id'  => $ci ? $this->findLogId($empLogs, $ci) : null,
                            'check_in_time'    => $detectCheckIn?->toDateTimeString(),
                            'check_out_log_id' => $co ? $this->findLogId($empLogs, $co) : null,
                            'check_out_time'   => $detectCheckOut?->toDateTimeString(),
                            'method'           => $workPatternType,
                        ];
                    } catch (\Throwable $e) {
                        Log::warning('ManualSync: auto-detect failed', [
                            'employee_id' => $employee->id,
                            'date'        => $dateStr,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }

                // Status awal
                $initialStatus = ($detectCheckIn && $detectCheckOut)
                    ? ManualDetect::STATUS_DRAFT
                    : ManualDetect::STATUS_PERHATIAN;

                // Build time_scan_result
                $tsr = [
                    'all_logs'    => $allLogsFormatted,
                    'auto_detect' => $autoDetect,
                ];

                if ($existing) {
                    // UPDATE: hanya field yang tidak overwrite user data
                    $updateData = [
                        'roster_id'         => $roster->id,
                        'work_pattern_id'   => $workPattern?->id,
                        'work_pattern_type' => $workPatternType,
                        'shift_id'          => $shift?->id,
                        'time_scan_result'  => $tsr,
                        'updated_by'        => $userId,
                    ];

                    // JANGAN overwrite check_in/check_out yang sudah di-set manual (non-null)
                    if ($existing->check_in === null && $detectCheckIn) {
                        $updateData['check_in'] = $detectCheckIn;
                    }
                    if ($existing->check_out === null && $detectCheckOut) {
                        $updateData['check_out'] = $detectCheckOut;
                    }

                    // Update status hanya jika belum lengkap
                    if ($existing->status !== ManualDetect::STATUS_LENGKAP) {
                        $updateData['status'] = $initialStatus;
                    }

                    $existing->update($updateData);
                    $updateCount++;
                } else {
                    // INSERT: kumpulkan untuk bulk insert
                    $inserts[] = [
                        'uuid'              => (string) Str::uuid(),
                        'employee_id'       => $employee->id,
                        'date'              => $dateStr,
                        'roster_id'         => $roster->id,
                        'work_pattern_id'   => $workPattern?->id,
                        'work_pattern_type' => $workPatternType,
                        'shift_id'          => $shift?->id,
                        'check_in'          => $detectCheckIn,
                        'check_out'         => $detectCheckOut,
                        'time_scan_result'  => json_encode($tsr),
                        'status'            => $initialStatus,
                        'created_by'        => $userId,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }
            }
        }

        // Bulk insert dalam chunk 500
        $insertedCount = 0;
        foreach (array_chunk($inserts, 500) as $chunk) {
            ManualDetect::insert($chunk);
            $insertedCount += count($chunk);
        }

        Log::info('ManualSync: fetch done', [
            'inserted' => $insertedCount,
            'updated'  => $updateCount,
            'skipped'  => $skipCount,
        ]);

        // Return paginated data
        return $this->returnRecords($startDate, $endDate, $page, $perPage, $validated, [
            'inserted' => $insertedCount,
            'updated'  => $updateCount,
            'skipped'  => $skipCount,
        ]);
    }

    /**
     * POST /api/v1/attendance/manual-sync/save
     *
     * Simpan satu atau banyak record. Support manual time input.
     *
     * Body: {
     *   mode: "single" | "all",
     *   items: [{
     *     id,                      // att_manual_detect.id
     *     check_in_log_id,         // null | int (raw_log_id) | "manual:HH:MM"
     *     check_out_log_id,        // null | int (raw_log_id) | "manual:HH:MM"
     *   }]
     * }
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode'                    => 'nullable|in:single,all',
            'items'                   => 'required|array|min:1',
            'items.*.id'              => 'required|integer|exists:att_manual_detect,id',
            'items.*.check_in_log_id' => 'nullable',
            'items.*.check_out_log_id'=> 'nullable',
        ]);

        $userId = Auth::id();
        $saved  = 0;
        $errors = [];

        foreach ($validated['items'] as $item) {
            DB::beginTransaction();
            try {
                $record = ManualDetect::findOrFail($item['id']);

                $checkIn  = $this->resolveCheckTime($record->date->toDateString(), $item['check_in_log_id'] ?? null);
                $checkOut = $this->resolveCheckTime($record->date->toDateString(), $item['check_out_log_id'] ?? null);

                // Deteksi manual input
                $manualIn  = is_string($item['check_in_log_id'] ?? null) && str_starts_with($item['check_in_log_id'], 'manual:');
                $manualOut = is_string($item['check_out_log_id'] ?? null) && str_starts_with($item['check_out_log_id'], 'manual:');

                // Update time_scan_result dengan info manual
                $tsr = $record->time_scan_result ?? [];
                if ($manualIn) {
                    $tsr['manual_in'] = str_replace('manual:', '', $item['check_in_log_id']);
                } else {
                    unset($tsr['manual_in']);
                }
                if ($manualOut) {
                    $tsr['manual_out'] = str_replace('manual:', '', $item['check_out_log_id']);
                } else {
                    unset($tsr['manual_out']);
                }

                // Tentukan status
                $status = ($checkIn && $checkOut)
                    ? ManualDetect::STATUS_LENGKAP
                    : ManualDetect::STATUS_PERHATIAN;

                $record->update([
                    'check_in'         => $checkIn,
                    'check_out'        => $checkOut,
                    'time_scan_result' => $tsr,
                    'status'           => $status,
                    'updated_by'       => $userId,
                ]);

                DB::commit();
                $saved++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = [
                    'id'    => $item['id'],
                    'error' => $e->getMessage(),
                ];
                Log::error('ManualSync: save item failed', [
                    'id'    => $item['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => "{$saved} record disimpan." . (count($errors) > 0 ? ' ' . count($errors) . ' gagal.' : ''),
            'saved'   => $saved,
            'errors'  => $errors,
        ]);
    }

    /**
     * POST /api/v1/attendance/manual-sync/push-prepare
     *
     * Push data dari att_manual_detect → att_prepares (upsert).
     * Memproses SEMUA record dalam rentang tanggal (bukan per halaman).
     *
     * Body: {
     *   period_id,  // untuk ambil start_date/end_date jika tidak di-override
     *   start_date, // optional, override rentang
     *   end_date,   // optional, override rentang
     * }
     */
    public function pushPrepare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id'  => 'required|integer|exists:pay_periods,id',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $period    = PayPeriod::findOrFail($validated['period_id']);
        $startDate = $validated['start_date'] ?? $period->start_date->toDateString();
        $endDate   = $validated['end_date']   ?? $period->end_date->toDateString();

        // Ambil SEMUA record att_manual_detect dalam rentang
        $records = ManualDetect::with(['roster', 'shift', 'workPattern'])
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        if ($records->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data manual detect untuk dipush.',
                'pushed'  => 0,
            ]);
        }

        $userId  = Auth::id();
        $pushed  = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($records as $record) {
            DB::beginTransaction();
            try {
                // Mapping review_status dari status
                $reviewStatus = match ($record->status) {
                    ManualDetect::STATUS_LENGKAP   => 'lengkap',
                    ManualDetect::STATUS_PERHATIAN => 'perhatian',
                    default                        => 'cek',
                };

                // Mapping status ke att_prepares format
                // att_prepares.status: cuti, izin, sakit, absent, hadir, terlambat, libur, off
                // att_manual_detect.status: draft, perhatian, lengkap
                // Jika check_in + check_out ada → 'hadir', else 'absent'
                $attStatus = ($record->check_in || $record->check_out) ? 'hadir' : 'absent';

                // Ambil schedule dari roster/shift
                $scheduleIn  = null;
                $scheduleOut = null;
                $roster = $record->roster;
                if ($roster?->shift) {
                    $scheduleIn  = $roster->shift->start_time?->format('H:i:s');
                    $scheduleOut = $roster->shift->end_time?->format('H:i:s');
                }

                DB::table('att_prepares')->upsert(
                    [
                        'employee_id'   => $record->employee_id,
                        'date'          => $record->date->toDateString(),
                        'periode_start' => $startDate,
                        'periode_end'   => $endDate,
                        'check_in'      => $record->check_in,
                        'check_out'     => $record->check_out,
                        'schedule_in'   => $scheduleIn,
                        'schedule_out'  => $scheduleOut,
                        'status'        => $attStatus,
                        'review_status' => $reviewStatus,
                        'updated_at'    => now(),
                    ],
                    ['employee_id', 'date'], // unique key
                    ['check_in', 'check_out', 'schedule_in', 'schedule_out', 'status', 'review_status', 'updated_at']
                );

                DB::commit();
                $pushed++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = [
                    'employee_id' => $record->employee_id,
                    'date'        => $record->date->toDateString(),
                    'error'       => $e->getMessage(),
                ];
                Log::error('ManualSync: pushPrepare failed', [
                    'employee_id' => $record->employee_id,
                    'date'        => $record->date->toDateString(),
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        Log::info('ManualSync: pushPrepare done', [
            'start'   => $startDate,
            'end'     => $endDate,
            'pushed'  => $pushed,
            'skipped' => $skipped,
            'errors'  => count($errors),
        ]);

        return response()->json([
            'success' => count($errors) === 0,
            'message' => "{$pushed} record dipush ke att_prepares." . (count($errors) > 0 ? ' ' . count($errors) . ' gagal.' : ''),
            'pushed'  => $pushed,
            'errors'  => $errors,
        ]);
    }

    // ─── Private: Return Records (dengan pagination) ──────────────

    /**
     * Baca att_manual_detect dari DB dan return dalam format frontend.
     * Pagination: per EMPLOYEE (bukan per record).
     * 1 halaman = N karyawan × semua record mereka dalam rentang tanggal.
     */
    private function returnRecords(
        string $startDate,
        string $endDate,
        int $page = 1,
        int $perPage = 10,
        array $filters = [],
        array $fetchStats = []
    ): JsonResponse {
        // ── Step 1: Query distinct employee_id ──────────────────
        $empQuery = ManualDetect::whereBetween('date', [$startDate, $endDate])
            ->select('employee_id')
            ->distinct();

        if (!empty($filters['employee_id'])) {
            $empQuery->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $empQuery->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $totalEmployees = (clone $empQuery)->count('employee_id');

        // ── Step 2: Paginate employee IDs ──────────────────────
        $employeeIds = (clone $empQuery)
            ->orderBy('employee_id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->pluck('employee_id');

        // ── Step 3: Preload EXCP data (leave + consecutive) ──
        // Preload approved leave requests overlapping the period
        $approvedLeaves = LeaveRequest::with('leaveType')
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->get();

        // Index leave by "employee_id|date" → leave_type_code
        $leaveMap = [];
        foreach ($approvedLeaves as $leave) {
            $leaveStart = Carbon::parse($leave->start_date);
            $leaveEnd   = Carbon::parse($leave->end_date);
            $leaveCode  = $leave->leaveType?->code;
            for ($d = $leaveStart->copy(); $d->lte($leaveEnd); $d->addDay()) {
                $leaveMap[$leave->employee_id . '|' . $d->toDateString()] = $leaveCode;
            }
        }

        // Preload consecutive days overlapping the period
        $consecutives = ConsecutiveDay::forPeriod($startDate, $endDate)->get();

        // Index consecutive by "employee_id|date" → "total_days|type_short"
        $consecMap = [];
        foreach ($consecutives as $c) {
            $cStart  = Carbon::parse($c->start_date);
            $cEnd    = Carbon::parse($c->end_date);
            $typeShort = $c->type === ConsecutiveDay::TYPE_WORKED ? 'H' : 'A';
            $label   = $c->total_days . $typeShort;
            for ($d = $cStart->copy(); $d->lte($cEnd); $d->addDay()) {
                $consecMap[$c->employee_id . '|' . $d->toDateString()] = $label;
            }
        }

        // ── Step 4: Preload holidays ──
        $holidaySet = array_flip(
            Holiday::whereBetween('date', [$startDate, $endDate])
                ->pluck('date')
                ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
                ->toArray()
        );

        // ── Step 5: Ambil semua record untuk employee terpilih ──
        $records = ManualDetect::with(['employee', 'roster', 'workPattern', 'shift'])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('employee_id')
            ->orderBy('date')
            ->get();

        $data = $records->map(function (ManualDetect $record) use ($leaveMap, $consecMap, $holidaySet) {
            $tsr = $record->time_scan_result ?? [];
            $excpKey = $record->employee_id . '|' . $record->date->toDateString();
            $excp = $leaveMap[$excpKey] ?? $consecMap[$excpKey] ?? null;

            return [
                'id'               => $record->id,
                'employee'         => [
                    'id'   => $record->employee?->id,
                    'nip'  => $record->employee?->nip ?? $record->employee?->employee_code,
                    'name' => $record->employee?->name,
                ],
                'date'            => $record->date->toDateString(),
                'is_sunday'       => Carbon::parse($record->date)->isSunday(),
                'is_holiday'      => isset($holidaySet[$record->date->toDateString()]),
                'roster'          => [
                    'id'            => $record->roster?->id,
                    'external_code' => $record->roster?->external_code,
                ],
                'wp'              => [
                    'id'            => $record->workPattern?->id,
                    'employee_type' => $record->workPattern?->employee_type ?? $record->work_pattern_type,
                ],
                'shift'           => $record->shift ? [
                    'id'            => $record->shift->id,
                    'code'          => $record->shift->code,
                    'external_code' => $record->shift->external_code,
                    'name'          => $record->shift->name,
                ] : null,
                'logs'            => $tsr['all_logs'] ?? [],
                'auto_detect'     => $tsr['auto_detect'] ?? null,
                'check_in'        => $record->check_in?->toDateTimeString() ?? null,
                'check_out'       => $record->check_out?->toDateTimeString() ?? null,
                'check_in_log_id' => $tsr['auto_detect']['check_in_log_id'] ?? null,
                'check_out_log_id'=> $tsr['auto_detect']['check_out_log_id'] ?? null,
                'status'          => $record->status,
                'is_manual_in'    => $tsr['manual_in'] ?? null,
                'is_manual_out'   => $tsr['manual_out'] ?? null,
                'excp'            => $excp,
            ];
        })->values()->toArray();

        $response = [
            'data'       => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $totalEmployees,
                'last_page'    => (int) ceil($totalEmployees / max($perPage, 1)),
            ],
        ];

        if (!empty($fetchStats)) {
            $response['fetch_stats'] = $fetchStats;
            $response['message'] = sprintf(
                '%d record baru, %d diperbarui, %d dilewati (sudah lengkap).',
                $fetchStats['inserted'] ?? 0,
                $fetchStats['updated'] ?? 0,
                $fetchStats['skipped'] ?? 0
            );
        }

        return response()->json($response);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Resolve check time dari log_id atau manual input.
     */
    private function resolveCheckTime(string $dateStr, $logId): ?Carbon
    {
        if ($logId === null || $logId === '' || $logId === 'null') {
            return null;
        }

        // Manual input: "manual:07:30"
        if (is_string($logId) && str_starts_with($logId, 'manual:')) {
            $time = str_replace('manual:', '', $logId);
            try {
                return Carbon::parse($dateStr . ' ' . $time . ':00');
            } catch (\Throwable) {
                return null;
            }
        }

        // Raw log ID
        $log = RawLog::find((int) $logId);
        return $log?->scan_datetime;
    }

    /**
     * Cari raw_log id dari log collection berdasarkan datetime.
     */
    private function findLogId($logs, $dateTime): ?int
    {
        if (!$dateTime) return null;

        $target = $dateTime instanceof Carbon
            ? $dateTime->format('Y-m-d H:i:s')
            : $dateTime;

        $found = $logs->first(fn (RawLog $log) =>
            $log->scan_datetime->format('Y-m-d H:i:s') === $target
        );

        return $found?->id;
    }
}
