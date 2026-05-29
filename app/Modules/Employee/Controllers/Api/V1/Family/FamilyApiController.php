<?php

namespace App\Modules\Employee\Controllers\Api\V1\Family;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeFamily;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FamilyApiController extends Controller
{
    public function index(Employee $employee): JsonResponse
    {
        return response()->json([
            'data' => $employee->families()->get(),
        ]);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'relation'             => 'required|in:spouse,child,parent,sibling,other',
            'name'                 => 'required|string|max:200',
            'gender'               => 'required|in:L,P',
            'nik'                  => 'nullable|string|max:50',
            'date_of_birth'        => 'nullable|date',
            'education'            => 'nullable|string|max:100',
            'occupation'           => 'nullable|string|max:100',
            'is_dependent'         => 'boolean',
            'is_emergency_contact' => 'boolean',
            'emergency_phone'      => 'nullable|string|max:50',
        ]);

        $data['created_by'] = Auth::id();

        $family = $employee->families()->create($data);

        return response()->json([
            'message' => 'Data keluarga berhasil ditambahkan.',
            'data'    => $family,
        ], 201);
    }

    public function update(Request $request, Employee $employee, EmployeeFamily $family): JsonResponse
    {
        abort_if($family->employee_id !== $employee->id, 404);

        $data = $request->validate([
            'relation'             => 'required|in:spouse,child,parent,sibling,other',
            'name'                 => 'required|string|max:200',
            'gender'               => 'required|in:L,P',
            'nik'                  => 'nullable|string|max:50',
            'date_of_birth'        => 'nullable|date',
            'education'            => 'nullable|string|max:100',
            'occupation'           => 'nullable|string|max:100',
            'is_dependent'         => 'boolean',
            'is_emergency_contact' => 'boolean',
            'emergency_phone'      => 'nullable|string|max:50',
        ]);

        $data['updated_by'] = Auth::id();
        $family->update($data);

        return response()->json([
            'message' => 'Data keluarga berhasil diperbarui.',
            'data'    => $family->fresh(),
        ]);
    }

    public function destroy(Employee $employee, EmployeeFamily $family): JsonResponse
    {
        abort_if($family->employee_id !== $employee->id, 404);
        $family->delete();

        return response()->json(['message' => 'Data keluarga berhasil dihapus.']);
    }
}
