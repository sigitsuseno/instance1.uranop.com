<?php

namespace App\Modules\Organization\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use App\Modules\Organization\Resources\DepartmentResource;
use App\Modules\Organization\Resources\PositionResource;
use Illuminate\Http\Request;

class OrganizationApiController extends Controller
{
    // --- Departments ---
    
    public function indexDepartments(Request $request)
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
    
    public function storeDepartment(Request $request)
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
    
    public function showDepartment($id)
    {
        $department = Department::with(['parent', 'manager', 'children'])->findOrFail($id);
        return new DepartmentResource($department);
    }
    
    public function updateDepartment(Request $request, $id)
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
    
    public function destroyDepartment($id)
    {
        $department = Department::findOrFail($id);
        $department->delete();
        
        return response()->json(['message' => 'Department deleted successfully']);
    }
    
    // --- Positions ---
    
    public function indexPositions(Request $request)
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
    
    public function storePosition(Request $request)
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
    
    public function showPosition($id)
    {
        $position = Position::with(['department', 'reportsTo', 'subordinates'])->findOrFail($id);
        return new PositionResource($position);
    }
    
    public function updatePosition(Request $request, $id)
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
    
    public function destroyPosition($id)
    {
        $position = Position::findOrFail($id);
        $position->delete();
        
        return response()->json(['message' => 'Position deleted successfully']);
    }

    // --- Dropdown Options (ringan, tanpa pagination) ---

    public function optionsDepartments()
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json(['data' => $departments]);
    }

    public function optionsPositions(Request $request)
    {
        $query = Position::where('is_active', true)->orderBy('name');

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        return response()->json([
            'data' => $query->get(['id', 'name', 'code', 'department_id']),
        ]);
    }
}
