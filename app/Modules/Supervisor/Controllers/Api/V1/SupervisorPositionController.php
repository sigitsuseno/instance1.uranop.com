<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Position;
use App\Modules\Organization\Resources\PositionResource;
use Illuminate\Http\Request;

class SupervisorPositionController extends Controller
{
    public function index(Request $request)
    {
        $query = Position::with(['department', 'reportsTo']);
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $positions = $query->paginate($request->input('per_page', 15));
        
        return PositionResource::collection($positions);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'code' => 'required|string|max:50|unique:positions,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'job_grade' => 'nullable|string|max:20',
            'reports_to_position_id' => 'nullable|exists:positions,id',
            'is_managerial' => 'boolean',
            'is_active' => 'boolean',
            'max_incumbents' => 'integer|min:1',
            'requirements' => 'nullable|string',
        ]);

        $position = Position::create($validated);
        
        return new PositionResource($position->load(['department', 'reportsTo']));
    }
    
    public function show($id)
    {
        $position = Position::with(['department', 'reportsTo', 'subordinates'])->findOrFail($id);
        return new PositionResource($position);
    }
    
    public function update(Request $request, $id)
    {
        $position = Position::findOrFail($id);
        
        $validated = $request->validate([
            'department_id' => 'sometimes|required|exists:departments,id',
            'code' => 'sometimes|required|string|max:50|unique:positions,code,' . $position->id,
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'job_grade' => 'nullable|string|max:20',
            'reports_to_position_id' => 'nullable|exists:positions,id',
            'is_managerial' => 'boolean',
            'is_active' => 'boolean',
            'max_incumbents' => 'integer|min:1',
            'requirements' => 'nullable|string',
        ]);

        $position->update($validated);
        
        return new PositionResource($position->load(['department', 'reportsTo']));
    }
    
    public function destroy($id)
    {
        $position = Position::findOrFail($id);
        $position->delete();
        
        return response()->json(['message' => 'Position deleted successfully (Supervisor)']);
    }

    public function options(Request $request)
    {
        $query = Position::where('is_active', true)->orderBy('name');

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $positions = $query->get(['id', 'name', 'code', 'department_id']);

        return response()->json(['data' => $positions]);
    }
}
