<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Services\SpvCutiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorSpvCutiController extends Controller
{
    public function __construct(
        protected SpvCutiService $spvCutiService
    ) {}

    /**
     * POST /api/v1/supervisor/leave/spv-ledger-cuti/import
     * Salin employee_leaves → spv_ledger_cuti untuk karyawan & periode terpilih.
     */
    public function importLedger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids'    => 'required|array|min:1',
            'employee_ids.*'  => 'integer|exists:employees,id',
            'leave_period_id' => 'required|integer|exists:leave_periods,id',
        ]);

        $result = $this->spvCutiService->copyLedgerFromEmployeeLeaves(
            $validated['employee_ids'],
            (int) $validated['leave_period_id'],
            $request->user()?->id
        );

        return response()->json([
            'message' => "{$result['created']} data cuti disalin, {$result['skipped']} dilewati.",
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);
    }
}
