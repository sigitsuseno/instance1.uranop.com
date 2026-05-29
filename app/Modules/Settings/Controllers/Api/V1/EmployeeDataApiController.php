<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Settings\Models\EmployeeGroupCategory;
use App\Modules\Settings\Models\EmployeeGroup;

class EmployeeDataApiController extends Controller
{
    // === Group Categories ===
    public function getCategories()
    {
        $categories = EmployeeGroupCategory::latest()->get();
        return response()->json(['data' => $categories]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:employee_group_categories',
            'description' => 'nullable|string',
            'is_multiple_choice' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $category = EmployeeGroupCategory::create($validated);
        return response()->json(['message' => 'Category created successfully', 'data' => $category], 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:employee_group_categories,code,'.$id,
            'description' => 'nullable|string',
            'is_multiple_choice' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $category = EmployeeGroupCategory::findOrFail($id);
        $category->update($validated);

        return response()->json(['message' => 'Category updated successfully', 'data' => $category]);
    }

    public function destroyCategory($id)
    {
        $category = EmployeeGroupCategory::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    // === Groups ===
    public function getGroups()
    {
        $groups = EmployeeGroup::with('category')->latest()->get();
        return response()->json(['data' => $groups]);
    }

    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:employee_group_categories,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:employee_groups',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $group = EmployeeGroup::create($validated);
        return response()->json(['message' => 'Group created successfully', 'data' => $group], 201);
    }

    public function updateGroup(Request $request, $id)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:employee_group_categories,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:employee_groups,code,'.$id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $group = EmployeeGroup::findOrFail($id);
        $group->update($validated);

        return response()->json(['message' => 'Group updated successfully', 'data' => $group]);
    }

    public function destroyGroup($id)
    {
        $group = EmployeeGroup::findOrFail($id);
        $group->delete();

        return response()->json(['message' => 'Group deleted successfully']);
    }
}
