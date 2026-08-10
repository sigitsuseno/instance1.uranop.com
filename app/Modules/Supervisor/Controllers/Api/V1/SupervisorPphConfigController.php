<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\PphConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorPphConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PphConfig::first()]);
    }

    public function storeOrUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calculation_method' => 'required|in:ter,progressive',
            'pph_method' => 'required|in:gross,gross_up,net',
            'non_npwp_penalty' => 'boolean',
            'non_npwp_multiplier' => 'numeric',
            'nik_as_npwp' => 'boolean',
            'is_dtp' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $config = PphConfig::first();
        if ($config) {
            $config->update($validated);
        } else {
            $validated['uuid'] = (string) \Illuminate\Support\Str::uuid();
            $validated['effective_date'] = now();
            $config = PphConfig::create($validated);
        }
        
        return response()->json(['message' => 'Konfigurasi PPh (Supervisor) diperbarui', 'data' => $config]);
    }
}
