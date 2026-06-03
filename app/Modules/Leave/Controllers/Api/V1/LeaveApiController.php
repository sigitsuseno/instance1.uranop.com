<?php

namespace App\Modules\Leave\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Services\LeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
}
