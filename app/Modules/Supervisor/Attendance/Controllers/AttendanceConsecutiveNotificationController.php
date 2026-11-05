<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AttendanceConsecutiveNotificationController extends Controller
{
    /**
     * Notifikasi consecutive day untuk lonceng layout Supervisor.
     *
     * GET /api/v1/supervisor/attendance/consecutive-notifications?date=YYYY-MM-DD
     *
     * Tanpa `date`, dipakai Sabtu terakhir yang punya data di attendance_autologs.
     * Karyawan muncul bila enam hari kalender terakhir (Senin–Sabtu) semuanya
     * berstatus `present` — cuti/izin/sakit/absen/off/libur nasional menggugurkan.
     */
    public function index(Request $request): JsonResponse
    {
        $saturday = $this->resolveSaturday($request->input('date'));

        $startDate = $saturday->copy()->subDays(5);
        $endDate = $saturday->copy();

        $employeeIds = $this->employeeIdsForPeriod($endDate);

        $records = AttendanceAutolog::query()
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('employee_id', $employeeIds)
            ->get(['employee_id', 'date', 'status', 'is_holiday'])
            ->groupBy('employee_id');

        $qualifiedIds = $records
            ->filter(fn (Collection $rows) => $rows->count() === 6
                && $rows->every(fn ($row) => $row->status === 'present' && ! $row->is_holiday))
            ->keys();

        $notifications = SupervisorEmployee::query()
            ->whereIn('id', $qualifiedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code'])
            ->map(fn (SupervisorEmployee $employee) => [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'message' => "Karyawan {$employee->name} - {$employee->employee_code} sudah masuk selama 6 hari",
            ])
            ->values();

        return response()->json([
            'saturday' => $saturday->toDateString(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'count' => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Tentukan Sabtu target: tanggal eksplisit (untuk uji) atau Sabtu terakhir
     * yang sudah punya baris attendance_autologs.
     */
    private function resolveSaturday(?string $date): Carbon
    {
        if ($date) {
            return Carbon::parse($date)->startOfDay();
        }

        $latestSaturday = Carbon::today()->startOfDay();

        while (! $latestSaturday->isSaturday()) {
            $latestSaturday->subDay();
        }

        $candidates = collect(range(0, 51))
            ->map(fn (int $weeks) => $latestSaturday->copy()->subWeeks($weeks)->toDateString());

        $latestWithData = AttendanceAutolog::query()
            ->whereIn('date', $candidates)
            ->max('date');

        return $latestWithData
            ? Carbon::parse($latestWithData)->startOfDay()
            : $latestSaturday;
    }

    /**
     * Karyawan dalam supervisor_employee_groups yang periodenya mencakup Sabtu.
     * Bila grup periode belum dibuat, fallback ke semua karyawan yang punya data.
     */
    private function employeeIdsForPeriod(Carbon $saturday): Collection
    {
        $date = $saturday->toDateString();

        $employeeIds = SupervisorEmployeeGroup::query()
            ->where('period_start', '<=', $date)
            ->where('period_end', '>=', $date)
            ->pluck('employee_id')
            ->unique()
            ->values();

        if ($employeeIds->isNotEmpty()) {
            return $employeeIds;
        }

        return AttendanceAutolog::query()
            ->whereBetween('date', [$saturday->copy()->subDays(5)->toDateString(), $date])
            ->distinct()
            ->pluck('employee_id');
    }
}
