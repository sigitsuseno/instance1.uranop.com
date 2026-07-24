<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Settings\Models\OvertimeCalculatorConfig;
use App\Modules\Settings\Models\OvertimeRule;
use Carbon\Carbon;

/**
 * Menghitung late, overtime, dan LM per record attendance prepare.
 * Multiplier diambil dari tabel overtime_rules (bisa dikonfigurasi per work pattern).
 * Fallback ke hardcoded jika rules tidak ditemukan.
 *
 * Nilai baseline (jam normal, max, flat, toleransi) sekarang dari tabel
 * attendance_calculator_configs — bisa dikonfigurasi per work pattern.
 */
class AttendanceCalculatorService
{
    /**
     * Calculate all durations for a single AttendancePrepare record.
     */
    public function calculate(
        AttendancePrepare $prepare,
        ?Shift $shift = null,
        bool $isHoliday = false,
        bool $isSunday = false,
        ?int $manualOvertime = null,
        ?string $workPatternType = null,
        bool $isSaturday = false,
        ?int $workPatternId = null,
    ): array {
        // Load config
        $config = OvertimeCalculatorConfig::forPattern($workPatternId);
        
        // Load dynamic setting (dari tabel payroll_configs)
        $settingConfig = \App\Modules\Payroll\Models\PayrollConfig::getConfig('attendance_overtime_setting');

        // ── Late Minutes ──────────────────────────────────────
        $lateMinutes = $this->calculateLate($prepare, $shift, $settingConfig);

        // ── Overtime ──────────────────────────────────────────
        $rawOvertime = $manualOvertime !== null
            ? $manualOvertime
            : $this->calculateRawOvertime($prepare, $isHoliday, $isSunday, $workPatternType, $isSaturday, $config, $shift, $settingConfig, $workPatternId);

        // ── LM vs Regular Overtime ────────────────────────────
        $isOffDay = $isHoliday || $isSunday;

        if ($isOffDay) {
            $lm             = $rawOvertime;
            $overtime       = 0;
            $lmCount        = $this->calculateLmMultiplier($lm, $workPatternId, $isSaturday, $config);
            $overtimeCount  = 0;
        } else {
            $lm             = 0;
            $overtime       = $rawOvertime;
            $lmCount        = 0;
            $overtimeCount  = $this->calculateOvertimeMultiplier($overtime, $workPatternId);
        }

        return [
            'late_minutes'   => $lateMinutes,
            'lm'             => $lm,
            'lm_count'       => $lmCount,
            'overtime'       => $overtime,
            'overtime_count' => $overtimeCount,
        ];
    }

    /**
     * Hitung keterlambatan dalam menit.
     *
     * FLEX-SHIFT S (external_code='S'): late_minutes SELALU 0 (hardcoded).
     */
    protected function calculateLate(AttendancePrepare $prepare, ?Shift $shift, array $settingConfig = []): int
    {
        if (!$shift || !$shift->work_hour_start || !$prepare->check_in) {
            return 0;
        }

        $zeroLateCodes = $settingConfig['zero_late_shift_codes'] ?? ['S'];

        // Cek dari konfigurasi dinamis (default: 'S')
        if (in_array($shift->external_code, $zeroLateCodes)) {
            return 0;
        }

        if (!empty($shift->shift_checkin_options)) {
            return 0;
        }

        $checkInTime = $prepare->check_in->startOfMinute();
        $scheduleIn  = Carbon::parse(
            $prepare->date->toDateString() . ' ' . $shift->work_hour_start
        )->startOfMinute();

        $tolerance   = $shift->tolerance_minutes ?? 30;
        $lateMinutes = $scheduleIn->diffInMinutes($checkInTime, false);

        return max(0, $lateMinutes - $tolerance);
    }

