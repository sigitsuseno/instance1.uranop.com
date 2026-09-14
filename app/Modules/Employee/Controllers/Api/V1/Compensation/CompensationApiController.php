<?php

namespace App\Modules\Employee\Controllers\Api\V1\Compensation;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\EmployeeContract;
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

        $compensationService = new \App\Modules\Employee\Services\CompensationPeriodService();

        try {
            $dateInfo = $compensationService->calculateCompensationDates($year, $month);
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
                $employee = $contract->employee;
                $period = sprintf('%d-%02d', $year, $month);

                $durationMonths = (int) ($contract->duration_months ?? 0);
                $nominal = $this->contractNominal($contract, $period);
                $potAdmin = $contract->pot_admin === null ? null : (float) $contract->pot_admin;

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
                    'pot_admin' => $potAdmin,
                    'total_terima' => $nominal - (float) ($potAdmin ?? 0),
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
            'pot_admins'   => ['sometimes', 'array'],
            'pot_admins.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $paymentDate = Carbon::parse($validated['payment_date'])->startOfDay();

        // Simpan potongan admin per kontrak (hanya untuk kontrak yang belum dibayar
        // dan memang termasuk dalam pilihan), sebelum ditandai dibayar.
        $selectedIds = array_map('intval', $validated['ids']);
        foreach ($validated['pot_admins'] ?? [] as $contractId => $value) {
            if (! in_array((int) $contractId, $selectedIds, true)) {
                continue;
            }

            EmployeeContract::where('id', $contractId)
                ->whereNull('compensation_paid_at')
                ->update(['pot_admin' => $value === null ? null : (float) $value]);
        }

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

        $compensationService = new \App\Modules\Employee\Services\CompensationPeriodService();

        try {
            $dateInfo = $compensationService->calculateCompensationDates($year, $month);
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
            new \App\Modules\Employee\Exports\CompensationExport($month, $year, $isLatest, $groups),
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
            $dateInfo = $compensationService->calculateCompensationDates($year, $month);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        $startDate = $dateInfo['start'];
        $endDate = $dateInfo['end'];

        $bulan = Carbon::createFromDate($year, $month, 1)->locale('id')->isoFormat('MMMM YYYY');

        // Nama & alamat kop diambil dari data cabang (tabel branches),
        // sedangkan nama HR / PIC / Pimpinan Cabang diambil dari kolom pic_name.
        $company = Company::with('branches')->first();
        $branch = $company ? $company->branches()->first() : \App\Modules\Organization\Models\Branch::first();
        $hrd = ($branch && $branch->pic_name) ? $branch->pic_name : Auth::user()->name;

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

            // Tj. masa kerja dihitung dinamis dari join_date per periode (konsisten
            // dengan tabel daftar & export kompensasi). Gaji pokok juga diambil dari
            // sumber yang sama dengan daftar/export (employee_salaries), bukan
            // employee_salary_components, supaya nominal slip tidak pernah beda.
            $period = sprintf('%d-%02d', $year, $month);
            $gajiPokok = $employee->gaji_pokok($period);
            $tjMasaKerja = $employee->tjMasaKerja($period);
            $durationMonths = (int) ($contract->duration_months ?? 0);
            $monthlyRate = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0;
            $totalRaw = $durationMonths * $monthlyRate;
            $totalRounded = (float) (ceil($totalRaw / 100) * 100);
            // Selisih ke total yang dibulatkan; dibulatkan ke rupiah terdekat supaya
            // baris "Pblt" + nominal yang tampil = TOTAL (bukan dipotong floor()).
            $pembulatan = (int) round($totalRounded - $totalRaw);
            $potAdmin = $contract->pot_admin === null ? null : (float) $contract->pot_admin;

            $slips[] = [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'start_date' => $contract->start_date?->format('Y-m-d'),
                'end_date' => $contract->end_date?->format('Y-m-d'),
                'compensation_paid_at' => $contract->compensation_paid_at?->format('Y-m-d'),
                'employee_name' => $employee->name,
                'bank_account_number' => $employee->bank_account_number ?? '-',
                'gajiPokok' => $gajiPokok,
                'tjMasaKerja' => $tjMasaKerja,
                'durationMonths' => $durationMonths,
                'monthlyRate' => $monthlyRate,
                'totalRaw' => $totalRaw,
                'totalRounded' => $totalRounded,
                'pembulatan' => $pembulatan,
                'potAdmin' => $potAdmin,
                'totalTerima' => $totalRounded - (float) ($potAdmin ?? 0),
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
                'branch' => $branch ? [
                    'name' => $branch->name ?? '',
                    'address' => $branch->address ?? '',
                    'phone' => $branch->phone ?? '',
                    'email' => $branch->email ?? '',
                    'pic_name' => $branch->pic_name ?? '',
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

    /**
     * PATCH /api/v1/employees/compensation/{contract}/pot-admin
     *
     * Isi/ubah potongan admin per kontrak. Kirim pot_admin = null untuk
     * menghapus potongan (karyawan tersebut tidak punya potongan).
     */
    public function updatePotAdmin(Request $request, EmployeeContract $contract): JsonResponse
    {
        $validated = $request->validate([
            'pot_admin' => ['present', 'nullable', 'numeric', 'min:0'],
        ]);

        $contract->pot_admin = $validated['pot_admin'] === null ? null : (float) $validated['pot_admin'];
        $contract->save();

        $period = $request->filled(['month', 'year'])
            ? sprintf('%d-%02d', $request->query('year'), $request->query('month'))
            : date('Y-m');

        $nominal  = $this->contractNominal($contract, $period);
        $potAdmin = $contract->pot_admin === null ? null : (float) $contract->pot_admin;

        return response()->json([
            'message' => $potAdmin === null
                ? 'Potongan admin dihapus.'
                : 'Potongan admin berhasil disimpan.',
            'data' => [
                'id'           => $contract->id,
                'pot_admin'    => $potAdmin,
                'nominal'      => $nominal,
                'total_terima' => $nominal - (float) ($potAdmin ?? 0),
            ],
        ]);
    }

    /**
     * Nominal kompensasi per kontrak, konsisten dengan tabel daftar & slip:
     * (gaji pokok + tj. masa kerja) / 12 × durasi, dibulatkan ke atas ke ratusan.
     */
    private function contractNominal(EmployeeContract $contract, string $period): float
    {
        $employee = $contract->employee;

        $gajiPokok      = $employee ? $employee->gaji_pokok($period) : 0;
        $tjMasaKerja    = $employee ? $employee->tjMasaKerja($period) : 0;
        $durationMonths = (int) ($contract->duration_months ?? 0);
        $monthlyRate    = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0;

        return (float) (ceil($durationMonths * $monthlyRate / 100) * 100);
    }
}
