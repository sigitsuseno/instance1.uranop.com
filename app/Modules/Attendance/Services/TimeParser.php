<?php

namespace App\Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class TimeParser
{
    /**
     * Parse a time value into a Carbon datetime combined with the given base date.
     *
     * Supports:
     *   - Excel numeric time (e.g. 0.53699 = 12:53:16)
     *   - HH:MM:SS string
     *   - HH:MM string
     *   - Carbon fallback parsing
     *
     * @param mixed $timeValue  Raw time value from Excel/CSV
     * @param Carbon $baseDate  The date to combine the parsed time with
     * @return Carbon|null
     */
    public static function parse($timeValue, Carbon $baseDate): ?Carbon
    {
        // Empty check
        if ($timeValue === null || $timeValue === '' || $timeValue === false) {
            return null;
        }

        // Excel numeric time (fraction of a day: 0.53699 * 86400 = seconds)
        if (is_numeric($timeValue) && $timeValue >= 0 && $timeValue < 1) {
            $totalSeconds = (int) round($timeValue * 86400);
            $hour   = (int) floor($totalSeconds / 3600);
            $minute = (int) floor(($totalSeconds % 3600) / 60);
            $second = $totalSeconds % 60;

            if ($hour >= 0 && $hour <= 23) {
                return $baseDate->copy()->setTime($hour, $minute, $second);
            }
        }

        // Clean string
        $timeString = trim((string) $timeValue);
        if (empty($timeString)) {
            return null;
        }

        // HH:MM:SS
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $timeString, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $second = (int) $m[3];

            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return $baseDate->copy()->setTime($hour, $minute, $second);
            }
        }

        // HH:MM
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];

            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return $baseDate->copy()->setTime($hour, $minute, 0);
            }
        }

        // Carbon fallback
        try {
            $dt = Carbon::parse($timeString);

            return $baseDate->copy()->setTime($dt->hour, $dt->minute, $dt->second);
        } catch (Throwable $e) {
            // Continue
        }

        Log::warning('TimeParser failed', [
            'original' => $timeValue,
            'cleaned'  => $timeString,
        ]);

        return null;
    }
}
