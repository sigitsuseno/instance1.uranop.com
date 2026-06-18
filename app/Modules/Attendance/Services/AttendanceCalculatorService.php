<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Settings\Models\OvertimeRule;
use Carbon\Carbon;

/**
 * Menghitung late, overtime, dan LM per record attendance prepare.
 * Multiplier diambil dari tabel overtime_rules (bisa dikonfigurasi per work pattern).
 * Fallback ke hardcoded jika rules tidak ditemukan.
 */
class AttendanceCalculatorService
{
    /**
     * Calculate all durations for a single AttendancePrepare record.
     *
     * @param  AttendancePrepare  $prepare          Record yang akan dihitung
     * @param  Shift|null         $shift            Shift terkait (dari roster)
     * @param  bool               $isHoliday        Apakah tanggal ini hari libur nasional
     * @param  bool               $isSunday         Apakah tanggal ini hari Minggu
     * @param  int|null           $manualOvertime   Overtime manual (dari user input)
     * @param  string|null        $workPatternType  Tipe work pattern: FIXED, SHIFT, FLEX-SHIFT
     * @param  bool               $isSaturday       Apakah tanggal ini hari Sabtu (dari roster)
     * @param  int|null           $workPatternId    ID work pattern (untuk lookup overtime rule)
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
        // ── Late Minutes ──────────────────────────────────────
        $lateMinutes = $this->calculateLate($prepare, $shift);

        // ── Overtime ──────────────────────────────────────────
        $rawOvertime = $manualOvertime !== null
            ? $manualOvertime
            : $this->calculateRawOvertime($prepare, $shift, $isHoliday, $isSunday, $workPatternType, $isSaturday);

        // ── LM vs Regular Overtime ────────────────────────────
        $isOffDay = $isHoliday || $isSunday;

        if ($isOffDay) {
            $lm             = $rawOvertime;
            $overtime       = 0;
            $lmCount        = $this->calculateLmMultiplier($lm, $workPatternId, $isSaturday);
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
     */
    protected function calculateLate(AttendancePrepare $prepare, ?Shift $shift): int
    {
        if (!$shift || !$shift->work_hour_start || !$prepare->check_in) {
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
     *
     * Rules (ref: penjelasan.md):
     * SHIFT : Holiday = full check_in→check_out (max 8j), Sabtu = 2j flat, else 0
     * FIXED : Holiday/Minggu = full check_in→check_out (max 8j), Kerja = schedule_out→check_out
     * FLEX-SHIFT P: Holiday/Minggu = full, Kerja = schedule_out→check_out
     * FLEX-SHIFT S: Holiday/Minggu = full, Kerja = aturan b.1/b.2/b.3 (pre/post-shift)
     */
    protected function calculateRawOvertime(
        AttendancePrepare $prepare,
        ?Shift $shift,
        bool $isHoliday,
        bool $isSunday,
        ?string $workPatternType = null,
        bool $isSaturday = false,
    ): int {
        if (!$prepare->check_in || !$prepare->check_out) {
            return 0;
        }

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
            // Holiday (termasuk holiday Sabtu): full, max 8 jam
            if ($isHoliday) {
                return min($totalMinutes, 480);
            }
            // Sabtu biasa: flat 2 jam
            if ($isSaturday) {
                return 120;
            }
            return 0;
        }

        // ═══════════════════════════════════════════════════════
        // Holiday / Minggu (FIXED & FLEX-SHIFT): full, max 8 jam
        // ═══════════════════════════════════════════════════════
        if ($isHoliday || $isSunday) {
            return min($totalMinutes, 480);
        }

        // ═══════════════════════════════════════════════════════
        // Hari kerja biasa — butuh schedule_in/out (dari sync)
        // ═══════════════════════════════════════════════════════
        if (!$prepare->schedule_in || !$prepare->schedule_out) {
            return 0;
        }

        $dateStr = $prepare->date->toDateString();
        $scheduleIn  = Carbon::parse($dateStr . ' ' . $prepare->schedule_in->format('H:i:s'))->startOfMinute();
        $scheduleOut = Carbon::parse($dateStr . ' ' . $prepare->schedule_out->format('H:i:s'))->startOfMinute();

        // Overnight shift
        if ($scheduleOut < $scheduleIn) {
            $scheduleOut->addDay();
        }

        // ── FIXED: post-shift only ───────────────────────────
        if ($workPatternType === 'FIXED') {
            if ($checkOut > $scheduleOut) {
                return $this->roundUp($checkOut->diffInMinutes($scheduleOut, true));
            }
            return 0;
        }

        // ── FLEX-SHIFT ──────────────────────────────────────
        $extCode = $shift?->external_code ?? '';

        // P (pagi): post-shift only
        if ($extCode === 'P') {
            if ($checkOut > $scheduleOut) {
                return $this->roundUp($checkOut->diffInMinutes($scheduleOut, true));
            }
            return 0;
        }

        // S (siang): sementara — OT = total jam kerja - 8 jam (atau 6 jam untuk Sabtu)
        if ($extCode === 'S') {
            $deduction = $isSaturday ? 360 : 480;
            $overtimeMinutes = max(0, $totalMinutes - $deduction);
            return $overtimeMinutes > 0 ? $this->roundUp($overtimeMinutes) : 0;
        }

        // Unknown FLEX-SHIFT code: fallback post-shift
        if ($checkOut > $scheduleOut) {
            return $this->roundUp($checkOut->diffInMinutes($scheduleOut, true));
        }
        return 0;
    }

    // ═══════════════════════════════════════════════════════════
    // MULTIPLIER — dari tabel overtime_rules
    // ═══════════════════════════════════════════════════════════

    /**
     * LM Multiplier (lembur mingguan / hari libur).
     * Minggu/Holiday: SEMUA jam kerja = lembur.
     * Rumus: (total_jam - 1) × 2 — potong 1 jam istirahat, lalu kali 2.
     * Menggunakan overtime_rule dengan is_holiday=true.
     * Fallback: (min(total_hours, 8) - 1) × 2 × 60
     */
    protected function calculateLmMultiplier(int $minutes, ?int $workPatternId = null, bool $isSaturday = false): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        // Potong 1 jam istirahat (60 menit) — minimal 0
        $effectiveMinutes = max(0, $minutes - 60);

        return $this->multiplyFromRule($effectiveMinutes, isHoliday: true, maxHours: 8, workPatternId: $workPatternId, isSaturday: $isSaturday)
            ?? $this->calculateLmMultiplierFallback($effectiveMinutes);
    }

