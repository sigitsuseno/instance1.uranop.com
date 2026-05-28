<?php

namespace App\Modules\Auth\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminManagementApiController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        
        $query = User::with(['roles', 'permissions']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $admins = $query->paginate(15);
        return response()->json($admins);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'is_active' => 'boolean',
            'user_type' => 'nullable|string',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $user = User::create($validated);

        return response()->json(['message' => 'Admin created successfully', 'data' => $user], 201);
    }

    public function show(User $admin)
    {
        $admin->load(['roles', 'permissions']);
        return response()->json($admin);
    }

    public function update(Request $request, User $admin)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($admin->id),
            ],
            'password' => 'nullable|string|min:8',
            'is_active' => 'boolean',
            'user_type' => 'nullable|string',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $admin->update($validated);

        return response()->json(['message' => 'Admin updated successfully', 'data' => $admin]);
    }

    public function destroy(User $admin)
    {
        // Protect superadmin from deletion
        if ($admin->hasRole('superadmin')) {
            return response()->json(['message' => 'Cannot delete a superadmin'], 403);
        }

        $admin->delete();
        return response()->json(['message' => 'Admin deleted successfully']);
    }

    public function syncRoles(Request $request, User $admin)
    {
        $validated = $request->validate([
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Sync Roles
        if (isset($validated['roles'])) {
            $admin->syncRoles($validated['roles']);
        }

        // Sync Direct Permissions
        if (isset($validated['permissions'])) {
            $admin->syncPermissions($validated['permissions']);
        }

        $admin->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'Roles and permissions synced successfully',
            'data' => $admin
        ]);
    }
}
