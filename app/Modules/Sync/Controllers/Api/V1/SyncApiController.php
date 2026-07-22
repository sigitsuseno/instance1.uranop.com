<?php

namespace App\Modules\Sync\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Instance\Models\Instance;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncApiController extends Controller
{
    /**
     * Activate license — called from desktop KonfigurasiScreen.
     * No auth required.
     */
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string|min:4',
        ]);

        $key = $validated['license_key'];

        // Simple validation: must start with "hsk_"
        if (!str_starts_with($key, 'hsk_')) {
            return response()->json([
                'valid' => false,
                'message' => 'License key tidak valid. Format: hsk_...',
            ], 422);
        }

        // Find instance by license key (stored as code or password hash)
        $instance = Instance::first();

        // For now, accept any hsk_ key
        return response()->json([
            'valid' => true,
            'instance_name' => $instance?->name ?? 'HRIS',
            'licensed_until' => now()->addYear()->toDateString(),
        ]);
    }

    /**
     * Check license status — called on startup and before sync.
     * No auth required.
     */
    public function licenseStatus(Request $request): JsonResponse
    {
        $instance = Instance::first();

        return response()->json([
            'status' => 'active',
            'instance_name' => $instance?->name ?? 'HRIS',
            'licensed_until' => now()->addYear()->toDateString(),
        ]);
    }

    /**
     * Sync organization data — flat arrays for desktop bulk upsert.
     * Auth required (Sanctum).
     */
    public function organization(): JsonResponse
    {
        return response()->json([
            'companies' => Company::orderBy('name')->get()->toArray(),
            'branches' => Branch::orderBy('name')->get()->toArray(),
            'departments' => Department::orderBy('name')->get()->toArray(),
            'positions' => Position::orderBy('name')->get()->toArray(),
        ]);
    }

    /**
     * Sync settings data — flat arrays for desktop bulk upsert.
     * Auth required (Sanctum).
     */
    public function settings(): JsonResponse
    {
        return response()->json([
            'settings' => SystemSetting::orderBy('key')->get()->toArray(),
        ]);
    }
}
