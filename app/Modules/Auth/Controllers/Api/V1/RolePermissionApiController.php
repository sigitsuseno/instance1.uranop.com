<?php

namespace App\Modules\Auth\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionApiController extends Controller
{
    // --- ROLES ---
    public function indexRoles(Request $request)
    {
        $search = $request->query('search');
        
        $query = Role::withCount('permissions');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->query('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate(20));
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'nullable|string'
        ]);

        $validated['guard_name'] = $validated['guard_name'] ?? 'web'; // Default to web guard per user config

        $role = Role::create($validated);
        
        return response()->json(['message' => 'Role created successfully', 'data' => $role], 201);
    }

    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->ignore($role->id),
            ],
            'guard_name' => 'nullable|string'
        ]);

        if ($role->name === 'superadmin' && $validated['name'] !== 'superadmin') {
            return response()->json(['message' => 'Cannot change superadmin role name'], 403);
        }

        $role->update($validated);

        return response()->json(['message' => 'Role updated successfully', 'data' => $role]);
    }

    public function destroyRole($id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'superadmin') {
            return response()->json(['message' => 'Cannot delete superadmin role'], 403);
        }

        $role->delete();
        
        return response()->json(['message' => 'Role deleted successfully']);
    }

    // --- PERMISSIONS ---
    public function indexPermissions(Request $request)
    {
        $search = $request->query('search');
        
        $query = Permission::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->query('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate(50));
    }

    public function storePermission(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'nullable|string'
        ]);

        $validated['guard_name'] = $validated['guard_name'] ?? 'web';

        $permission = Permission::create($validated);
        
        return response()->json(['message' => 'Permission created successfully', 'data' => $permission], 201);
    }

    public function updatePermission(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);
        
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions')->ignore($permission->id),
            ],
            'guard_name' => 'nullable|string'
        ]);

        $permission->update($validated);

        return response()->json(['message' => 'Permission updated successfully', 'data' => $permission]);
    }

    public function destroyPermission($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();
        
        return response()->json(['message' => 'Permission deleted successfully']);
    }
}
