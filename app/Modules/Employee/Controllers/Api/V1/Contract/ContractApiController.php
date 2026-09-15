<?php

namespace App\Modules\Employee\Controllers\Api\V1\Contract;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContractApiController extends Controller
{
    /**
     * GET /api/employees/{employee}/contracts
     */
    public function index(Employee $employee): JsonResponse
    {
        $contracts = $employee->contracts()
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json(['data' => $contracts]);
    }

    /**
     * POST /api/employees/{employee}/contracts
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'contract_number' => 'required|string|max:100|unique:employee_contracts',
            'contract_type'   => 'required|in:pkwt,pkwtt,outsourcing,freelance',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after:start_date',
            'document_path'   => 'nullable|string',
            'notes'           => 'nullable|string',
            'status'          => 'nullable|in:draft,active,expired,terminated',
        ]);

        $data['created_by'] = Auth::id();

        $contract = $employee->contracts()->create($data);

        return response()->json([
            'message' => 'Kontrak berhasil ditambahkan.',
            'data'    => $contract,
        ], 201);
    }

    /**
     * GET /api/employees/{employee}/contracts/{contract}
     */
    public function show(Employee $employee, EmployeeContract $contract): JsonResponse
    {
        abort_if($contract->employee_id !== $employee->id, 404);

        return response()->json(['data' => $contract]);
    }

    /**
     * PUT /api/employees/{employee}/contracts/{contract}
     */
    public function update(Request $request, Employee $employee, EmployeeContract $contract): JsonResponse
    {
        abort_if($contract->employee_id !== $employee->id, 404);

        $data = $request->validate([
            'contract_number' => "required|string|max:100|unique:employee_contracts,contract_number,{$contract->id}",
            'contract_type'   => 'required|in:pkwt,pkwtt,outsourcing,freelance',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after:start_date',
            'document_path'   => 'nullable|string',
            'notes'           => 'nullable|string',
            'status'          => 'nullable|in:draft,active,expired,terminated',
        ]);

        $data['updated_by'] = Auth::id();
        $contract->update($data);

        return response()->json([
            'message' => 'Kontrak berhasil diperbarui.',
            'data'    => $contract->fresh(),
        ]);
    }

    /**
     * GET /api/employees/{employee}/contracts/{contract}/print
     *
     * Render template Perjanjian Kerja Waktu Tertentu (PKWT) siap cetak
     * (mengikuti layout docs/kontrak kerja.pdf — Legal 216mm x 356mm).
     */
    public function printContract(Employee $employee, EmployeeContract $contract): \Illuminate\Http\Response
    {
        abort_if($contract->employee_id !== $employee->id, 404);

        $employee->loadMissing(['department', 'position']);

        $company = Company::with('branches')->first();

        $html = view('employee.contract-print', [
            'employee' => $employee,
            'contract' => $contract,
            'company'  => $company,
            // Kop & penandatangan diambil dari cabang (instance tunggal → cabang pertama).
            'branch'   => $company?->branches->first() ?? Branch::first(),
        ])->render();

        return response($html);
    }

    /**
     * DELETE /api/employees/{employee}/contracts/{contract}
     */
    public function destroy(Employee $employee, EmployeeContract $contract): JsonResponse
    {
        abort_if($contract->employee_id !== $employee->id, 404);
        $contract->delete();

        return response()->json(['message' => 'Kontrak berhasil dihapus.']);
    }

    /**
     * PATCH /api/employees/{employee}/contracts/{contract}/mark-paid
     */
    public function markPaid(Employee $employee, EmployeeContract $contract): JsonResponse
    {
        abort_if($contract->employee_id !== $employee->id, 404);
        $contract->update(['compensation_paid_at' => now()]);

        return response()->json([
            'message' => "Kompensasi kontrak {$contract->contract_number} ditandai sudah dibayar.",
            'data'    => ['compensation_paid_at' => $contract->compensation_paid_at],
        ]);
    }

    /**
     * PATCH /api/employees/{employee}/contracts/{contract}/mark-unpaid
     */
    public function markUnpaid(Employee $employee, EmployeeContract $contract): JsonResponse
    {
        abort_if($contract->employee_id !== $employee->id, 404);
        $contract->update(['compensation_paid_at' => null]);

        return response()->json(['message' => 'Status kompensasi dikembalikan.']);
    }

    /**
     * GET /api/contracts/stats
     */
    public function stats(): JsonResponse
    {
        $now = now();
        $fourteenDaysLater = now()->addDays(14);

        $baseQuery = \App\Modules\Employee\Models\EmployeeContract::where('is_latest', true)
            ->whereHas('employee', function ($q) {
                $q->whereNull('end_date')->whereNull('resign_date');
            });

        $active = (clone $baseQuery)
            ->where('end_date', '>', $fourteenDaysLater->format('Y-m-d'))
            ->count();

        $expiringSoon = (clone $baseQuery)
            ->whereBetween('end_date', [$now->format('Y-m-d'), $fourteenDaysLater->format('Y-m-d')])
            ->count();

        $expired = (clone $baseQuery)
            ->where('end_date', '<', $now->format('Y-m-d'))
            ->count();

        return response()->json([
            'data' => [
                'active' => $active,
                'expiring_soon' => $expiringSoon,
                'expired' => $expired,
            ]
        ]);
    }

    /**
     * POST /api/contracts/import
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120' // max 5MB
        ]);

        $import = new \App\Modules\Employee\Imports\EmployeeContractImport();
        \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

        $result = $import->getResult();

        if (count($result['failed']) > 0) {
            return response()->json([
                'message' => 'Import selesai dengan beberapa error.',
                'stats' => $result['stats'],
                'errors' => $result['failed']
            ], 422);
        }

        return response()->json([
            'message' => 'Import kontrak berhasil.',
            'stats' => $result['stats']
        ]);
    }

    /**
     * GET /api/contracts/export
     *
     * Filter opsional:
     * - is_latest      : hanya kontrak terbaru tiap karyawan (range tanggal diabaikan)
     * - end_date_start : batas awal range end_date (dipakai bila is_latest tidak aktif)
     * - end_date_end   : batas akhir range end_date (dipakai bila is_latest tidak aktif)
     * - only_active    : hanya kontrak milik karyawan yang aktif
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $isLatest = $request->boolean('is_latest');

        $request->validate([
            'end_date_start' => 'nullable|date',
            'end_date_end'   => $isLatest ? 'nullable|date' : 'nullable|date|after_or_equal:end_date_start',
        ]);

        $filters = [
            'search'         => $request->input('search'),
            'employee_id'    => $request->input('employee_id'),
            'contract_type'  => $request->input('contract_type'),
            'status'         => $request->input('status'),
            'is_latest'      => $isLatest,
            'end_date_start' => $isLatest ? null : $request->input('end_date_start'),
            'end_date_end'   => $isLatest ? null : $request->input('end_date_end'),
            'only_active'    => $request->boolean('only_active'),
        ];

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Employee\Exports\EmployeeContractExport($filters),
            'kontrak_kerja_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
