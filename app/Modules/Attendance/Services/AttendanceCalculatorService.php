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

        // ── Late Minutes ──────────────────────────────────────
        $lateMinutes = $this->calculateLate($prepare, $shift);

        // ── Overtime ──────────────────────────────────────────
        $rawOvertime = $manualOvertime !== null
            ? $manualOvertime
            : $this->calculateRawOvertime($prepare, $isHoliday, $isSunday, $workPatternType, $isSaturday, $config);

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
    protected function calculateLate(AttendancePrepare $prepare, ?Shift $shift): int
    {
        if (!$shift || !$shift->work_hour_start || !$prepare->check_in) {
            return 0;
        }

        // FLEX-SHIFT S: late_minutes = 0 (hardcoded)
        if ($shift->external_code === 'S') {
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

        // ═══════════════════════════════════════════════════════
        // SHIFT Pattern
        // ═══════════════════════════════════════════════════════
        if ($workPatternType === 'SHIFT') {
            if ($isHoliday) {
                return $this->roundUp(min($totalMinutes, $config->holiday_max_minutes ?? 480), $config);
            }
            if ($isSaturday) {
                return $config->shift_saturday_flat ?? 120;
            }
            return 0;
        }

        // ═══════════════════════════════════════════════════════
        // Holiday / Minggu (FIXED & FLEX-SHIFT): full, max capped
        // ═══════════════════════════════════════════════════════
        if ($isHoliday || $isSunday) {
            // KRY-TKN: mulai Juni 2026, teknisi maksimal 20 jam lembur holiday
            $isTkn = $prepare->date >= '2026-06-01'
                && in_array($prepare->employee_id, [31, 115, 174]);
            $maxMinutes = $isTkn ? 1200 : ($config->holiday_max_minutes ?? 480);

            return $this->roundUp(min($totalMinutes, $maxMinutes), $config);
        }

        // ═══════════════════════════════════════════════════════
        // Hari kerja biasa (FIXED & FLEX-SHIFT):
        // OT = jarak check_in → check_out dikurangi jam normal
        // ═══════════════════════════════════════════════════════
        $deduction = $isSaturday
            ? ($config->saturday_work_minutes ?? 360)
            : ($config->normal_work_minutes ?? 480);

        $overtimeMinutes = max(0, $totalMinutes - $deduction);
        return $overtimeMinutes > 0 ? $this->roundUp($overtimeMinutes, $config) : 0;
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
    public function roundUp(int $minutes, ?OvertimeCalculatorConfig $config = null): int
    {
        $config   = $config ?? new OvertimeCalculatorConfig();
        $interval = $config->rounding_interval ?? 30;
        $threshold = $config->rounding_threshold ?? 5;

        if ($interval <= 0) {
            return $minutes;
        }

        return (int) floor(($minutes + $threshold) / $interval) * $interval;
    }
}
