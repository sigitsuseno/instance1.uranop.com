<?php

namespace App\Modules\Leave\Services;

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeaveChangeRequest;
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
            ->whereIn('transaction_type', ['increment', 'initial'])
            ->sum('amount');
            
        $deductions = EmployeeLeave::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_period_id', $periodId)
            ->where('transaction_type', 'decrement')
            ->sum('amount');
            
        return $additions - $deductions;
    }

    /**
     * Hitung sisa cuti setelah transaksi & update di leave_requests.sisa_cuti.
     * Dipanggil setelah approve / cancel / change yang mengubah saldo.
     */
    protected function syncSisaCuti(LeaveRequest $request): void
    {
        $leaveType = $request->leaveType;

        // Hanya untuk tipe cuti yang mengurangi jatah (balance_type = decrement)
        if ($leaveType && $leaveType->balance_type === 'decrement') {
            $sisa = $this->getAvailableBalance(
                $request->employee_id,
                $request->leave_type_id,
                $request->leave_period_id
            );
            $request->updateQuietly(['sisa_cuti' => $sisa]);
        }
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
                'note' => $data['note'] ?? null,
                'status' => 'pending'
            ]);
        });
    }

    /**
     * Update pengajuan cuti (Hanya jika masih pending)
     */
    public function updateRequest(LeaveRequest $request, array $data)
    {
        if ($request->status !== 'pending') {
            throw new Exception('Hanya pengajuan dengan status pending yang dapat diedit.');
        }

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        
        // Validasi saldo jika cuti mengurangi jatah
        if ($leaveType->balance_type === 'decrement') {
            $available = $this->getAvailableBalance($data['employee_id'], $leaveType->id, $request->leave_period_id);
            
            // Hitung pengajuan pending (exclude current request)
            $pendingDays = LeaveRequest::where('employee_id', $data['employee_id'])
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'pending')
                ->where('id', '!=', $request->id)
                ->sum('days_requested');
                
            if ($data['days_requested'] > ($available - $pendingDays)) {
                throw new Exception('Saldo cuti tidak mencukupi atau masih ada pengajuan yang belum di-approve.');
            }
        }

        return DB::transaction(function () use ($request, $data) {
            $request->update([
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days_requested' => $data['days_requested'],
                'reason' => $data['reason'] ?? null,
            ]);
            return $request;
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

            // Update roster
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

            // Sync sisa cuti setelah decrement
            $this->syncSisaCuti($request);
        });

        return $request;
    }

    /**
     * Approve & Print: langsung simpan approved + potong saldo + update roster
     * Khusus HR/Admin — tanpa flow pending dulu.
     */
    public function approveAndPrintRequest(array $data, $user)
    {
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        $period = LeavePeriod::where('status', 'active')->orderBy('start_date', 'desc')->first();
        if (!$period) {
            throw new Exception('Tidak ada periode cuti yang aktif.');
        }

        // Validasi saldo jika cuti mengurangi jatah
        if ($leaveType->balance_type === 'decrement') {
            $available = $this->getAvailableBalance($data['employee_id'], $leaveType->id, $period->id);

            $pendingDays = LeaveRequest::where('employee_id', $data['employee_id'])
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'pending')
                ->sum('days_requested');

            if ($data['days_requested'] > ($available - $pendingDays)) {
                throw new Exception('Saldo cuti tidak mencukupi atau masih ada pengajuan yang belum di-approve.');
            }
        }

        return DB::transaction(function () use ($data, $user, $period, $leaveType) {
            // 1. Buat leave_request langsung approved
            $leaveRequest = LeaveRequest::create([
                'employee_id'    => $data['employee_id'],
                'leave_type_id'  => $data['leave_type_id'],
                'leave_period_id' => $period->id,
                'start_date'     => $data['start_date'],
                'end_date'       => $data['end_date'],
                'days_requested' => $data['days_requested'],
                'reason'         => $data['reason'] ?? null,
                'note'           => $data['note'] ?? null,
                'status'         => 'approved',
                'approved_by'    => $user->id,
                'approved_at'    => now(),
            ]);

            // 2. Potong saldo (decrement)
            if ($leaveType->balance_type === 'decrement') {
                EmployeeLeave::create([
                    'employee_id'    => $data['employee_id'],
                    'leave_type_id'  => $leaveType->id,
                    'leave_period_id' => $period->id,
                    'reference_id'   => $leaveRequest->id,
                    'transaction_type' => 'decrement',
                    'amount'         => $data['days_requested'],
                    'description'    => 'Approval & Print Cuti #' . $leaveRequest->id,
                    'created_by'     => $user->id,
                    'updated_by'     => $user->id,
                ]);
            }

            // 3. Update roster
            $isLeave  = in_array($leaveType->category, ['leave', 'sick', 'special']) ? 1 : 0;
            $isPermit = ($leaveType->category === 'permit') ? 1 : 0;

            DB::table('sch_employee_shift_rosters')
                ->where('employee_id', $data['employee_id'])
                ->whereBetween('date', [$data['start_date'], $data['end_date']])
                ->update([
                    'external_code' => $leaveType->code,
                    'is_leave'      => $isLeave,
                    'is_permit'     => $isPermit,
                    'leave_id'      => $leaveRequest->id,
                    'updated_at'    => now(),
                ]);

            // Sync sisa cuti setelah decrement
            $this->syncSisaCuti($leaveRequest);

            return $leaveRequest;
        });
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

                        // Sync sisa cuti setelah pengembalian
                        $this->syncSisaCuti($request);
                    }
                }
            }
        });

        return $request;
    }

    /**
     * Submit pengajuan perubahan cuti
     */
    public function submitChangeRequest(LeaveRequest $originalRequest, array $data, $user)
    {
        if ($originalRequest->status !== 'approved') {
            throw new Exception('Hanya cuti yang sudah disetujui yang dapat diajukan perubahannya.');
        }

        // Check if there is already a pending change request
        $hasPending = LeaveChangeRequest::where('leave_request_id', $originalRequest->id)
            ->where('status', 'pending')
            ->exists();
            
        if ($hasPending) {
            throw new Exception('Sudah ada pengajuan perubahan cuti yang masih pending untuk cuti ini.');
        }

        return LeaveChangeRequest::create([
            'leave_request_id' => $originalRequest->id,
            'new_start_date' => $data['new_start_date'],
            'new_end_date' => $data['new_end_date'],
            'new_days_requested' => $data['new_days_requested'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Approve pengajuan perubahan cuti
     */
    public function approveChangeRequest(LeaveChangeRequest $changeRequest, $user)
    {
        $roles = $user->roles->pluck('name')->toArray();
        $allowed = ['superadmin', 'hrmanager', 'hr_manager', 'hr'];
        if (empty(array_intersect($roles, $allowed))) {
            throw new Exception('Anda tidak memiliki akses untuk menyetujui perubahan cuti.');
        }

        if ($changeRequest->status !== 'pending') {
            throw new Exception('Hanya pengajuan perubahan dengan status pending yang dapat disetujui.');
        }

        DB::transaction(function () use ($changeRequest, $user) {
            $originalRequest = $changeRequest->leaveRequest;
            $leaveType = $originalRequest->leaveType;
            $period = LeavePeriod::where('status', 'active')->orderBy('start_date', 'desc')->first();

            $oldDays = $originalRequest->days_requested;
            $newDays = $changeRequest->new_days_requested;
            $diffDays = $newDays - $oldDays;

            // Validasi saldo jika tipe cuti ini mengurangi jatah dan butuh tambahan hari
            if ($leaveType->balance_type === 'decrement' && $diffDays > 0) {
                $available = $this->getAvailableBalance($originalRequest->employee_id, $leaveType->id, $period->id);
                if ($diffDays > $available) {
                    throw new Exception('Saldo cuti tidak mencukupi untuk penambahan hari pada perubahan cuti ini.');
                }
            }

            // 1. Update status change request
            $changeRequest->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            // 2. Sesuaikan Ledger Balance (EmployeeLeave)
            if ($leaveType->balance_type === 'decrement' && $diffDays != 0 && $period) {
                $transactionType = $diffDays > 0 ? 'decrement' : 'increment';
                $amount = abs($diffDays);
                $desc = $diffDays > 0 
                    ? 'Penambahan hari karena perubahan cuti #' . $originalRequest->id 
                    : 'Pengembalian saldo karena perubahan cuti #' . $originalRequest->id;

                EmployeeLeave::create([
                    'employee_id' => $originalRequest->employee_id,
                    'leave_type_id' => $leaveType->id,
                    'leave_period_id' => $period->id,
                    'reference_id' => $originalRequest->id,
                    'transaction_type' => $transactionType,
                    'amount' => $amount,
                    'description' => $desc,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                // Sync sisa cuti setelah penyesuaian
                $this->syncSisaCuti($originalRequest);
            }

            // 3. Clear old rosters
            DB::table('sch_employee_shift_rosters')
                ->where('employee_id', $originalRequest->employee_id)
                ->where('leave_id', $originalRequest->id)
                ->update([
                    'external_code' => null,
                    'is_leave' => 0,
                    'is_permit' => 0,
                    'leave_id' => null,
                    'updated_at' => now(),
                ]);

            // 4. Update Original Request
            $originalRequest->update([
                'start_date' => $changeRequest->new_start_date,
                'end_date' => $changeRequest->new_end_date,
                'days_requested' => $changeRequest->new_days_requested,
                'updated_by' => $user->id,
            ]);

            // 5. Apply new rosters
            $isLeave = in_array($leaveType->category, ['leave', 'sick', 'special']) ? 1 : 0;
            $isPermit = ($leaveType->category === 'permit') ? 1 : 0;

            DB::table('sch_employee_shift_rosters')
                ->where('employee_id', $originalRequest->employee_id)
                ->whereBetween('date', [$changeRequest->new_start_date, $changeRequest->new_end_date])
                ->update([
                    'external_code' => $leaveType->code,
                    'is_leave' => $isLeave,
                    'is_permit' => $isPermit,
                    'leave_id' => $originalRequest->id,
                    'updated_at' => now(),
                ]);
        });

        return $changeRequest;
    }

    /**
     * Reject pengajuan perubahan cuti
     */
    public function rejectChangeRequest(LeaveChangeRequest $changeRequest, $user, $reason)
    {
        $roles = $user->roles->pluck('name')->toArray();
        $allowed = ['superadmin', 'hrmanager', 'hr_manager', 'hr'];
        if (empty(array_intersect($roles, $allowed))) {
            throw new Exception('Anda tidak memiliki akses untuk menolak perubahan cuti.');
        }

        if ($changeRequest->status !== 'pending') {
            throw new Exception('Hanya pengajuan perubahan dengan status pending yang dapat ditolak.');
        }

        $changeRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return $changeRequest;
    }
}
