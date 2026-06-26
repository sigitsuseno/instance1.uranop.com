<?php

namespace App\Modules\Kasbon\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Kasbon\Models\KasbonRequest;
use App\Modules\Kasbon\Models\KasbonInstallment;
use App\Modules\Kasbon\Services\KasbonService;
use App\Modules\Kasbon\Services\KasbonInstallmentService;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class KasbonApiController extends Controller
{
    protected KasbonService $kasbonService;
    protected KasbonInstallmentService $installmentService;

    public function __construct(KasbonService $kasbonService, KasbonInstallmentService $installmentService)
    {
        $this->kasbonService = $kasbonService;
        $this->installmentService = $installmentService;
    }

    // ========================
    //  REQUESTS (Pengajuan)
    // ========================

    /**
     * GET /api/v1/kasbon/requests
     */
    public function index(Request $request): JsonResponse
    {
        $query = KasbonRequest::with([
            'employee.department',
            'employee.position',
            'approvedBy',
            'installments',
        ])->orderBy('created_at', 'desc');

        // Filter by employee
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by employee name/nip
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $requests = $query->paginate($request->get('per_page', 15));

        // Stats
        $stats = [
            'total' => KasbonRequest::count(),
            'pending' => KasbonRequest::where('status', 'pending')->count(),
            'approved' => KasbonRequest::whereIn('status', ['approved', 'disbursed'])->count(),
            'outstanding' => KasbonRequest::whereIn('status', ['approved', 'disbursed'])
                ->where('remaining_amount', '>', 0)
                ->count(),
        ];

        return response()->json([
            'data' => $requests->items(),
            'stats' => $stats,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/kasbon/requests
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:1',
            'tenor' => 'required|integer|min:1|max:60',
            'reason' => 'nullable|string|max:500',
        ]);

        // Validasi terhadap config
        $errors = $this->kasbonService->validateAgainstConfig($data);
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $errors,
            ], 422);
        }

        try {
            $kasbon = $this->kasbonService->create($data, Auth::id());
            return response()->json([
                'message' => 'Pengajuan kasbon berhasil dibuat.',
                'data' => $kasbon->load(['employee.department', 'employee.position']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/kasbon/requests/{id}
     */
    public function show(int $id): JsonResponse
    {
        $kasbon = KasbonRequest::with([
            'employee.department',
            'employee.position',
            'approvedBy',
            'installments.payPeriod',
        ])->findOrFail($id);

        return response()->json(['data' => $kasbon]);
    }

    /**
     * PUT /api/v1/kasbon/requests/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $kasbon = KasbonRequest::findOrFail($id);

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:1',
            'tenor' => 'required|integer|min:1|max:60',
            'reason' => 'nullable|string|max:500',
        ]);

        $errors = $this->kasbonService->validateAgainstConfig($data);
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $errors,
            ], 422);
        }

        try {
            $kasbon = $this->kasbonService->update($kasbon, $data, Auth::id());
            return response()->json([
                'message' => 'Pengajuan kasbon berhasil diupdate.',
                'data' => $kasbon->load(['employee.department', 'employee.position']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * DELETE /api/v1/kasbon/requests/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $kasbon = KasbonRequest::findOrFail($id);

        try {
            $this->kasbonService->destroy($kasbon);
            return response()->json(['message' => 'Pengajuan kasbon berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ========================
    //  APPROVAL
    // ========================

    /**
     * POST /api/v1/kasbon/requests/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        $kasbon = KasbonRequest::findOrFail($id);

        try {
            $kasbon = $this->kasbonService->approve($kasbon, Auth::id());
            return response()->json([
                'message' => 'Kasbon berhasil disetujui. Cicilan telah digenerate.',
                'data' => $kasbon->load(['installments']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/kasbon/requests/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $kasbon = KasbonRequest::findOrFail($id);

        $data = $request->validate([
            'rejection_note' => 'nullable|string|max:500',
        ]);

        try {
            $kasbon = $this->kasbonService->reject($kasbon, Auth::id(), $data['rejection_note'] ?? null);
            return response()->json(['message' => 'Kasbon telah ditolak.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/kasbon/requests/{id}/disburse
     */
    public function disburse(int $id): JsonResponse
    {
        $kasbon = KasbonRequest::findOrFail($id);

        try {
            $kasbon = $this->kasbonService->disburse($kasbon, Auth::id());
            return response()->json(['message' => 'Kasbon ditandai sudah dicairkan.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ========================
    //  APPROVALS VIEW
    // ========================

    /**
     * GET /api/v1/kasbon/approvals
     */
    public function approvals(Request $request): JsonResponse
    {
        $query = KasbonRequest::with([
            'employee.department',
            'employee.position',
        ])->where('status', 'pending')->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $requests = $query->paginate($request->get('per_page', 15));

        $stats = [
            'total_pending' => KasbonRequest::where('status', 'pending')->count(),
            'total_nominal' => (float) KasbonRequest::where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'data' => $requests->items(),
            'stats' => $stats,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/kasbon/requests/bulk-approve
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:kasbon_requests,id',
        ]);

        $userId = Auth::id();
        $approved = 0;
        $failed = [];

        foreach ($data['ids'] as $id) {
            try {
                $kasbon = KasbonRequest::findOrFail($id);
                $this->kasbonService->approve($kasbon, $userId);
                $approved++;
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => "{$approved} kasbon berhasil disetujui.",
            'approved' => $approved,
            'failed' => $failed,
        ]);
    }

    // ========================
    //  INSTALLMENTS (Pelunasan)
    // ========================

    /**
     * GET /api/v1/kasbon/installments
     */
    public function installments(Request $request): JsonResponse
    {
        $query = KasbonInstallment::with([
            'kasbonRequest.employee.department',
            'payPeriod',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->whereHas('kasbonRequest', function ($q) use ($request) {
                $q->where('employee_id', $request->employee_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('kasbonRequest.employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $installments = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $installments->items(),
            'pagination' => [
                'current_page' => $installments->currentPage(),
                'last_page' => $installments->lastPage(),
                'per_page' => $installments->perPage(),
                'total' => $installments->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/kasbon/installments/{id}/pay
     */
    public function payInstallment(int $id): JsonResponse
    {
        $installment = KasbonInstallment::findOrFail($id);

        try {
            $this->installmentService->payInstallment($installment);
            return response()->json(['message' => 'Cicilan berhasil dibayar.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/kasbon/installments/bulk-pay
     */
    public function bulkPay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:kasbon_installments,id',
        ]);

        $paid = 0;
        $failed = [];

        foreach ($data['ids'] as $id) {
            try {
                $installment = KasbonInstallment::findOrFail($id);
                $this->installmentService->payInstallment($installment);
                $paid++;
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => "{$paid} cicilan berhasil dibayar.",
            'paid' => $paid,
            'failed' => $failed,
        ]);
    }

    /**
     * POST /api/v1/kasbon/installments/{id}/push
     */
    public function pushToPayroll(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'pay_period_id' => 'required|exists:pay_periods,id',
        ]);

        $installment = KasbonInstallment::findOrFail($id);

        try {
            $this->installmentService->pushToPayPeriod($installment, $data['pay_period_id']);
            return response()->json(['message' => 'Cicilan berhasil di-push ke periode payroll.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ========================
    //  HISTORY (Riwayat)
    // ========================

    /**
     * GET /api/v1/kasbon/history
     */
    public function history(Request $request): JsonResponse
    {
        $query = KasbonRequest::with([
            'employee.department',
            'employee.position',
            'approvedBy',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $requests = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $requests->items(),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    // ========================
    //  UTILITIES
    // ========================

    /**
     * GET /api/v1/kasbon/pay-periods
     * Dropdown list periode payroll untuk select
     */
    public function payPeriods(): JsonResponse
    {
        $periods = PayPeriod::orderBy('start_date', 'desc')->get(['id', 'name', 'start_date', 'end_date']);
        return response()->json(['data' => $periods]);
    }
}
