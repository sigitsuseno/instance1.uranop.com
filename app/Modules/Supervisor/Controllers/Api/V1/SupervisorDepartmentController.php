<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Resources\DepartmentResource;
use Illuminate\Http\Request;

class SupervisorDepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::with(['parent', 'manager']);
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $departments = $query->paginate($request->input('per_page', 15));
        
        return DepartmentResource::collection($departments);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:departments,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'cost_center' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $department = Department::create($validated);
        
        return new DepartmentResource($department->load(['parent', 'manager']));
    }
    
    public function show($id)
    {
        $department = Department::with(['parent', 'manager', 'children'])->findOrFail($id);
        return new DepartmentResource($department);
    }
    
    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);
        
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:departments,code,' . $department->id,
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'cost_center' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $department->update($validated);
        
        return new DepartmentResource($department->load(['parent', 'manager']));
    }
    
    public function destroy($id)
    {
        $department = Department::findOrFail($id);
        $department->delete();
        
        return response()->json(['message' => 'Department deleted successfully (Supervisor)']);
    }

    public function options()
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json(['data' => $departments]);
    }
}
