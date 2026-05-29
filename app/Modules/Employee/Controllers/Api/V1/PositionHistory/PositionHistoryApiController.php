<?php

namespace App\Modules\Employee\Controllers\Api\V1\PositionHistory;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PositionHistoryApiController extends Controller
{
    public function index(Employee $employee): JsonResponse
    {
        $histories = $employee->positionHistories()
            ->with(['oldDepartment', 'oldPosition', 'newDepartment', 'newPosition'])
            ->orderBy('effective_date', 'desc')
            ->get();

        return response()->json(['data' => $histories]);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'old_department_id'   => 'nullable|integer|exists:departments,id',
            'old_position_id'     => 'nullable|integer|exists:positions,id',
            'old_salary_grade_id' => 'nullable|integer|exists:salary_grades,id',
            'old_salary'          => 'nullable|numeric|min:0',
            'new_department_id'   => 'nullable|integer|exists:departments,id',
            'new_position_id'     => 'nullable|integer|exists:positions,id',
            'new_salary_grade_id' => 'nullable|integer|exists:salary_grades,id',
            'new_salary'          => 'nullable|numeric|min:0',
            'effective_date'      => 'required|date',
            'change_reason'       => 'nullable|in:initial,promotion,demotion,transfer,rotation,upgrade,restructuring',
            'notes'               => 'nullable|string',
            'document_path'       => 'nullable|string',
        ]);

        $data['created_by'] = Auth::id();

        $history = $employee->positionHistories()->create($data);

        return response()->json([
            'message' => 'Riwayat jabatan berhasil ditambahkan.',
            'data'    => $history->load(['newDepartment', 'newPosition']),
        ], 201);
    }
}
