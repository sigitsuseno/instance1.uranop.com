<?php

namespace App\Modules\Leave\Services;

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeaveChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class LeaveRequestService
{
    /**
     * Ambil saldo Cuti Tahunan (CT) dari SINGLE record employee_leaves.
     * Model: 1 employee + 1 leave_type + 1 period = 1 record.
     */
    public function getAvailableBalance($employeeId, $leaveTypeId, $periodId): int
    {
        $record = EmployeeLeave::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_period_id', $periodId)
            ->first();

        return $record ? (int) $record->amount : 0;
    }

    /**
     * Ambil atau buat SINGLE balance record untuk Cuti Tahunan.
     * Kalau belum ada → buat dengan amount default 12.
     */
    protected function getOrCreateBalanceRecord(int $employeeId, int $leaveTypeId, int $periodId): EmployeeLeave
    {
        $record = EmployeeLeave::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_period_id', $periodId)
            ->first();

        if (! $record) {
            $record = EmployeeLeave::create([
                'uuid'             => (string) Str::uuid(),
                'employee_id'      => $employeeId,
                'leave_type_id'    => $leaveTypeId,
                'leave_period_id'  => $periodId,
                'transaction_type' => 'initial',
                'amount'           => 12,
                'description'      => 'Saldo awal Cuti Tahunan (auto)',
                'created_by'       => 1,
            ]);
        }

        return $record;
    }

    /**
     * Decrement saldo pada SINGLE balance record.
     * Return sisa saldo setelah decrement.
     */
    protected function decrementBalance(int $employeeId, int $leaveTypeId, int $periodId, int $days): int
    {
        $record = $this->getOrCreateBalanceRecord($employeeId, $leaveTypeId, $periodId);
        $sisa = max(0, (int) $record->amount - $days);
        $record->updateQuietly(['amount' => $sisa]);

        return $sisa;
    }

    /**
     * Increment/kembalikan saldo pada SINGLE balance record.
     */
    protected function incrementBalance(int $employeeId, int $leaveTypeId, int $periodId, int $days): int
    {
        $record = $this->getOrCreateBalanceRecord($employeeId, $leaveTypeId, $periodId);
        $sisa = (int) $record->amount + $days;
        $record->updateQuietly(['amount' => $sisa]);

        return $sisa;
    }

    /**
     * Hitung sisa cuti setelah transaksi & update di leave_requests.sisa_cuti.
     * Dipanggil setelah approve / cancel / change yang mengubah saldo.
     */
    protected function syncSisaCuti(LeaveRequest $request): void
    {
        $leaveType = $request->leaveType;

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

        // Validasi saldo jika cuti mengurangi jatah (CT)
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

        return DB::transaction(function () use ($data, $period) {
            return LeaveRequest::create([
                'employee_id'    => $data['employee_id'],
                'leave_type_id'  => $data['leave_type_id'],
                'leave_period_id' => $period->id,
                'start_date'     => $data['start_date'],
                'end_date'       => $data['end_date'],
                'days_requested' => $data['days_requested'],
                'reason'         => $data['reason'] ?? null,
                'note'           => $data['note'] ?? null,
                'status'         => 'pending',
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

        if ($leaveType->balance_type === 'decrement') {
            $available = $this->getAvailableBalance($data['employee_id'], $leaveType->id, $request->leave_period_id);

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
                'employee_id'    => $data['employee_id'],
                'leave_type_id'  => $data['leave_type_id'],
                'start_date'     => $data['start_date'],
                'end_date'       => $data['end_date'],
                'days_requested' => $data['days_requested'],
                'reason'         => $data['reason'] ?? null,
            ]);
            return $request;
        });
    }

    /**
     * Approve pengajuan (Hanya Superadmin / HR Manager)
     */
    public function approveRequest(LeaveRequest $request, $user)
    {
        $roles = $user->roles->pluck('name')->toArray();
        $allowed = ['superadmin', 'hrmanager', 'hr_manager', 'hr'];
        if (empty(array_intersect($roles, $allowed))) {
            throw new Exception('Anda tidak memiliki akses untuk menyetujui pengajuan cuti.');
        }

        if ($request->status !== 'pending') {
            throw new Exception('Hanya pengajuan dengan status pending yang dapat disetujui.');
        }

        DB::transaction(function () use ($request, $user) {
            $request->update([
                'status'      => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $leaveType = $request->leaveType;

            // CT: UPDATE single balance record (decrement)
            if ($leaveType->balance_type === 'decrement') {
                $this->decrementBalance(
                    $request->employee_id,
                    $leaveType->id,
                    $request->leave_period_id,
                    $request->days_requested
                );
            }

            // Update roster
            $isLeave  = in_array($leaveType->category, ['leave', 'sick', 'special']) ? 1 : 0;
            $isPermit = ($leaveType->category === 'permit') ? 1 : 0;

            DB::table('sch_employee_shift_rosters')
                ->where('employee_id', $request->employee_id)
                ->whereBetween('date', [$request->start_date, $request->end_date])
                ->update([
                    'external_code' => $leaveType->code,
                    'is_leave'      => $isLeave,
                    'is_permit'     => $isPermit,
                    'leave_id'      => $request->id,
                    'updated_at'    => now(),
                ]);

            // Sync sisa_cuti
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

        // Validasi saldo CT
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

            // 2. CT: UPDATE single balance record (decrement)
            if ($leaveType->balance_type === 'decrement') {
                $this->decrementBalance(
                    $data['employee_id'],
                    $leaveType->id,
                    $period->id,
                    $data['days_requested']
                );
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

            // Sync sisa_cuti
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
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'approved_by'      => $user->id,
            'approved_at'      => now(),
        ]);

        return $request;
    }

    /**
     * Batalkan pengajuan dan kembalikan kuota.
     * CT: UPDATE single balance record (increment).
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

            $request->update(['status' => 'cancelled']);

            // Jika sebelumnya approved dan CT → kembalikan saldo (UPDATE, bukan CREATE)
            if ($oldStatus === 'approved') {
                $leaveType = $request->leaveType;
                if ($leaveType->balance_type === 'decrement') {
                    $this->incrementBalance(
                        $request->employee_id,
                        $leaveType->id,
                        $request->leave_period_id,
                        $request->days_requested
                    );

                    $this->syncSisaCuti($request);
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

        $hasPending = LeaveChangeRequest::where('leave_request_id', $originalRequest->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            throw new Exception('Sudah ada pengajuan perubahan cuti yang masih pending untuk cuti ini.');
        }

        return LeaveChangeRequest::create([
            'leave_request_id'   => $originalRequest->id,
            'new_start_date'     => $data['new_start_date'],
            'new_end_date'       => $data['new_end_date'],
            'new_days_requested' => $data['new_days_requested'],
            'reason'             => $data['reason'] ?? null,
            'status'             => 'pending',
            'created_by'         => $user->id,
            'updated_by'         => $user->id,
        ]);
    }

    /**
     * Approve pengajuan perubahan cuti.
     * CT: UPDATE single balance record (adjust diff days).
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
            $periodId  = $originalRequest->leave_period_id;

            $oldDays  = $originalRequest->days_requested;
            $newDays  = $changeRequest->new_days_requested;
            $diffDays = $newDays - $oldDays;

            // Validasi saldo jika butuh tambahan hari
            if ($leaveType->balance_type === 'decrement' && $diffDays > 0) {
                $available = $this->getAvailableBalance($originalRequest->employee_id, $leaveType->id, $periodId);
                if ($diffDays > $available) {
                    throw new Exception('Saldo cuti tidak mencukupi untuk penambahan hari pada perubahan cuti ini.');
                }
            }

            // 1. Update status change request
            $changeRequest->update([
                'status'      => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            // 2. CT: UPDATE single balance record (adjust diff)
            if ($leaveType->balance_type === 'decrement' && $diffDays != 0) {
                if ($diffDays > 0) {
                    // Tambahan hari → decrement
                    $this->decrementBalance($originalRequest->employee_id, $leaveType->id, $periodId, $diffDays);
                } else {
                    // Pengurangan hari → increment/kembalikan
                    $this->incrementBalance($originalRequest->employee_id, $leaveType->id, $periodId, abs($diffDays));
                }

                $this->syncSisaCuti($originalRequest);
            }

            // 3. Clear old rosters
            DB::table('sch_employee_shift_rosters')
                ->where('employee_id', $originalRequest->employee_id)
                ->where('leave_id', $originalRequest->id)
                ->update([
                    'external_code' => null,
                    'is_leave'      => 0,
                    'is_permit'     => 0,
                    'leave_id'      => null,
                    'updated_at'    => now(),
                ]);

            // 4. Update Original Request
            $originalRequest->update([
                'start_date'     => $changeRequest->new_start_date,
                'end_date'       => $changeRequest->new_end_date,
                'days_requested' => $changeRequest->new_days_requested,
                'updated_by'     => $user->id,
            ]);

            // 5. Apply new rosters
            $isLeave  = in_array($leaveType->category, ['leave', 'sick', 'special']) ? 1 : 0;
            $isPermit = ($leaveType->category === 'permit') ? 1 : 0;

            DB::table('sch_employee_shift_rosters')
                ->where('employee_id', $originalRequest->employee_id)
                ->whereBetween('date', [$changeRequest->new_start_date, $changeRequest->new_end_date])
                ->update([
                    'external_code' => $leaveType->code,
                    'is_leave'      => $isLeave,
                    'is_permit'     => $isPermit,
                    'leave_id'      => $originalRequest->id,
                    'updated_at'    => now(),
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
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'approved_by'      => $user->id,
            'approved_at'      => now(),
        ]);

        return $changeRequest;
    }
}
