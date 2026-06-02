<?php

namespace App\Modules\Attendance\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Jobs\ProcessAttendanceLogImport;
use App\Modules\Attendance\Models\RawLog;
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
}
