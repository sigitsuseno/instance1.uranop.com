<?php

namespace App\Modules\Attendance\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Jobs\ProcessAttendanceLogImport;
use App\Modules\Attendance\Models\AttConfig;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSummary;
use App\Modules\Attendance\Models\Overtime;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Attendance\Models\ScanDetectionConfig;
use App\Modules\Attendance\Resources\AttendanceRecordResource;
use App\Modules\Attendance\Resources\AttendanceSummaryResource;
use App\Modules\Attendance\Resources\OvertimeResource;
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
    // ATTENDANCE CONFIGS
    // =================================================================

    /**
     * GET /api/v1/attendance/configs
     * List all attendance configs.
     */
    public function configs(Request $request): JsonResponse
    {
        $group = $request->input('group');

        $configs = AttConfig::query()
            ->when($group, fn($q) => $q->where('group', $group))
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        // Convert to key-value pairs grouped by group
        $grouped = [];
        foreach ($configs as $config) {
            $grouped[$config->group][$config->key] = [
                'value' => $config->typed_value,
                'type' => $config->type,
                'label' => $config->label,
                'description' => $config->description,
                'is_editable' => $config->is_editable,
            ];
        }

        return response()->json([
            'data' => $group ? ($grouped[$group] ?? []) : $grouped,
        ]);
    }

    /**
     * PUT /api/v1/attendance/configs
     * Bulk update attendance configs.
     */
    public function updateConfigs(Request $request): JsonResponse
    {
        $request->validate([
            'configs' => 'required|array',
        ]);

        foreach ($request->input('configs') as $key => $data) {
            $value = $data['value'] ?? $data;
            $type = $data['type'] ?? 'string';

            AttConfig::setValue($key, $value, $type);
        }

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi absensi berhasil diperbarui.',
        ]);
    }

    // =================================================================
    // SCAN DETECTION CONFIGS
    // =================================================================

    /**
     * GET /api/v1/attendance/scan-configs
     * List scan detection configs.
     */
    public function scanConfigs(): JsonResponse
    {
        $configs = ScanDetectionConfig::orderBy('priority', 'desc')
            ->orderBy('machine_name')
            ->get();

        return response()->json(['data' => $configs]);
    }

    /**
     * POST /api/v1/attendance/scan-configs
     * Create scan detection config.
     */
    public function storeScanConfig(Request $request): JsonResponse
    {
        $request->validate([
            'machine_sn' => 'nullable|string|max:100',
            'machine_name' => 'nullable|string|max:100',
            'scan_type_rules' => 'required|array',
            'scan_type_rules.*.type' => 'required|in:in,out,break',
            'scan_type_rules.*.start' => 'required|date_format:H:i',
            'scan_type_rules.*.end' => 'required|date_format:H:i',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'description' => 'nullable|string',
        ]);

        $config = ScanDetectionConfig::create([
            'machine_sn' => $request->input('machine_sn'),
            'machine_name' => $request->input('machine_name'),
            'scan_type_rules' => $request->input('scan_type_rules'),
            'is_active' => $request->input('is_active', true),
            'priority' => $request->input('priority', 0),
            'description' => $request->input('description'),
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $config,
            'message' => 'Konfigurasi scan berhasil dibuat.',
        ], 201);
    }

    /**
     * PUT /api/v1/attendance/scan-configs/{id}
     * Update scan detection config.
     */
    public function updateScanConfig(Request $request, int $id): JsonResponse
    {
        $config = ScanDetectionConfig::findOrFail($id);

        $request->validate([
            'machine_sn' => 'nullable|string|max:100',
            'machine_name' => 'nullable|string|max:100',
            'scan_type_rules' => 'array',
            'scan_type_rules.*.type' => 'in:in,out,break',
            'scan_type_rules.*.start' => 'date_format:H:i',
            'scan_type_rules.*.end' => 'date_format:H:i',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'description' => 'nullable|string',
        ]);

        $config->update($request->only([
            'machine_sn', 'machine_name', 'scan_type_rules',
            'is_active', 'priority', 'description',
        ]) + ['updated_by' => Auth::id()]);

        return response()->json([
            'success' => true,
            'data' => $config->fresh(),
            'message' => 'Konfigurasi scan berhasil diperbarui.',
        ]);
    }

    /**
     * DELETE /api/v1/attendance/scan-configs/{id}
     * Delete scan detection config.
     */
    public function deleteScanConfig(int $id): JsonResponse
    {
        $config = ScanDetectionConfig::findOrFail($id);
        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi scan berhasil dihapus.',
        ]);
    }

    // =================================================================
    // ATTENDANCE RECORDS (att_records)
    // =================================================================

    /**
     * GET /api/v1/attendance/records
     */
    public function records(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $perPage = $request->input('per_page', 50);

        $records = AttendanceRecord::with('employee')
            ->whereBetween('date', [$startDate, $endDate])
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('date', 'desc')
            ->orderBy('employee_id')
            ->paginate($perPage);

        return response()->json([
            'data' => AttendanceRecordResource::collection($records),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/attendance/records/{id}
     */
    public function showRecord(int $id): JsonResponse
    {
        $record = AttendanceRecord::with('employee')->findOrFail($id);
        return response()->json(['data' => new AttendanceRecordResource($record)]);
    }

    /**
     * PUT /api/v1/attendance/records/{id}
     */
    public function updateRecord(Request $request, int $id): JsonResponse
    {
        $record = AttendanceRecord::findOrFail($id);

        if ($record->is_locked) {
            return response()->json(['message' => 'Record sudah dikunci.'], 422);
        }

        $request->validate([
            'status' => 'nullable|in:present,late,absent,off,holiday,permit,sick,half_day',
            'actual_in' => 'nullable|date',
            'actual_out' => 'nullable|date',
            'late_minutes' => 'nullable|integer|min:0',
            'early_minutes' => 'nullable|integer|min:0',
            'overtime_minutes' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $record->update($request->only([
            'status', 'actual_in', 'actual_out', 'late_minutes',
            'early_minutes', 'overtime_minutes', 'notes',
        ]) + ['is_manual' => true, 'updated_by' => Auth::id()]);

        return response()->json([
            'data' => new AttendanceRecordResource($record->fresh()->load('employee')),
            'message' => 'Record absensi berhasil diperbarui.',
        ]);
    }

    // =================================================================
    // ATTENDANCE SUMMARIES
    // =================================================================

    /**
     * GET /api/v1/attendance/summaries
     */
    public function summaries(Request $request): JsonResponse
    {
        $period = $request->input('period', Carbon::now()->format('Y-m'));
        $startDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

        $employeeId = $request->input('employee_id');

        $summaries = AttendanceSummary::with('employee')
            ->where('period_start', $startDate->toDateString())
            ->where('period_end', $endDate->toDateString())
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->paginate(50);

        return response()->json([
            'data' => AttendanceSummaryResource::collection($summaries),
            'meta' => [
                'period' => $period,
                'current_page' => $summaries->currentPage(),
                'last_page' => $summaries->lastPage(),
                'total' => $summaries->total(),
            ],
        ]);
    }

    // =================================================================
    // PROCESSING ENDPOINTS
    // =================================================================

    /**
     * POST /api/v1/attendance/process/autolog
     * Jalankan auto-proses scan mentah → autolog.
     */
    public function processAutolog(Request $request): JsonResponse
    {
        $date = $request->input('date');
        $service = new AttendanceService();
        $result = $service->processAutologs($date);

        return response()->json([
            'success' => true,
            'message' => "Auto-proses selesai. {$result['autologs']} autolog dibuat, {$result['processed']} scan diproses.",
            'data' => $result,
        ]);
    }

    /**
     * POST /api/v1/attendance/process/generate-records
     * Generate attendance records dari autolog.
     */
    public function processGenerateRecords(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));

        $service = new AttendanceService();
        $result = $service->generateRecords($startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => "Generate records selesai. {$result['created']} dibuat, {$result['updated']} diperbarui.",
            'data' => $result,
        ]);
    }

    /**
     * POST /api/v1/attendance/process/generate-summary
     */
    public function processGenerateSummary(Request $request): JsonResponse
    {
        $period = $request->input('period', Carbon::now()->format('Y-m'));

        $service = new AttendanceService();
        $result = $service->generateSummary($period);

        return response()->json([
            'success' => true,
            'message' => "Generate summary selesai. {$result['created']} dibuat, {$result['updated']} diperbarui.",
            'data' => $result,
        ]);
    }

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
     * List prepares dengan filter: date, status, review_status, dll.
     */
    public function prepareList(Request $request): JsonResponse
    {
        $startDate  = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate    = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $employeeId = $request->input('employee_id');
        $status     = $request->input('status');
        $reviewStatus = $request->input('review_status');
        $isLocked   = $request->input('is_locked');
        $perPage    = $request->input('per_page', 50);

        $query = AttendancePrepare::with('employee:id,name,nip,employee_code,department_id')
            ->whereBetween('date', [$startDate, $endDate])
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($reviewStatus, fn($q) => $q->where('review_status', $reviewStatus))
            ->when($isLocked !== null, fn($q) => $q->where('is_locked', $isLocked === '1'))
            ->orderBy('date')
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
            'message' => "Hitung lembur selesai. {$result['updated']} record diperbarui.",
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

    // =================================================================
    // OVERTIME
    // =================================================================

    /**
     * GET /api/v1/attendance/overtimes
     */
    public function overtimes(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $employeeId = $request->input('employee_id');
        $status = $request->input('status');

        $overtimes = Overtime::with(['employee', 'overtimeRule', 'approver'])
            ->whereBetween('date', [$startDate, $endDate])
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('date', 'desc')
            ->paginate(50);

        return response()->json([
            'data' => OvertimeResource::collection($overtimes),
            'meta' => [
                'current_page' => $overtimes->currentPage(),
                'last_page' => $overtimes->lastPage(),
                'total' => $overtimes->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/attendance/overtimes
     */
    public function storeOvertime(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'overtime_rule_id' => 'nullable|exists:att_overtime_rules,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'notes' => 'nullable|string',
        ]);

        $startTime = Carbon::parse($request->input('start_time'));
        $endTime = Carbon::parse($request->input('end_time'));
        $totalHours = round($endTime->diffInMinutes($startTime) / 60, 2);

        // Get multiplier from overtime rule
        $multiplier = 1.00;
        if ($overtimeRuleId = $request->input('overtime_rule_id')) {
            $rule = \App\Modules\Attendance\Models\OvertimeRule::find($overtimeRuleId);
            if ($rule) {
                $multiplier = $rule->getMultiplier((int) ceil($totalHours));
            }
        }

        $overtime = Overtime::create([
            'uuid' => \Str::uuid(),
            'employee_id' => $request->input('employee_id'),
            'date' => $request->input('date'),
            'overtime_rule_id' => $request->input('overtime_rule_id'),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_hours' => $totalHours,
            'multiplier' => $multiplier,
            'calculated_hours' => round($totalHours * $multiplier, 2),
            'status' => Overtime::STATUS_PENDING,
            'notes' => $request->input('notes'),
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => new OvertimeResource($overtime->load(['employee', 'overtimeRule'])),
            'message' => 'Lembur berhasil diajukan.',
        ], 201);
    }

    /**
     * PUT /api/v1/attendance/overtimes/{id}/approve
     */
    public function approveOvertime(Request $request, int $id): JsonResponse
    {
        $overtime = Overtime::findOrFail($id);

        if ($overtime->status !== Overtime::STATUS_PENDING) {
            return response()->json(['message' => 'Lembur sudah diproses sebelumnya.'], 422);
        }

        $request->validate(['action' => 'required|in:approve,reject']);

        if ($request->input('action') === 'reject') {
            $overtime->update([
                'status' => Overtime::STATUS_REJECTED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'reject_reason' => $request->input('reject_reason'),
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'data' => new OvertimeResource($overtime->fresh()),
                'message' => 'Lembur ditolak.',
            ]);
        }

        $overtime->update([
            'status' => Overtime::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'data' => new OvertimeResource($overtime->fresh()),
            'message' => 'Lembur disetujui.',
        ]);
    }

    /**
     * DELETE /api/v1/attendance/overtimes/{id}
     */
    public function deleteOvertime(int $id): JsonResponse
    {
        $overtime = Overtime::findOrFail($id);

        if ($overtime->status === Overtime::STATUS_APPROVED) {
            return response()->json(['message' => 'Lembur yang sudah disetujui tidak dapat dihapus.'], 422);
        }

        $overtime->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lembur berhasil dihapus.',
        ]);
    }
}
