<?php

namespace App\Modules\Supervisor\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\FileManager\Support\FileManagerPathGuard;
use App\Modules\FileManager\Support\FileType;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Supervisor\Payroll\Services\SupervisorBreakdownImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Update Data — import breakdown gaji supervisor dari berkas Excel yang sudah
 * tersimpan di File Manager.
 *
 * Berkas tidak diunggah lewat halaman ini: sumbernya disk privat "file_manager",
 * dan path-nya divalidasi FileManagerPathGuard agar tidak bisa keluar dari root
 * disk. Karena itu rutenya dibatasi superadmin, sama seperti modul File Manager.
 *
 * preview() menyusun rencana tanpa menulis apa pun; store() menuliskan ke
 * supervisor_breakdowns (update kalau sudah ada, create kalau belum).
 */
class SupervisorBreakdownImportController extends Controller
{
    /** Batas ukuran berkas sumber (20 MB), sejalan dengan batas unggah File Manager. */
    public const MAX_FILE_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly SupervisorBreakdownImportService $service,
        private readonly FileManagerPathGuard $guard,
    ) {
    }

    /**
     * POST /api/v1/supervisor/payroll/breakdown/import/preview
     */
    public function preview(Request $request): JsonResponse
    {
        return $this->process($request, commit: false);
    }

    /**
     * POST /api/v1/supervisor/payroll/breakdown/import/store
     */
    public function store(Request $request): JsonResponse
    {
        return $this->process($request, commit: true);
    }

    protected function process(Request $request, bool $commit): JsonResponse
    {
        $validated = $request->validate([
            'path'      => 'required|string|max:1000',
            'period_id' => 'required|integer|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period  = PayPeriod::findOrFail($validated['period_id']);
        $segment = $this->resolveSegment($period, $validated['segment'] ?? null);

        try {
            $absolute = $this->resolveSourceFile($validated['path']);

            $built = $this->service->build($absolute, $period, $segment);

            $result = null;
            if ($commit) {
                $result = $this->service->commit($built['plan'], $period, $segment, $request->user()?->id);
            }

            return response()->json([
                'success'   => true,
                'message'   => $commit
                    ? "Import selesai: {$result['created']} record dibuat, {$result['updated']} record diperbarui."
                    : 'Pratinjau berhasil disusun.',
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
            Log::error('Supervisor breakdown import failed', [
                'period_id' => $period->id,
                'segment'   => $segment,
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
     * Periode split menyimpan satu record per segmen, jadi segmen wajib ada;
     * periode biasa selalu memakai segmen null.
     */
    protected function resolveSegment(PayPeriod $period, ?string $segment): ?string
    {
        if (! $period->is_split) {
            return null;
        }

        return $segment ?: 'A';
    }

    /**
     * Terjemahkan path relatif File Manager menjadi absolute path yang aman.
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
