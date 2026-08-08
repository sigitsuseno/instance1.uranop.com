<?php

namespace App\Modules\Attendance\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Jobs\ProcessAttendanceLogImport;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Attendance\Resources\RawLogResource;
use App\Modules\Attendance\Services\AttendanceCalculatorService;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Attendance\Services\FingerprintBinParser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Modules\Attendance\Exports\ResumeKehadiranExport;
use App\Modules\Payroll\Services\PphCalculationService;

class AttendanceApiController extends Controller
{
    /**
     * GET /api/v1/attendance/logs
     * List raw logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $startDate  = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate    = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $employeeId = $request->input('employee_code');
        $processed  = $request->input('processed');

        $logs = RawLog::query()
            ->whereBetween('scan_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($employeeId, fn ($q) => $q->where('employee_code', $employeeId))
            ->when($processed !== null, fn ($q) => $q->where('is_processed', $processed === '1'))
            ->orderBy('scan_datetime', 'desc')
            ->paginate(50);

        // Statistics
        $stats = [
            'total'             => RawLog::whereBetween('scan_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->count(),
            'processed'         => RawLog::whereBetween('scan_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->where('is_processed', true)->count(),
            'unprocessed'       => RawLog::whereBetween('scan_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->where('is_processed', false)->count(),
            'with_employee'     => RawLog::whereBetween('scan_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->whereNotNull('employee_code')->count(),
            'without_employee'  => RawLog::whereBetween('scan_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->whereNull('employee_code')->count(),
        ];

        return response()->json([
            'data'  => $logs,
            'stats' => $stats,
        ]);
    }

    /**
     * POST /api/v1/attendance/logs/import
     * Upload file log absensi untuk diproses di background.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'mode'   => 'nullable|in:create,replace',
            'format' => 'nullable|in:auto,raw,pivoted',
        ]);

        try {
            $file   = $request->file('file');
            $mode   = $request->input('mode', 'create');
            $format = $request->input('format', 'auto');

            // Create temp directory if not exists
            $tempDir = storage_path('app/temp/imports');
            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Store file with unique name
            $filename = uniqid('import_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $fullPath = $tempDir . DIRECTORY_SEPARATOR . $filename;
            $file->move($tempDir, $filename);

            $batch = uniqid('batch_');

            // Dispatch import job
            ProcessAttendanceLogImport::dispatch(
                $fullPath,
                $file->getClientOriginalName(),
                $batch,
                $mode,
                $format
            );

            $modeText = $mode === 'replace' ? 'Replace (hapus data lama)' : 'Create (tambah baru)';

            return response()->json([
                'success' => true,
                'message' => "Import mode {$modeText} sedang diproses di background.",
                'batch'   => $batch,
                'mode'    => $mode,
            ]);

        } catch (\Exception $e) {
            Log::error('Import dispatch error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/attendance/logs/import/status/{batch}
     * Polling status import.
     */
    public function importStatus(Request $request, string $batch): JsonResponse
    {
        $result = cache()->get('import_result_' . $batch);

        if (! $result) {
            return response()->json([
                'status'  => 'processing',
                'message' => 'Import sedang diproses...',
                'batch'   => $batch,
            ]);
        }

        return response()->json($result);
    }

    /**
     * DELETE /api/v1/attendance/logs/batch/{batch}
     * Delete logs by import batch.
     */
    public function deleteBatch(Request $request, string $batch): JsonResponse
    {
        $deleted = RawLog::where('import_batch', $batch)->delete();

        return response()->json([
            'success' => true,
            'message' => "Berhasil menghapus {$deleted} log dari batch {$batch}",
        ]);
    }

    /**
     * GET /api/v1/attendance/logs/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $stats = [
            'total'       => RawLog::whereBetween('scan_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->count(),
            'processed'   => RawLog::whereBetween('scan_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->where('is_processed', true)->count(),
            'unprocessed' => RawLog::whereBetween('scan_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->where('is_processed', false)->count(),
        ];

        return response()->json($stats);
    }

    /**
     * GET /api/v1/attendance/logs/import/template
     * Download template import.
     */
    public function template(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = public_path('templates/template_import_absensi.xlsx');

        if (! file_exists($path)) {
            abort(404, 'Template tidak ditemukan.');
        }

        return response()->download($path, 'Template_Import_Absensi.xlsx');
    }

    // =================================================================
    // BIN IMPORT (Fingerprint .bin file)
    // =================================================================

