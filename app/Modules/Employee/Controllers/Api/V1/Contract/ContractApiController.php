<?php

namespace App\Modules\Employee\Controllers\Api\V1\Contract;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
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
}
