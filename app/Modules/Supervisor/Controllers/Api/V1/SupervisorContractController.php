<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupervisorContractController extends Controller
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
     * POST /api/employees/contracts/bulk-type
     * Bulk update contract_type (latest contract) + employment_status.
     * Superadmin only (route middleware role:superadmin).
     * Mapping: pkwt -> employment_status=contract, pkwtt -> employment_status=permanent.
     */
    public function bulkUpdateType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_ids'   => 'required|array|min:1|max:200',
            'employee_ids.*' => 'integer|distinct|exists:employees,id',
            'target'         => 'required|in:pkwt,pkwtt',
        ]);

        $contractType = $data['target'];
        $employmentStatus = $contractType === 'pkwt' ? 'contract' : 'permanent';

        $updatedEmployees = 0;
        $updatedContracts = 0;
        $skippedNoContract = 0;

        DB::transaction(function () use ($data, $contractType, $employmentStatus, &$updatedEmployees, &$updatedContracts, &$skippedNoContract) {
            $employees = Employee::whereIn('id', $data['employee_ids'])
                ->where('is_active', true)
                ->with('latestContract')
                ->get();

            foreach ($employees as $employee) {
                // Hanya ubah kolom employment_status di tabel employees
                if ($employee->employment_status !== $employmentStatus) {
                    $employee->employment_status = $employmentStatus;
                    $employee->save();
                }
                $updatedEmployees++;

                // Hanya ubah kolom contract_type di kontrak terakhir (jika ada)
                $latest = $employee->latestContract;
                if ($latest) {
                    if ($latest->contract_type !== $contractType) {
                        $latest->contract_type = $contractType;
                        $latest->save();
                    }
                    $updatedContracts++;
                } else {
                    $skippedNoContract++;
                }
            }
        });

        return response()->json([
            'message' => 'Bulk update tipe kontrak berhasil.',
            'data'    => [
                'target'              => $contractType,
                'employment_status'   => $employmentStatus,
                'updated_employees'   => $updatedEmployees,
                'updated_contracts'   => $updatedContracts,
                'skipped_no_contract' => $skippedNoContract,
            ],
        ]);
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
}
