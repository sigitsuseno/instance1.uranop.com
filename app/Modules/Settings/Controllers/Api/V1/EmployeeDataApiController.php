<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Settings\Models\EmployeeGroupMaster;

class EmployeeDataApiController extends Controller
{
    // === Employee Group Masters ===
    public function getGroups()
    {
        $groups = EmployeeGroupMaster::latest()->get();
        return response()->json(['data' => $groups]);
    }

    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'group_label' => 'required|string|max:100',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:employee_group_masters',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $group = EmployeeGroupMaster::create($validated);
        return response()->json(['message' => 'Group master created successfully', 'data' => $group], 201);
    }

    public function updateGroup(Request $request, $id)
    {
        $validated = $request->validate([
            'group_label' => 'required|string|max:100',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:employee_group_masters,code,'.$id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $group = EmployeeGroupMaster::findOrFail($id);
        $group->update($validated);

        return response()->json(['message' => 'Group master updated successfully', 'data' => $group]);
    }

    public function destroyGroup($id)
    {
        $group = EmployeeGroupMaster::findOrFail($id);
        $group->delete();

        return response()->json(['message' => 'Group master deleted successfully']);
    }
}
