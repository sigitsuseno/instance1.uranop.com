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
        $contracts = EmployeeContract::with(['employee'])
            ->whereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('end_date', 'asc')
            ->get()
            ->map(function ($contract) {
                // Return necessary fields
                $employee = current($contract->employee()->getModels());
                $baseSalary = $employee ? $employee->baseSalary() : 0;
                
                return [
                    'id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'contract_type' => $contract->contract_type,
                    'start_date' => $contract->start_date,
                    'end_date' => $contract->end_date,
                    'duration_months' => $contract->duration_months,
                    'is_compensation_paid' => $contract->is_compensation_paid,
                    'compensation_paid_at' => $contract->compensation_paid_at,
                    'employee' => [
                        'name' => $employee?->name,
                        'employee_code' => $employee?->employee_code,
                        'department' => $employee?->department?->name,
                        'base_salary' => $baseSalary
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
     * PATCH /api/v1/employees/compensation/{contract}/mark-paid
     */
    public function markPaid(EmployeeContract $contract): JsonResponse
    {
        $contract->update(['compensation_paid_at' => now()]);

        return response()->json([
            'message' => "Kompensasi kontrak {$contract->contract_number} ditandai sudah dibayar.",
            'data'    => ['compensation_paid_at' => $contract->compensation_paid_at],
        ]);
    }

    /**
     * PATCH /api/v1/employees/compensation/{contract}/mark-unpaid
     */
    public function markUnpaid(EmployeeContract $contract): JsonResponse
    {
        $contract->update(['compensation_paid_at' => null]);

        return response()->json(['message' => 'Status kompensasi dikembalikan.']);
    }

    /**
     * POST /api/v1/employees/compensation/bulk-mark-paid
     */
    public function bulkMarkPaid(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return response()->json(['message' => 'Pilih minimal satu kontrak.'], 400);
        }

        $updated = EmployeeContract::whereIn('id', $ids)
            ->whereNull('compensation_paid_at')
            ->update(['compensation_paid_at' => now()]);

        return response()->json([
            'message' => "$updated kontrak berhasil ditandai sudah dibayar.",
            'data'    => ['updated' => $updated],
        ]);
    }

    /**
     * GET /api/v1/employees/compensation/export
     */
    public function export(Request $request)
    {
        $month = $request->query('month', date('n'));
        $year = $request->query('year', date('Y'));
        $periode = $request->query('periode', 'auto');
        
        $fileName = "kompensasi_{$year}_{$month}.xlsx";

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Employee\Exports\CompensationExport($month, $year, $periode), 
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

        $contracts = EmployeeContract::with(['employee'])
            ->whereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
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
