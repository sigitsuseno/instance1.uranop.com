<?php

namespace App\Modules\Reports\Services;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\EmployeeOvertime;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LemburUangMakanUpdateService
{
    /**
     * Update data lembur & uang makan untuk periode tertentu.
     *
     * Aturan per group (prioritas: TKN > SPC > group-based):
     *   1. GRP-JKT   — uang makan (UM weekdays, 2/FULL sabtu, HALF/FULL minggu)
     *   2. GRP-ALLIN, GRP-GD — uang makan (mirip JKT, tanpa pengecualian minggu)
     *   3. GRP-SPR   — lembur (weekday-sabtu=0, minggu=lm_count)
     *   4. GRP-PS1, GRP-SS — lembur (overtime_count weekday-sabtu, lm_count minggu)
     *   5. KRY-TKN   — uang makan (overwrite, aturan teknisi sendiri)
     *   6. KRY-SPC   — lembur (overwrite, overtime_count/lm_count, pake gaji_pokok/173)
     *
     * @param int   $periodId
     * @param array $params  { emp_tanpa_sabtu_minggu_holiday, position_rules, technician_rules }
     */
    public function update(int $periodId, array $params = []): array
    {
        $period    = PayPeriod::findOrFail($periodId);
        $startDate = Carbon::parse($period->start_date);
        $endDate   = Carbon::parse($period->end_date);
        $today     = Carbon::today();

        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        // ── Parameter dari modal ────────────────────────────────────
        $empTanpaSabtuMingguHoliday = $params['emp_tanpa_sabtu_minggu_holiday'] ?? [];
        $positionRules              = $params['position_rules'] ?? [];
        $technicianRules            = $params['technician_rules'] ?? [];

        // Default fallback
        if (empty($positionRules)) {
            $positionRules = [
                'KABAG'   => ['weekday' => 15000, 'sabtu_dua' => 55000, 'sabtu_full' => 110000, 'minggu_half' => 110000, 'minggu_full' => 220000],
                'KASHIFT' => ['weekday' => 15000, 'sabtu_dua' => 52522, 'sabtu_full' => 105000, 'minggu_half' => 105000, 'minggu_full' => 210000],
                'ALLIN'   => ['weekday' => 15000, 'sabtu_dua' => 50000, 'sabtu_full' => 100000, 'minggu_half' => 100000, 'minggu_full' => 200000],
            ];
        }
        if (empty($technicianRules)) {
            $technicianRules = ['weekday' => 15000, 'saturday' => 100000, 'holiday' => 200000];
        }

        // ── Generate date list ──────────────────────────────────────
        $dates = [];
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            $dates[] = $d->format('Y-m-d');
            $d->addDay();
        }

        // ── Fetch data ──────────────────────────────────────────────
        $employees = Employee::query()
            ->whereHas('shiftRosters', fn($q) => $q->whereBetween('date', [$startDate, $endDate]))
            ->with(['position', 'groups', 'groups.master'])
            ->get();

        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->get()->groupBy('employee_id');

        $rosters = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->with('shift')->get()->groupBy('employee_id');

        $payRecords = PayRecord::where('pay_period_id', $period->id)
            ->get()->groupBy('employee_id');

        // ── Process ─────────────────────────────────────────────────
        $inserts = [];
        $now     = now();
        $userId  = auth()->id();