    /**
     * Hitung overtime mentah dalam menit (sebelum multiplier).
     */
    protected function calculateRawOvertime(
        AttendancePrepare $prepare,
        bool $isHoliday,
        bool $isSunday,
        ?string $workPatternType = null,
        bool $isSaturday = false,
        ?OvertimeCalculatorConfig $config = null,
        ?Shift $shift = null,
        array $settingConfig = [],
        ?int $workPatternId = null,
    ): int {
        if (!$prepare->check_in || !$prepare->check_out) {
            return 0;
        }

        // Pastikan config selalu tersedia
        $config = $config ?? new OvertimeCalculatorConfig();

        $checkIn  = $prepare->check_in->startOfMinute();
        $checkOut = $prepare->check_out->startOfMinute();

        // Overnight check-out
        if ($checkOut < $checkIn) {
            $checkOut->addDay();
        }

        $totalMinutes = $checkIn->diffInMinutes($checkOut, true);

        // Dynamic rounding overrides from PayrollConfig (modal Setting)
        $roundingThreshold = $settingConfig['rounding_threshold'] ?? null;
        $roundingInterval  = $settingConfig['rounding_interval'] ?? null;

        // ═══════════════════════════════════════════════════════
        // SHIFT Pattern
        // ═══════════════════════════════════════════════════════
        $workHoursConfig = $settingConfig['work_hours'] ?? [
            'FIXED' => ['weekday' => 540, 'saturday' => 360],
            'FLEX-SHIFT' => ['weekday' => 480, 'saturday' => 360],
            'SHIFT' => ['weekday' => 480, 'saturday' => 360],
            'PL' => ['weekday' => 480, 'saturday' => 360],
            'PL2' => ['weekday' => 540, 'saturday' => 360],
        ];

        if ($workPatternType === 'SHIFT') {
            // Holiday: 8 jam fix (prioritas, meskipun jatuh di Minggu)
            if ($isHoliday) {
                return 480;
            }
            // Sabtu: 2 jam fix
            if ($isSaturday) {
                return 120;
            }
            // Weekday (Sen-Jum) & Minggu (non-holiday):
            // Hitung raw overtime dulu (total - jam kerja normal 8 jam)
            $shiftWd = $workHoursConfig['SHIFT']['weekday'] ?? ($config->normal_work_minutes ?? 480);
            $rawOvertime = max(0, $totalMinutes - $shiftWd);
            // Kalau 3-5 jam (180-300 menit) → 4 jam fix
            // Kalau < 3 jam atau > 5 jam → 0 (ngakomodir yg jaga tambahan karena rekan cuti)
            if ($rawOvertime >= 180 && $rawOvertime <= 300) {
                return 240;
            }
            return 0;
        }

        // ═══════════════════════════════════════════════════════
        // Holiday / Minggu (FIXED & FLEX-SHIFT): full, max capped
        // ═══════════════════════════════════════════════════════
        if ($isHoliday || $isSunday) {
            $tknRule = $settingConfig['technician_rule'] ?? [
                'employee_ids' => [31, 115, 174],
                'start_date' => '2026-06-01',
                'max_holiday_minutes' => 1200
            ];
            
            $isTkn = $prepare->date >= $tknRule['start_date']
                && in_array($prepare->employee_id, $tknRule['employee_ids'] ?? []);
                
            $maxMinutes = $isTkn ? ($tknRule['max_holiday_minutes'] ?? 1200) : ($config->holiday_max_minutes ?? 480);

            return $this->roundUp(min($totalMinutes, $maxMinutes), $config, $roundingThreshold, $roundingInterval);
        }

        // ═══════════════════════════════════════════════════════
        // Hari kerja biasa (FIXED & FLEX-SHIFT):
        // ═══════════════════════════════════════════════════════
        
        // ── Tentukan baseline jam kerja (deduction) ──
        // Prioritas 1: Dari tabel WorkPattern (work_day_hours — sudah termasuk istirahat)
        $deduction = null;
        $workPattern = null;
        if ($workPatternId) {
            $workPattern = \App\Modules\Schedule\Models\WorkPattern::find($workPatternId);
            if ($workPattern && $workPattern->work_day_hours !== null) {
                if ($isSaturday && $workPattern->sat_type === 'half') {
                    $deduction = ($workPattern->half_day_hours ?? 0) * 60;
                } else {
                    $deduction = $workPattern->work_day_hours * 60;
                }
            }
        }

        // Prioritas 2: Fallback ke setting modal / config kalo WorkPattern null
        if ($deduction === null) {
            $workPatternCode = $workPattern?->code;
            $patternKey = $workPatternType ?? 'FIXED';

            if ($workPatternCode && isset($workHoursConfig[$workPatternCode])) {
                $patternHours = $workHoursConfig[$workPatternCode];
            } else {
                $patternHours = $workHoursConfig[$patternKey] ?? $workHoursConfig['FIXED'];
            }

            $deduction = $isSaturday
                ? ($patternHours['saturday'] ?? ($config->saturday_work_minutes ?? 360))
                : ($patternHours['weekday'] ?? ($config->normal_work_minutes ?? 480));
        }

        // Tentukan Rumus (Strategy)
        $formulaToUse = 'rumus_1';
        $specialEmployees = $settingConfig['special_employees'] ?? [];
        $formulas = $settingConfig['formulas'] ?? [];

        if (in_array($prepare->employee_id, $specialEmployees['ids'] ?? [])) {
            $formulaToUse = $specialEmployees['formula'] ?? 'rumus_1';
        } else {
            if ($workPatternType === 'FIXED') {
                $formulaToUse = $formulas['FIXED'] ?? 'rumus_1';
            } elseif ($workPatternType === 'FLEX-SHIFT') {
                if ($shift && $shift->external_code === 'S') {
                    $formulaToUse = $formulas['FLEX_S'] ?? 'rumus_1';
                } elseif ($shift && $shift->external_code === 'P') {
                    $formulaToUse = $formulas['FLEX_P'] ?? 'rumus_1';
                }
            }
        }

        // Eksekusi Rumus
        if ($formulaToUse === 'rumus_2' && $shift && $shift->work_hour_end) {
            $scheduleOut = Carbon::parse($prepare->date->toDateString() . ' ' . $shift->work_hour_end)->startOfMinute();
            
            // Handle overnight shift schedule_out
            if ($shift->work_hour_start) {
                $scheduleIn = Carbon::parse($prepare->date->toDateString() . ' ' . $shift->work_hour_start)->startOfMinute();
                if ($scheduleOut < $scheduleIn) {
                    $scheduleOut->addDay();
                }
            }

            if ($checkOut > $scheduleOut) {
                $overtimeMinutes = $scheduleOut->diffInMinutes($checkOut, true);
                return $overtimeMinutes > 0 ? $this->roundUp($overtimeMinutes, $config, $roundingThreshold, $roundingInterval) : 0;
            } else {
                return 0;
            }
        }

        // Default to rumus_1 (Scan In - Scan Out dikurangi deduction jam kerja)
        $overtimeMinutes = max(0, $totalMinutes - $deduction);
        return $overtimeMinutes > 0 ? $this->roundUp($overtimeMinutes, $config, $roundingThreshold, $roundingInterval) : 0;
    }

