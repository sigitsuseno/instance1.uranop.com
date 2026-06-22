<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\ThrConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorThrConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ThrConfig::orderBy('min_months')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'min_months' => 'required|integer|min:0',
            'max_months' => 'nullable|integer|gt:min_months',
            'is_prorated' => 'boolean',
            'percentage' => 'required|numeric|min:0',
            'is_active' => 'boolean'
        ]);

        $thr = ThrConfig::create($validated);
        return response()->json(['message' => 'Konfigurasi THR (Supervisor) berhasil dibuat', 'data' => $thr], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'min_months' => 'required|integer|min:0',
            'max_months' => 'nullable|integer|gt:min_months',
            'is_prorated' => 'boolean',
            'percentage' => 'required|numeric|min:0',
            'is_active' => 'boolean'
        ]);

        $thr = ThrConfig::findOrFail($id);
        $thr->update($validated);
        return response()->json(['message' => 'Konfigurasi THR (Supervisor) berhasil diperbarui', 'data' => $thr]);
    }

    public function destroy($id): JsonResponse
    {
        ThrConfig::findOrFail($id)->delete();
        return response()->json(['message' => 'Konfigurasi THR (Supervisor) berhasil dihapus']);
    }
}
