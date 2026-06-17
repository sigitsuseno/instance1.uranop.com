<?php

namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\ReportConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportConfigApiController extends Controller
{
    public function __construct(
        private ReportConfigService $service
    ) {}

    /**
     * GET /api/v1/settings/report-configs
     * List semua report config yang sudah ada di DB.
     */
    public function index(): JsonResponse
    {
        $configs = \App\Modules\Settings\Models\ReportConfig::all()
            ->map(fn ($c) => [
                'report_type'     => $c->report_type,
                'employee_groups' => $c->employee_groups,
                'config'          => $c->config,
                'updated_by'      => $c->updatedBy?->name,
                'updated_at'      => $c->updated_at?->toDateTimeString(),
            ]);

        return response()->json(['data' => $configs]);
    }

    /**
     * GET /api/v1/settings/report-configs/{reportType}
     * Ambil 1 config. Kalau belum ada → return default.
     */
    public function show(string $reportType): JsonResponse
    {
        return response()->json(
            $this->service->getFull($reportType)
        );
    }

    /**
     * PUT /api/v1/settings/report-configs/{reportType}
     * Simpan / update config.
     */
    public function update(Request $request, string $reportType): JsonResponse
    {
        $validated = $request->validate([
            'employee_groups'   => 'nullable|array',
            'employee_groups.*' => 'string',
            'config'            => 'nullable|array',
        ]);

        $config = $this->service->set(
            $reportType,
            $validated,
            auth()->id()
        );

        return response()->json([
            'message'    => 'Pengaturan laporan disimpan.',
            'updated_by' => auth()->user()?->name,
            'updated_at' => $config->updated_at?->toDateTimeString(),
        ]);
    }
}
