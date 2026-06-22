<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\BpjsConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorBpjsConfigController extends Controller
{
    /** List all configs, latest first */
    public function index(Request $request): JsonResponse
    {
        $query = BpjsConfig::query()->orderBy('effective_date', 'desc');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $configs = $query->paginate($request->integer('per_page', 15));

        return response()->json($configs);
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
            'message' => 'Konfigurasi BPJS (Supervisor) berhasil dibuat.',
            'data'    => $config,
        ], 201);
    }

    /** Update config */
    public function update(Request $request, $id): JsonResponse
    {
        $bpjs_config = BpjsConfig::findOrFail($id);

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
            'message' => 'Konfigurasi BPJS (Supervisor) berhasil diperbarui.',
            'data'    => $bpjs_config->fresh(),
        ]);
    }

    /** Delete config */
    public function destroy($id): JsonResponse
    {
        $bpjs_config = BpjsConfig::findOrFail($id);
        $bpjs_config->delete();

        return response()->json(['message' => 'Konfigurasi BPJS (Supervisor) berhasil dihapus.']);
    }

    /** Activate config */
    public function activate($id): JsonResponse
    {
        $config = BpjsConfig::findOrFail($id);
        $config->update(['is_active' => true, 'updated_by' => auth()->id()]);

        return response()->json(['message' => 'Konfigurasi berhasil diaktifkan.', 'data' => $config]);
    }

    /** Deactivate config */
    public function deactivate($id): JsonResponse
    {
        $config = BpjsConfig::findOrFail($id);
        $config->update(['is_active' => false, 'updated_by' => auth()->id()]);

        return response()->json(['message' => 'Konfigurasi berhasil dinonaktifkan.', 'data' => $config]);
    }
}
