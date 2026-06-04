<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Schedule\Models\Shift;
use Carbon\Carbon;

/**
 * Menghitung late, overtime, dan LM per record attendance prepare.
 * Port dari hris-system AttendanceCalculatorService — disederhanakan sesuai kolom baru.
 */
class AttendanceCalculatorService
{
    /**
     * Calculate all durations for a single AttendancePrepare record.
     *
     * @param  AttendancePrepare  $prepare   Record yang akan dihitung (bisa new / existing)
     * @param  Shift|null         $shift     Shift terkait (dari roster)
     * @param  bool               $isHoliday Apakah tanggal ini hari libur nasional
     * @param  bool               $isSunday  Apakah tanggal ini hari Minggu
     * @param  int|null           $manualOvertime  Overtime manual (dari user input)
     */
    public function calculate(
        AttendancePrepare $prepare,
        ?Shift $shift = null,
        bool $isHoliday = false,
        bool $isSunday = false,
        ?int $manualOvertime = null
    ): array {
        // ── Late Minutes ──────────────────────────────────────
        $lateMinutes = $this->calculateLate($prepare, $shift);

        // ── Overtime ──────────────────────────────────────────
        // Tentukan overtime dari manual input atau kalkulasi
        $rawOvertime = $manualOvertime !== null
            ? $manualOvertime
            : $this->calculateRawOvertime($prepare, $shift, $isHoliday, $isSunday);

        // ── LM vs Regular Overtime ────────────────────────────
        $isOffDay = $isHoliday || $isSunday;

        if ($isOffDay) {
            $lm         = $rawOvertime;
            $overtime   = 0;
            $lmCount    = $this->calculateLmMultiplier($lm);
            $overtimeCount = 0;
        } else {
            $lm         = 0;
            $overtime   = $rawOvertime;
            $lmCount    = 0;
            $overtimeCount = $this->calculateOvertimeMultiplier($overtime);
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
     * Hanya dihitung jika shift punya work_hour_start dan check_in tersedia.
     */
    protected function calculateLate(AttendancePrepare $prepare, ?Shift $shift): int
    {
        if (!$shift || !$shift->work_hour_start || !$prepare->check_in) {
            return 0;
        }

        // Shift dengan flexible check-in tidak dihitung late
        if (!empty($shift->shift_checkin_options)) {
            return 0;
        }

        $checkInTime = $prepare->check_in->startOfMinute();
        $scheduleIn  = Carbon::parse(
            $prepare->date->toDateString() . ' ' . $shift->work_hour_start
        )->startOfMinute();

        $tolerance   = $shift->tolerance_minutes ?? 30;
        $lateMinutes = $scheduleIn->diffInMinutes($checkInTime, false); // negatif = telat

        return max(0, $lateMinutes - $tolerance);
    }

    /**
     * Hitung overtime mentah dalam menit (sebelum multiplier).
     * Overtime = total menit kerja - jam normal.
     */
    protected function calculateRawOvertime(
        AttendancePrepare $prepare,
        ?Shift $shift,
        bool $isHoliday,
        bool $isSunday
    ): int {
        if (!$prepare->check_in || !$prepare->check_out) {
            return 0;
        }

        $checkIn  = $prepare->check_in->startOfMinute();
        $checkOut = $prepare->check_out->startOfMinute();

        if ($checkOut < $checkIn) {
            $checkOut->addDay();
        }

        $totalMinutes = $checkIn->diffInMinutes($checkOut);

        // Hari Minggu / Libur → semua jam kerja = overtime
        if ($isSunday || $isHoliday) {
            return min($totalMinutes, 480); // cap 8 jam
        }

        // Hari Sabtu → 6 jam normal
        $isSaturday = $prepare->date->isSaturday();
        if ($isSaturday) {
            $normalMinutes = 6 * 60; // 360 menit
        } else {
            $normalMinutes = 8 * 60; // 480 menit
        }

        $rawOvertime = max(0, $totalMinutes - $normalMinutes);

        return $this->roundUp($rawOvertime);
    }

    /**
     * LM Multiplier: ((base_hours) - 1) × 2
     * base = min(menit, 480) / 60
     */
    protected function calculateLmMultiplier(int $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        $hours = $minutes / 60;
        $clampedHours = min($hours, 8);

        // (max 8 jam - 1 jam istirahat) × 2
        $workHoursAfterRest = max(0, $clampedHours - 1);

        return (int) round($workHoursAfterRest * 2 * 60);
    }

    /**
     * Overtime Multiplier (hari kerja):
     * 60 menit pertama × 1.5, sisanya × 2
     */
    protected function calculateOvertimeMultiplier(int $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        if ($minutes <= 60) {
            return (int) round($minutes * 1.5);
        }

        $firstHour = 60 * 1.5; // 90
        $remaining = ($minutes - 60) * 2;

        return (int) round($firstHour + $remaining);
    }

    /**
     * Round up overtime per 30 menit (dengan threshold 5 menit).
     * 0-24 → 0, 25-54 → 30, 55-84 → 60, dst.
     */
    public function roundUp(int $minutes): int
    {
        return (int) floor(($minutes + 5) / 30) * 30;
    }
}
