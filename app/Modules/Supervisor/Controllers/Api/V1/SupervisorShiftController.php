<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Schedule\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Supervisor Shift Controller
 * 
 * Mirror dari ScheduleApiController untuk supervisor dashboard.
 * Mengelola Shift — CRUD.
 */
class SupervisorShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Shift::orderBy('name');

        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $shifts = $query->paginate($request->input('per_page', 15));

        return response()->json($shifts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:sch_shifts,code',
            'work_hour_start' => 'nullable|date_format:H:i',
            'work_hour_end' => 'nullable|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'is_active' => 'boolean',
        ]);

        $shift = Shift::create($validated);

        return response()->json($shift, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $shift = Shift::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => "sometimes|string|max:50|unique:sch_shifts,code,{$id}",
            'work_hour_start' => 'nullable|date_format:H:i',
            'work_hour_end' => 'nullable|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'is_active' => 'boolean',
        ]);

        $shift->update($validated);

        return response()->json($shift);
    }

    public function destroy(int $id): JsonResponse
    {
        $shift = Shift::findOrFail($id);
        $shift->delete();

        return response()->json(['message' => 'Shift deleted.']);
    }
}