    /**
     * Overtime Multiplier (hari kerja).
     * Menggunakan overtime_rule dengan is_holiday=false.
     * Fallback: 60 menit pertama × 1.5, sisanya × 2
     */
    protected function calculateOvertimeMultiplier(int $minutes, ?int $workPatternId = null): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        // Jika lembur di bawah 1 jam (misal 25-54 menit yang dibulatkan jadi 30), maka dikalikan 1
        if ($minutes < 60) {
            return $minutes;
        }

        return $this->multiplyFromRule($minutes, isHoliday: false, maxHours: null, workPatternId: $workPatternId)
            ?? $this->calculateOvertimeMultiplierFallback($minutes);
    }

    /**
     * Hitung multiplier dari tabel overtime_rules.
     *
     * Konvensi: detail dengan `hour` tertinggi berlaku untuk jam tersebut dan seterusnya.
     * Contoh: hour=1→1.5, hour=2→2.0  →  jam ke-1 = 1.5x, jam 2+ = 2.0x
     *
     * @return int|null  null jika rule tidak ditemukan (pakai fallback)
     */
    protected function multiplyFromRule(
        int $minutes,
        bool $isHoliday,
        ?int $maxHours = null,
        ?int $workPatternId = null,
        bool $isSaturday = false,
    ): ?int {
        // Cari rule yang cocok
        $rule = OvertimeRule::where('is_active', true)
            ->where('is_holiday', $isHoliday)
            ->where('is_saturday', $isSaturday)
            ->when($workPatternId, function ($q) use ($workPatternId) {
                // Prioritaskan rule spesifik work_pattern
                $q->where('work_pattern_id', $workPatternId);
            }, function ($q) {
                // Fallback: rule global (work_pattern_id = null)
                $q->whereNull('work_pattern_id');
            })
            ->first();

        // Kalau ga ketemu dengan filter work_pattern_id, coba yang global
        if (!$rule && $workPatternId) {
            $rule = OvertimeRule::where('is_active', true)
                ->where('is_holiday', $isHoliday)
                ->where('is_saturday', $isSaturday)
                ->whereNull('work_pattern_id')
                ->first();
        }

        if (!$rule) {
            return null; // trigger fallback
        }

        $details = $rule->details()->orderBy('hour')->get();

        if ($details->isEmpty()) {
            return null;
        }

        // Cap total menit jika ada batasan (untuk LM: max 8 jam)
        $remainingMinutes = $minutes;
        if ($maxHours !== null) {
            $remainingMinutes = min($remainingMinutes, $maxHours * 60);
        }

        $total = 0.0;
        $currentHour = 1;

        while ($remainingMinutes > 0) {
            // Cari multiplier yang applicable untuk jam ke-currentHour:
            // detail dengan hour <= currentHour yang paling tinggi
            $detail = $details->filter(fn($d) => $d->hour <= $currentHour)->sortByDesc('hour')->first();

            if (!$detail) {
                break; // tidak ada rule, hentikan
            }

            // Blok ini maksimal 60 menit (1 jam), atau sisa menit terakhir
            $minutesInBlock = min($remainingMinutes, 60);
            $total += $minutesInBlock * (float) $detail->multiplier;
            $remainingMinutes -= $minutesInBlock;
            $currentHour++;
        }

        return (int) round($total);
    }

    // ═══════════════════════════════════════════════════════════
    // FALLBACK (kalau overtime_rules tidak ditemukan)
    // ═══════════════════════════════════════════════════════════

    /**
     * LM Multiplier fallback: total_jam × 2
     * Asumsi: potongan 1 jam istirahat sudah dilakukan di calculateLmMultiplier().
     */
    protected function calculateLmMultiplierFallback(int $minutes): int
    {
        $hours = $minutes / 60;
        $clampedHours = min($hours, 8);

        return (int) round($clampedHours * 2 * 60);
    }

    /**
     * Overtime Multiplier fallback: 60 menit pertama × 1.5, sisanya × 2
     */
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
     * Round up overtime per 30 menit (dengan threshold 5 menit).
     * 0-24 → 0, 25-54 → 30, 55-84 → 60, dst.
     */
    public function roundUp(int $minutes): int
    {
        return (int) floor(($minutes + 5) / 30) * 30;
    }
}
