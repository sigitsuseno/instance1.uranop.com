<?php

namespace App\Modules\Employee\Controllers\Api\V1\Compensation;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\EmployeeContract;
use App\Modules\Employee\Models\EmployeeSalaryComponent;
use App\Modules\Organization\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompensationApiController extends Controller
{
    /**
     * GET /api/v1/employees/compensation
     */
    public function index(Request $request): JsonResponse
    {
        $month = $request->query('month', date('n'));
        $year = $request->query('year', date('Y'));
        $periode = $request->query('periode', 'auto');

        $compensationService = new \App\Modules\Employee\Services\CompensationPeriodService();

        try {
            $dateInfo = $compensationService->calculateCompensationDates($year, $month, $periode);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [
                    'period' => null,
                    'contracts' => []
                ],
                'message' => $e->getMessage()
            ], 400);
        }

        $startDate = $dateInfo['start'];
        $endDate = $dateInfo['end'];
        $label = $dateInfo['label'];

        // Get contracts that fall into this period (e.g. expiring in this period)
        $query = EmployeeContract::with(['employee'])
            ->whereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

        // Filter opsional: hanya tampilkan kontrak terakhir (is_latest = true)
        if ($request->boolean('is_latest')) {
            $query->where('is_latest', true);
        }

        $contracts = $query
            ->orderBy('end_date', 'asc')
            ->get()
            ->map(function ($contract) use ($year, $month) {
                // Return necessary fields
                $employee = current($contract->employee()->getModels());
                $period = sprintf('%d-%02d', $year, $month);

                // Nominal kompensasi, konsisten dengan export/slip:
                // (gaji pokok + tj. masa kerja) / 12 × durasi, dibulatkan ke atas.
                $gajiPokok = $employee ? $employee->gaji_pokok($period) : 0;
                $tjMasaKerja = $employee ? $employee->tjMasaKerja($period) : 0;
                $durationMonths = (int) ($contract->duration_months ?? 0);
                $monthlyRate = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0;
                $nominal = (float) (ceil($durationMonths * $monthlyRate / 100) * 100);

                return [
                    'id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'contract_type' => $contract->contract_type,
                    'start_date' => $contract->start_date,
                    'end_date' => $contract->end_date,
                    'duration_months' => $durationMonths,
                    'is_compensation_paid' => $contract->is_compensation_paid,
                    'compensation_paid_at' => $contract->compensation_paid_at,
                    'comp_group' => $contract->comp_group,
                    'nominal' => $nominal,
                    'employee' => [
                        'name' => $employee?->name,
                        'employee_code' => $employee?->employee_code,
                        'department' => $employee?->department?->name,
                        'base_salary' => $employee ? $employee->baseSalary() : 0,
                    ]
                ];
            });

        return response()->json([
            'data' => [
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'label' => $label,
                ],
                'contracts' => $contracts
            ]
        ]);
    }

    /**
     * POST /api/v1/employees/compensation/create-group
     *
     * Kelompokkan kontrak terpilih ke dalam satu group laporan kompensasi,
     * sekaligus menandainya sudah dibayar dengan tanggal pembayaran tertentu.
     */
    public function createGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'          => ['required', 'array', 'min:1'],
            'ids.*'        => ['integer'],
            'group_name'   => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
        ]);

        $paymentDate = Carbon::parse($validated['payment_date'])->startOfDay();

        $updated = EmployeeContract::whereIn('id', $validated['ids'])
            ->whereNull('compensation_paid_at')
            ->update([
                'comp_group'            => $validated['group_name'],
                'compensation_paid_at'  => $paymentDate,
            ]);

        return response()->json([
            'message' => $updated > 0
                ? "{$updated} kontrak berhasil digabung ke group '{$validated['group_name']}' dan ditandai dibayar."
                : 'Tidak ada kontrak yang diperbarui (mungkin sudah dibayar sebelumnya).',
            'data'    => ['updated' => $updated],
        ]);
    }

    /**
     * POST /api/v1/employees/compensation/delete-group
     *
     * Hapus satu group kompensasi: null-kan comp_group + compensation_paid_at
     * untuk SEMUA kontrak anggota group tersebut (reset ke status belum dibayar).
     */
    public function deleteGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_name' => ['required', 'string', 'max:255'],
        ]);

        $updated = EmployeeContract::where('comp_group', $validated['group_name'])
            ->update([
                'comp_group'           => null,
                'compensation_paid_at' => null,
            ]);

        return response()->json([
            'message' => $updated > 0
                ? "Group '{$validated['group_name']}' berhasil dihapus ({$updated} kontrak dikembalikan ke belum dibayar)."
                : "Tidak ada kontrak di group '{$validated['group_name']}'.",
            'data'    => ['updated' => $updated],
        ]);
    }

    /**
     * GET /api/v1/employees/compensation/export-groups
     *
     * Daftar group kompensasi (comp_group + compensation_paid_at + jumlah kontrak)
     * yang pembayarannya jatuh dalam rentang start_date s/d end_date + 7 dari periode terpilih.
     */
    public function exportGroups(Request $request): JsonResponse
    {
        $month = $request->query('month', date('n'));
        $year = $request->query('year', date('Y'));
        $periode = $request->query('periode', 'auto');

        $compensationService = new \App\Modules\Employee\Services\CompensationPeriodService();

        try {
            $dateInfo = $compensationService->calculateCompensationDates($year, $month, $periode);
        } catch (\Exception $e) {
            return response()->json([
                'data' => ['period' => null, 'groups' => []],
                'message' => $e->getMessage(),
            ], 400);
        }

        $startDate = $dateInfo['start'];
        $endDatePlus7 = $dateInfo['end']->copy()->addDays(7);

        $groups = EmployeeContract::whereNotNull('comp_group')
            ->whereNotNull('compensation_paid_at')
            ->whereBetween('compensation_paid_at', [
                $startDate->format('Y-m-d'),
                $endDatePlus7->format('Y-m-d'),
            ])
            ->get(['comp_group', 'compensation_paid_at'])
            ->groupBy('comp_group')
            ->map(function ($items, $groupName) {
                return [
                    'comp_group'            => $groupName,
                    'compensation_paid_at'  => $items->max('compensation_paid_at')->format('Y-m-d'),
                    'total_contracts'       => $items->count(),
                ];
            })
            ->sortByDesc('compensation_paid_at')
            ->values();

        return response()->json([
            'data' => [
                'period' => [
                    'start'      => $startDate->format('Y-m-d'),
                    'end'        => $dateInfo['end']->format('Y-m-d'),
                    'end_plus_7' => $endDatePlus7->format('Y-m-d'),
                    'label'      => $dateInfo['label'],
                ],
                'groups' => $groups,
            ],
        ]);
    }

    /**
     * GET /api/v1/employees/compensation/export
     *
     * Parameter opsional `groups`: daftar nama group (dipisah koma) untuk membatasi
     * kontrak yang diekspor hanya milik group-group tersebut.
     */
    public function export(Request $request)
    {
        $month = $request->query('month', date('n'));
        $year = $request->query('year', date('Y'));
        $periode = $request->query('periode', 'auto');
        $isLatest = $request->boolean('is_latest');

        $groupsRaw = $request->query('groups');
        if (is_array($groupsRaw)) {
            $groups = collect($groupsRaw)->filter()->map('trim')->values()->all();
        } elseif ($groupsRaw) {
            $groups = collect(explode(',', (string) $groupsRaw))->filter()->map('trim')->values()->all();
        } else {
            $groups = [];
        }

        $fileName = "kompensasi_{$year}_{$month}.xlsx";

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Employee\Exports\CompensationExport($month, $year, $periode, $isLatest, $groups),
            $fileName
        );
    }

    /**
     * GET /api/v1/employees/compensation/print
     * Returns slip data for bulk printing (F4 format, 6 slips per page)
     */
    public function print(Request $request): JsonResponse
    {
        $month = $request->query('month', date('n'));
        $year = $request->query('year', date('Y'));
        $periode = $request->query('periode', 'auto');

        $groupsRaw = $request->query('groups');
        if (is_array($groupsRaw)) {
            $groups = collect($groupsRaw)->filter()->map('trim')->values()->all();
        } elseif ($groupsRaw) {
            $groups = collect(explode(',', (string) $groupsRaw))->filter()->map('trim')->values()->all();
        } else {
            $groups = [];
        }

        $compensationService = new \App\Modules\Employee\Services\CompensationPeriodService();

        try {
            $dateInfo = $compensationService->calculateCompensationDates($year, $month, $periode);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        $startDate = $dateInfo['start'];
        $endDate = $dateInfo['end'];

        $bulan = Carbon::createFromDate($year, $month, 1)->locale('id')->isoFormat('MMMM YYYY');
        $hrd = Auth::user()->name;

        // Get the company
        $company = Company::first();

        $query = EmployeeContract::with(['employee']);

        if (! empty($groups)) {
            // Print per group: ambil SEMUA kontrak anggota group yang sudah dibayar
            // dalam rentang pembayaran (konsisten dengan export-groups),
            // tanpa memotong berdasarkan end_date atau is_latest lagi.
            $query->whereIn('comp_group', $groups)
                ->whereNotNull('compensation_paid_at')
                ->whereBetween('compensation_paid_at', [
                    $startDate->format('Y-m-d'),
                    $endDate->copy()->addDays(7)->format('Y-m-d'),
                ]);
        } else {
            $query->whereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            // Filter opsional: hanya tampilkan kontrak terakhir (is_latest = true)
            if ($request->boolean('is_latest')) {
                $query->where('is_latest', true);
            }
        }

        $contracts = $query
            ->orderBy('end_date', 'asc')
            ->get();

        $slips = [];
        foreach ($contracts as $contract) {
            $employee = $contract->employee;
            if (!$employee) continue;

            // Get salary component data
            $salaryData = EmployeeSalaryComponent::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->latest('effective_date')
                ->first();

            $gajiPokok = $salaryData ? (float) $salaryData->gaji_pokok : (float) ($employee->base_salary ?? 0);
            $tjMasaKerja = $salaryData ? (float) $salaryData->tunjangan_masa_kerja : 0;
            $durationMonths = (int) ($contract->duration_months ?? 0);
            $monthlyRate = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0;
            $totalRaw = $durationMonths * $monthlyRate;
            $totalRounded = (float) (ceil($totalRaw / 100) * 100);
            $pembulatan = (int) floor($totalRounded - $totalRaw);

            $slips[] = [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'start_date' => $contract->start_date?->format('Y-m-d'),
                'end_date' => $contract->end_date?->format('Y-m-d'),
                'employee_name' => $employee->name,
                'bank_account_number' => $employee->bank_account_number ?? '-',
                'gajiPokok' => $gajiPokok,
                'tjMasaKerja' => $tjMasaKerja,
                'durationMonths' => $durationMonths,
                'monthlyRate' => $monthlyRate,
                'totalRaw' => $totalRaw,
                'totalRounded' => $totalRounded,
                'pembulatan' => $pembulatan,
            ];
        }

        return response()->json([
            'data' => [
                'company' => $company ? [
                    'name' => $company->name,
                    'address' => $company->address ?? '',
                    'phone' => $company->phone ?? '',
                    'email' => $company->email ?? '',
                    'website' => $company->website ?? '',
                    'logo_url' => $company->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($company->logo_path) : null,
                ] : null,
                'bulan' => $bulan,
                'hrd' => $hrd,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'slips' => $slips,
            ]
        ]);
    }
}
