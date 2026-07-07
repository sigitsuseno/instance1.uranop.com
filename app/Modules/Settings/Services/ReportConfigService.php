<?php

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\ReportConfig;
use Illuminate\Support\Facades\Cache;

class ReportConfigService
{
    const CACHE_TTL = 86400; // 24 jam

    // ─── Readers ──────────────────────────────────────────

    /**
     * Ambil 1 row report_config dari DB (dengan cache).
     * Cache pakai raw attributes (bukan Model object) untuk hindari __PHP_Incomplete_Class.
     */
    public function get(string $reportType): ?ReportConfig
    {
        $data = Cache::remember(
            $this->cacheKey($reportType),
            self::CACHE_TTL,
            function () use ($reportType) {
                $row = ReportConfig::byType($reportType)->first();
                if (!$row) return null;
                // Simpan RAW attributes (sebelum cast) — JSON columns tetap string
                return $row->getAttributes();
            }
        );

        if (!$data) {
            return null;
        }

        // Re-hydrate model dari cached raw attributes
        $model = new ReportConfig();
        $model->setRawAttributes((array) $data);
        $model->exists = true;

        return $model;
    }

    /**
     * Ambil isi kolom `config` saja (array).
     * Kalau ga ada di DB → fallback ke default.
     */
    public function getConfig(string $reportType): array
    {
        $row = $this->get($reportType);
        return $row?->config ?? $this->getDefaultConfig($reportType);
    }

    /**
     * Ambil isi kolom `employee_groups` saja (array).
     * Kalau ga ada di DB → return empty array.
     */
    public function getGroups(string $reportType): array
    {
        $row = $this->get($reportType);
        return $row?->employee_groups ?? [];
    }

    /**
     * Ambil full data (groups + config) dalam 1 array.
     * Untuk dipake API response.
     */
    public function getFull(string $reportType): array
    {
        $row = $this->get($reportType);

        return [
            'report_type'     => $reportType,
            'employee_groups' => $row?->employee_groups ?? [],
            'config'          => $row?->config ?? $this->getDefaultConfig($reportType),
            'updated_by'      => $row?->updatedBy?->name,
            'updated_at'      => $row?->updated_at?->toDateTimeString(),
        ];
    }

    // ─── Writer ───────────────────────────────────────────

    /**
     * Simpan / update config. Auto-invalidate cache.
     */
    public function set(string $reportType, array $data, ?int $userId = null): ReportConfig
    {
        $config = ReportConfig::updateOrCreate(
            ['report_type' => $reportType],
            [
                'employee_groups' => $data['employee_groups'] ?? [],
                'config'          => $data['config'] ?? [],
                'updated_by'      => $userId,
            ]
        );

        Cache::forget($this->cacheKey($reportType));

        return $config;
    }

    // ─── Default Fallbacks ────────────────────────────────

    /**
     * Default config per report type.
     * Ini hardcode satu-satunya yang boleh ada di sistem.
     */
    public function getDefaultConfig(string $reportType): array
    {
        return match ($reportType) {
            'lembur_uang_makan' => [
                'KABAG'   => [
                    'weekday'      => 15000,
                    'sabtu_dua'    => 55000,
                    'sabtu_full'   => 110000,
                    'minggu_half'  => 110000,
                    'minggu_full'  => 220000,
                ],
                'KASHIFT' => [
                    'weekday'      => 15000,
                    'sabtu_dua'    => 52522,
                    'sabtu_full'   => 105000,
                    'minggu_half'  => 105000,
                    'minggu_full'  => 210000,
                ],
                'ALL IN'  => [
                    'weekday'      => 15000,
                    'sabtu_dua'    => 50000,
                    'sabtu_full'   => 100000,
                    'minggu_half'  => 100000,
                    'minggu_full'  => 200000,
                ],
                'spc_start_period_id'        => null,
                'spc_base_salary'            => null,
                'jkt_no_overtime_employees'  => [],
                'allin_no_overtime_employees' => [],
                'allin_driver_overtime'       => [],
            ],
            'attendance_overtime_setting' => [
                'formulas' => [
                    'FIXED' => 'rumus_1',
                    'FLEX_S' => 'rumus_1',
                    'FLEX_P' => 'rumus_1'
                ],
                'special_employees' => [
                    'ids' => [],
                    'formula' => 'rumus_1'
                ],
                'technician_rule' => [
                    'employee_ids' => [31, 115, 174],
                    'start_date' => '2026-06-01',
                    'max_holiday_minutes' => 1200
                ],
                'zero_late_shift_codes' => ['S', 'P'],
                'work_hours' => [
                    'FIXED' => ['weekday' => 540, 'saturday' => 360],
                    'FLEX-SHIFT' => ['weekday' => 480, 'saturday' => 360],
                    'SHIFT' => ['weekday' => 480, 'saturday' => 360],
                ]
            ],
            default => [],
        };
    }

    // ─── Helpers ──────────────────────────────────────────

    private function cacheKey(string $reportType): string
    {
        return "report_config:{$reportType}";
    }
}
