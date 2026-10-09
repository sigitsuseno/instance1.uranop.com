<?php

namespace App\Modules\Attendance\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Services\LegacyAttendanceInputService;
use App\Modules\FileManager\Support\FileManagerPathGuard;
use App\Modules\FileManager\Support\FileType;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Input Data Lama — pengisian kehadiran periode lama dari berkas Excel
 * "absen_core" yang ada di File Manager.
 *
 * Berkas tidak diunggah lewat halaman ini: sumbernya adalah disk privat
 * "file_manager", dan path-nya divalidasi lewat FileManagerPathGuard agar
 * tidak bisa keluar dari root disk.
 *
 * preview() menyusun rencana tanpa menulis apa pun; store() menuliskan ke
 * att_prepares, leave_requests, dan att_consecutive_days.
 */
class LegacyInputApiController extends Controller
{
    /** Batas ukuran berkas sumber (20 MB), sejalan dengan batas unggah File Manager. */
    public const MAX_FILE_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly LegacyAttendanceInputService $service,
        private readonly FileManagerPathGuard $guard,
    ) {
    }

    /**
     * POST /api/v1/attendance/legacy-input/preview
     */
    public function preview(Request $request): JsonResponse
    {
        return $this->process($request, commit: false);
    }

    /**
     * POST /api/v1/attendance/legacy-input/store
     */
    public function store(Request $request): JsonResponse
    {
        return $this->process($request, commit: true);
    }

    protected function process(Request $request, bool $commit): JsonResponse
    {
        $validated = $request->validate([
            'path'          => 'required|string|max:1000',
            'pay_period_id' => 'required|integer|exists:pay_periods,id',
        ]);

        $period = PayPeriod::findOrFail($validated['pay_period_id']);

        try {
            $absolute = $this->resolveSourceFile($validated['path']);

            $built = $this->service->build($absolute, $period);

            $result = null;
            if ($commit) {
                $result = $this->service->commit($built['plan'], $period, $request->user()?->id);
            }

            return response()->json([
                'success'   => true,
                'message'   => $commit ? 'Data lama berhasil disimpan.' : 'Pratinjau berhasil disusun.',
                'committed' => $commit,
                'data'      => [
                    'summary' => $built['summary'],
                    'issues'  => $built['issues'],
                    'result'  => $result,
                ],
            ]);
        } catch (RuntimeException $e) {
            // HttpException dari FileManagerPathGuard juga turunan RuntimeException —
            // diteruskan apa adanya supaya status aslinya (404/422) tidak berubah jadi 500.
            if ($e instanceof HttpException) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Legacy attendance input failed', [
                'period_id' => $period->id,
                'path'      => $validated['path'],
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses berkas: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Terjemahkan path relatif File Manager menjadi absolute path yang aman.
     *
     * FileManagerPathGuard menolak traversal dan symlink; sisanya memastikan
     * berkasnya benar-benar ada, berupa berkas, dan bertipe spreadsheet.
     */
    protected function resolveSourceFile(string $path): string
    {
        $absolute = $this->guard->absolute($path);

        if (! is_file($absolute)) {
            throw new RuntimeException('Path yang dipilih bukan sebuah berkas.');
        }

        $name = basename($absolute);

        if (FileType::isBlockedExtension($name) || ! FileType::isSpreadsheet($name)) {
            throw new RuntimeException(
                'Berkas harus berupa spreadsheet (.xlsx, .xls, .xlsm, .xlsb, .ods). '
                ."Berkas terpilih: {$name}"
            );
        }

        $size = @filesize($absolute);
        if ($size !== false && $size > self::MAX_FILE_BYTES) {
            throw new RuntimeException(
                'Ukuran berkas melebihi batas '.number_format(self::MAX_FILE_BYTES / 1024 / 1024, 0).' MB.'
            );
        }

        return $absolute;
    }
}
