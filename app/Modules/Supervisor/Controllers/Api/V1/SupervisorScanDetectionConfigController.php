<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\OvertimeCalculatorConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorScanDetectionConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => OvertimeCalculatorConfig::with('workPattern')->get()
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'work_pattern_id' => 'nullable|exists:sch_work_patterns,id',
            'normal_work_minutes' => 'integer|min:0',
            'saturday_work_minutes' => 'integer|min:0',
            'holiday_max_minutes' => 'integer|min:0',
            'shift_saturday_flat' => 'integer|min:0',
            'late_deducts_overtime' => 'boolean',
            'late_tolerance' => 'integer|min:0',
            'lm_rest_deduction' => 'integer|min:0',
            'rounding_interval' => 'integer|min:1',
            'rounding_threshold' => 'integer|min:0',
            'hourly_divisor' => 'integer|min:1',
            'description' => 'nullable|string',
        ]);

        $validated['uuid'] = (string) \Illuminate\Support\Str::uuid();
        $validated['is_active'] = true;

        $config = OvertimeCalculatorConfig::create($validated);

        return response()->json([
            'message' => 'Konfigurasi Deteksi Jam Kerja dibuat (Supervisor)',
            'data' => $config->load('workPattern'),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $config = OvertimeCalculatorConfig::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'work_pattern_id' => 'nullable|exists:sch_work_patterns,id',
            'normal_work_minutes' => 'integer|min:0',
            'saturday_work_minutes' => 'integer|min:0',
            'holiday_max_minutes' => 'integer|min:0',
            'shift_saturday_flat' => 'integer|min:0',
            'late_deducts_overtime' => 'boolean',
            'late_tolerance' => 'integer|min:0',
            'lm_rest_deduction' => 'integer|min:0',
            'rounding_interval' => 'integer|min:1',
            'rounding_threshold' => 'integer|min:0',
            'hourly_divisor' => 'integer|min:1',
            'description' => 'nullable|string',
        ]);

        $config->update($validated);

        return response()->json([
            'message' => 'Konfigurasi Deteksi Jam Kerja diupdate (Supervisor)',
            'data' => $config->load('workPattern'),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        OvertimeCalculatorConfig::findOrFail($id)->delete();
        return response()->json(['message' => 'Konfigurasi Deteksi Jam Kerja dihapus (Supervisor)']);
    }
}
