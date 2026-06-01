<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use App\Modules\Settings\Models\EmployeeGroupSetting;

class EmployeeDataApiController extends Controller
{
    // === Employee Group Settings ===
    public function getGroupSettings()
    {
        $settings = EmployeeGroupSetting::orderBy('sort_order')->get();
        return response()->json(['data' => $settings]);
    }

    public function storeGroupSetting(Request $request)
    {
        $validated = $request->validate([
            'tab_id' => 'required|string|max:100|unique:employee_group_settings,tab_id',
            'tab_name' => 'required|string|max:100',
            'group_label' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:100',
            'filters' => 'nullable|array',
            'sort_order' => 'integer',
            'is_active' => 'boolean'
        ]);

        $setting = EmployeeGroupSetting::create($validated);
        return response()->json(['message' => 'Group setting created successfully', 'data' => $setting], 201);
    }

    public function updateGroupSetting(Request $request, $id)
    {
        $validated = $request->validate([
            'tab_id' => 'required|string|max:100|unique:employee_group_settings,tab_id,'.$id,
            'tab_name' => 'required|string|max:100',
            'group_label' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:100',
            'filters' => 'nullable|array',
            'sort_order' => 'integer',
            'is_active' => 'boolean'
        ]);

        $setting = EmployeeGroupSetting::findOrFail($id);
        $setting->update($validated);

        return response()->json(['message' => 'Group setting updated successfully', 'data' => $setting]);
    }

    public function destroyGroupSetting($id)
    {
        $setting = EmployeeGroupSetting::findOrFail($id);
        $setting->delete();

        return response()->json(['message' => 'Group setting deleted successfully']);
    }

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
