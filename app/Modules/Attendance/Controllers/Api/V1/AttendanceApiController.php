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
     * List resume kehadiran per periode
     */
    public function recapList(Request $request): JsonResponse
    {
        $periodId = $request->period_id;
        $search = $request->search;
        $departmentId = $request->department_id;
        $perPage = $request->per_page ?? 50;

        if (!$periodId) {
            return response()->json(['data' => [], 'message' => 'Silakan pilih periode'], 422);
        }

        $query = \App\Modules\Attendance\Models\AttendanceRecord::with([
            'employee' => fn($q) => $q->select('id', 'name', 'employee_code', 'department_id'),
            'employee.department' => fn($q) => $q->select('id', 'name'),
            'payPeriod' => fn($q) => $q->select('id', 'name', 'start_date', 'end_date'),
        ])->where('pay_period_id', $periodId);

        // Selalu ambil record utuh (segment = null)
        $query->whereNull('segment');

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        if ($departmentId) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        // Order by employee code
        $query->orderBy(
            \App\Modules\Employee\Models\Employee::select('employee_code')
                ->whereColumn('employees.id', 'att_records.employee_id')
        );

        $records = $query->paginate($perPage);

        return response()->json($records);
    }

    /**
     * GET /api/v1/attendance/recap/export
     * Export resume kehadiran ke Excel untuk semua records (no pagination).
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

        $query = \App\Modules\Attendance\Models\AttendanceRecord::with([
            'employee' => fn($q) => $q->select('id', 'name', 'employee_code', 'department_id'),
            'employee.department' => fn($q) => $q->select('id', 'name'),
        ])->where('pay_period_id', $periodId)
          ->whereNull('segment');

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        if ($departmentId) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $query->orderBy(
            \App\Modules\Employee\Models\Employee::select('employee_code')
                ->whereColumn('employees.id', 'att_records.employee_id')
        );

        $records = $query->get()->toArray();

        $filename = 'Resume_Kehadiran_' . str_replace(' ', '_', $period->name ?? '') . '.xlsx';

        return Excel::download(
            new ResumeKehadiranExport($records, $periodLabel),
            $filename
        );
    }

    /**
     * POST /api/v1/attendance/recap/generate
     * Generate resume kehadiran untuk satu periode.
     * Jika is_split=true → 2 att_records per karyawan (seg-A & seg-B).
     */
    public function recapGenerate(Request $request): JsonResponse
    {
        $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
        ]);

        $periodId = $request->period_id;
        $period = \App\Modules\Payroll\Models\PayPeriod::findOrFail($periodId);
        $startDate = $period->start_date;
        $endDate = $period->end_date;

        // Ambil semua karyawan yang punya data roster di periode ini
        $employees = \App\Modules\Employee\Models\Employee::with('department')
            ->activeInPeriod($startDate, $endDate)
            ->whereHas('shiftRosters', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            })
            ->get();

        // Fixed days — ambil dari setting payroll_config
        $payrollConfig = \App\Modules\Settings\Models\SystemSetting::where('key', 'payroll_config')->first();
        if (!$payrollConfig || empty($payrollConfig->fixed_working_day)) {
            return response()->json(['success' => false, 'message' => 'Hari kerja (Fixed Working Day) belum diatur di menu Pengaturan Penggajian.'], 400);
        }
        $fixedDays = (int) $payrollConfig->fixed_working_day;

        // Selalu 1 segment utuh di tabel kehadiran (att_records)
        $segments = [
            ['segment' => null, 'start' => $startDate, 'end' => $endDate, 'hk' => $fixedDays],
        ];

        // Cleanup jika ada record split (A/B) lama yang tersisa dan belum locked
        \App\Modules\Attendance\Models\AttendanceRecord::where('pay_period_id', $periodId)
            ->whereNotNull('segment')
            ->where('status', '!=', 'locked')
            ->delete();

        $generatedCount = 0;

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                foreach ($segments as $seg) {
                    $segStart = $seg['start'];
                    $segEnd = $seg['end'];
                    $hk = $seg['hk'];

                    // 1. Aggregate leave yang approved dalam rentang segmen
                    $leaves = \App\Modules\Leave\Models\LeaveRequest::where('employee_id', $employee->id)
                        ->where('status', 'approved')
                        ->where(function ($q) use ($segStart, $segEnd) {
                            $q->whereBetween('start_date', [$segStart, $segEnd])
                              ->orWhereBetween('end_date', [$segStart, $segEnd]);
                        })
                        ->with('leaveType')
                        ->get();

                    $cuti = $leaves->filter(fn($l) => optional($l->leaveType)->category === 'leave')->sum('days_requested');
                    $izin = $leaves->filter(fn($l) => optional($l->leaveType)->category === 'permit')->sum('days_requested');
                    $sakit = $leaves->filter(fn($l) => optional($l->leaveType)->category === 'sick')->sum('days_requested');
                    $unpaid = $leaves->filter(fn($l) => optional($l->leaveType)->is_paid === false)->sum('days_requested');

                    // 2. Aggregate att_prepares dalam rentang segmen
                    $prepares = \App\Modules\Attendance\Models\AttendancePrepare::where('employee_id', $employee->id)
                        ->whereBetween('date', [$segStart, $segEnd])
                        ->get();

                    $absen = $prepares->where('status', 'absent')->count();
                    $lateMinutes = $prepares->sum('late_minutes');
                    $lm = $prepares->sum('lm');
                    $lmCount = $prepares->sum('lm_count');
                    $lembur = $prepares->sum('overtime');
                    $lemburCount = $prepares->sum('overtime_count');

                    // 3. Hitung
                    $deductDay = $unpaid + $absen;   // hari pemotongan — hanya leave yg unpaid + absent
                    $hariKerja = max(0, $hk - $deductDay);

                    // 4. Upsert per segment
                    \App\Modules\Attendance\Models\AttendanceRecord::updateOrCreate(
                        [
                            'employee_id'   => $employee->id,
                            'pay_period_id' => $periodId,
                            'segment'       => $seg['segment'],
                        ],
                        [
                            'hari_kerja'   => $hariKerja,
                            'cuti'         => $cuti,
                            'izin'         => $izin,
                            'sakit'        => $sakit,
                            'absen'        => $absen,
                            'deduct_day'   => $deductDay,
                            'late_minutes' => $lateMinutes,
                            'lm'           => $lm,
                            'lm_count'     => $lmCount,
                            'lembur'       => $lembur,
                            'lembur_count' => $lemburCount,
                            'status'       => 'generated',
                        ]
                    );
                }

                $generatedCount++;
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil me-generate resume kehadiran untuk {$generatedCount} karyawan.",
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal generate: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/attendance/recap/approve
     * Lock att_records + create/update pay_records (dengan split logic)
     */
    public function recapApprove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:att_records,id',
        ]);

        $records = \App\Modules\Attendance\Models\AttendanceRecord::with(['employee', 'payPeriod'])
            ->whereIn('id', $request->ids)
            ->get();

        if ($records->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $processed = 0;
        $errors = [];

        try {
            foreach ($records as $record) {
                // Cek apakah udah locked
                if ($record->status === 'locked') {
                    $errors[] = "{$record->employee->name}: sudah dilock sebelumnya.";
                    continue;
                }

                $period = $record->payPeriod;
                $employee = $record->employee;
                $startDate = $period->start_date;
                $endDate = $period->end_date;

                $payrollConfig = \App\Modules\Settings\Models\SystemSetting::where('key', 'payroll_config')->first();
                if (!$payrollConfig || empty($payrollConfig->fixed_working_day)) {
                    throw new \Exception("Kalkulasi gagal: Hari kerja (Fixed Working Day) belum diatur di menu Pengaturan Penggajian.");
                }
                $fixedDays = (int) $payrollConfig->fixed_working_day;

                // ── Tentukan Segmen ──
                if ($period->is_split) {
                    $month1End = \Carbon\Carbon::parse($startDate)->endOfMonth()->toDateString();
                    $month2Start = \Carbon\Carbon::parse($endDate)->startOfMonth()->toDateString();
                    
                    $splitDays = json_decode($payrollConfig->value ?? '{}', true);
                    if (!isset($splitDays['A']) || !isset($splitDays['B'])) {
                        throw new \Exception("Kalkulasi gagal: Nilai hari kerja untuk split periode (A dan B) belum diatur di menu Pengaturan Penggajian.");
                    }
                    $hkA = (int) $splitDays['A'];
                    $hkB = (int) $splitDays['B'];

                    $segments = [
                        ['segment' => 'A', 'start' => $startDate, 'end' => $month1End, 'hk' => $hkA],
                        ['segment' => 'B', 'start' => $month2Start, 'end' => $endDate, 'hk' => $hkB],
                    ];
                } else {
                    $segments = [
                        ['segment' => null, 'start' => $startDate, 'end' => $endDate, 'hk' => $fixedDays],
                    ];
                }

                foreach ($segments as $seg) {
                    $segment = $seg['segment'];
                    $segStart = $seg['start'];
                    $segEnd = $seg['end'];
                    $hkSegment = $seg['hk'];

                    // Skip karyawan yang bukan group penggajian — hapus pay_record jika ada
                    if (! $employee->isGroupGaji()) {
                        \App\Modules\Payroll\Models\PayRecord::where('employee_id', $employee->id)
                            ->where('pay_period_id', $period->id)
                            ->where('segment', $segment)
                            ->delete();
                        continue;
                    }

                    if ($segment !== null) {
                        // HITUNG ULANG UNTUK SEGMEN A/B
                        $prepares = \App\Modules\Attendance\Models\AttendancePrepare::where('employee_id', $employee->id)
                            ->whereBetween('date', [$segStart, $segEnd])
                            ->get();

                        $leaves = \App\Modules\Leave\Models\LeaveRequest::where('employee_id', $employee->id)
                            ->where('status', 'approved')
                            ->where(function ($q) use ($segStart, $segEnd) {
                                $q->whereBetween('start_date', [$segStart, $segEnd])
                                  ->orWhereBetween('end_date', [$segStart, $segEnd])
                                  ->orWhere(function ($q2) use ($segStart, $segEnd) {
                                      $q2->where('start_date', '<=', $segStart)
                                         ->where('end_date', '>=', $segEnd);
                                  });
                            })->get();

                        $absen = $prepares->where('status', 'absent')->count();
                        $lm = $prepares->sum('lm');
                        $lmCount = $prepares->sum('lm_count');
                        $lemburCount = $prepares->sum('overtime_count');

                        $cuti = 0; $izin = 0; $sakit = 0; $unpaid = 0;
                        foreach ($leaves as $l) {
                            // Hitung durasi irisan cuti di dalam segmen ini (karena bisa menyeberang)
                            $lStart = \Carbon\Carbon::parse(max($l->start_date->toDateString(), $segStart));
                            $lEnd = \Carbon\Carbon::parse(min($l->end_date->toDateString(), $segEnd));
                            $dur = max(0, $lStart->diffInDays($lEnd) + 1);

                            $cat = optional($l->leaveType)->category;
                            $isPaid = optional($l->leaveType)->is_paid;
                            if ($cat === 'leave') $cuti += $dur;
                            elseif ($cat === 'permit') $izin += $dur;
                            elseif ($cat === 'sick') $sakit += $dur;
                            if ($isPaid === false) $unpaid += $dur;
                        }

                        $deductDay = $unpaid + $absen;
                        $hariKerja = max(0, $hkSegment - $deductDay);
                    } else {
                        // HITUNG ULANG UNTUK NON-SPLIT — dari att_prepares & leave_requests langsung, bukan dari att_record
                        $prepares = \App\Modules\Attendance\Models\AttendancePrepare::where('employee_id', $employee->id)
                            ->whereBetween('date', [$segStart, $segEnd])
                            ->get();

                        $leaves = \App\Modules\Leave\Models\LeaveRequest::where('employee_id', $employee->id)
                            ->where('status', 'approved')
                            ->where(function ($q) use ($segStart, $segEnd) {
                                $q->whereBetween('start_date', [$segStart, $segEnd])
                                  ->orWhereBetween('end_date', [$segStart, $segEnd])
                                  ->orWhere(function ($q2) use ($segStart, $segEnd) {
                                      $q2->where('start_date', '<=', $segStart)
                                         ->where('end_date', '>=', $segEnd);
                                  });
                            })->get();

                        $absen = $prepares->where('status', 'absent')->count();
                        $lm = $prepares->sum('lm');
                        $lmCount = $prepares->sum('lm_count');
                        $lemburCount = $prepares->sum('overtime_count');

                        $cuti = 0; $izin = 0; $sakit = 0; $unpaid = 0;
                        foreach ($leaves as $l) {
                            $lStart = \Carbon\Carbon::parse(max($l->start_date->toDateString(), $segStart));
                            $lEnd = \Carbon\Carbon::parse(min($l->end_date->toDateString(), $segEnd));
                            $dur = max(0, $lStart->diffInDays($lEnd) + 1);
                            $cat = optional($l->leaveType)->category;
                            $isPaid = optional($l->leaveType)->is_paid;
                            if ($cat === 'leave') $cuti += $dur;
                            elseif ($cat === 'permit') $izin += $dur;
                            elseif ($cat === 'sick') $sakit += $dur;
                            if ($isPaid === false) $unpaid += $dur;
                        }

                        $deductDay = $unpaid + $absen;
                        $hariKerja = max(0, $hkSegment - $deductDay);
                    }

                    // ── DATA MASUKAN ──
                    $segmentMonth = $segment !== null
                        ? \Carbon\Carbon::parse($segStart)->format('Y-m')
                        : $period->period_year . '-' . str_pad($period->period_month, 2, '0', STR_PAD_LEFT);
                    $gajiPokok = $employee->gaji_pokok($segmentMonth);
                    $premi = $employee->premi($segmentMonth);
                    $tjMasaKerja = $employee->tunjangan_masa_kerja($segmentMonth);
                    $tunjangan = $employee->tunjangan($segmentMonth);

                    // ── HITUNGAN ──
                    // Pembagi selalu fixedDays (Misal: 22), agar gaji per hari valid, bukan dibagi hkSegment (Misal: 11)
                    $gaji = round(($gajiPokok / $fixedDays) * $hariKerja, 2);
                    $totalLemburJam = ($lmCount + $lemburCount) / 60;

                    // Zero overtime: Section A groups (ALL IN) — baca dari payroll config
                    // GRP-SPR: LM tetap, LBR JAM = 0, upah lembur dari LM saja
                    $payrollConfig = \App\Modules\Payroll\Models\PayrollConfig::getConfig('gaji_karyawan');
                    $sectionAGroups = $payrollConfig['sections']['A'] ?? ['GRP-ALLIN', 'GRP-SPR'];
                    $isZeroOvertime = $employee->groups()->whereIn('reference_code', $sectionAGroups)
                        ->where('reference_code', '!=', 'GRP-SPR')
                        ->exists();

                    if ($isZeroOvertime) {
                        $upahLembur = 0;
                        $lm = 0;
                        $lmCount = 0;
                        $lemburCount = 0;
                    } else {
                        // GRP-SPR: LBR JAM = 0, upah lembur hanya dari LM
                        $isSpr = $employee->groups()->where('reference_code', 'GRP-SPR')->exists();
                        if ($isSpr) {
                            $lemburCount = 0;
                            $totalLemburJam = $lmCount / 60;
                        }
                        $upahLembur = ceil((($gajiPokok + $tjMasaKerja + $tunjangan) / 173) * $totalLemburJam / 100) * 100;
                    }
                    
                    $premiHadir = round(($premi / $fixedDays) * $hariKerja, 2);

                    // Part 1 vs Part 2
                    $isPart1 = ($segment === 'A');
                    $revisi = $isPart1 ? ($tjMasaKerja * -1) : 0;
                    $bpjsTk = $isPart1 ? 0 : ($employee->bpjs?->bpjs_tk_karyawan ?? 0);
                    $bpjsKs = $isPart1 ? 0 : ($employee->bpjs?->bpjs_kes_karyawan ?? 0);
                    $bpjsPen = $isPart1 ? 0 : ($employee->bpjs?->bpjs_pensiun ?? 0);
                    $pph = $isPart1 ? 0 : 0; // TODO: dari pengelolaan PPH
                    $cashbon = 0;

                    $gajiKotor = $gaji + $tjMasaKerja + $upahLembur + $revisi + $premiHadir + $tunjangan;
                    $potKehadiran = round($deductDay * ($gajiPokok / $fixedDays), 2);
                    // potKehadiran tetap disimpan di DB untuk slip gaji, tapi DITIDAKMASUKKAN ke totalPotongan karena $gaji sudah proporsional
                    $totalPotongan = $bpjsTk + $bpjsKs + $bpjsPen + $pph + $cashbon;

                    // Pembulatan 100
                    $beforeRounding = $gajiKotor - $totalPotongan;
                    $rounded = ceil($beforeRounding / 100) * 100;
                    $pblt = $rounded - $beforeRounding;
                    $gajiBersih = $beforeRounding + $pblt;

                    // ── CREATE/UPDATE PAY_RECORD ──
                    \App\Modules\Payroll\Models\PayRecord::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'pay_period_id' => $period->id,
                            'segment' => $segment,
                        ],
                        [
                            'att_record_id' => $record->id,
                            'gaji_pokok' => $gajiPokok,
                            'premi' => $premi,
                            'tj_masa_kerja' => $tjMasaKerja,
                            'tunjangan' => $tunjangan,
                            'hari_kerja' => $hariKerja,
                            'deduct_day' => $deductDay,
                            'lm' => $lm,
                            'lm_count' => $lmCount,
                            'lembur_count' => $lemburCount,
                            'gaji' => $gaji,
                            'upah_lembur' => $upahLembur,
                            'premi_hadir' => $premiHadir,
                            'revisi' => $revisi,
                            'gaji_kotor' => $gajiKotor,
                            'bpjs_tk' => $bpjsTk,
                            'bpjs_ks' => $bpjsKs,
                            'bpjs_pen' => $bpjsPen,
                            'pph' => $pph,
                            'cashbon' => $cashbon,
                            'pot_kehadiran' => $potKehadiran,
                            'pblt' => $pblt,
                            'gaji_bersih' => $gajiBersih,
                            'status' => 'generated',
                        ]
                    );
                }

                // ── LOCK ATT_RECORD ──
                $record->update(['status' => 'locked']);

                $processed++;
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil memproses {$processed} data." . (count($errors) ? ' ' . implode(' ', $errors) : ''),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal approve: ' . $e->getMessage(),
            ], 500);
        }
    }

}
