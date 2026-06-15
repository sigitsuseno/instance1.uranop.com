<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\BpjsConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BpjsConfigController extends Controller
{
    /** List all configs, latest first */
    public function index(Request $request): JsonResponse
    {
        $query = BpjsConfig::query()->orderBy('effective_date', 'desc');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $configs = $query->paginate($request->integer('per_page', 10));

        return response()->json($configs);
    }

    /** Show single config */
    public function show(BpjsConfig $bpjs_config): JsonResponse
    {
        return response()->json(['data' => $bpjs_config]);
    }

    /** Create new config. If is_active=true, deactivate other configs. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'effective_date'       => 'required|date',
            'is_active'            => 'boolean',
            'jht_employer'         => 'numeric|min:0|max:100',
            'jht_employee'         => 'numeric|min:0|max:100',
            'jkk'                  => 'numeric|min:0|max:100',
            'jkm'                  => 'numeric|min:0|max:100',
            'jp_employer'          => 'numeric|min:0|max:100',
            'jp_employee'          => 'numeric|min:0|max:100',
            'kesehatan_employer'   => 'numeric|min:0|max:100',
            'kesehatan_employee'   => 'numeric|min:0|max:100',
            'max_wage_cap'         => 'nullable|numeric|min:0',
            'description'          => 'nullable|string|max:500',
        ]);

        $data['uuid'] = (string) \Illuminate\Support\Str::uuid();
        $data['created_by'] = auth()->id();

        // Deactivate others if this one is active
        if ($data['is_active'] ?? true) {
            BpjsConfig::where('effective_date', '<=', $data['effective_date'])
                ->update(['is_active' => false]);
        }

        $config = BpjsConfig::create($data);

        return response()->json([
            'message' => 'Konfigurasi BPJS berhasil dibuat.',
            'data'    => $config,
        ], 201);
    }

    /** Update config */
    public function update(Request $request, BpjsConfig $bpjs_config): JsonResponse
    {
        $data = $request->validate([
            'effective_date'       => 'date',
            'is_active'            => 'boolean',
            'jht_employer'         => 'numeric|min:0|max:100',
            'jht_employee'         => 'numeric|min:0|max:100',
            'jkk'                  => 'numeric|min:0|max:100',
            'jkm'                  => 'numeric|min:0|max:100',
            'jp_employer'          => 'numeric|min:0|max:100',
            'jp_employee'          => 'numeric|min:0|max:100',
            'kesehatan_employer'   => 'numeric|min:0|max:100',
            'kesehatan_employee'   => 'numeric|min:0|max:100',
            'max_wage_cap'         => 'nullable|numeric|min:0',
            'description'          => 'nullable|string|max:500',
        ]);

        $data['updated_by'] = auth()->id();
        $bpjs_config->update($data);

        return response()->json([
            'message' => 'Konfigurasi BPJS berhasil diperbarui.',
            'data'    => $bpjs_config->fresh(),
        ]);
    }

    /** Delete config */
    public function destroy(BpjsConfig $bpjs_config): JsonResponse
    {
        $bpjs_config->delete();

        return response()->json(['message' => 'Konfigurasi BPJS berhasil dihapus.']);
    }

    /** Activate config */
    public function activate(BpjsConfig $config): JsonResponse
    {
        $config->update(['is_active' => true, 'updated_by' => auth()->id()]);

        return response()->json(['message' => 'Konfigurasi berhasil diaktifkan.', 'data' => $config]);
    }

    /** Deactivate config */
    public function deactivate(BpjsConfig $config): JsonResponse
    {
        $config->update(['is_active' => false, 'updated_by' => auth()->id()]);

        return response()->json(['message' => 'Konfigurasi berhasil dinonaktifkan.', 'data' => $config]);
    }

    /** Get the currently active config */
    public function active(): JsonResponse
    {
        $config = BpjsConfig::active()
            ->where('effective_date', '<=', now())
            ->orderBy('effective_date', 'desc')
            ->first();

        return response()->json(['data' => $config]);
    }
}
