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
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'mode' => 'nullable|in:create,replace',
        ]);

        try {
            $file = $request->file('file');
            $mode = $request->input('mode', 'create');

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
                $mode
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
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $syncService = new \App\Modules\Attendance\Services\AttendanceSyncService(
            new \App\Modules\Attendance\Services\AttendanceCalculatorService()
        );

        try {
            $result = $syncService->syncPeriod(
                $request->start_date,
                $request->end_date
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
     */
    public function prepareLengkapi(Request $request): JsonResponse
    {
        $request->validate([
            'records'   => 'required|array',
            'records.*.id' => 'required|integer',
        ]);

        $service = new AttendanceService();
        $result  = $service->bulkLengkapi($request->input('records'), Auth::id());

        return response()->json([
            'success' => true,
            'message' => "Lengkapi selesai. {$result['updated']} record diperbarui.",
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

    // =================================================================
    // CONSECUTIVE DAYS — CRUD (mirip leave_request)
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
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Consecutive day berhasil dihapus.',
        ]);
    }

}
