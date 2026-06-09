<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LaporanLemburController extends Controller
{
    /**
     * Laporan Lembur Harian — per tanggal.
     * Source: att_prepares
     * Filter: karyawan dgn shift_roster + group terpilih
     */
    public function harian(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));
        $groups = $request->input('groups', []); // array of reference_code

        $parsedDate = Carbon::parse($date);

        // Ambil semua att_prepares untuk tanggal ini
        $prepares = AttendancePrepare::whereDate('date', $parsedDate)
            ->get()
            ->keyBy('employee_id');

        // Ambil employee yang punya roster di tanggal ini + group yg dipilih
        $employees = Employee::query()
            ->whereHas('shiftRosters', function ($q) use ($parsedDate) {
                $q->whereDate('date', $parsedDate);
            })
            ->when(!empty($groups), function ($q) use ($groups) {
                $q->whereHas('groups', function ($gq) use ($groups) {
                    $gq->whereIn('reference_code', $groups);
                });
            })
            ->with(['position', 'activeSalary'])
            ->get();

        // Cari periode payroll yang mencakup tanggal ini
        $period = PayPeriod::where('start_date', '<=', $parsedDate)
            ->where('end_date', '>=', $parsedDate)
            ->first();

        // Ambil pay_records untuk periode ini (index by employee_id + segment)
        $payRecords = collect();
        if ($period) {
            $payRecords = PayRecord::where('pay_period_id', $period->id)
                ->get()
                ->groupBy('employee_id');
        }

        // Ambil shift roster untuk lookup kode shift
        $rosters = EmployeeShiftRoster::whereDate('date', $parsedDate)
            ->with('shift')
            ->get()
            ->keyBy('employee_id');

        $data = $employees->map(function ($employee) use ($prepares, $payRecords, $period, $parsedDate, $rosters) {
            $prepare = $prepares->get($employee->id);
            $roster = $rosters->get($employee->id);

            // Ambil pay_record — handle split
            $empPayRecords = $payRecords->get($employee->id);
            $payRecord = null;

            if ($empPayRecords && $empPayRecords->isNotEmpty()) {
                if ($period && $period->is_split) {
                    // Untuk split: pilih segment berdasarkan tanggal
                    $day = (int) $parsedDate->day;
                    $cutOff = (int) ($period->cut_off_date ?? 25);
                    $segment = ($day >= $cutOff && $day <= 31) ? 'A' : 'B';
                    $payRecord = $empPayRecords->where('segment', $segment)->first()
                        ?? $empPayRecords->first();
                } else {
                    $payRecord = $empPayRecords->first();
                }
            }

            // Gaji
            if ($payRecord) {
                $gaji = (float) ($payRecord->gaji_pokok ?? 0);
                $tjMk = (float) ($payRecord->tj_masa_kerja ?? 0);
                $tunjangan = (float) ($payRecord->tunjangan ?? 0);
            } else {
                $gaji = $employee->baseSalary();
                $tjMk = $employee->gaji_pokok(); // fallback
                $tunjangan = (float) ($employee->activeSalary()?->tunjangan ?? 0);
            }

            $upahPerHari = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 25) : 0;
            $hourlyRate = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173) : 0;

            // Data lembur dari att_prepares
            $lm = $prepare ? (int) $prepare->lm : 0;           // menit
            $overtime = $prepare ? (int) $prepare->overtime : 0; // menit
            $totalMenit = $lm + $overtime;
            $uangLembur = $hourlyRate > 0 ? round($hourlyRate * ($totalMenit / 60)) : 0;

            // Shift kode dari roster
            $shiftKode = $roster && $roster->shift ? $roster->shift->external_code : '';
            $status = $prepare ? $prepare->status : '-';

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'jabatan' => $employee->position->name ?? '-',
                'gender' => $employee->gender ?? '',
                'tj_mk' => $tjMk,
                'tunjangan' => $tunjangan,
                'upah_per_hari' => $upahPerHari,
                'upah_lembur_per_jam' => $hourlyRate,
                'shift_kode' => $shiftKode,
                'status' => $status,
                'lembur_minggu' => $lm > 0 ? round($lm / 60, 2) : 0,
                'lembur' => $overtime > 0 ? round($overtime / 60, 2) : 0,
                'nominal' => $uangLembur,
                'group_name' => $employee->groups->pluck('reference_code')->first() ?? '',
            ];
        });

        return response()->json(['data' => $data->values()]);
    }

    /**
     * Laporan Lembur Bulanan — matrix per tahun.
     * Source: att_prepares (aggregate per bulan)
     * Filter: karyawan dgn shift_roster + group terpilih
     */
    public function bulanan(Request $request)
    {
        $year = (int) $request->input('year', date('Y'));
        $groups = $request->input('groups', []);

        // Ambil semua att_prepares di tahun ini
        $prepares = AttendancePrepare::whereYear('date', $year)
            ->get()
            ->groupBy('employee_id');

        // Ambil employee yang punya roster di tahun ini + group terpilih
        $employees = Employee::query()
            ->whereHas('shiftRosters', function ($q) use ($year) {
                $q->whereYear('date', $year);
            })
            ->when(!empty($groups), function ($q) use ($groups) {
                $q->whereHas('groups', function ($gq) use ($groups) {
                    $gq->whereIn('reference_code', $groups);
                });
            })
            ->with(['activeSalary'])
            ->get();

        // Ambil pay_records untuk tahun ini (untuk gaji)
        $periodIds = PayPeriod::whereYear('end_date', $year)->pluck('id');
        $payRecords = PayRecord::whereIn('pay_period_id', $periodIds)
            ->get()
            ->groupBy('employee_id');

        $data = $employees->map(function ($employee) use ($prepares, $payRecords) {
            $empPrepares = $prepares->get($employee->id, collect());
            $empPayRecords = $payRecords->get($employee->id, collect());

            // Ambil gaji terbaru dari pay_records atau fallback
            $latestPayRecord = $empPayRecords->sortByDesc('created_at')->first();
            if ($latestPayRecord) {
                $gaji = (float) ($latestPayRecord->gaji_pokok ?? 0);
                $tjMk = (float) ($latestPayRecord->tj_masa_kerja ?? 0);
                $tunjangan = (float) ($latestPayRecord->tunjangan ?? 0);
            } else {
                $gaji = $employee->baseSalary();
                $tjMk = $employee->gaji_pokok();
                $tunjangan = (float) ($employee->activeSalary()?->tunjangan ?? 0);
            }

            $hourlyRate = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173) : 0;

            // Aggregate per bulan
            $months = [];
            $preparesByMonth = $empPrepares->groupBy(function ($p) {
                return Carbon::parse($p->date)->month;
            });

            for ($m = 1; $m <= 12; $m++) {
                $monthPrepares = $preparesByMonth->get($m, collect());

                $lmTotal = $monthPrepares->sum('lm');               // menit mentah
                $overtimeTotal = $monthPrepares->sum('overtime');   // menit mentah
                $lmCountTotal = $monthPrepares->sum('lm_count');     // menit hasil multiplier
                $overtimeCountTotal = $monthPrepares->sum('overtime_count'); // menit hasil multiplier
                $totalMinutes = $lmCountTotal + $overtimeCountTotal;
                $overtimePay = $hourlyRate > 0 ? round($hourlyRate * ($totalMinutes / 60)) : 0;

                $months[$m] = [
                    'lm' => $lmTotal,
                    'overtime' => $overtimeTotal,
                    'calculated' => round(($lmCountTotal + $overtimeCountTotal) / 60, 2),
                    'hourlyRate' => $hourlyRate,
                    'overtimePay' => $overtimePay,
                ];
            }

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'gaji_pokok' => $gaji,
                'premi' => $tjMk,
                'tunjangan' => $tunjangan,
                'months' => $months,
                'group_name' => $employee->groups->pluck('reference_code')->first() ?? '',
            ];
        });

        return response()->json(['data' => $data->values()]);
    }
}
