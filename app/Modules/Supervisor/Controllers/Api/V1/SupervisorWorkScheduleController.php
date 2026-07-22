<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Schedule\Models\WorkPattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Supervisor Work Schedule Controller
 * 
 * Mirror dari ScheduleApiController untuk supervisor dashboard.
 * Mengelola WorkPattern (work-schedules) — CRUD + activate/deactivate.
 */
class SupervisorWorkScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WorkPattern::with(['type', 'details'])->orderBy('name');

        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $workPatterns = $query->paginate($request->input('per_page', 15));

        return response()->json($workPatterns);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:sch_work_patterns,code',
            'work_pattern_type_id' => 'required|exists:sch_work_pattern_types,id',
            'is_active' => 'boolean',
        ]);

        $workPattern = WorkPattern::create($validated);

        return response()->json($workPattern, 201);
    }

    public function show(int $id): JsonResponse
    {
        $workPattern = WorkPattern::with(['type', 'details'])->findOrFail($id);

        return response()->json($workPattern);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $workPattern = WorkPattern::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => "sometimes|string|max:50|unique:sch_work_patterns,code,{$id}",
            'work_pattern_type_id' => 'sometimes|exists:sch_work_pattern_types,id',
            'is_active' => 'boolean',
        ]);

        $workPattern->update($validated);

        return response()->json($workPattern);
    }

    public function destroy(int $id): JsonResponse
    {
        $workPattern = WorkPattern::findOrFail($id);
        $workPattern->delete();

        return response()->json(['message' => 'Work schedule deleted.']);
    }

    public function activate(int $id): JsonResponse
    {
        $workPattern = WorkPattern::findOrFail($id);
        $workPattern->update(['is_active' => true]);

        return response()->json(['message' => 'Work schedule activated.']);
    }

    public function deactivate(int $id): JsonResponse
    {
        $workPattern = WorkPattern::findOrFail($id);
        $workPattern->update(['is_active' => false]);

        return response()->json(['message' => 'Work schedule deactivated.']);
    }
}
