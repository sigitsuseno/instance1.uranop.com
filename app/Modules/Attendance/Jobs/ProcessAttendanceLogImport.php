<?php

namespace App\Modules\Attendance\Jobs;

use App\Modules\Attendance\Imports\AttendanceLogImport;
use App\Modules\Attendance\Models\RawLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProcessAttendanceLogImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 3;

    /**
     * @param  string  $filePath   Full path to uploaded file
     * @param  string  $fileName   Original filename
     * @param  string  $importBatch  Unique batch identifier
     * @param  string  $mode        'create' | 'replace'
     */
    public function __construct(
        protected string $filePath,
        protected string $fileName,
        protected string $importBatch,
        protected string $mode = 'create'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Memulai proses import log absensi', [
            'batch' => $this->importBatch,
            'file'  => $this->fileName,
            'mode'  => $this->mode,
        ]);

        try {
            if (! file_exists($this->filePath)) {
                throw new \Exception('File tidak ditemukan: ' . $this->filePath);
            }

            // Replace mode: delete existing records from same source file
            if ($this->mode === 'replace') {
                $deleted = RawLog::where('source_file', $this->fileName)->delete();

                Log::info('Replace mode: deleted old records', [
                    'batch'   => $this->importBatch,
                    'deleted' => $deleted,
                    'file'    => $this->fileName,
                ]);
            }

            // Run import
            $import = new AttendanceLogImport($this->importBatch, $this->fileName);
            Excel::import($import, $this->filePath);

            $result = [
                'status'        => 'completed',
                'success'       => true,
                'inserted'      => $import->getInserted(),
                'total_errors'  => count($import->getErrors()),
                'total_warnings'=> count($import->getWarnings()),
                'errors'        => $import->getErrors(),
                'warnings'      => $import->getWarnings(),
                'batch'         => $this->importBatch,
                'mode'          => $this->mode,
                'file'          => $this->fileName,
            ];

            // Store result in cache (24 hours)
            Cache::put('import_result_' . $this->importBatch, $result, now()->addHours(24));

            // Clean up temp file
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }

            Log::info('Import log absensi selesai', [
                'batch'    => $this->importBatch,
                'inserted' => $import->getInserted(),
                'errors'   => count($import->getErrors()),
                'warnings' => count($import->getWarnings()),
            ]);

        } catch (Throwable $e) {
            Log::error('Import log absensi gagal', [
                'batch' => $this->importBatch,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Cache::put('import_result_' . $this->importBatch, [
                'status'  => 'failed',
                'success' => false,
                'message' => $e->getMessage(),
                'batch'   => $this->importBatch,
                'mode'    => $this->mode,
                'file'    => $this->fileName,
            ], now()->addHours(24));

            // Clean up on error too
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }

            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Job import log absensi failed', [
            'batch' => $this->importBatch,
            'error' => $exception->getMessage(),
        ]);

        Cache::put('import_result_' . $this->importBatch, [
            'status'  => 'failed',
            'success' => false,
            'message' => 'Import gagal: ' . $exception->getMessage(),
            'batch'   => $this->importBatch,
            'mode'    => $this->mode,
            'file'    => $this->fileName,
        ], now()->addHours(24));

        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }
}
