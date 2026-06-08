<?php

namespace App\Modules\Employee\Services;

use App\Modules\Settings\Models\BpjsConfig;

/**
 * Kalkulasi nominal iuran BPJS.
 * Menerima bpjs_config aktif, gaji dasar, checkbox, dan dependents.
 */
class BpjsCalculator
{
    protected ?BpjsConfig $config = null;

    public function setConfig(BpjsConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Hitung iuran BPJS untuk seorang karyawan.
     *
     * @param  float  $baseSalary  Dasar gaji = gaji_pokok + tj_masa_kerja + tunjangan
     * @param  bool   $hasTk       Checkbox BPJS TK
     * @param  bool   $hasKs       Checkbox BPJS KES
     * @param  bool   $hasPen      Checkbox BPJS PEN
     * @param  int    $dependents  Jumlah tanggungan kesehatan
     * @return array  [employer => [...5], employee => [...3]]
     */
    public function calculate(
        float $baseSalary,
        bool  $hasTk = false,
        bool  $hasKs = false,
        bool  $hasPen = false,
        int   $dependents = 0
    ): array {
        $result = [
            'employer' => [
                'jht'        => 0,
                'jkk'        => 0,
                'jkm'        => 0,
                'kesehatan'  => 0,
                'jp'         => 0,
            ],
            'employee' => [
                'jht'        => 0,
                'kesehatan'  => 0,
                'jp'         => 0,
            ],
        ];

        if (! $this->config) {
            return $result;
        }

        // --- BPJS Ketenagakerjaan (TK) ---
        if ($hasTk) {
            $jht = $this->config->calculateJHT($baseSalary);
            $result['employer']['jht'] = $jht['employer'];
            $result['employee']['jht'] = $jht['employee'];

            $result['employer']['jkk'] = $this->config->calculateJKK($baseSalary);
            $result['employer']['jkm'] = $this->config->calculateJKM($baseSalary);
        }

        // --- BPJS Kesehatan (KES) ---
        if ($hasKs) {
            $kes = $this->config->calculateKesehatan($baseSalary, $dependents);
            $result['employer']['kesehatan'] = $kes['employer'];
            $result['employee']['kesehatan'] = $kes['employee'];
        }

        // --- BPJS Pensiun (PEN) ---
        if ($hasPen) {
            $jp = $this->config->calculateJP($baseSalary);
            $result['employer']['jp'] = $jp['employer'];
            $result['employee']['jp'] = $jp['employee'];
        }

        return $result;
    }
}
