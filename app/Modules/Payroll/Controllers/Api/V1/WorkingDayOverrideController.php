<?php

namespace App\Modules\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Payroll\Models\PayWorkingDayOverride;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pengaturan khusus hari_kerja per karyawan per periode.
 *
 * Nilai hari_kerja di sini menimpa hasil hitung otomatis saat tombol Finalisasi
 * ditekan (lihat GajiKaryawanController::finalisasi()). deduct_day/pot_kehadiran
 * ikut menyesuaikan: max(0, hk_segmen - hari_kerja).
 */
class WorkingDayOverrideController extends Controller
{
    /**
     * GET /api/v1/payroll/working-day-overrides?period_id=X
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);

        // Segmen karyawan (hanya relevan di periode split) → untuk hitung deduct_day
        $segments = PayRecord::where('pay_period_id', $period->id)
            ->pluck('segment', 'employee_id');

        $data = PayWorkingDayOverride::with('employee')
            ->forPeriod($period->id)
            ->join('employees', 'pay_working_day_overrides.employee_id', '=', 'employees.id')
            ->orderByRaw('employees.no_urut IS NULL, employees.no_urut ASC')
            ->orderBy('employees.nip')
            ->select('pay_working_day_overrides.*')
            ->get()
            ->map(function ($o) use ($period, $segments) {
                $segment = $segments[$o->employee_id] ?? null;
                $hkSegment = $this->hkSegment($period, $segment);

                return [
                    'id'            => $o->id,
                    'employee_id'   => $o->employee_id,
                    'employee_code' => $o->employee?->employee_code ?? $o->employee?->nip ?? '-',
                    'name'          => $o->employee?->name ?? '-',
                    'segment'       => $segment,
                    'hari_kerja'    => (int) $o->hari_kerja,
                    // Potongan kehadiran turunan: hk segmen − hari_kerja (tidak pernah negatif)
                    'deduct_day'    => max(0, $hkSegment - (int) $o->hari_kerja),
                ];
            })
            ->values();

        return response()->json([
            'data'              => $data,
            'period_id'         => $period->id,
            'is_split'          => (bool) $period->is_split,
            'fixed_working_day' => $this->fixedWorkingDay(),
        ]);
    }

    /**
     * POST /api/v1/payroll/working-day-overrides
     * Body: { period_id, employee_ids: [], hari_kerja }
     * Karyawan yang sudah ada di-update (bukan duplikat).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id'     => 'required|exists:pay_periods,id',
            'employee_ids'  => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'hari_kerja'    => 'required|integer|min:0|max:31',
        ]);

        $saved = 0;

        foreach ($validated['employee_ids'] as $employeeId) {
            PayWorkingDayOverride::updateOrCreate(
                [
                    'employee_id'   => $employeeId,
                    'pay_period_id' => $validated['period_id'],
                ],
                [
                    'hari_kerja' => $validated['hari_kerja'],
                ]
            );
            $saved++;
        }

        return response()->json([
            'message' => "Pengaturan khusus disimpan untuk {$saved} karyawan. Klik Finalisasi untuk menerapkan.",
            'saved'   => $saved,
        ]);
    }

    /**
     * DELETE /api/v1/payroll/working-day-overrides/{id}
     * Karyawan kembali ke hitungan hari_kerja otomatis pada finalisasi berikutnya.
     */
    public function destroy(int $id): JsonResponse
    {
        $override = PayWorkingDayOverride::findOrFail($id);
        $override->delete();

        return response()->json([
            'message' => 'Pengaturan khusus dihapus. Karyawan kembali ke hitungan otomatis.',
        ]);
    }

    // =====================================================================
    // Helper — konsisten dengan GajiKaryawanController::finalisasi()
    // =====================================================================

    /** fixed_working_day dari SystemSetting payroll_config (default 25). */
    private function fixedWorkingDay(): int
    {
        return (int) (SystemSetting::where('key', 'payroll_config')->first()?->fixed_working_day ?? 25);
    }

    /** Hari kerja segmen: non-split = fixed_working_day; split = konfigurasi A/B. */
    private function hkSegment(PayPeriod $period, ?string $segment): int
    {
        $fixedDays = $this->fixedWorkingDay();

        if (! $period->is_split) {
            return $fixedDays;
        }

        $raw = SystemSetting::where('key', 'payroll_config')->first()?->value;
        $splitDays = json_decode($raw ?? '{}', true) ?? [];

        if ($segment === 'A') {
            return (int) ($splitDays['A'] ?? 5);
        }

        if ($segment === 'B') {
            return (int) ($splitDays['B'] ?? max(0, $fixedDays - (int) ($splitDays['A'] ?? 5)));
        }

        // Segmen belum diketahui (belum ada pay_records) — pakai total periode
        return $fixedDays;
    }
}
