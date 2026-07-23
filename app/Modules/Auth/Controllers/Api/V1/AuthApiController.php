<?php

namespace App\Modules\Auth\Controllers\Api\V1;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Resources\AuthResource;
use App\Modules\Employee\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    /**
     * Web/Desktop login — hanya untuk HR/Admin.
     * Karyawan tanpa role admin akan ditolak.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak valid.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda dinonaktifkan. Hubungi administrator.'],
            ]);
        }

        // === GATE: karyawan murni (punya employee record tapi nggak punya role admin) ditolak ===
        $adminRoles = ['superadmin', 'hrmanager', 'adm_manager', 'hrbranch', 'hr_ast', 'manajemen'];
        $hasAdminRole = $user->hasAnyRole($adminRoles);

        if (! $hasAdminRole) {
            throw ValidationException::withMessages([
                'email' => ['Akses ditolak. Gunakan aplikasi mobile untuk login.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'user' => new AuthResource($user->load('roles', 'permissions')),
        ]);
    }

    /**
     * Mobile login — khusus karyawan (harus punya employee record).
     * Return user + employee data.
     */
    public function mobileLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak valid.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda dinonaktifkan. Hubungi administrator.'],
            ]);
        }

        // === GATE: harus punya employee record ===
        $employee = $user->employee;
        if (! $employee) {
            throw ValidationException::withMessages([
                'email' => ['Akun ini bukan akun karyawan. Gunakan aplikasi desktop.'],
            ]);
        }

        if (! $employee->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun karyawan Anda sudah tidak aktif. Hubungi HR.'],
            ]);
        }

        $token = $user->createToken('mobile-token')->plainTextToken;

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'nip' => $employee->nip,
                'nik' => $employee->nik,
                'employee_code' => $employee->employee_code,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'photo_url' => $employee->photo_url,
                'join_date' => $employee->join_date?->format('Y-m-d'),
                'employment_status' => $employee->employment_status_label,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    public function user(Request $request): AuthResource
    {
        return new AuthResource($request->user()->load('roles', 'permissions'));
    }

    /**
     * List all users for desktop sync.
     * Returns flat array of users with roles.
     */
    public function listUsers(): JsonResponse
    {
        $users = User::with('roles')
            ->select(['id', 'name', 'email', 'is_active', 'created_at', 'updated_at'])
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? 'user',
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toDateTimeString(),
                'updated_at' => $user->updated_at?->toDateTimeString(),
            ]);

        return response()->json($users);
    }
}
