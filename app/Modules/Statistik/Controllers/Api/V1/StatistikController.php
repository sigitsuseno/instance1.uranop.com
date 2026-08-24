<?php

namespace App\Modules\Statistik\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatistikController extends Controller
{
    /**
     * Statistik Kehadiran dari att_prepares
     * GET /api/v1/statistik/kehadiran?range=harian|bulanan|semester&date=YYYY-MM-DD&month=YYYY-MM
     */
    public function kehadiran(Request $request): JsonResponse
    {
        $range = $request->query('range', 'harian');
        $range = in_array($range, ['harian', 'bulanan', 'semester']) ? $range : 'harian';

        if ($range === 'harian') {
            return $this->kehadiranHarian($request);
        }
        if ($range === 'bulanan') {
            return $this->kehadiranBulanan($request);
        }
        return $this->kehadiranSemester($request);
    }

    private function kehadiranHarian(Request $request): JsonResponse
    {
        $dateStr = $request->query('date', now()->toDateString());
        try {
            $date = Carbon::parse($dateStr)->toDateString();
        } catch (\Throwable $e) {
            $date = now()->toDateString();
        }

        $base = AttendancePrepare::whereDate('date', $date);

        $total = (clone $base)->count();
        $hadir = (clone $base)->where('status', 'hadir')->count();
        $absent = (clone $base)->where('status', 'absent')->count();
        $libur = (clone $base)->where('status', 'libur')->count();
        $off = (clone $base)->where('status', 'off')->count();
        // cuti = semua status yang bukan hadir/absent/libur/off
        $cuti = (clone $base)->whereNotIn('status', ['hadir', 'absent', 'libur', 'off'])->count();

        $overtimeSum = (clone $base)->sum('overtime');
        $overtimeCountSum = (clone $base)->sum('overtime_count');
        $lmSum = (clone $base)->sum('lm');
        $lmCountSum = (clone $base)->sum('lm_count');
        $lateSum = (clone $base)->sum('late_minutes');

        // breakdown cuti by kode (CT, CM, SKT, dll) — top 5
        $cutiBreakdown = (clone $base)
            ->whereNotIn('status', ['hadir', 'absent', 'libur', 'off'])
            ->select('status', DB::raw('COUNT(*) as jml'))
            ->groupBy('status')
            ->orderByDesc('jml')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'label' => $this->statusLabel($r->status), 'count' => (int) $r->jml]);

        // overtime top employees hari itu (optional preview)
        $topOvertime = (clone $base)
            ->with('employee:id,name')
            ->where('overtime', '>', 0)
            ->orderByDesc('overtime')
            ->limit(5)
            ->get(['employee_id', 'status', 'overtime', 'late_minutes'])
            ->map(fn ($r) => [
                'employee' => $r->employee?->name ?? '-',
                'status' => $r->status,
                'overtime' => (int) $r->overtime,
                'late' => (int) $r->late_minutes,
            ]);

        return response()->json([
            'range' => 'harian',
            'date' => $date,
            'summary' => [
                'total' => $total,
                'hadir' => $hadir,
                'absent' => $absent,
                'libur' => $libur,
                'off' => $off,
                'cuti' => $cuti,
            ],
            'totals' => [
                'overtime_minutes' => (int) $overtimeSum,
                'overtime_count' => (int) $overtimeCountSum,
                'lm_minutes' => (int) $lmSum,
                'lm_count' => (int) $lmCountSum,
                'late_minutes' => (int) $lateSum,
            ],
            'cuti_breakdown' => $cutiBreakdown,
            'top_overtime' => $topOvertime,
            'chart' => [
                ['label' => 'Hadir', 'value' => $hadir, 'color' => '#10b981'],
                ['label' => 'Absent', 'value' => $absent, 'color' => '#ef4444'],
                ['label' => 'Cuti', 'value' => $cuti, 'color' => '#3b82f6'],
                ['label' => 'Libur', 'value' => $libur, 'color' => '#9ca3af'],
                ['label' => 'Off', 'value' => $off, 'color' => '#6b7280'],
            ],
        ]);
    }

    private function kehadiranBulanan(Request $request): JsonResponse
    {
        $monthStr = $request->query('month', $request->query('date', now()->format('Y-m')));
        // normalize: allow YYYY-MM or YYYY-MM-DD
        try {
            $carbon = Carbon::parse($monthStr);
            $year = $carbon->year;
            $month = $carbon->month;
        } catch (\Throwable $e) {
            $year = now()->year;
            $month = now()->month;
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth();

        $base = AttendancePrepare::whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        $total = (clone $base)->count();
        $hadir = (clone $base)->where('status', 'hadir')->count();
        $absent = (clone $base)->where('status', 'absent')->count();
        $libur = (clone $base)->where('status', 'libur')->count();
        $off = (clone $base)->where('status', 'off')->count();
        $cuti = (clone $base)->whereNotIn('status', ['hadir', 'absent', 'libur', 'off'])->count();

        // daily trend
        $daily = (clone $base)
            ->selectRaw('date, COUNT(*) as total, SUM(CASE WHEN status="hadir" THEN 1 ELSE 0 END) as hadir, SUM(CASE WHEN status="absent" THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN status NOT IN ("hadir","absent","libur","off") THEN 1 ELSE 0 END) as cuti')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date' => Carbon::parse($r->date)->format('Y-m-d'),
                'label' => Carbon::parse($r->date)->format('d'),
                'total' => (int) $r->total,
                'hadir' => (int) $r->hadir,
                'absent' => (int) $r->absent,
                'cuti' => (int) $r->cuti,
            ]);

        // fill missing days with zero (biar chart konsisten 1-31)
        $daysInMonth = $start->daysInMonth;
        $dailyMap = $daily->keyBy('date');
        $dailyFull = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dt = Carbon::create($year, $month, $d)->toDateString();
            if (isset($dailyMap[$dt])) {
                $dailyFull[] = $dailyMap[$dt];
            } else {
                $dailyFull[] = ['date' => $dt, 'label' => sprintf('%02d', $d), 'total' => 0, 'hadir' => 0, 'absent' => 0, 'cuti' => 0];
            }
        }

        $cutiBreakdown = (clone $base)
            ->whereNotIn('status', ['hadir', 'absent', 'libur', 'off'])
            ->select('status', DB::raw('COUNT(*) as jml'))
            ->groupBy('status')
            ->orderByDesc('jml')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'label' => $this->statusLabel($r->status), 'count' => (int) $r->jml]);

        return response()->json([
            'range' => 'bulanan',
            'month' => sprintf('%04d-%02d', $year, $month),
            'month_label' => Carbon::create($year, $month, 1)->locale('id')->translatedFormat('F Y'),
            'summary' => [
                'total' => $total,
                'hadir' => $hadir,
                'absent' => $absent,
                'libur' => $libur,
                'off' => $off,
                'cuti' => $cuti,
            ],
            'daily_trend' => $dailyFull,
            'cuti_breakdown' => $cutiBreakdown,
            'chart' => [
                ['label' => 'Hadir', 'value' => $hadir, 'color' => '#10b981'],
                ['label' => 'Absent', 'value' => $absent, 'color' => '#ef4444'],
                ['label' => 'Cuti', 'value' => $cuti, 'color' => '#3b82f6'],
                ['label' => 'Libur', 'value' => $libur, 'color' => '#9ca3af'],
                ['label' => 'Off', 'value' => $off, 'color' => '#6b7280'],
            ],
        ]);
    }

    private function kehadiranSemester(Request $request): JsonResponse
    {
        // semester = 6 bulan terakhir (termasuk bulan ini) ATAU param month sebagai anchor
        $anchorStr = $request->query('month', $request->query('date', now()->format('Y-m')));
        try {
            $anchor = Carbon::parse($anchorStr)->endOfMonth();
        } catch (\Throwable $e) {
            $anchor = now()->endOfMonth();
        }
        $start = (clone $anchor)->subMonths(5)->startOfMonth();
        $end = (clone $anchor)->endOfMonth();

        $base = AttendancePrepare::whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        $total = (clone $base)->count();
        $hadir = (clone $base)->where('status', 'hadir')->count();
        $absent = (clone $base)->where('status', 'absent')->count();
        $libur = (clone $base)->where('status', 'libur')->count();
        $off = (clone $base)->where('status', 'off')->count();
        $cuti = (clone $base)->whereNotIn('status', ['hadir', 'absent', 'libur', 'off'])->count();

        // monthly trend
        $monthly = (clone $base)
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as ym, COUNT(*) as total, SUM(CASE WHEN status="hadir" THEN 1 ELSE 0 END) as hadir, SUM(CASE WHEN status="absent" THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN status NOT IN ("hadir","absent","libur","off") THEN 1 ELSE 0 END) as cuti')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        $trend = [];
        $cursor = (clone $start)->copy();
        while ($cursor->lte($end)) {
            $ym = $cursor->format('Y-m');
            $row = $monthly->get($ym);
            $trend[] = [
                'ym' => $ym,
                'label' => $cursor->locale('id')->translatedFormat('M y'),
                'total' => $row ? (int) $row->total : 0,
                'hadir' => $row ? (int) $row->hadir : 0,
                'absent' => $row ? (int) $row->absent : 0,
                'cuti' => $row ? (int) $row->cuti : 0,
            ];
            $cursor->addMonth();
        }

        $cutiBreakdown = (clone $base)
            ->whereNotIn('status', ['hadir', 'absent', 'libur', 'off'])
            ->select('status', DB::raw('COUNT(*) as jml'))
            ->groupBy('status')
            ->orderByDesc('jml')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'label' => $this->statusLabel($r->status), 'count' => (int) $r->jml]);

        return response()->json([
            'range' => 'semester',
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $start->locale('id')->translatedFormat('M Y') . ' – ' . $end->locale('id')->translatedFormat('M Y') . ' (6 bln)',
            'summary' => [
                'total' => $total,
                'hadir' => $hadir,
                'absent' => $absent,
                'libur' => $libur,
                'off' => $off,
                'cuti' => $cuti,
            ],
            'monthly_trend' => $trend,
            'cuti_breakdown' => $cutiBreakdown,
            'chart' => [
                ['label' => 'Hadir', 'value' => $hadir, 'color' => '#10b981'],
                ['label' => 'Absent', 'value' => $absent, 'color' => '#ef4444'],
                ['label' => 'Cuti', 'value' => $cuti, 'color' => '#3b82f6'],
                ['label' => 'Libur', 'value' => $libur, 'color' => '#9ca3af'],
                ['label' => 'Off', 'value' => $off, 'color' => '#6b7280'],
            ],
        ]);
    }

    /**
     * Statistik Payroll dari pay_records
     * GET /api/v1/statistik/payroll?range=bulanan|semester
     */
    public function payroll(Request $request): JsonResponse
    {
        $range = $request->query('range', 'bulanan');
        $range = in_array($range, ['bulanan', 'semester']) ? $range : 'bulanan';

        // ambil 6 periode terakhir yang sudah punya pay_records atau yang start_date <= now
        $periods = PayPeriod::where('start_date', '<=', now()->toDateString())
            ->orderBy('start_date', 'desc')
            ->limit(12)
            ->get()
            ->sortBy('start_date')
            ->values();

        if ($periods->isEmpty()) {
            return response()->json([
                'range' => $range,
                'periods' => [],
                'summary' => ['total_kotor' => 0, 'total_bersih' => 0, 'avg_kotor' => 0, 'avg_bersih' => 0, 'count' => 0],
                'chart' => [],
                'breakdown' => [],
            ]);
        }

        // untuk semester, ambil 6 terbaru; untuk bulanan ambil 6 juga (konsisten preview)
        $take = $range === 'semester' ? 6 : 6;
        $periods = $periods->slice(max(0, $periods->count() - $take))->values();

        $ids = $periods->pluck('id');
        $sums = PayRecord::whereIn('pay_period_id', $ids)
            ->selectRaw('pay_period_id, COUNT(*) as jml, SUM(gaji_kotor) as total_kotor, SUM(gaji_bersih) as total_bersih, SUM(upah_lembur) as total_lembur, SUM(pph) as total_pph, SUM(bpjs_tk+bpjs_ks+bpjs_pen) as total_bpjs, SUM(cashbon) as total_cashbon')
            ->groupBy('pay_period_id')
            ->get()
            ->keyBy('pay_period_id');

        $chart = [];
        $totalKotor = 0;
        $totalBersih = 0;
        $totalCount = 0;

        foreach ($periods as $p) {
            $row = $sums->get($p->id);
            $kotor = (float) ($row->total_kotor ?? 0);
            $bersih = (float) ($row->total_bersih ?? 0);
            $jml = (int) ($row->jml ?? 0);
            $totalKotor += $kotor;
            $totalBersih += $bersih;
            $totalCount += $jml;

            if ($p->period_year && $p->period_month) {
                $label = Carbon::create($p->period_year, $p->period_month, 1)->locale('id')->translatedFormat('M y');
            } else {
                $label = $p->start_date ? $p->start_date->locale('id')->translatedFormat('M y') : $p->name;
            }

            $chart[] = [
                'period_id' => $p->id,
                'period_name' => $p->name,
                'label' => $label,
                'start_date' => $p->start_date?->toDateString(),
                'end_date' => $p->end_date?->toDateString(),
                'count' => $jml,
                'total_kotor' => $kotor,
                'total_bersih' => $bersih,
                'total_lembur' => (float) ($row->total_lembur ?? 0),
                'total_pph' => (float) ($row->total_pph ?? 0),
                'total_bpjs' => (float) ($row->total_bpjs ?? 0),
                'total_cashbon' => (float) ($row->total_cashbon ?? 0),
            ];
        }

        // semester summary: total & avg
        $avgKotor = $chart ? $totalKotor / count($chart) : 0;
        $avgBersih = $chart ? $totalBersih / count($chart) : 0;

        // breakdown komponen untuk donut (agregat semua periode di chart)
        $breakdown = [
            ['label' => 'Gaji Bersih', 'value' => $totalBersih, 'color' => '#10b981'],
            ['label' => 'BPJS', 'value' => (float) PayRecord::whereIn('pay_period_id', $ids)->selectRaw('SUM(bpjs_tk+bpjs_ks+bpjs_pen) as v')->value('v') ?? 0, 'color' => '#3b82f6'],
            ['label' => 'PPh 21', 'value' => (float) PayRecord::whereIn('pay_period_id', $ids)->sum('pph'), 'color' => '#f59e0b'],
            ['label' => 'Kasbon', 'value' => (float) PayRecord::whereIn('pay_period_id', $ids)->sum('cashbon'), 'color' => '#ef4444'],
        ];

        return response()->json([
            'range' => $range,
            'periods' => $chart,
            'summary' => [
                'total_kotor' => $totalKotor,
                'total_bersih' => $totalBersih,
                'avg_kotor' => $avgKotor,
                'avg_bersih' => $avgBersih,
                'count' => $totalCount,
                'period_count' => count($chart),
            ],
            'chart' => $chart,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Komposisi Total Gaji per Position (pie chart).
     * GET /api/v1/statistik/payroll/komposisi?month=YYYY-MM
     */
    public function komposisi(Request $request): JsonResponse
    {
        // Daftar bulan yang punya pay_records (untuk dropdown)
        $months = PayRecord::query()
            ->join('pay_periods', 'pay_periods.id', '=', 'pay_records.pay_period_id')
            ->whereNotNull('pay_periods.period_year')
            ->whereNotNull('pay_periods.period_month')
            ->selectRaw('pay_periods.period_year as y, pay_periods.period_month as m, COUNT(*) as jml')
            ->groupBy('pay_periods.period_year', 'pay_periods.period_month')
            ->orderByRaw('pay_periods.period_year DESC, pay_periods.period_month DESC')
            ->get()
            ->map(fn ($r) => [
                'ym'    => sprintf('%04d-%02d', $r->y, $r->m),
                'label' => Carbon::create($r->y, $r->m, 1)->locale('id')->translatedFormat('F Y'),
                'count' => (int) $r->jml,
            ]);

        $defaultMonth = $months->first()['ym'] ?? now()->format('Y-m');
        $monthStr = $request->query('month', $defaultMonth);

        $parts = explode('-', $monthStr);
        $year = (int) ($parts[0] ?? now()->year);
        $month = max(1, min(12, (int) ($parts[1] ?? now()->month)));

        // Periode(s) untuk bulan tsb (bisa lebih dari satu bila split period)
        $periodIds = PayPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->pluck('id');

        if ($periodIds->isEmpty()) {
            return response()->json([
                'months' => $months,
                'month' => $monthStr,
                'month_label' => Carbon::create($year, $month, 1)->locale('id')->translatedFormat('F Y'),
                'total' => 0,
                'data' => [],
            ]);
        }

        $rows = PayRecord::query()
            ->join('employees', 'employees.id', '=', 'pay_records.employee_id')
            ->leftJoin('positions', 'positions.id', '=', 'employees.position_id')
            ->whereIn('pay_records.pay_period_id', $periodIds)
            ->selectRaw('positions.id as position_id, COALESCE(positions.name, "Tanpa Jabatan") as position, COUNT(*) as jml, SUM(pay_records.gaji_kotor) as gaji')
            ->groupBy('positions.id', 'position')
            ->orderByDesc('gaji')
            ->get()
            ->map(fn ($r) => [
                'position_id' => $r->position_id,
                'position' => $r->position,
                'gaji' => (float) $r->gaji,
                'count' => (int) $r->jml,
            ]);

        $total = (float) $rows->sum('gaji');

        return response()->json([
            'months' => $months,
            'month' => $monthStr,
            'month_label' => Carbon::create($year, $month, 1)->locale('id')->translatedFormat('F Y'),
            'total' => $total,
            'data' => $rows->values(),
        ]);
    }

    private function statusLabel(string $status): string
    {
        $map = [
            'hadir' => 'Hadir',
            'absent' => 'Absen',
            'libur' => 'Libur',
            'off' => 'Off',
        ];
        if (isset($map[$status])) return $map[$status];
        // leave type fallback: uppercase code
        return strtoupper($status);
    }
}