    /**
     * POST /api/v1/attendance/logs/import-bin
     * Upload file .bin dari mesin fingerprint untuk diproses.
     */
    public function importBin(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:bin,txt,dat|max:51200',
            'mode' => 'nullable|in:create,replace',
        ]);

        try {
            $file = $request->file('file');
            $mode = $request->input('mode', 'create');

            $tempDir = storage_path('app/temp/imports');
            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $filename = uniqid('bin_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $fullPath = $tempDir . DIRECTORY_SEPARATOR . $filename;
            $file->move($tempDir, $filename);

            $batch = uniqid('bin_batch_');

            // Replace mode: delete existing
            if ($mode === 'replace') {
                RawLog::where('source_file', $file->getClientOriginalName())->delete();
            }

            // Parse .bin file
            $parser = new FingerprintBinParser();
            $result = $parser->parse($fullPath, $batch, $file->getClientOriginalName());

            // Clean up
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return response()->json([
                'success' => true,
                'message' => "Import .bin selesai. {$result['inserted']} record berhasil diimport.",
                'batch' => $batch,
                'inserted' => $result['inserted'],
                'total_lines' => $result['total_lines'],
                'errors' => $result['errors'],
            ]);

        } catch (\Exception $e) {
            Log::error('Bin import error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal import file .bin: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =================================================================
    // PREPARE / SYNC ENDPOINTS
    // =================================================================

    /**
     * POST /api/v1/attendance/prepare/sync
     * Sync: deteksi kehadiran dari raw_logs → att_prepares.
     */
    public function prepareSync(Request $request): JsonResponse
    {
        $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'employee_id' => 'nullable|integer',
        ]);

        $syncService = new \App\Modules\Attendance\Services\AttendanceSyncService(
            new \App\Modules\Attendance\Services\AttendanceCalculatorService()
        );

        try {
            $result = $syncService->syncPeriod(
                $request->start_date,
                $request->end_date,
                $request->employee_id
            );

            return response()->json([
                'success' => true,
                'message' => "Sync selesai. {$result['processed']} diproses, {$result['failed']} gagal.",
                'data'    => $result,
            ]);

        } catch (\Throwable $e) {
            Log::error('Prepare sync error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Sync gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/attendance/prepare/list
     * List prepares dengan filter: date, status, review_status, group_codes, incomplete_only.
     */
    public function prepareList(Request $request): JsonResponse
    {
        $startDate    = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate      = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $employeeId   = $request->input('employee_id');
        $status       = $request->input('status');
        $reviewStatus = $request->input('review_status');
        $isLocked     = $request->input('is_locked');
        $groupCodes   = $request->input('group_codes', []);       // array of reference_code
        $perPage      = $request->input('per_page', 50);

        $query = AttendancePrepare::with('employee:id,name,nip,employee_code,department_id')
            ->whereBetween('date', [$startDate, $endDate])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($reviewStatus, fn ($q) => $q->where('review_status', $reviewStatus))
            ->when($isLocked !== null, fn ($q) => $q->where('is_locked', $isLocked === '1' || $isLocked === 'true'));

        // Filter by employee group codes
        if (! empty($groupCodes)) {
            $query->whereHas('employee.groups', function ($q) use ($groupCodes) {
                $q->whereIn('reference_code', (array) $groupCodes);
            });
        }

        // Only incomplete records
        if ($request->boolean('incomplete_only')) {
            $query->where(function ($q) {
                $q->whereNull('check_in')
                  ->orWhereNull('check_out');
            });
        }

        $query = $query->orderBy('date')
            ->orderBy('employee_id')
            ->paginate($perPage);

        return response()->json([
            'data' => $query,
            'meta' => [
                'current_page' => $query->currentPage(),
                'last_page'    => $query->lastPage(),
                'total'        => $query->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/attendance/prepare/lengkapi
     * Lengkapi: isi check_in/check_out yang kosong (bulk).
     * Support create (kalau id null) + update (kalau id ada).
     */
    public function prepareLengkapi(Request $request): JsonResponse
    {
        $request->validate([
            'records'   => 'required|array',
            'records.*.id' => 'nullable|integer',
            'records.*.employee_id' => 'required_without:records.*.id|integer',
            'records.*.date' => 'required_without:records.*.id|date',
        ]);

        $service = new AttendanceService();
        $result  = $service->bulkLengkapi($request->input('records'), Auth::id());

        return response()->json([
            'success' => true,
            'message' => "Lengkapi selesai. {$result['updated']} update, {$result['created']} baru.",
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/v1/attendance/prepare/auto-lengkapi
     * Auto-lengkapi: isi check_in/check_out otomatis berdasarkan rules per tanggal.
     */
    public function prepareAutoLengkapi(Request $request): JsonResponse
    {
        $request->validate([
            'group_codes'   => 'required|array',
            'group_codes.*' => 'string',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'fill_absent'   => 'boolean',
        ]);

        $service = new AttendanceService();
        $result  = $service->autoLengkapi(
            $request->input('group_codes'),
            $request->input('start_date'),
            $request->input('end_date'),
            $request->boolean('fill_absent'),
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/v1/attendance/prepare/hitung-lembur
     * Hitung Lembur: kalkulasi overtime multiplier.
     */
    public function prepareHitungLembur(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $calculator = new AttendanceCalculatorService();
        $service    = new AttendanceService();
        $result     = $service->bulkHitungLembur(
            $request->start_date,
            $request->end_date,
            $calculator
        );

        return response()->json([
            'success' => true,
            'message' => "Hitung lembur selesai. {$result['updated']} record dihitung" .
                (($result['skipped'] ?? 0) > 0 ? ", {$result['skipped']} diskip (cuti/izin/sakit)." : "."),
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/v1/attendance/prepare/lock
     * Kunci / Lock attendance records (bulk).
     * Role: superadmin, hrmanager.
     */
    public function prepareLock(Request $request): JsonResponse
    {
        $request->validate([
            'ids'  => 'required|array',
            'lock' => 'required|boolean',
        ]);

        $service = new AttendanceService();
        $result  = $service->bulkLock($request->input('ids'), Auth::id(), $request->input('lock'));

        return response()->json([
            'success' => true,
            'message' => ($request->input('lock') ? 'Lock' : 'Unlock') . " selesai. {$result['updated']} record.",
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/v1/attendance/prepare/update-status-legacy
     * Migrasi status generic (cuti/izin/sakit) → kode LeaveType spesifik.
     * Aman: tidak menyentuh field selain status.
     */
    public function prepareUpdateStatusLegacy(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $service = new AttendanceService();
        $result  = $service->migrateLegacyStatuses(
            $request->start_date,
            $request->end_date
        );

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result,
        ]);
    }

    /**
     * GET /api/v1/attendance/prepare/stats
     * Quick stats untuk dashboard prepare.
     */
    public function prepareStats(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $syncService = new \App\Modules\Attendance\Services\AttendanceSyncService(
            new AttendanceCalculatorService()
        );

        $stats = $syncService->getSyncSummary($startDate, $endDate);

        return response()->json(['data' => $stats]);
    }

    /**
     * GET /api/v1/attendance/prepare/employee-groups
     * Return employee_id → group reference_codes mapping untuk frontend filtering.
     */
    public function prepareEmployeeGroups(Request $request): JsonResponse
    {
        $groups = \App\Modules\Settings\Models\EmployeeGroup::select('employee_id', 'reference_code')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($group) => $group->pluck('reference_code')->toArray());

        return response()->json(['groups' => $groups]);
    }

    /**
     * GET /api/v1/attendance/prepare/overtime-summary
     * List employees with aggregated overtime values
     */
    public function prepareOvertimeSummary(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $search    = $request->input('search');
        $perPage   = $request->input('per_page', 20);
        $employees = \App\Modules\Employee\Models\Employee::with([
            'department',
            'position',
            'attendancePrepares' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            },
        ])
            ->where('is_active', true)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('resign_date')
                    ->orWhere('resign_date', '>=', $startDate);
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code')
            ->paginate($perPage);

        $data = [];
        foreach ($employees as $employee) {
            $totalHadir = 0;
            $lm = 0;
            $totalLm = 0;
            $lemburHb = 0;
            $totalLhb = 0;

            foreach ($employee->attendancePrepares as $attendance) {
                // Count hadir days only (terlambat sudah tidak dipakai)
                if ($attendance->status === 'hadir') {
                    $totalHadir++;
                }

                $lemburHb += $attendance->overtime ?? 0;
                $totalLhb += $attendance->overtime_count ?? 0;
                $lm += $attendance->lm ?? 0;
                $totalLm += $attendance->lm_count ?? 0;
            }

            $data[] = [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'total_hadir' => $totalHadir,
                'lm' => $lm,
                'total_lm' => $totalLm,
                'lembur_hb' => $lemburHb,
                'total_lhb' => $totalLhb,
                'total_lembur' => $totalLm + $totalLhb,
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page'    => $employees->lastPage(),
                'total'        => $employees->total(),
                'from'         => $employees->firstItem(),
                'to'           => $employees->lastItem(),
                'links'        => [
                    'prev' => $employees->previousPageUrl(),
                    'next' => $employees->nextPageUrl(),
                ]
            ],
        ]);
    }

    /**
     * GET /api/v1/attendance/prepare/overtime-navigation
     * Get prev/next employee ID for Detail page
     */
    public function prepareOvertimeNavigation(Request $request): JsonResponse
    {
        $employeeId = $request->input('employee_id');
        $search     = $request->input('search');

        // We use the same base query as prepareOvertimeSummary to maintain the list order
        $baseQuery = \App\Modules\Employee\Models\Employee::where('is_active', true)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code');

        $employees = $baseQuery->pluck('id')->toArray();
        $currentIndex = array_search($employeeId, $employees);

        $prevId = null;
        $nextId = null;

        if ($currentIndex !== false) {
            if ($currentIndex > 0) {
                $prevId = $employees[$currentIndex - 1];
            }
            if ($currentIndex < count($employees) - 1) {
                $nextId = $employees[$currentIndex + 1];
            }
        }

        return response()->json([
            'prev_id' => $prevId,
            'next_id' => $nextId,
        ]);
    }

    /**
     * GET /api/v1/attendance/prepare/overtime-summary/export
     * Export overtime summary to Excel
     */
    public function prepareOvertimeSummaryExport(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $search    = $request->input('search');

        $employees = \App\Modules\Employee\Models\Employee::with([
            'department',
            'position',
            'attendancePrepares' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            },
        ])
            ->where('is_active', true)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('resign_date')
                    ->orWhere('resign_date', '>=', $startDate);
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code')
            ->get();

        $data = [];
        foreach ($employees as $employee) {
            $totalHadir = 0;
            $lm = 0;
            $totalLm = 0;
            $lemburHb = 0;
            $totalLhb = 0;

            foreach ($employee->attendancePrepares as $attendance) {
                if ($attendance->status === 'hadir') {
                    $totalHadir++;
                }

                $lemburHb += $attendance->overtime ?? 0;
                $totalLhb += $attendance->overtime_count ?? 0;
                $lm += $attendance->lm ?? 0;
                $totalLm += $attendance->lm_count ?? 0;
            }

            $data[] = [
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'total_hadir' => $totalHadir,
                'lm' => $lm,
                'total_lm' => $totalLm,
                'lembur_hb' => $lemburHb,
                'total_lhb' => $totalLhb,
                'total_lembur' => $totalLm + $totalLhb,
            ];
        }

        $periodLabel = Carbon::parse($startDate)->format('d M Y') . ' - ' . Carbon::parse($endDate)->format('d M Y');
        $filename = 'Rekap_Hitung_Lembur_' . Carbon::parse($startDate)->format('Ymd') . '_' . Carbon::parse($endDate)->format('Ymd') . '.xlsx';

        return Excel::download(new \App\Modules\Attendance\Exports\OvertimeSummaryExport($data, $periodLabel), $filename);
    }

    /**
     * GET /api/v1/attendance/prepare/overtime-detail/export
     * Export overtime detail for an employee to Excel
     */
    public function prepareOvertimeDetailExport(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        if (!$employeeId) {
            return response()->json(['message' => 'Employee ID required'], 422);
        }

        $employee = \App\Modules\Employee\Models\Employee::find($employeeId);
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $days = AttendancePrepare::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $periodLabel = Carbon::parse($startDate)->format('d M Y') . ' - ' . Carbon::parse($endDate)->format('d M Y');
        $filename = 'Detail_Lembur_' . preg_replace('/[^a-zA-Z0-9]/', '_', $employee->name) . '_' . Carbon::parse($startDate)->format('Ymd') . '_' . Carbon::parse($endDate)->format('Ymd') . '.xlsx';

        return Excel::download(new \App\Modules\Attendance\Exports\OvertimeDetailExport($days, $employee->name, $periodLabel), $filename);
    }

    /**
     * GET /api/v1/attendance/prepare/overtime-roster/export
     * Export roster absensi + overtime per tanggal (dynamic columns).
     */
    public function prepareRosterExport(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $search    = $request->input('search');

        // Generate date range
        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);
        $dates = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        // Query employees with attendancePrepares in range
        $employees = \App\Modules\Employee\Models\Employee::with([
            'attendancePrepares' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            },
        ])
            ->where('is_active', true)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('resign_date')
                    ->orWhere('resign_date', '>=', $startDate);
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code')
            ->get();

        // Build rows
        $rows = [];
        foreach ($employees as $employee) {
            $row = [
                $employee->name,
                $employee->nip,
            ];

            $preparesByDate = $employee->attendancePrepares->keyBy(function ($item) {
                return $item->date instanceof Carbon
                    ? $item->date->toDateString()
                    : (is_string($item->date) ? $item->date : $item->date);
            });

            foreach ($dates as $date) {
                $prepare = $preparesByDate->get($date);
                if ($prepare) {
                    $status = \App\Modules\Attendance\Exports\AttendanceRosterExport::shortStatus($prepare->status);
                    $isSunday = \Carbon\Carbon::parse($date)->isSunday();
                    $isHoliday = in_array($prepare->status, ['libur', 'off']);
                    $otMinutes = ($isSunday || $isHoliday)
                        ? ($prepare->lm ?? 0)
                        : ($prepare->overtime ?? 0);
                    $row[] = $status;
                    $row[] = round($otMinutes / 60, 1);
                } else {
                    $row[] = 'A';
                    $row[] = 0;
                }
            }

            $rows[] = $row;
        }

        $periodLabel = Carbon::parse($startDate)->format('d M Y') . ' - ' . Carbon::parse($endDate)->format('d M Y');
        $filename = 'Roster_Absensi_Lembur_' . Carbon::parse($startDate)->format('Ymd') . '_' . Carbon::parse($endDate)->format('Ymd') . '.xlsx';

        return Excel::download(
            new \App\Modules\Attendance\Exports\AttendanceRosterExport($rows, $dates, $periodLabel),
            $filename
        );
    }
    // =================================================================

    /**
     * GET /api/v1/attendance/consecutive
     * List consecutive days dengan filter.
     */
    public function consecutiveList(Request $request): JsonResponse
    {
        $service = new \App\Modules\Attendance\Services\ConsecutiveDayService();

        $filters = $request->only(['start_date', 'end_date', 'employee_id', 'type', 'per_page']);
        $result  = $service->list($filters);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * GET /api/v1/attendance/consecutive/export
     * Export consecutive days ke Excel. Filter: leave_period_id ATAU pay_period_id (+ employee_id, type).
     */
    public function consecutiveExport(Request $request)
    {
        $query = \App\Modules\Attendance\Models\ConsecutiveDay::with('employee:id,name,employee_code,nip,department_id')
            ->orderBy('start_date', 'desc')
            ->orderBy('employee_id');

        // Filter periode: leave_period_id ATAU pay_period_id (overlap tanggal)
        $leavePeriodId = $request->input('leave_period_id');
        $payPeriodId   = $request->input('pay_period_id');

        $periodName = '';
        if ($leavePeriodId) {
            $period = \App\Modules\Leave\Models\LeavePeriod::find($leavePeriodId);
            if ($period) {
                $query->forPeriod($period->start_date->toDateString(), $period->end_date->toDateString());
                $periodName = $period->name;
            }
        } elseif ($payPeriodId) {
            $period = \App\Modules\Payroll\Models\PayPeriod::find($payPeriodId);
            if ($period) {
                $query->forPeriod($period->start_date->toDateString(), $period->end_date->toDateString());
                $periodName = $period->name;
            }
        }

        if ($request->input('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->input('type')) {
            $query->byType($request->input('type'));
        }

        $data = $query->get()->toArray();

        $filename = 'Consecutive_Day' . ($periodName ? '_' . str_replace(' ', '_', $periodName) : '') . '.xlsx';
        return Excel::download(new \App\Modules\Attendance\Exports\ConsecutiveDayExport($data, $periodName), $filename);
    }

    /**
     * POST /api/v1/attendance/consecutive
     * Buat record consecutive day baru (HR input manual).
     */
    public function consecutiveStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'type'        => 'required|in:worked,absent',
            'notes'       => 'nullable|string|max:500',
        ]);

        $start  = \Carbon\Carbon::parse($data['start_date']);
        $end    = \Carbon\Carbon::parse($data['end_date']);
        $days   = $start->diffInDays($end, true) + 1;

        $record = \App\Modules\Attendance\Models\ConsecutiveDay::create([
            'employee_id' => $data['employee_id'],
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'total_days'  => $days,
            'type'        => $data['type'],
            'status'      => \App\Modules\Attendance\Models\ConsecutiveDay::STATUS_CALCULATED,
            'notes'       => $data['notes'] ?? null,
        ]);

        // Update att_prepares: set status + review_status = CSF
        $this->syncAttPreparesForConsecutive(
            $data['employee_id'], $data['start_date'], $data['end_date'], $data['type']
        );

        $record->load('employee:id,name,employee_code,department_id');

        return response()->json([
            'success' => true,
            'message' => 'Consecutive day berhasil ditambahkan.',
            'data'    => $record,
        ], 201);
    }

    /**
     * PUT /api/v1/attendance/consecutive/{id}
     * Update record.
     */
    public function consecutiveUpdate(Request $request, int $id): JsonResponse
    {
        $record = \App\Modules\Attendance\Models\ConsecutiveDay::findOrFail($id);

        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'type'        => 'required|in:worked,absent',
            'notes'       => 'nullable|string|max:500',
        ]);

        // Simpan old range untuk revert
        $oldEmployeeId = $record->employee_id;
        $oldStartDate  = $record->start_date->toDateString();
        $oldEndDate    = $record->end_date->toDateString();

        $start = \Carbon\Carbon::parse($data['start_date']);
        $end   = \Carbon\Carbon::parse($data['end_date']);
        $days  = $start->diffInDays($end, true) + 1;

        $record->update([
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'total_days' => $days,
            'type'       => $data['type'],
            'notes'      => $data['notes'] ?? $record->notes,
        ]);

        // Revert att_prepares di range lama
        $this->revertAttPreparesForConsecutive($oldEmployeeId, $oldStartDate, $oldEndDate);

        // Apply att_prepares di range baru
        $this->syncAttPreparesForConsecutive(
            $record->employee_id, $data['start_date'], $data['end_date'], $data['type']
        );

        $record->load('employee:id,name,employee_code,department_id');

        return response()->json([
            'success' => true,
            'message' => 'Consecutive day berhasil diperbarui.',
            'data'    => $record,
        ]);
    }

    /**
     * DELETE /api/v1/attendance/consecutive/{id}
     */
    public function consecutiveDestroy(int $id): JsonResponse
    {
        $record = \App\Modules\Attendance\Models\ConsecutiveDay::findOrFail($id);

        // Revert att_prepares sebelum hapus
        $this->revertAttPreparesForConsecutive(
            $record->employee_id,
            $record->start_date->toDateString(),
            $record->end_date->toDateString()
        );

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Consecutive day berhasil dihapus.',
        ]);
    }

    // ─── Consecutive Day Helpers ───────────────────────────────────

    /**
     * Update att_prepares untuk range tanggal dengan status CSF.
     */
    private function syncAttPreparesForConsecutive(int $employeeId, string $startDate, string $endDate, string $type): void
    {
        $status = $type === 'worked'
            ? \App\Modules\Attendance\Models\AttendancePrepare::STATUS_HADIR
            : \App\Modules\Attendance\Models\AttendancePrepare::STATUS_ABSENT;

        $start = \Carbon\Carbon::parse($startDate);
        $end   = \Carbon\Carbon::parse($endDate);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();
            \App\Modules\Attendance\Models\AttendancePrepare::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'date'        => $dateStr,
                ],
                [
                    'status'        => $status,
                    'review_status' => \App\Modules\Attendance\Models\AttendancePrepare::REVIEW_CSF,
                    'periode_start' => $date->copy()->startOfMonth()->toDateString(),
                    'periode_end'   => $date->copy()->endOfMonth()->toDateString(),
                ]
            );
        }
    }

    /**
     * Revert att_prepares: kembalikan review_status ke 'cek'.
     */
    private function revertAttPreparesForConsecutive(int $employeeId, string $startDate, string $endDate): void
    {
        \App\Modules\Attendance\Models\AttendancePrepare::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('review_status', \App\Modules\Attendance\Models\AttendancePrepare::REVIEW_CSF)
            ->update(['review_status' => \App\Modules\Attendance\Models\AttendancePrepare::REVIEW_CEK]);
    }

    // ========== RESUME KEHADIRAN (Attendance Records) ==========

    /**
     * GET /api/v1/attendance/recap
     * List resume kehadiran per periode — dihitung ON_THE_FLY dari att_prepares
     * (bukan baca att_records). Klik "Save Resume" untuk snapshot ke att_records.
     */
    public function recapList(Request $request): JsonResponse
    {
        $periodId = $request->period_id;
        $search = $request->search;
        $departmentId = $request->department_id;
        $perPage = (int) ($request->per_page ?? 50);

        if (!$periodId) {
            return response()->json(['data' => [], 'message' => 'Silakan pilih periode'], 422);
        }

        $rows = $this->computeRecapOnTheFly($periodId, $search, $departmentId);

        // Manual pagination (data on-the-fly, bukan query DB)
        $page = max(1, (int) ($request->page ?? 1));
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $items = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $items,
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Hitung resume kehadiran ON_THE_FLY untuk satu periode.
     * Konsisten dengan payroll baru (logic_payroll_baru.md KEPUTUSAN #5, #8, #9):
     *  - karyawan: isGroupGaji() + activeInPeriod + punya roster
     *  - hari_kerja = count record att_prepares (start→min(end, now)) − Minggu − holiday − absent
     *  - cuti/izin/sakit/deduct dari leave_requests approved (irisan durasi)
     *
     * @return array<int, array>
     */
    private function computeRecapOnTheFly(int $periodId, ?string $search = null, $departmentId = null): array
    {
        $period = \App\Modules\Payroll\Models\PayPeriod::findOrFail($periodId);
        $now = Carbon::today();

        // KEPUTUSAN #9: holiday dari tabel sch_holidays, rentang start–end periode
        $holidays = \App\Modules\Schedule\Models\Holiday::whereBetween('date', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ])
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->toArray();

        // Periode belum berakhir → hitung sampai hari ini; sudah lewat → full
        $effEnd = $period->end_date && $period->end_date->lt($now)
            ? $period->end_date->toDateString()
            : $now->toDateString();

        // KEPUTUSAN #5: filter isGroupGaji() — whitelist group penggajian
        $employees = \App\Modules\Employee\Models\Employee::with(['department', 'groups'])
            ->activeInPeriod($period->start_date->toDateString(), $period->end_date->toDateString())
            ->whereHas('shiftRosters', fn ($q) => $q->whereBetween('date', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ]))
            ->get()
            ->filter(fn ($emp) => $emp->isGroupGaji())
            ->sortBy('no_urut')->sortBy('nip')
            ->values();

        $rows = [];

        foreach ($employees as $employee) {
            if ($departmentId && (int) $employee->department_id !== (int) $departmentId) {
                continue;
            }

            // KEPUTUSAN #8: count record aktual (start → effEnd)
            $prepares = AttendancePrepare::where('employee_id', $employee->id)
                ->whereBetween('date', [$period->start_date->toDateString(), $effEnd])
                ->get();

            $hariKerja = $prepares->filter(function ($p) use ($holidays) {
                $date = $p->date->toDateString();
                if (Carbon::parse($date)->isSunday()) return false;      // Minggu → skip
                if (in_array($date, $holidays)) return false;             // holiday → skip
                if ($p->status === AttendancePrepare::STATUS_ABSENT) return false; // absent → skip
                return true;
            })->count();

            $absen       = $prepares->where('status', AttendancePrepare::STATUS_ABSENT)->count();
            $lateMinutes = $prepares->sum('late_minutes');
            $lm          = $prepares->sum('lm');
            $lmCount     = $prepares->sum('lm_count');
            $lembur      = $prepares->sum('overtime');
            $lemburCount = $prepares->sum('overtime_count');

            // Leave approved yang overlap periode (irisan durasi — akurat utk leave menyeberang)
            $leaves = \App\Modules\Leave\Models\LeaveRequest::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->where(function ($q) use ($period) {
                    $start = $period->start_date->toDateString();
                    $end   = $period->end_date->toDateString();
                    $q->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->where('start_date', '<=', $start)
                             ->where('end_date', '>=', $end);
                      });
                })
                ->with('leaveType')
                ->get();

            $cuti = 0; $izin = 0; $sakit = 0; $unpaid = 0;
            foreach ($leaves as $l) {
                $lStart = Carbon::parse(max($l->start_date->toDateString(), $period->start_date->toDateString()));
                $lEnd   = Carbon::parse(min($l->end_date->toDateString(), $period->end_date->toDateString()));
                $dur    = max(0, $lStart->diffInDays($lEnd) + 1);

                $cat    = optional($l->leaveType)->category;
                $isPaid = optional($l->leaveType)->is_paid;
                if ($cat === 'leave') $cuti += $dur;
                elseif ($cat === 'permit') $izin += $dur;
                elseif ($cat === 'sick') $sakit += $dur;
                if ($isPaid === false) $unpaid += $dur;
            }

            $deductDay = $unpaid + $absen;

            // Search: nama / employee_code
            if ($search) {
                $name = strtolower($employee->name ?? '');
                $code = strtolower($employee->employee_code ?? '');
                $q    = strtolower(trim($search));
                if ($q !== '' && strpos($name, $q) === false && strpos($code, $q) === false) {
                    continue;
                }
            }

            $rows[] = [
                'id' => 'est-' . $employee->id,
                'employee' => [
                    'id'            => $employee->id,
                    'name'          => $employee->name,
                    'employee_code' => $employee->employee_code,
                    'department_id' => $employee->department_id,
                    'department'    => $employee->department
                        ? ['id' => $employee->department->id, 'name' => $employee->department->name]
                        : null,
                ],
                'hari_kerja'   => $hariKerja,
                'deduct_day'   => $deductDay,
                'cuti'         => $cuti,
                'izin'         => $izin,
                'sakit'        => $sakit,
                'absen'        => $absen,
                'late_minutes' => $lateMinutes,
                'lm'           => $lm,
                'lm_count'     => $lmCount,
                'lembur'       => $lembur,
                'lembur_count' => $lemburCount,
                'status'       => 'on_the_fly',
            ];
        }

        return $rows;
    }

    /**
     * GET /api/v1/attendance/recap/export
     * Export resume kehadiran ke Excel (semua records, no pagination) — ON_THE_FLY.
     */
    public function recapExport(Request $request)
    {
        $periodId = $request->period_id;
        $search = $request->search;
        $departmentId = $request->department_id;

        if (!$periodId) {
            return response()->json(['message' => 'Silakan pilih periode'], 422);
        }

        $period = \App\Modules\Payroll\Models\PayPeriod::find($periodId);
        $periodLabel = $period
            ? $period->name . ' (' . $period->start_date->format('d/m/Y') . ' s/d ' . $period->end_date->format('d/m/Y') . ')'
            : 'Periode #' . $periodId;

        $rows = $this->computeRecapOnTheFly($periodId, $search, $departmentId);

        $filename = 'Resume_Kehadiran_' . str_replace(' ', '_', $period->name ?? '') . '.xlsx';

        return Excel::download(
            new ResumeKehadiranExport($rows, $periodLabel),
            $filename
        );
    }

    /**
     * POST /api/v1/attendance/recap/save
     * Simpan snapshot resume kehadiran (hasil hitung ON_THE_FLY) ke att_records.
     *
     * Menggantikan flow lama generate/approve (logic_payroll_baru.md §6/#7):
     *  - perhitungan on-the-fly = sama dgn tampilan (computeRecapOnTheFly)
     *  - hanya menulis att_records segment null (snapshot tampilan) — TIDAK menyentuh pay_records.
     */
    public function recapSave(Request $request): JsonResponse
    {
        $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
        ]);

        $periodId = (int) $request->period_id;
        $rows = $this->computeRecapOnTheFly($periodId);

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $saved = 0;
            foreach ($rows as $row) {
                $emp = $row['employee'];

                \App\Modules\Attendance\Models\AttendanceRecord::updateOrCreate(
                    [
                        'employee_id'   => $emp['id'],
                        'pay_period_id' => $periodId,
                        'segment'       => null,
                    ],
                    [
                        'hari_kerja'   => $row['hari_kerja'],
                        'cuti'         => $row['cuti'],
                        'izin'         => $row['izin'],
                        'sakit'        => $row['sakit'],
                        'absen'        => $row['absen'],
                        'deduct_day'   => $row['deduct_day'],
                        'late_minutes' => $row['late_minutes'],
                        'lm'           => $row['lm'],
                        'lm_count'     => $row['lm_count'],
                        'lembur'       => $row['lembur'],
                        'lembur_count' => $row['lembur_count'],
                        'status'       => 'generated',
                    ]
                );

                $saved++;
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil menyimpan resume kehadiran untuk {$saved} karyawan.",
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal simpan: ' . $e->getMessage(),
            ], 500);
        }
    }

}
