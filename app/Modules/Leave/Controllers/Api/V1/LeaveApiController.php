<?php

namespace App\Modules\Leave\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeaveChangeRequest;
use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Services\LeaveRequestService;
use App\Modules\Leave\Exports\LeaveRequestsExport;
use App\Modules\Leave\Exports\LeaveBalancesExport;
use App\Modules\Organization\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LeaveApiController extends Controller
{
    protected $leaveService;

    public function __construct(LeaveRequestService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    /**
     * Get list of leave requests
     */
    public function index(Request $request)
    {
        $query = LeaveRequest::with(['employee.department', 'leaveType', 'leavePeriod', 'documents'])->orderBy('created_at', 'desc');

        $user = Auth::user();
        $roles = $user->roles->pluck('name')->toArray();
        $isHr = !empty(array_intersect($roles, ['superadmin', 'hrmanager', 'hr_manager', 'hr']));

        if (!$isHr) {
            // Jika punya employee profile terkait, filter hanya data miliknya.
            if (isset($user->employee_id)) {
                $query->where('employee_id', $user->employee_id);
            } else {
                $query->whereHas('employee', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }
        }

        if ($request->filled('leave_period_id')) {
            $period = LeavePeriod::find($request->leave_period_id);
            if ($period) {
                $query->where(function ($q) use ($period) {
                    $q->whereBetween('start_date', [$period->start_date, $period->end_date])
                      ->orWhereBetween('end_date', [$period->start_date, $period->end_date])
                      ->orWhere(function ($sq) use ($period) {
                          $sq->where('start_date', '<=', $period->start_date)
                             ->where('end_date', '>=', $period->end_date);
                      });
                });
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        // Calculate stats
        $statsQuery = LeaveRequest::query();
        if (!$isHr) {
            if (isset($user->employee_id)) {
                $statsQuery->where('employee_id', $user->employee_id);
            } else {
                $statsQuery->whereHas('employee', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }
        }
        if ($request->filled('leave_period_id')) {
            $period = LeavePeriod::find($request->leave_period_id);
            if ($period) {
                $statsQuery->where(function ($q) use ($period) {
                    $q->whereBetween('start_date', [$period->start_date, $period->end_date])
                      ->orWhereBetween('end_date', [$period->start_date, $period->end_date])
                      ->orWhere(function ($sq) use ($period) {
                          $sq->where('start_date', '<=', $period->start_date)
                             ->where('end_date', '>=', $period->end_date);
                      });
                });
            }
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $statsQuery)->where('status', 'rejected')->count(),
            'cancelled' => (clone $statsQuery)->where('status', 'cancelled')->count(),
        ];

        $paginated = $query->paginate(15);

        return response()->json([
            'paginated' => $paginated,
            'stats' => $stats
        ]);
    }

    /**
     * Submit new leave request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days_requested' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        try {
            $leaveRequest = $this->leaveService->submitRequest($validated);
            return response()->json(['message' => 'Pengajuan cuti berhasil disubmit', 'data' => $leaveRequest], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Approve & Print: langsung simpan approved + potong saldo + return data untuk cetak
     * Hanya HR/Admin. Tidak ada flow pending.
     */
    public function approveAndPrint(Request $request)
    {
        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'leave_type_id'  => 'required|exists:leave_types,id',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'days_requested' => 'required|integer|min:1',
            'reason'         => 'nullable|string',
        ]);

        try {
            $leaveRequest = $this->leaveService->approveAndPrintRequest($validated, Auth::user());
            $leaveRequest->load(['employee.department', 'employee.position', 'leaveType', 'leavePeriod']);

            return response()->json([
                'message' => 'Pengajuan cuti disetujui & siap cetak.',
                'data'    => $leaveRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Print form cuti — return HTML siap window.print()
     */
    public function printForm($id)
    {
        $leaveRequest = LeaveRequest::with([
            'employee.department', 'employee.position', 'leaveType', 'leavePeriod'
        ])->findOrFail($id);

        // Hitung sisa cuti tahunan
        // Rumus: 12 - total_leave
        // total_leave = SUM days_requested dari semua leave_request CT milik employee,
        // dengan end_date dalam rentang leave_period->start_date s/d leave_request->end_date
        // (termasuk leave_request yang sedang dicetak — jadi tiap request punya sisa unique)
        $ct = LeaveType::where('code', 'CT')->first();
        $sisaCuti = 0;
        if ($ct && $leaveRequest->leave_period_id && $leaveRequest->leavePeriod) {
            $totalLeave = LeaveRequest::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $ct->id)
                ->where('status', 'approved')
                ->whereDate('end_date', '>=', $leaveRequest->leavePeriod->start_date->toDateString())
                ->whereDate('end_date', '<=', $leaveRequest->end_date->toDateString())
                ->sum('days_requested');

            $sisaCuti = 12 - (int)$totalLeave;
        }

        $company  = Company::first();
        $logoUrl  = null;
        if ($company && $company->logo_path) {
            $logoUrl = asset('storage/' . $company->logo_path);
        }

        $html = view('leave.print-form', [
            'request'   => $leaveRequest,
            'sisaCuti'  => $sisaCuti,
            'tglEfektif'=> now()->format('d/m/Y'),
            'noDokumen' => $this->generateNoDokumen($leaveRequest),
            'company'   => $company,
            'logoUrl'   => $logoUrl,
        ])->render();

        return response($html);
    }

    /**
     * Generate nomor dokumen format: nomor_surat / SPc / KUS / bulan_romawi / tahun
     * nomor_surat = count leave_requests di periode aktif + 1
     */
    private function generateNoDokumen($leaveRequest)
    {
        // Hitung total leave_requests di periode aktif
        $activePeriod = LeavePeriod::where('status', 'active')->orderBy('start_date', 'desc')->first();
        $nomorSurat = 1;
        if ($activePeriod) {
            $nomorSurat = LeaveRequest::where('leave_period_id', $activePeriod->id)->count() + 1;
        }

        $romawi = ['', 'I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
        $bulan  = $romawi[(int) now()->format('m')];
        $tahun  = now()->format('Y');

        return "{$nomorSurat} / SPc / KUS / {$bulan} / {$tahun}";
    }

    /**
     * Update leave request (only pending ones)
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days_requested' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);

        try {
            $result = $this->leaveService->updateRequest($leaveRequest, $validated);
            return response()->json(['message' => 'Pengajuan cuti berhasil diupdate', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Approve leave request
     */
    public function approve(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        try {
            $result = $this->leaveService->approveRequest($leaveRequest, Auth::user());
            return response()->json(['message' => 'Pengajuan cuti disetujui', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Reject leave request
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string'
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);

        try {
            $result = $this->leaveService->rejectRequest($leaveRequest, Auth::user(), $validated['rejection_reason']);
            return response()->json(['message' => 'Pengajuan cuti ditolak', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Cancel leave request
     */
    public function cancel(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        try {
            $result = $this->leaveService->cancelRequest($leaveRequest, Auth::user());
            return response()->json(['message' => 'Pengajuan cuti dibatalkan dan kuota dikembalikan', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Bulk approve leave requests
     */
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:leave_requests,id',
        ]);

        $user = Auth::user();
        $success = 0;
        $failed = [];

        foreach ($validated['ids'] as $id) {
            try {
                $leaveRequest = LeaveRequest::findOrFail($id);
                $this->leaveService->approveRequest($leaveRequest, $user);
                $success++;
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => "{$success} pengajuan disetujui" . (count($failed) > 0 ? ', ' . count($failed) . ' gagal' : ''),
            'success_count' => $success,
            'failed' => $failed,
        ]);
    }

    /**
     * Bulk reject leave requests
     */
    public function bulkReject(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:leave_requests,id',
            'rejection_reason' => 'required|string',
        ]);

        $user = Auth::user();
        $success = 0;
        $failed = [];

        foreach ($validated['ids'] as $id) {
            try {
                $leaveRequest = LeaveRequest::findOrFail($id);
                $this->leaveService->rejectRequest($leaveRequest, $user, $validated['rejection_reason']);
                $success++;
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => "{$success} pengajuan ditolak" . (count($failed) > 0 ? ', ' . count($failed) . ' gagal' : ''),
            'success_count' => $success,
            'failed' => $failed,
        ]);
    }

    /**
     * Bulk cancel leave requests
     */
    public function bulkCancel(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:leave_requests,id',
        ]);

        $user = Auth::user();
        $success = 0;
        $failed = [];

        foreach ($validated['ids'] as $id) {
            try {
                $leaveRequest = LeaveRequest::findOrFail($id);
                $this->leaveService->cancelRequest($leaveRequest, $user);
                $success++;
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => "{$success} pengajuan dibatalkan" . (count($failed) > 0 ? ', ' . count($failed) . ' gagal' : ''),
            'success_count' => $success,
            'failed' => $failed,
        ]);
    }

    /**
     * Get list of leave change requests
     */
    public function indexChangeRequests(Request $request)
    {
        $query = LeaveChangeRequest::with(['leaveRequest.employee.department', 'leaveRequest.leaveType'])->orderBy('created_at', 'desc');

        $user = Auth::user();
        $roles = $user->roles->pluck('name')->toArray();
        $isHr = !empty(array_intersect($roles, ['superadmin', 'hrmanager', 'hr_manager', 'hr']));

        if (!$isHr) {
            if (isset($user->employee_id)) {
                $query->whereHas('leaveRequest', function($q) use ($user) {
                    $q->where('employee_id', $user->employee_id);
                });
            } else {
                $query->whereHas('leaveRequest.employee', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $paginated = $query->paginate(15);
        return response()->json(['paginated' => $paginated]);
    }

    /**
     * Store a new change request
     */
    public function storeChangeRequest(Request $request, $id)
    {
        $validated = $request->validate([
            'new_start_date' => 'required|date',
            'new_end_date' => 'required|date|after_or_equal:new_start_date',
            'new_days_requested' => 'required|integer|min:1',
            'reason' => 'required|string',
        ]);

        $originalRequest = LeaveRequest::findOrFail($id);

        try {
            $changeRequest = $this->leaveService->submitChangeRequest($originalRequest, $validated, Auth::user());
            return response()->json(['message' => 'Pengajuan perubahan cuti berhasil disubmit', 'data' => $changeRequest], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Approve change request
     */
    public function approveChangeRequest(Request $request, $id)
    {
        $changeRequest = LeaveChangeRequest::findOrFail($id);

        try {
            $result = $this->leaveService->approveChangeRequest($changeRequest, Auth::user());
            return response()->json(['message' => 'Perubahan cuti disetujui', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Reject change request
     */
    public function rejectChangeRequest(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string'
        ]);

        $changeRequest = LeaveChangeRequest::findOrFail($id);

        try {
            $result = $this->leaveService->rejectChangeRequest($changeRequest, Auth::user(), $validated['rejection_reason']);
            return response()->json(['message' => 'Perubahan cuti ditolak', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Get employee leave balances for a period
     */
    public function balances(Request $request)
    {
        $periodId = $request->input('leave_period_id');
        if (!$periodId) {
            $activePeriod = LeavePeriod::where('status', 'active')->first();
            $periodId = $activePeriod ? $activePeriod->id : null;
        }

        if (!$periodId) {
            return response()->json(['data' => []]);
        }

        $employees = Employee::with(['department'])
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $leaveTypes = LeaveType::all();
        $balances = [];

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $type) {
                $additions = EmployeeLeave::where('employee_id', $employee->id)
                    ->where('leave_type_id', $type->id)
                    ->where('leave_period_id', $periodId)
                    ->where('transaction_type', 'increment')
                    ->sum('amount');

                $deductions = EmployeeLeave::where('employee_id', $employee->id)
                    ->where('leave_type_id', $type->id)
                    ->where('leave_period_id', $periodId)
                    ->where('transaction_type', 'decrement')
                    ->sum('amount');

                $remaining = $additions - $deductions;

                // Hanya tampilkan jika ada data kuota (addition/deduction) — jangan tampilin row kosong
                if ($additions > 0 || $deductions > 0) {
                    $balances[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'nip' => $employee->nip,
                        'department_name' => $employee->department?->name ?? '-',
                        'leave_type_id' => $type->id,
                        'leave_type_name' => $type->name,
                        'entitlement' => (float)$additions,
                        'used' => (float)$deductions,
                        'balance' => (float)$remaining,
                    ];
                }
            }
        }

        return response()->json(['data' => $balances]);
    }

    /**
     * Get single employee leave balance — ringan, langsung query spesifik.
     * GET /api/v1/leave/employee-balance?employee_id=X&leave_type_id=Y
     */
    public function employeeBalance(Request $request)
    {
        $validated = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
        ]);

        $periodId = $request->input('leave_period_id');
        if (!$periodId) {
            $activePeriod = LeavePeriod::where('status', 'active')->orderBy('start_date', 'desc')->first();
            $periodId = $activePeriod ? $activePeriod->id : null;
        }

        if (!$periodId) {
            return response()->json(['data' => ['balance' => 0]]);
        }

        $balance = $this->leaveService->getAvailableBalance(
            $validated['employee_id'],
            $validated['leave_type_id'],
            $periodId
        );

        return response()->json([
            'data' => [
                'employee_id'   => (int) $validated['employee_id'],
                'leave_type_id' => (int) $validated['leave_type_id'],
                'period_id'     => (int) $periodId,
                'balance'       => (float) $balance,
            ]
        ]);
    }

    /**
     * Bulk generate leave quota for a period
     */
    public function generateQuota(Request $request)
    {
        // Pengecekan role HR/Admin
        $user = Auth::user();
        $roles = $user->roles->pluck('name')->toArray();
        $isHr = !empty(array_intersect($roles, ['superadmin', 'hrmanager', 'hr_manager', 'hr']));
        if (!$isHr) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk generate kuota cuti.'], 403);
        }

        $validated = $request->validate([
            'leave_period_id' => 'required|exists:leave_periods,id',
            'leave_policy_id' => 'required|exists:leave_policies,id'
        ]);

        $period = LeavePeriod::findOrFail($validated['leave_period_id']);
        $employees = Employee::where('is_active', 1)->get();
        $policy = LeavePolicy::findOrFail($validated['leave_policy_id']);

        $generatedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                // Hitung masa kerja
                $yearsOfService = 0;
                if ($employee->join_date) {
                    $yearsOfService = \Carbon\Carbon::parse($employee->join_date)->diffInYears(now());
                }

                // Check if employee meets requirements (e.g. 1 year or more)
                if ($policy->requires_one_year_service && $yearsOfService < 1) {
                    continue;
                }

                // Gunakan firstOrCreate agar atomic (didukung unique constraint di DB)
                try {
                    $employeeLeave = EmployeeLeave::firstOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'leave_type_id' => $policy->leave_type_id,
                            'leave_period_id' => $period->id,
                            'transaction_type' => 'increment',
                            'reference_id' => 0,
                        ],
                        [
                            'amount' => $policy->entitlement_days,
                            'description' => 'Generate Kuota ' . $policy->name,
                            'created_by' => Auth::id(),
                            'updated_by' => Auth::id(),
                        ]
                    );

                    if ($employeeLeave->wasRecentlyCreated) {
                        $generatedCount++;
                    }
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Duplicate entry — skip, already exists (race condition handling)
                    continue;
                }
            }

            // Update status
            $period->update(['is_generated' => true]);

            DB::commit();
            return response()->json([
                'message' => "Kuota cuti berhasil digenerate untuk {$generatedCount} entri."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal generate kuota: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Recapitulate and close period
     */
    public function recapPeriod(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles->pluck('name')->toArray();
        $isHr = !empty(array_intersect($roles, ['superadmin', 'hrmanager', 'hr_manager', 'hr']));
        if (!$isHr) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk mengakhiri periode cuti.'], 403);
        }

        $validated = $request->validate([
            'leave_period_id' => 'required|exists:leave_periods,id'
        ]);

        $period = LeavePeriod::findOrFail($validated['leave_period_id']);

        DB::beginTransaction();
        try {
            // Update status to closed
            $period->update(['status' => 'closed']);

            // Jika is_carry_forward is false, hanguskan sisa cuti (bikin sisa jadi 0)
            if (!$period->is_carry_forward) {
                $employees = Employee::where('is_active', 1)->get();
                $leaveTypes = LeaveType::all();

                foreach ($employees as $employee) {
                    foreach ($leaveTypes as $type) {
                        $additions = EmployeeLeave::where('employee_id', $employee->id)
                            ->where('leave_type_id', $type->id)
                            ->where('leave_period_id', $period->id)
                            ->where('transaction_type', 'increment')
                            ->sum('amount');

                        $deductions = EmployeeLeave::where('employee_id', $employee->id)
                            ->where('leave_type_id', $type->id)
                            ->where('leave_period_id', $period->id)
                            ->where('transaction_type', 'decrement')
                            ->sum('amount');

                        $remaining = $additions - $deductions;

                        if ($remaining > 0) {
                            // Insert deduction to zero it out
                            EmployeeLeave::create([
                                'employee_id' => $employee->id,
                                'leave_type_id' => $type->id,
                                'leave_period_id' => $period->id,
                                'transaction_type' => 'decrement',
                                'amount' => $remaining,
                                'description' => 'Penghangusan Akhir Periode (Carry Forward False)',
                                'created_by' => Auth::id(),
                                'updated_by' => Auth::id(),
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            return response()->json([
                'message' => "Periode '{$period->name}' berhasil diakhiri." . (!$period->is_carry_forward ? " Sisa cuti telah dihanguskan." : "")
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengakhiri periode: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export leave requests to Excel
     */
    public function exportRequests(Request $request)
    {
        $periodId = $request->input('leave_period_id');
        $status = $request->input('status', '');

        $query = LeaveRequest::with(['employee.department', 'leaveType'])
            ->orderBy('created_at', 'desc');

        if ($periodId) {
            $period = LeavePeriod::find($periodId);
            if ($period) {
                $query->where(function ($q) use ($period) {
                    $q->whereBetween('start_date', [$period->start_date, $period->end_date])
                      ->orWhereBetween('end_date', [$period->start_date, $period->end_date])
                      ->orWhere(function ($sq) use ($period) {
                          $sq->where('start_date', '<=', $period->start_date)
                             ->where('end_date', '>=', $period->end_date);
                      });
                });
            }
        }

        if ($status) {
            $query->where('status', $status);
        }

        $data = $query->get()->toArray();

        $periodName = '';
        if ($periodId) {
            $period = LeavePeriod::find($periodId);
            $periodName = $period ? $period->name : '';
        }

        $filename = 'Pengajuan_Cuti' . ($periodName ? '_' . str_replace(' ', '_', $periodName) : '') . '.xlsx';
        return Excel::download(new LeaveRequestsExport($data, $periodName), $filename);
    }

    /**
     * Export leave balances to Excel
     */
    public function exportBalances(Request $request)
    {
        $periodId = $request->input('leave_period_id');
        if (!$periodId) {
            $activePeriod = LeavePeriod::where('status', 'active')->first();
            $periodId = $activePeriod ? $activePeriod->id : null;
        }

        if (!$periodId) {
            return response()->json(['message' => 'Tidak ada periode cuti.'], 400);
        }

        $employees = Employee::with(['department'])
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $leaveTypes = LeaveType::all();
        $balances = [];

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $type) {
                $additions = EmployeeLeave::where('employee_id', $employee->id)
                    ->where('leave_type_id', $type->id)
                    ->where('leave_period_id', $periodId)
                    ->where('transaction_type', 'increment')
                    ->sum('amount');

                $deductions = EmployeeLeave::where('employee_id', $employee->id)
                    ->where('leave_type_id', $type->id)
                    ->where('leave_period_id', $periodId)
                    ->where('transaction_type', 'decrement')
                    ->sum('amount');

                $remaining = $additions - $deductions;

                if ($additions > 0 || $deductions > 0) {
                    $balances[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'nip' => $employee->nip,
                        'department_name' => $employee->department?->name ?? '-',
                        'leave_type_id' => $type->id,
                        'leave_type_name' => $type->name,
                        'entitlement' => (float)$additions,
                        'used' => (float)$deductions,
                        'balance' => (float)$remaining,
                    ];
                }
            }
        }

        $period = LeavePeriod::find($periodId);
        $periodName = $period ? $period->name : '';

        $filename = 'Saldo_Cuti' . ($periodName ? '_' . str_replace(' ', '_', $periodName) : '') . '.xlsx';
        return Excel::download(new LeaveBalancesExport($balances, $periodName), $filename);
    }
}
