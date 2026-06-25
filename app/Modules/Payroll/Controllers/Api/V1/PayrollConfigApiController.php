<?php

namespace App\Modules\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayrollConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollConfigApiController extends Controller
{
    /**
     * GET /api/v1/payroll/configs/{type}
     */
    public function show(string $type): JsonResponse
    {
        $config = PayrollConfig::byType($type)->first();

        return response()->json([
            'config_type' => $type,
            'config' => $config?->config ?? PayrollConfig::getDefault($type),
            'updated_by' => $config?->updatedBy?->name,
            'updated_at' => $config?->updated_at?->toDateTimeString(),
        ]);
    }

    /**
     * PUT /api/v1/payroll/configs/{type}
     */
    public function update(Request $request, string $type): JsonResponse
    {
        $validated = $request->validate([
            'config' => 'required|array',
        ]);

        $config = PayrollConfig::updateOrCreate(
            ['config_type' => $type],
            [
                'config' => $validated['config'],
                'updated_by' => auth()->id(),
            ]
        );

        return response()->json([
            'message' => 'Konfigurasi berhasil disimpan.',
            'config_type' => $type,
            'config' => $config->config,
            'updated_by' => $config->updatedBy?->name,
            'updated_at' => $config->updated_at?->toDateTimeString(),
        ]);
    }
}
