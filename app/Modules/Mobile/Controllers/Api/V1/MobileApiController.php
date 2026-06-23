<?php

namespace App\Modules\Mobile\Controllers\Api\V1;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MobileApiController extends Controller
{
    /**
     * Middleware: pastikan user punya employee record.
     */
    private function resolveEmployee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            abort(403, 'Akun ini bukan akun karyawan.');
        }

        if (! $employee->is_active) {
            abort(403, 'Akun karyawan sudah tidak aktif.');
        }

        return $employee;
    }

    /**
     * GET /api/mobile/profile
     * Profile lengkap karyawan yang sedang login.
     */
    public function profile(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        return response()->json([
            'data' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'nip' => $employee->nip,
                'nik' => $employee->nik,
                'employee_code' => $employee->employee_code,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'gender' => $employee->gender,
                'place_of_birth' => $employee->place_of_birth,
                'date_of_birth' => $employee->date_of_birth?->format('Y-m-d'),
                'religion' => $employee->religion,
                'blood_type' => $employee->blood_type,
                'address' => $employee->address,
                'photo_url' => $employee->photo_url,
                'join_date' => $employee->join_date?->format('Y-m-d'),
                'employment_status' => $employee->employment_status_label,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'bank_name' => $employee->bank_name,
                'bank_account_number' => $employee->bank_account_number,
                'bank_account_name' => $employee->bank_account_name,
                'npwp' => $employee->npwp,
                'bpjs_kesehatan' => $employee->bpjs_kesehatan,
                'bpjs_ketenagakerjaan' => $employee->bpjs_ketenagakerjaan,
                'base_salary' => $employee->base_salary,
            ],
        ]);
    }

    /**
     * GET /api/mobile/attendance?month=2026-06
     * Riwayat absensi karyawan (per bulan).
     */
    public function attendance(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $month = $request->input('month', now()->format('Y-m'));
        [$year, $monthNum] = explode('-', $month);
        $startDate = "{$year}-{$monthNum}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $records = AttendancePrepare::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($record) {
                return [
                    'date' => $record->date->format('Y-m-d'),
                    'day_name' => $record->date->translatedFormat('l'),
                    'check_in' => $record->check_in_time,
                    'check_out' => $record->check_out_time,
                    'schedule_in' => $record->schedule_in?->format('H:i'),
                    'schedule_out' => $record->schedule_out?->format('H:i'),
                    'status' => $record->status,
                    'status_label' => $record->status_label,
                    'status_badge' => $record->status_badge_class,
                    'late_minutes' => $record->late_minutes,
                    'overtime' => $record->overtime,
                    'lm' => $record->lm,
                ];
            });

        // Summary
        $summary = [
            'total_days' => $records->count(),
            'hadir' => $records->where('status', AttendancePrepare::STATUS_HADIR)->count(),
            'absent' => $records->where('status', AttendancePrepare::STATUS_ABSENT)->count(),
            'libur' => $records->where('status', AttendancePrepare::STATUS_LIBUR)->count(),
            'off' => $records->where('status', AttendancePrepare::STATUS_OFF)->count(),
            'total_late_minutes' => $records->sum('late_minutes'),
            'total_overtime_minutes' => $records->sum('overtime'),
            'total_lm_minutes' => $records->sum('lm'),
        ];

        return response()->json([
            'month' => $month,
            'employee_name' => $employee->name,
            'summary' => $summary,
            'records' => $records,
        ]);
    }

    /**
     * GET /api/mobile/attendance/summary?month=2026-06
     * Ringkasan saja (tanpa detail harian).
     */
    public function attendanceSummary(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $month = $request->input('month', now()->format('Y-m'));
        [$year, $monthNum] = explode('-', $month);
        $startDate = "{$year}-{$monthNum}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $records = AttendancePrepare::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        // Hitung status selain hadir/absent/libur/off (leave types)
        $leaveDays = $records->filter(function ($r) {
            return !in_array($r->status, [
                AttendancePrepare::STATUS_HADIR,
                AttendancePrepare::STATUS_ABSENT,
                AttendancePrepare::STATUS_LIBUR,
                AttendancePrepare::STATUS_OFF,
            ]);
        });

        return response()->json([
            'month' => $month,
            'employee_name' => $employee->name,
            'summary' => [
                'total_work_days' => $records->count(),
                'hadir' => $records->where('status', AttendancePrepare::STATUS_HADIR)->count(),
                'absent' => $records->where('status', AttendancePrepare::STATUS_ABSENT)->count(),
                'libur' => $records->where('status', AttendancePrepare::STATUS_LIBUR)->count(),
                'off' => $records->where('status', AttendancePrepare::STATUS_OFF)->count(),
                'leave_count' => $leaveDays->count(),
                'total_late_minutes' => $records->sum('late_minutes'),
                'total_overtime_minutes' => $records->sum('overtime'),
                'total_lm_minutes' => $records->sum('lm'),
            ],
        ]);
    }
}
