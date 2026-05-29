<?php

namespace App\Modules\Employee\Controllers\Api\V1\Termination;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeTermination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TerminationApiController extends Controller
{
    /**
     * GET /api/employees/{employee}/terminations
     */
    public function index(Employee $employee): JsonResponse
    {
        $terminations = $employee->terminations()
            ->with(['approvedBy'])
            ->latest('termination_date')
            ->get();

        return response()->json(['data' => $terminations]);
    }

    /**
     * POST /api/employees/{employee}/terminations
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'termination_type'       => 'required|in:resign,retirement,fired,contract_end,death,other',
            'termination_date'       => 'required|date',
            'effective_date'         => 'required|date',
            'reason'                 => 'nullable|string',
            'settlement_amount'      => 'nullable|numeric|min:0',
            'settlement_notes'       => 'nullable|string',
            'is_eligible_for_rehire' => 'boolean',
            'document_path'          => 'nullable|string',
        ]);

        $data['created_by'] = Auth::id();

        $termination = $employee->terminations()->create($data);

        return response()->json([
            'message' => 'Pengajuan terminasi berhasil dibuat.',
            'data'    => $termination,
        ], 201);
    }

    /**
     * PATCH /api/employees/{employee}/terminations/{termination}/approve
     */
    public function approve(Request $request, Employee $employee, EmployeeTermination $termination): JsonResponse
    {
        abort_if($termination->employee_id !== $employee->id, 404);

        $data = $request->validate([
            'approval_notes'   => 'nullable|string',
            'clearance_asset'  => 'boolean',
            'clearance_finance'=> 'boolean',
            'clearance_it'     => 'boolean',
        ]);

        $termination->update([
            'approval_status'  => 'approved',
            'approved_by'      => Auth::id(),
            'approved_at'      => now(),
            'approval_notes'   => $data['approval_notes'] ?? null,
            'clearance_asset'  => $data['clearance_asset'] ?? false,
            'clearance_finance'=> $data['clearance_finance'] ?? false,
            'clearance_it'     => $data['clearance_it'] ?? false,
        ]);

        return response()->json([
            'message' => "Terminasi karyawan {$employee->name} disetujui. Status karyawan diperbarui.",
            'data'    => $termination->fresh()->load('approvedBy'),
        ]);
    }

    /**
     * PATCH /api/employees/{employee}/terminations/{termination}/reject
     */
    public function reject(Request $request, Employee $employee, EmployeeTermination $termination): JsonResponse
    {
        abort_if($termination->employee_id !== $employee->id, 404);

        $data = $request->validate([
            'approval_notes' => 'required|string',
        ]);

        $termination->update([
            'approval_status' => 'rejected',
            'approved_by'     => Auth::id(),
            'approved_at'     => now(),
            'approval_notes'  => $data['approval_notes'],
        ]);

        return response()->json([
            'message' => 'Terminasi ditolak.',
            'data'    => $termination->fresh(),
        ]);
    }
}
