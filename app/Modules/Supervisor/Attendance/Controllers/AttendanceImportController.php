<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Supervisor\Attendance\Exceptions\AttendanceScanFileException;
use App\Modules\Supervisor\Attendance\Services\AttendanceImportService;
use App\Modules\Supervisor\Attendance\Services\AttendanceScanImportService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class AttendanceImportController extends Controller
{
    /**
     * File scanlog mesin fingerprint → dibaca dan ditulis ke attendance_autologs.
     *
     * @var array<int, string>
     */
    protected const SCANLOG_EXTENSIONS = ['xls', 'xlsx'];

    /**
     * File mesin fingerprint mentah → prosedur lama (generate dari roster +
     * att_prepares). Isi file tidak dibaca.
     *
     * @var array<int, string>
     */
    protected const BIN_EXTENSIONS = ['bin', 'dat'];

    public function __construct(
        protected AttendanceImportService $importService,
        protected AttendanceScanImportService $scanImportService,
    ) {}

    public function create()
    {
        $periods = PayPeriod::select('id', 'name', 'start_date', 'end_date', 'status')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'periods' => $periods,
        ]);
    }

    /**
     * Import attendance untuk periode terpilih.
     *
     * Ekstensi file menentukan prosedurnya:
     *   .xls / .xlsx → baca scanlog, tulis langsung ke attendance_autologs.
     *   .bin / .dat  → prosedur lama (generate dari roster + att_prepares).
     */
    public function store(Request $request)
    {
        $request->validate([
            'payroll_period_id' => ['required', 'exists:pay_periods,id'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        // Sengaja tidak memakai rule `mimes:`/`extensions:`: keduanya memvalidasi
        // ekstensi hasil sniffing konten, dan file .dat biner ter-sniff sebagai
        // application/octet-stream yang tidak memuat 'dat' di daftar Symfony —
        // file .dat yang sah jadi ikut ditolak.
        $allowed = array_merge(self::SCANLOG_EXTENSIONS, self::BIN_EXTENSIONS);
        if (! in_array($extension, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Format file tidak didukung. Gunakan .xls/.xlsx (scanlog) atau .bin/.dat.',
            ], 422);
        }

        $period = PayPeriod::findOrFail((int) $request->input('payroll_period_id'));

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $temporaryPath = null;

        try {
            if (in_array($extension, self::SCANLOG_EXTENSIONS, true)) {
                $temporaryPath = $this->storeTemporary($file);

                $result = $this->scanImportService->import(
                    filePath: $temporaryPath,
                    originalName: $originalName,
                    startDate: $period->start_date->toDateString(),
                    endDate: $period->end_date->toDateString(),
                );

                $message = $this->scanlogMessage($result);
            } else {
                $result = $this->importService->import(
                    startDate: $period->start_date->toDateString(),
                    endDate: $period->end_date->toDateString(),
                    periodId: (int) $period->id,
                );

                $message = $this->binMessage($result);
            }

            Log::info('Attendance import completed', [
                'period_id' => $period->id,
                'file' => $originalName,
                'result' => $result,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        } catch (AttendanceScanFileException $e) {
            // Berkasnya yang salah (rusak / format tidak dikenali) — bukan 500.
            Log::warning('Attendance import: berkas tidak valid', [
                'period_id' => $period->id,
                'file' => $originalName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Attendance import failed', [
                'period_id' => $period->id,
                'file' => $originalName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import gagal: '.$e->getMessage(),
            ], 500);
        } finally {
            if ($temporaryPath !== null && file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Simpan upload ke storage/app/temp/imports (konvensi modul Attendance),
     * supaya PhpSpreadsheet membaca dari path file, bukan dari tmp upload.
     */
    protected function storeTemporary(UploadedFile $file): string
    {
        $directory = storage_path('app/temp/imports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = uniqid('scan_').'_'.preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
        $file->move($directory, $filename);

        return $directory.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * Pesan hasil jalur scanlog (.xls/.xlsx).
     *
     * `skipped` dihitung per baris scan, bukan per hari karyawan — satu hari
     * yang diabaikan bisa menyumbang beberapa baris.
     */
    protected function scanlogMessage(array $result): string
    {
        $message = sprintf(
            'Import scanlog (%s): %d hari karyawan ditulis (%d baru, %d ditimpa)',
            $result['format'],
            $result['inserted'] + $result['updated'],
            $result['inserted'],
            $result['updated'],
        );

        if (($result['skipped'] ?? 0) > 0) {
            $message .= sprintf(
                ', %d baris scan diabaikan (PIN tak dikenal / di luar periode / tanpa roster)',
                $result['skipped']
            );
        }

        if (($result['locked'] ?? 0) > 0) {
            $message .= sprintf(', %d baris terkunci dilewati', $result['locked']);
        }

        return $message.'. Jangan lupa jalankan Perhitungan Lembur!';
    }

    /**
     * Pesan hasil jalur lama (.bin/.dat) — tidak diubah dari sebelumnya.
     */
    protected function binMessage(array $result): string
    {
        $inserted = $result['inserted'] ?? 0;
        $updated = $result['updated'] ?? 0;
        $skipped = $result['skipped'] ?? 0;
        $hasExcel = isset($result['excel']);

        $message = "Import: {$inserted} inserted, {$updated} updated";
        if ($skipped > 0) {
            $message .= ", {$skipped} skipped";
        }
        if ($hasExcel) {
            $message .= ' 1';
        }

        return $message.'. Jangan lupa jalankan Perhitungan Lembur!';
    }
}
