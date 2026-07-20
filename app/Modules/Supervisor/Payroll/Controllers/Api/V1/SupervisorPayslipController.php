<?php

namespace App\Modules\Supervisor\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Supervisor\Payroll\Models\SupervisorBreakdown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SupervisorPayslipController extends Controller
{
    /**
     * GET /api/v1/supervisor/payroll/payslips
     *
     * Tampilkan daftar slip gaji dari supervisor_breakdowns.
     * Adaptasi dari PengelolaanGajiController@index (hris-app Inertia)
     * ke Vue SPA dengan Sanctum token auth.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
            'search'    => 'nullable|string|max:100',
            'page'      => 'nullable|integer|min:1',
            'per_page'  => 'nullable|integer|min:1|max:200',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 50);

        $setting = SystemSetting::first();
        $fixedWorkDay = (int) ($setting?->fixed_working_day ?? 25);

        // Query dari supervisor_breakdowns (tempat data sebenarnya)
        $query = SupervisorBreakdown::with(['employee'])
            ->where('pay_period_id', $period->id);

        if ($period->is_split) {
            if (!$segment) {
                $segment = 'A';
            }
            $query->where('segment', $segment);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%")
                         ->orWhere('employee_code', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%");
                  });
            });
        }

        $paginator = $query->orderBy('id')->paginate($perPage);

        // --- Leave quota per employee ---
        $leavePeriod = \App\Modules\Leave\Models\LeavePeriod::where('status', 'active')
            ->where('start_date', '<=', $period->end_date)
            ->where('end_date', '>=', $period->start_date)
            ->orderBy('start_date', 'desc')
            ->first();
        if (!$leavePeriod) {
            $leavePeriod = \App\Modules\Leave\Models\LeavePeriod::where('status', 'active')
                ->orderBy('start_date', 'desc')
                ->first();
        }
        $employeeIds = $paginator->pluck('employee_id')->unique()->toArray();
        $leaveBalances = [];
        if ($leavePeriod && !empty($employeeIds)) {
            // Ambil sisa_cuti dari leave_request terakhir per employee di periode aktif
            $ct = \App\Modules\Leave\Models\LeaveType::where('code', 'CT')->first();
            if ($ct) {
                $sub = \App\Modules\Leave\Models\LeaveRequest::selectRaw('MAX(id)')
                    ->where('leave_period_id', $leavePeriod->id)
                    ->where('leave_type_id', $ct->id)
                    ->whereNotNull('sisa_cuti')
                    ->whereIn('employee_id', $employeeIds)
                    ->groupBy('employee_id');

                $latestRequests = \App\Modules\Leave\Models\LeaveRequest::whereIn('id', $sub)
                    ->pluck('sisa_cuti', 'employee_id');

                foreach ($latestRequests as $empId => $sisa) {
                    $leaveBalances[$empId] = (int) $sisa;
                }
            }

            // Fallback: karyawan yang belum punya leave_request, cek dari EmployeeLeave
            $missingIds = array_diff($employeeIds, array_keys($leaveBalances));
            if (!empty($missingIds)) {
                $rawBalances = \App\Modules\Leave\Models\EmployeeLeave::whereIn('employee_id', $missingIds)
                    ->where('leave_period_id', $leavePeriod->id)
                    ->select('employee_id',
                        \DB::raw("SUM(CASE WHEN transaction_type IN ('increment','initial') THEN amount ELSE 0 END) as total_in"),
                        \DB::raw("SUM(CASE WHEN transaction_type='decrement' THEN amount ELSE 0 END) as total_out")
                    )
                    ->groupBy('employee_id')
                    ->get();
                foreach ($rawBalances as $b) {
                    $leaveBalances[$b->employee_id] = (int) ($b->total_in - $b->total_out);
                }
            }
        }

        // --- Other segment data (untuk split period cross-reference) ---
        $otherSegmentRecords = collect();
        if ($period->is_split) {
            $otherSegment = $segment === 'A' ? 'B' : 'A';
            $otherSegmentRecords = SupervisorBreakdown::with(['employee'])
                ->where('pay_period_id', $period->id)
                ->where('segment', $otherSegment)
                ->get()
                ->keyBy('employee_id');
        }

        $data = $paginator->map(function ($record) use ($period, $fixedWorkDay, $otherSegmentRecords, $leaveBalances) {
            return $this->formatRow($record, $period, $fixedWorkDay, $otherSegmentRecords, $leaveBalances);
        });

        // --- Stats: semua record (bukan cuma current page) ---
        $allRecordsQuery = SupervisorBreakdown::where('pay_period_id', $period->id);
        if ($period->is_split && $segment) {
            $allRecordsQuery->where('segment', $segment);
        }
        $allRecords = $allRecordsQuery->get();

        $buildStats = function ($records) {
            return [
                'total_karyawan'   => $records->count(),
                'total_gaji_kotor' => $records->sum(fn($r) => (float) $r->gaji_kotor),
                'total_potongan'   => $records->sum(fn($r) =>
                    (float) $r->bpjs_tk + (float) $r->bpjs_ks + (float) $r->bpjs_pen
                    + (float) $r->pph + (float) $r->cashbon + (float) $r->pot_kehadiran
                ),
                'total_bersih'     => $records->sum(fn($r) => (float) $r->gaji_bersih),
            ];
        };

        $stats = $buildStats($allRecords);

        $statsByPart = null;
        if ($period->is_split) {
            $allSegA = SupervisorBreakdown::where('pay_period_id', $period->id)
                ->where('segment', 'A')->get();
            $allSegB = SupervisorBreakdown::where('pay_period_id', $period->id)
                ->where('segment', 'B')->get();
            $statsByPart = [
                'A' => $buildStats($allSegA),
                'B' => $buildStats($allSegB),
            ];
        }

        return response()->json([
            'data'            => $data->values(),
            'stats'           => $stats,
            'stats_by_part'   => $statsByPart,
            'period'          => [
                'id'         => $period->id,
                'name'       => $period->name,
                'is_split'   => $period->is_split,
                'segment'    => $segment,
                'start_date' => $period->start_date?->format('Y-m-d'),
                'end_date'   => $period->end_date?->format('Y-m-d'),
                'tanggal_penggajian' => $period->tanggal_penggajian?->format('Y-m-d'),
            ],
            'fixed_work_day'  => $fixedWorkDay,
            'pagination'      => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * PUT /api/v1/supervisor/payroll/payslips/{id}
     *
     * Update manual fields (cashbon / notes) lalu recalculate gaji_bersih.
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'cashbon' => 'nullable|numeric|min:0',
            'notes'   => 'nullable|string|max:500',
        ]);

        $record = SupervisorBreakdown::findOrFail($id);

        $cashbon = $request->has('cashbon') ? (float) $request->cashbon : (float) $record->cashbon;

        // Recalculate total potongan
        $totalPot = (float) $record->bpjs_tk
                  + (float) $record->bpjs_ks
                  + (float) $record->bpjs_pen
                  + (float) $record->pph
                  + $cashbon
                  + (float) $record->pot_kehadiran;

        // Gaji bersih setelah pembulatan
        $grossRaw = (float) $record->gaji_kotor - $totalPot;
        $totalRounded = ceil($grossRaw / 100) * 100;
        $pblt = $totalRounded - $grossRaw;

        $record->update([
            'cashbon'     => $cashbon,
            'pblt'        => round($pblt, 2),
            'gaji_bersih' => round($totalRounded, 2),
            'notes'       => $request->input('notes', $record->notes),
            'updated_by'  => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data slip gaji diperbarui.',
            'data'    => $this->formatRow($record->fresh()),
        ]);
    }

    /**
     * PATCH /api/v1/supervisor/payroll/payslips/{id}/status
     *
     * Update status slip gaji.
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:draft,synced,imported',
        ]);

        $record = SupervisorBreakdown::findOrFail($id);
        $record->update([
            'status'     => $request->status,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status slip gaji diperbarui.',
        ]);
    }

    /**
     * DELETE /api/v1/supervisor/payroll/payslips/{id}
     *
     * Hapus satu record slip gaji.
     */
    public function destroy(int $id)
    {
        $record = SupervisorBreakdown::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data slip gaji dihapus.',
        ]);
    }

    // ── Private Helpers ────────────────────────────────────────────

    /**
     * Format satu row SupervisorBreakdown untuk response JSON.
     *
     * Karena SupervisorBreakdown sudah denormalized, kita pakai
     * field langsung (employee_name, department_name, dll) sebagai
     * primary source, fallback ke relasi employee.
     */
    private function formatRow(
        SupervisorBreakdown $record,
        ?PayPeriod $period = null,
        int $fixedWorkDay = 25,
        $otherSegmentRecords = null,
        array $leaveBalances = []
    ): array {
        $emp = $record->employee;
        $period = $period ?? $record->payPeriod;

        // Join date: prioritaskan field denormalized, fallback ke relasi
        $joinDateRaw = $record->join_date ?? $emp?->join_date;
        $joinDate = $joinDateRaw ? Carbon::parse($joinDateRaw) : null;

        // HK untuk segment ini
        $hk = $fixedWorkDay;
        if ($period && $period->is_split && $record->segment) {
            $hk = (int) $record->hari_kerja + (int) round((float) $record->deduct_day);
        }

        $ratePerHari = $hk > 0 ? (float) $record->gaji_pokok / $fixedWorkDay : 0;
        $deductDays = (float) $record->deduct_day;
        $potKehadiran = round($deductDays * $ratePerHari);

        // Employee info: denormalized first, fallback ke relasi
        $employeeCode = $record->employee_code ?: ($emp?->nip ?? $emp?->employee_code ?? '-');
        $employeeName = $record->employee_name ?: ($emp?->name ?? '-');
        $department   = $record->department_name ?: ($emp?->department?->name ?? '-');
        $position     = $record->position_name ?: ($emp?->position?->name ?? '-');
        $gender       = $record->gender ?: ($emp?->gender ?? '-');

        $item = [
            'id'              => $record->id,
            'employee_id'     => $record->employee_id,
            'employee_code'   => $employeeCode,
            'employee_name'   => $employeeName,
            'department'      => $department,
            'position'        => $position,
            'gender'          => $gender,
            'join_year'       => $joinDate ? $joinDate->format('Y') : '-',
            'remaining_leave' => $leaveBalances[$record->employee_id] ?? 0,
            'segment'         => $record->segment,
            'section'         => $record->section,
            'status'          => $record->status,
            'notes'           => $record->notes,

            // Data masukan
            'gaji_pokok'      => (float) $record->gaji_pokok,
            'premi'           => (float) $record->premi,
            'tj_masa_kerja'   => (float) $record->tj_masa_kerja,
            'tunjangan'       => (float) $record->tunjangan,
            'hk'              => $hk,
            'hari_kerja'      => (int) $record->hari_kerja,
            'deduct_day'      => $deductDays,
            'rate_per_hari'   => round($ratePerHari),
            'lm'              => (int) $record->lm,
            'lm_count'        => (float) $record->lm_count,
            'lembur_count'    => (float) $record->lembur_count,

            // Hasil hitungan
            'gaji'            => (float) $record->gaji,
            'upah_lembur'     => (float) $record->upah_lembur,
            'premi_hadir'     => (float) $record->premi_hadir,
            'revisi'          => (float) $record->revisi,
            'gaji_kotor'      => (float) $record->gaji_kotor,

            // Potongan
            'bpjs_tk'         => (float) $record->bpjs_tk,
            'bpjs_ks'         => (float) $record->bpjs_ks,
            'bpjs_pen'        => (float) $record->bpjs_pen,
            'pph'             => (float) $record->pph,
            'cashbon'         => (float) $record->cashbon,
            'pot_kehadiran'   => $potKehadiran,

            // Pembulatan & hasil akhir
            'pblt'            => (float) $record->pblt,
            'gaji_bersih'     => (float) $record->gaji_bersih,
        ];

        // Other segment data (untuk split period cross-reference)
        if ($period && $period->is_split && $otherSegmentRecords && $otherSegmentRecords->has($record->employee_id)) {
            $other = $otherSegmentRecords->get($record->employee_id);
            $otherHk = (int) $other->hari_kerja + (int) round((float) $other->deduct_day);
            $otherRate = $fixedWorkDay > 0 ? (float) $other->gaji_pokok / $fixedWorkDay : 0;
            $otherDeductDays = (float) $other->deduct_day;
            $otherPotKehadiran = round($otherDeductDays * $otherRate);

            $otherEmpCode = $other->employee_code ?: ($other->employee?->nip ?? $other->employee?->employee_code ?? '-');
            $otherEmpName = $other->employee_name ?: ($other->employee?->name ?? '-');
            $otherDept    = $other->department_name ?: ($other->employee?->department?->name ?? '-');
            $otherPos     = $other->position_name ?: ($other->employee?->position?->name ?? '-');
            $otherGender  = $other->gender ?: ($other->employee?->gender ?? '-');

            $item['other_segment'] = [
                'id'            => $other->id,
                'employee_code' => $otherEmpCode,
                'employee_name' => $otherEmpName,
                'department'    => $otherDept,
                'position'      => $otherPos,
                'gender'        => $otherGender,
                'segment'       => $other->segment,
                'gaji_pokok'    => (float) $other->gaji_pokok,
                'premi'         => (float) $other->premi,
                'tj_masa_kerja' => (float) $other->tj_masa_kerja,
                'tunjangan'     => (float) $other->tunjangan,
                'hk'            => $otherHk,
                'hari_kerja'    => (int) $other->hari_kerja,
                'deduct_day'    => $otherDeductDays,
                'rate_per_hari' => round($otherRate),
                'lm'            => (int) $other->lm,
                'lm_count'      => (float) $other->lm_count,
                'lembur_count'  => (float) $other->lembur_count,
                'gaji'          => (float) $other->gaji,
                'upah_lembur'   => (float) $other->upah_lembur,
                'premi_hadir'   => (float) $other->premi_hadir,
                'revisi'        => (float) $other->revisi,
                'gaji_kotor'    => (float) $other->gaji_kotor,
                'bpjs_tk'       => (float) $other->bpjs_tk,
                'bpjs_ks'       => (float) $other->bpjs_ks,
                'bpjs_pen'      => (float) $other->bpjs_pen,
                'pph'           => (float) $other->pph,
                'cashbon'       => (float) $other->cashbon,
                'pot_kehadiran' => $otherPotKehadiran,
                'pblt'          => (float) $other->pblt,
                'gaji_bersih'   => (float) $other->gaji_bersih,
            ];
        }

        return $item;
    }
}