    // ═══════════════════════════════════════════════════════════
    // MULTIPLIER — dari tabel overtime_rules
    // ═══════════════════════════════════════════════════════════

    protected function calculateLmMultiplier(int $minutes, ?int $workPatternId = null, bool $isSaturday = false, ?OvertimeCalculatorConfig $config = null): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        $config = $config ?? new OvertimeCalculatorConfig();
        $deduction = $config->lm_rest_deduction ?? 60;

        // Potong istirahat
        $effectiveMinutes = max(0, $minutes - $deduction);

        return $this->multiplyFromRule($effectiveMinutes, isHoliday: true, maxHours: 8, workPatternId: $workPatternId, isSaturday: $isSaturday)
            ?? $this->calculateLmMultiplierFallback($effectiveMinutes);
    }

    protected function calculateOvertimeMultiplier(int $minutes, ?int $workPatternId = null): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        if ($minutes < 60) {
            return $minutes;
        }

        return $this->multiplyFromRule($minutes, isHoliday: false, maxHours: null, workPatternId: $workPatternId)
            ?? $this->calculateOvertimeMultiplierFallback($minutes);
    }

    protected function multiplyFromRule(
        int $minutes,
        bool $isHoliday,
        ?int $maxHours = null,
        ?int $workPatternId = null,
        bool $isSaturday = false,
    ): ?int {
        $rule = OvertimeRule::where('is_active', true)
            ->where('is_holiday', $isHoliday)
            ->where('is_saturday', $isSaturday)
            ->when($workPatternId, function ($q) use ($workPatternId) {
                $q->where('work_pattern_id', $workPatternId);
            }, function ($q) {
                $q->whereNull('work_pattern_id');
            })
            ->first();

        if (!$rule && $workPatternId) {
            $rule = OvertimeRule::where('is_active', true)
                ->where('is_holiday', $isHoliday)
                ->where('is_saturday', $isSaturday)
                ->whereNull('work_pattern_id')
                ->first();
        }

        if (!$rule) {
            return null;
        }

        $details = $rule->details()->orderBy('hour')->get();

        if ($details->isEmpty()) {
            return null;
        }

        $remainingMinutes = $minutes;
        if ($maxHours !== null) {
            $remainingMinutes = min($remainingMinutes, $maxHours * 60);
        }

        $total = 0.0;
        $currentHour = 1;

        while ($remainingMinutes > 0) {
            $detail = $details->filter(fn($d) => $d->hour <= $currentHour)->sortByDesc('hour')->first();

            if (!$detail) {
                break;
            }

            $minutesInBlock = min($remainingMinutes, 60);
            $total += $minutesInBlock * (float) $detail->multiplier;
            $remainingMinutes -= $minutesInBlock;
            $currentHour++;
        }

        return (int) round($total);
    }

    // ═══════════════════════════════════════════════════════════
    // FALLBACK
    // ═══════════════════════════════════════════════════════════

    protected function calculateLmMultiplierFallback(int $minutes): int
    {
        $hours = $minutes / 60;
        $clampedHours = min($hours, 8);

        return (int) round($clampedHours * 2 * 60);
    }

    protected function calculateOvertimeMultiplierFallback(int $minutes): int
    {
        if ($minutes <= 60) {
            return (int) round($minutes * 1.5);
        }

        $firstHour = 60 * 1.5;
        $remaining = ($minutes - 60) * 2;

        return (int) round($firstHour + $remaining);
    }

    // ═══════════════════════════════════════════════════════════
    // UTILITY
    // ═══════════════════════════════════════════════════════════

    /**
     * Round up overtime per interval (default 30 menit) dengan threshold.
     * 0-(t-1) → 0, t-(i+t-1) → i, dst.
     * Default: i=30, t=5 → 0-24→0, 25-54→30, 55-84→60
     */
    public function roundUp(int $minutes, ?OvertimeCalculatorConfig $config = null, ?int $thresholdOverride = null, ?int $intervalOverride = null): int
    {
        $config    = $config ?? new OvertimeCalculatorConfig();
        $interval  = $intervalOverride ?? $config->rounding_interval ?? 30;
        $threshold = $thresholdOverride ?? $config->rounding_threshold ?? 5;

        if ($interval <= 0) {
            return $minutes;
        }

        return (int) floor(($minutes + $threshold) / $interval) * $interval;
    }

    // ═══════════════════════════════════════════════════════════
    //  MANUAL CALC — tanpa AttendancePrepare (buat Supervisor)
    // ═══════════════════════════════════════════════════════════

    /**
     * Hitung overtime/LM multiplier tanpa perlu AttendancePrepare.
     *
     * Dipanggil dari Supervisor adjustment (Perhitungan Lembur).
     * ManualOvertime = raw menit lembur dari tabel supervisor.
     * Multiplier dari OvertimeRule (per work_pattern_id).
     * Fallback ke hardcoded jika rule tidak ditemukan.
     *
     * Untuk SHIFT (satpam), aturan khusus:
     * - Holiday (non-Sabtu): (jam-1) × 2
     * - Holiday + Sabtu: progressive jam1-5×2, jam6×3, jam7+×4
     */
    public function calculateManual(
        int $manualOvertimeMinutes,
        ?int $workPatternId = null,
        bool $isHoliday = false,
        bool $isSunday = false,
        bool $isSaturday = false,
        ?string $workPatternType = null,
    ): array {
        $isOffDay = $isHoliday || $isSunday;

        if ($isOffDay && $workPatternType === 'SHIFT') {
            // ── SHIFT khusus: holiday / libur ──
            $overtimeHours = $manualOvertimeMinutes / 60;
            $remainingHours = max(0, $overtimeHours - 1);
            $lmCount = 0;

            if ($isSaturday) {
                // Progressive: jam1-5 x2, jam6 x3, jam7+ x4
                for ($i = 1; $i <= ceil($remainingHours); $i++) {
                    $seg = min(1, max(0, $remainingHours - ($i - 1)));
                    $mult = match (true) { $i <= 5 => 2, $i === 6 => 3, default => 4 };
                    $lmCount += $seg * $mult;
                }
                $lmCount = (int) round($lmCount * 60);
            } else {
                // Flat: (jam-1) x 2
                $lmCount = (int) round($remainingHours * 2 * 60);
            }

            return [
                'late_minutes'   => 0,
                'lm'             => $manualOvertimeMinutes,
                'lm_count'       => $lmCount,
                'overtime'       => 0,
                'overtime_count' => 0,
            ];
        }

        if ($isOffDay) {
            // ── Non-SHIFT off-day: pake OvertimeRule / fallback ──
            return [
                'late_minutes'   => 0,
                'lm'             => $manualOvertimeMinutes,
                'lm_count'       => $this->calculateLmMultiplier($manualOvertimeMinutes, $workPatternId, $isSaturday),
                'overtime'       => 0,
                'overtime_count' => 0,
            ];
        }

        // ── Workday (semua tipe) ──
        return [
            'late_minutes'   => 0,
            'lm'             => 0,
            'lm_count'       => 0,
            'overtime'       => $manualOvertimeMinutes,
            'overtime_count' => $this->calculateOvertimeMultiplier($manualOvertimeMinutes, $workPatternId),
        ];
    }
}
