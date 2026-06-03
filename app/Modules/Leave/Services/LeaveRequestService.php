<?php

namespace App\Modules\Leave\Services;

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\EmployeeLeave;
use Illuminate\Support\Facades\DB;
use Exception;

class LeaveRequestService
{
    /**
     * Helper: Hitung saldo cuti yang sudah di-approve
     */
    public function getAvailableBalance($employeeId, $leaveTypeId, $periodId)
    {
        $additions = EmployeeLeave::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_period_id', $periodId)
            ->where('transaction_type', 'increment')
            ->sum('amount');
            
        $deductions = EmployeeLeave::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_period_id', $periodId)
            ->where('transaction_type', 'decrement')
            ->sum('amount');
            
        return $additions - $deductions;
    }

    /**
     * Submit pengajuan cuti baru
     */
    public function submitRequest(array $data)
    {
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        
        $period = LeavePeriod::where('status', 'active')->orderBy('start_date', 'desc')->first();
        if (!$period) {
            throw new Exception('Tidak ada periode cuti yang aktif.');
        }
        
        // Validasi saldo jika cuti mengurangi jatah
        if ($leaveType->balance_type === 'decrement') {
            $available = $this->getAvailableBalance($data['employee_id'], $leaveType->id, $period->id);
            
            // Hitung juga pengajuan yang masih pending agar tidak overlap
            $pendingDays = LeaveRequest::where('employee_id', $data['employee_id'])
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'pending')
                ->sum('days_requested');
                
            if ($data['days_requested'] > ($available - $pendingDays)) {
                throw new Exception('Saldo cuti tidak mencukupi atau masih ada pengajuan yang belum di-approve.');
            }
        }

        return DB::transaction(function () use ($data, $period) {
            return LeaveRequest::create([
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'leave_period_id' => $period->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days_requested' => $data['days_requested'],
                'reason' => $data['reason'] ?? null,
                'status' => 'pending'
            ]);
        });
    }

    /**
     * Approve pengajuan (Hanya Superadmin / HR Manager)
     */
    public function approveRequest(LeaveRequest $request, $user)
    {
        // Pengecekan role sesuai request user: "jika bukan superadmin dan hrmanager tidak bisa klik approve"
        if (!$user->hasRole(['superadmin', 'hrmanager', 'hr'])) { // Asumsi ada role hr/hrmanager
             // Kita pakai pengecekan string manual untuk aman jika Role namanya agak beda
            $roles = $user->roles->pluck('name')->toArray();
            $allowed = ['superadmin', 'hrmanager', 'hr_manager', 'hr'];
            if (empty(array_intersect($roles, $allowed))) {
                throw new Exception('Anda tidak memiliki akses untuk menyetujui pengajuan cuti.');
            }
        }

        if ($request->status !== 'pending') {
            throw new Exception('Hanya pengajuan dengan status pending yang dapat disetujui.');
        }

        DB::transaction(function () use ($request, $user) {
            $request->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            // Jika tipe cuti ini mengurangi jatah, buat record deduksi di employee_leaves
            $leaveType = $request->leaveType;
            if ($leaveType->balance_type === 'decrement') {
                $period = LeavePeriod::where('status', 'active')->orderBy('start_date', 'desc')->first();
                if ($period) {
                    EmployeeLeave::create([
                        'employee_id' => $request->employee_id,
                        'leave_type_id' => $leaveType->id,
                        'leave_period_id' => $period->id,
                        'reference_id' => $request->id,
                        'transaction_type' => 'decrement',
                        'amount' => $request->days_requested,
                        'description' => 'Approval Pengajuan Cuti #' . $request->id,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            // Update sch_employee_shift_rosters: set external_code sesuai kode leave type
            // Kalo ga ada roster record → update 0 rows (skip, no error)
            $isLeave = in_array($leaveType->category, ['leave', 'sick', 'special']) ? 1 : 0;
            $isPermit = ($leaveType->category === 'permit') ? 1 : 0;

            DB::table('sch_employee_shift_rosters')
                ->where('employee_id', $request->employee_id)
                ->whereBetween('date', [$request->start_date, $request->end_date])
                ->update([
                    'external_code' => $leaveType->code,
                    'is_leave' => $isLeave,
                    'is_permit' => $isPermit,
                    'leave_id' => $request->id,
                    'updated_at' => now(),
                ]);
        });

        return $request;
    }

    /**
     * Reject pengajuan (Hanya Superadmin / HR Manager)
     */
    public function rejectRequest(LeaveRequest $request, $user, $reason)
    {
        $roles = $user->roles->pluck('name')->toArray();
        $allowed = ['superadmin', 'hrmanager', 'hr_manager', 'hr'];
        if (empty(array_intersect($roles, $allowed))) {
            throw new Exception('Anda tidak memiliki akses untuk menolak pengajuan cuti.');
        }

        if ($request->status !== 'pending') {
            throw new Exception('Hanya pengajuan dengan status pending yang dapat ditolak.');
        }

        $request->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return $request;
    }

    /**
     * Batalkan pengajuan dan kembalikan kuota (Kompensasi)
     */
    public function cancelRequest(LeaveRequest $request, $user)
    {
        $roles = $user->roles->pluck('name')->toArray();
        $allowed = ['superadmin', 'hrmanager', 'hr_manager', 'hr'];
        if (empty(array_intersect($roles, $allowed))) {
            throw new Exception('Anda tidak memiliki akses untuk membatalkan pengajuan cuti.');
        }

        DB::transaction(function () use ($request, $user) {
            $oldStatus = $request->status;
            
            $request->update([
                'status' => 'cancelled',
            ]);

            // Jika sebelumnya sudah diapprove dan sudah memotong jatah, kembalikan jatahnya (increment)
            if ($oldStatus === 'approved') {
                $leaveType = $request->leaveType;
                if ($leaveType->balance_type === 'decrement') {
                    $period = LeavePeriod::where('status', 'active')->orderBy('start_date', 'desc')->first();
                    if ($period) {
                        EmployeeLeave::create([
                            'employee_id' => $request->employee_id,
                            'leave_type_id' => $leaveType->id,
                            'leave_period_id' => $period->id,
                            'reference_id' => $request->id,
                            'transaction_type' => 'increment', // KOMPENSASI PENGEMBALIAN
                            'amount' => $request->days_requested,
                            'description' => 'Kompensasi Pembatalan Cuti #' . $request->id,
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }
                }
            }
        });

        return $request;
    }
}