        foreach ($employees as $employee) {
            $empPrepares = $prepares->get($employee->id, collect())->keyBy(fn($p) => $p->date->format('Y-m-d'));
            $empRosters  = $rosters->get($employee->id, collect())->keyBy(fn($r) => $r->date->format('Y-m-d'));
            $empPayRecord = $payRecords->get($employee->id)?->first();

            // ── Salary ──────────────────────────────────────────────
            $gaji      = $empPayRecord ? (float)($empPayRecord->gaji_pokok ?? 0) : $employee->baseSalary();
            $tjMk      = $empPayRecord ? (float)($empPayRecord->tj_masa_kerja ?? 0)
                : (float)($employee->salaryComponents()->latest('effective_date')->first()?->tunjangan_masa_kerja ?? 0);
            if ($tjMk == 0) {
                $tjMk = $employee->tunjangan_masa_kerja($startDate->format('Y-m'));
            }
            $tunjangan = $empPayRecord ? (float)($empPayRecord->tunjangan ?? 0)
                : (float)($employee->activeSalary()?->tunjangan ?? 0);

            $upahLemburPerJam = ($gaji + $tjMk + $tunjangan) > 0
                ? round(($gaji + $tjMk + $tunjangan) / 173, 2)
                : 0;

            // ── Classify employee ───────────────────────────────────
            $empGroupCodes = $employee->groups->pluck('reference_code')->toArray();

            $isTkn = in_array('KRY-TKN', $empGroupCodes);
            $isSpc = in_array('KRY-SPC', $empGroupCodes);
            $isJkt = in_array('GRP-JKT', $empGroupCodes);
            $isAllIn = !empty(array_intersect(['GRP-ALLIN', 'GRP-GD'], $empGroupCodes));
            $isSpr = in_array('GRP-SPR', $empGroupCodes);
            $isPrinting = !empty(array_intersect(['GRP-PS1', 'GRP-SS'], $empGroupCodes));

            // Resolve position group untuk UM rates
            $umGroupName = $this->resolveUangMakanGroupName($employee);

            foreach ($dates as $dateStr) {
                $prep   = $empPrepares->get($dateStr);
                $roster = $empRosters->get($dateStr);
                $parsedDate = Carbon::parse($dateStr);
                $dayOfWeek  = $parsedDate->dayOfWeek; // 0=Minggu, 6=Sabtu
                $isHoliday  = $roster && $roster->is_holiday;
                $isMingguHoliday = ($dayOfWeek == 0 || $isHoliday);

                $status = $prep ? $prep->status : '-';

                // Raw values (menit → jam)
                $ovtRaw    = $prep ? (int)$prep->overtime : 0;
                $lmRaw     = $prep ? (int)$prep->lm : 0;
                $ovtCount  = $prep ? (int)$prep->overtime_count : 0;
                $lmCount   = $prep ? (int)$prep->lm_count : 0;
                $ovtHours  = round($ovtRaw / 60, 2);
                $lmHours   = round($lmRaw / 60, 2);

                // ── Apply rules based on group ──────────────────────
                if ($isTkn) {
                    $record = $this->applyTknRules(
                        $ovtHours, $lmHours, $ovtCount, $lmCount,
                        $dayOfWeek, $isHoliday, $isMingguHoliday,
                        $status, $technicianRules, $upahLemburPerJam
                    );
                } elseif ($isSpc) {
                    $record = $this->applySpcRules(
                        $ovtHours, $lmHours, $ovtCount, $lmCount,
                        $dayOfWeek, $isMingguHoliday, $status, $upahLemburPerJam
                    );
                } elseif ($isJkt) {
                    $record = $this->applyJktRules(
                        $ovtHours, $lmHours, $dayOfWeek, $isHoliday, $isMingguHoliday,
                        $status, $positionRules, $umGroupName,
                        $empTanpaSabtuMingguHoliday, $employee->id
                    );
                } elseif ($isAllIn) {
                    $record = $this->applyAllInRules(
                        $ovtHours, $lmHours, $dayOfWeek, $isHoliday, $isMingguHoliday,
                        $status, $positionRules, $umGroupName
                    );
                } elseif ($isSpr) {
                    $record = $this->applySprRules(
                        $ovtHours, $lmHours, $ovtCount, $lmCount,
                        $dayOfWeek, $isMingguHoliday, $status, $upahLemburPerJam
                    );
                } elseif ($isPrinting) {
                    $record = $this->applyPrintingRules(
                        $ovtHours, $lmHours, $ovtCount, $lmCount,
                        $dayOfWeek, $isMingguHoliday, $status, $upahLemburPerJam
                    );
                } else {
                    // Fallback: treat as AllIn
                    $record = $this->applyAllInRules(
                        $ovtHours, $lmHours, $dayOfWeek, $isHoliday, $isMingguHoliday,
                        $status, $positionRules, $umGroupName
                    );
                }

                // Skip kalau tidak ada data
                if ($record['lembur'] == 0 && $record['um_code'] === '' && $record['nominal'] == 0) {
                    // Tetap insert agar 1 record per tanggal (untuk tracking)
                    // Tapi skip kalau status = '-' (tidak ada data sama sekali)
                    if ($status === '-') {
                        continue;
                    }
                }

                $inserts[] = [
                    'uuid'           => (string) \Illuminate\Support\Str::uuid(),
                    'autolog_id'     => null,
                    'employee_id'    => $employee->id,
                    'pay_periode_id' => $period->id,
                    'date'           => $dateStr,
                    'lembur'         => $record['lembur'],
                    'lembur_hitung'  => $record['lembur_hitung'],
                    'um_code'        => $record['um_code'] ?: null,
                    'nominal'        => $record['nominal'],
                    'insentif'       => $record['insentif'],
                    'komponen'       => $record['komponen'],
                    'created_by'     => $userId,
                    'updated_by'     => $userId,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        // ── Simpan ──────────────────────────────────────────────────
        DB::transaction(function () use ($period, $inserts) {
            EmployeeOvertime::where('pay_periode_id', $period->id)->forceDelete();

            if (!empty($inserts)) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    EmployeeOvertime::insert($chunk);
                }
            }
        });

        return [
            'success'    => true,
            'period_id'  => $period->id,
            'period'     => $period->name,
            'total_rows' => count($inserts),
            'message'    => "Data lembur & uang makan periode {$period->name} berhasil diupdate (" . count($inserts) . " baris)",
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // 1. GRP-JKT
    // ═══════════════════════════════════════════════════════════════
    private function applyJktRules(
        float $ovtHours, float $lmHours,
        int $dayOfWeek, bool $isHoliday, bool $isMingguHoliday,
        string $status, array $positionRules, string $umGroupName,
        array $empTanpa, int $employeeId
    ): array {
        $record = $this->emptyRecord($status);

        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            // a. Senin-Jumat: overtime >= 2 jam
            if ($ovtHours >= 2) {
                $record['lembur']  = $ovtHours;
                $record['um_code'] = 'UM';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'weekday');
            }
        } elseif ($dayOfWeek == 6) {
            // b. Sabtu
            if ($ovtHours >= 4) {
                $record['lembur']  = $ovtHours;
                $record['um_code'] = 'FULL';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'sabtu_full');
            } elseif ($ovtHours >= 2 && $ovtHours <= 3.5) {
                $record['lembur']  = $ovtHours;
                $record['um_code'] = '2';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'sabtu_dua');
            }
        } else {
            // c. Minggu & Holiday
            if (in_array($employeeId, $empTanpa)) {
                // c1. Dikecualikan → semua 0
                return $this->emptyRecord($status);
            }
            // c2. Normal
            if ($lmHours >= 8) {
                $record['lembur']  = $lmHours;
                $record['um_code'] = 'FULL';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'minggu_full');
            } elseif ($lmHours >= 4 && $lmHours <= 7.5) {
                $record['lembur']  = $lmHours;
                $record['um_code'] = 'HALF';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'minggu_half');
            }
        }

        return $record;
    }

    // ═══════════════════════════════════════════════════════════════
    // 2. GRP-ALLIN, GRP-GD
    // ═══════════════════════════════════════════════════════════════
    private function applyAllInRules(
        float $ovtHours, float $lmHours,
        int $dayOfWeek, bool $isHoliday, bool $isMingguHoliday,
        string $status, array $positionRules, string $umGroupName
    ): array {
        $record = $this->emptyRecord($status);

        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            // a. Senin-Jumat: overtime >= 2 jam
            if ($ovtHours >= 2) {
                $record['lembur']  = $ovtHours;
                $record['um_code'] = 'UM';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'weekday');
            }
        } elseif ($dayOfWeek == 6) {
            // b. Sabtu
            if ($ovtHours >= 4) {
                $record['lembur']  = $ovtHours;
                $record['um_code'] = 'FULL';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'sabtu_full');
            } elseif ($ovtHours >= 2 && $ovtHours <= 3.5) {
                $record['lembur']  = $ovtHours;
                $record['um_code'] = '2';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'sabtu_dua');
            }
        } else {
            // c. Minggu & Holiday (tanpa pengecualian)
            if ($lmHours >= 8) {
                $record['lembur']  = $lmHours;
                $record['um_code'] = 'FULL';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'minggu_full');
            } elseif ($lmHours >= 4 && $lmHours <= 7.5) {
                $record['lembur']  = $lmHours;
                $record['um_code'] = 'HALF';
                $record['nominal'] = $this->getPositionRate($umGroupName, $positionRules, 'minggu_half');
            }
        }

        return $record;
    }

    // ═══════════════════════════════════════════════════════════════
    // 3. GRP-SPR
    //   a. Senin-Sabtu → semua 0
    //   b. Minggu/Holiday → lembur=lm, hitung=lm_count, nominal=lm_count × upah
    // ═══════════════════════════════════════════════════════════════
    private function applySprRules(
        float $ovtHours, float $lmHours,
        int $ovtCount, int $lmCount,
        int $dayOfWeek, bool $isMingguHoliday,
        string $status, float $upahLemburPerJam
    ): array {
        $record = $this->emptyRecord($status);

        if ($isMingguHoliday) {
            // b. Minggu & Holiday
            $lmCountHours = round($lmCount / 60, 2);
            $record['lembur']        = $lmHours;
            $record['lembur_hitung'] = $lmCountHours;
            $record['um_code']       = '';
            $record['nominal']       = round($lmCountHours * $upahLemburPerJam, 2);
        }
        // a. Senin-Sabtu → semua 0 (default empty record)

        return $record;
    }

    // ═══════════════════════════════════════════════════════════════
    // 4. GRP-PS1, GRP-SS
    //   a. Senin-Sabtu → lembur=overtime, hitung=overtime_count, nominal=count × upah
    //   b. Minggu/Holiday → lembur=lm, hitung=lm_count, nominal=count × upah
    // ═══════════════════════════════════════════════════════════════
    private function applyPrintingRules(
        float $ovtHours, float $lmHours,
        int $ovtCount, int $lmCount,
        int $dayOfWeek, bool $isMingguHoliday,
        string $status, float $upahLemburPerJam
    ): array {
        $record = $this->emptyRecord($status);

        if ($isMingguHoliday) {
            // b. Minggu & Holiday → lm / lm_count
            $lmCountHours = round($lmCount / 60, 2);
            $record['lembur']        = $lmHours;
            $record['lembur_hitung'] = $lmCountHours;
            $record['um_code']       = '';
            $record['nominal']       = round($lmCountHours * $upahLemburPerJam, 2);
        } else {
            // a. Senin-Sabtu → overtime / overtime_count
            $ovtCountHours = round($ovtCount / 60, 2);
            $record['lembur']        = $ovtHours;
            $record['lembur_hitung'] = $ovtCountHours;
            $record['um_code']       = '';
            $record['nominal']       = round($ovtCountHours * $upahLemburPerJam, 2);
        }

        return $record;
    }

    // ═══════════════════════════════════════════════════════════════
    // 5. KRY-TKN (overwrite — uang_makan, rate teknisi)
    //   a. Senin-Jumat: overtime > 2 jam → UM, nominal = flat teknisi weekday
    //   b. Sabtu: TANPA batas minimal → nominal = overtime × (rate / 7)
    //   c. Minggu/Holiday: TANPA batas minimal → nominal = lm × (rate / 7)
    // ═══════════════════════════════════════════════════════════════
    private function applyTknRules(
        float $ovtHours, float $lmHours,
        int $ovtCount, int $lmCount,
        int $dayOfWeek, bool $isHoliday, bool $isMingguHoliday,
        string $status, array $technicianRules, float $upahLemburPerJam
    ): array {
        $record = $this->emptyRecord($status);

        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            // a. Senin-Jumat: overtime > 2 jam → flat
            if ($ovtHours > 2) {
                $record['lembur']  = $ovtHours;
                $record['um_code'] = 'UM';
                $record['nominal'] = (int)($technicianRules['weekday'] ?? 15000);
            }
        } elseif ($dayOfWeek == 6) {
            // b. Sabtu: TANPA minimal → overtime × (rate / 7)
            if ($ovtHours > 0) {
                $rate = (int)($technicianRules['saturday'] ?? 100000);
                $record['lembur']  = $ovtHours;
                $record['um_code'] = 'TKN';
                $record['nominal'] = round($ovtHours * ($rate / 7), 2);
            }
        } else {
            // c. Minggu & Holiday: TANPA minimal → lm × (rate / 7)
            if ($lmHours > 0) {
                $rate = (int)($technicianRules['holiday'] ?? 200000);
                $record['lembur']  = $lmHours;
                $record['um_code'] = 'TKN';
                $record['nominal'] = round($lmHours * ($rate / 7), 2);
            }
        }

        return $record;
    }

    // ═══════════════════════════════════════════════════════════════
    // 6. KRY-SPC (overwrite)
    //   a. Senin-Sabtu → lembur=overtime, hitung=overtime_count, nominal=count × (gaji/173)
    //   b. Minggu/Holiday → lembur=lm, hitung=lm_count, nominal=count × (gaji/173)
    // ═══════════════════════════════════════════════════════════════
    private function applySpcRules(
        float $ovtHours, float $lmHours,
        int $ovtCount, int $lmCount,
        int $dayOfWeek, bool $isMingguHoliday,
        string $status, float $upahLemburPerJam
    ): array {
        // KRY-SPC pake gaji_pokok hardcode 2940088 / 173
        $spcUpahPerJam = round(2940088 / 173, 2);

        $record = $this->emptyRecord($status);

        if ($isMingguHoliday) {
            // b. Minggu & Holiday → lm / lm_count
            $lmCountHours = round($lmCount / 60, 2);
            $record['lembur']        = $lmHours;
            $record['lembur_hitung'] = $lmCountHours;
            $record['um_code']       = '';
            $record['nominal']       = round($lmCountHours * $spcUpahPerJam, 2);
        } else {
            // a. Senin-Sabtu → overtime / overtime_count
            $ovtCountHours = round($ovtCount / 60, 2);
            $record['lembur']        = $ovtHours;
            $record['lembur_hitung'] = $ovtCountHours;
            $record['um_code']       = '';
            $record['nominal']       = round($ovtCountHours * $spcUpahPerJam, 2);
        }

        return $record;
    }

    // ═══════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════

    /**
     * Record kosong sebagai baseline.
     */
    private function emptyRecord(string $status): array
    {
        return [
            'lembur'        => 0,
            'lembur_hitung' => 0,
            'um_code'       => '',
            'nominal'       => 0,
            'insentif'      => 0,
            'komponen'      => json_encode(['status' => $status]),
        ];
    }

    /**
     * Resolve nama group Uang Makan untuk employee (KABAG / KASHIFT / ALLIN).
     */
    private function resolveUangMakanGroupName($employee): string
    {
        $umGroup = $employee->groups->first(fn($g) =>
            $g->master && strtoupper($g->master->group_label ?? '') === 'UANG MAKAN'
        );
        return $umGroup ? strtoupper($umGroup->master->name ?? '') : '';
    }

    /**
     * Ambil rate dari position rules berdasarkan nama group UM.
     * Matching: KABAG → KABAG rules, KASHIFT/KEPALA SHIFT → KASHIFT rules,
     *           lainnya → ALLIN rules.
     */
    private function getPositionRate(string $umGroupName, array $positionRules, string $rateKey): float
    {
        $upper = strtoupper($umGroupName);

        if (str_contains($upper, 'KABAG')) {
            $ruleKey = 'KABAG';
        } elseif (str_contains($upper, 'KASHIFT') || str_contains($upper, 'KEPALA SHIFT')) {
            $ruleKey = 'KASHIFT';
        } else {
            $ruleKey = 'ALLIN';
        }

        return (float)($positionRules[$ruleKey][$rateKey] ?? 0);
    }
}