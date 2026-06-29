<?php

namespace App\Modules\Schedule\Imports;

use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Schedule\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ShiftRosterMatrixImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable;

    protected $datesByColumn = []; 
    protected $holidays = [];
    protected $errors = [];
    protected $inserted = 0;
    protected $updated = 0;
    protected $month;
    protected $year;

    public function __construct($month, $year)
    {
        $this->month = (int) $month;
        $this->year = (int) $year;
        $this->calculateDateRange();
    }

    public function startRow(): int
    {
        return 3;
    }

    public function headingRow(): int
    {
        return 2;
    }

    protected function calculateDateRange()
    {
        $start = Carbon::create($this->year, $this->month, 25)->subMonth();
        $end = Carbon::create($this->year, $this->month, 24);

        $this->holidays = Holiday::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $current = $start->copy();
        $colIndex = 0;
        while ($current <= $end) {
            $dayNum = $current->day;

            $this->datesByColumn[$dayNum] = [
                'date' => $current->format('Y-m-d'),
                'day_of_week' => $current->dayOfWeek, 
                'col_index' => $colIndex,
            ];

            $current->addDay();
            $colIndex++;
        }
    }

    public function collection(Collection $rows)
    {
        Log::info('ShiftRosterMatrixImport: Processing '.$rows->count().' rows');

        foreach ($rows as $index => $row) {
            $rowArray = $row->toArray();

            $nip = trim($rowArray['nip'] ?? $rowArray['NIP'] ?? '');

            if (empty($nip)) {
                continue;
            }

            $wpCode = trim($rowArray['wp'] ?? $rowArray['WP'] ?? '');

            $employee = Employee::where('employee_code', $nip)
                ->orWhere('nip', $nip)
                ->first();

            if (! $employee) {
                $this->errors[] = 'Baris '.($index + 3).": Data master karyawan untuk NIP {$nip} tidak ditemukan.";
                continue;
            }

            $workPattern = null;
            if (! empty($wpCode)) {
                $workPattern = WorkPattern::where('code', $wpCode)->first();

                if (! $workPattern) {
                    $this->errors[] = 'Baris '.($index + 3).": WP '{$wpCode}' tidak terdaftar di sistem.";
                    continue;
                }
            }

            foreach ($this->datesByColumn as $dayNum => $dateInfo) {
                $externalCode = trim($rowArray[$dayNum] ?? '');

                if ($externalCode === '') {
                    continue;
                }

                $fullDate = $dateInfo['date'];
                $dayOfWeek = $dateInfo['day_of_week'];

                $shift = $this->resolveShift($externalCode, $workPattern, $dayOfWeek);

                if ($shift) {
                    // Debug: log untuk satpam di holiday
                    if ($workPattern && $workPattern->employee_type === 'SHIFT' && in_array($fullDate, $this->holidays)) {
                        Log::info('SATDUBug', [
                            'nip' => $nip,
                            'wp' => $wpCode,
                            'wp_id' => $workPattern->id,
                            'date' => $fullDate,
                            'day_num' => $dayNum,
                            'external_from_excel' => $externalCode,
                            'resolved_shift_id' => $shift->id,
                            'resolved_shift_code' => $shift->code,
                            'resolved_shift_ext' => $shift->external_code,
                        ]);
                    }
                    $this->saveRoster($employee, $workPattern, $shift, $fullDate, $externalCode);
                } else {
                    $this->errors[] = 'Baris '.($index + 3).": Kode '{$externalCode}' tidak ditemukan untuk WP {$wpCode} pada tanggal {$dayNum}.";
                }
            }
        }

        Log::info('ShiftRosterMatrixImport: Completed', [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'errors' => count($this->errors),
        ]);
    }

    protected function resolveShift($externalCode, $workPattern, $dayOfWeek)
    {
        $isSaturday = ($dayOfWeek == 6);

        if ($isSaturday) {
            if ($workPattern && $workPattern->sat_type === 'full') {
                // Skip weekend handling
            } else {
                $weekendQuery = Shift::where('is_weekend', true)
                    ->when($workPattern, function ($q) use ($workPattern) {
                        $q->where('work_pattern_id', $workPattern->id);
                    }, function ($q) {
                        $q->whereNull('work_pattern_id');
                    });

                $shift = (clone $weekendQuery)->where('external_code', $externalCode)->first();

                if (! $shift) {
                    $shift = (clone $weekendQuery)->first();
                }

                if ($shift) {
                    return $shift;
                }
            }
        }

        if ($workPattern) {
            $shift = Shift::where('work_pattern_id', $workPattern->id)
                ->where('external_code', $externalCode)
                ->first();
            if ($shift) {
                return $shift;
            }
        }

        $shift = Shift::whereNull('work_pattern_id')
            ->where('external_code', $externalCode)
            ->first();
            
        if ($shift) {
            return $shift;
        }

        return Shift::where('code', $externalCode)->first();
    }

    protected function saveRoster($employee, $workPattern, $shift, $date, $externalCode)
    {
        $carbonDate = Carbon::parse($date);

        $isSaturday = $carbonDate->dayOfWeek == 6;
        $isHalfDay = $workPattern && $isSaturday && $workPattern->sat_type == 'half';
        $isHoliday = $shift->is_dayoff || in_array($date, $this->holidays);

        // Fallback: kalo WP dari Excel kosong, cari dari roster existing
        if (! $workPattern) {
            $existingRoster = EmployeeShiftRoster::where('employee_id', $employee->id)
                ->whereNotNull('work_pattern_id')
                ->with('workPattern')
                ->orderBy('date', 'desc')
                ->first();
            $workPattern = $existingRoster?->workPattern;
        }

        // WP "SC" tidak dioverride — tetap pakai kode asli
        // Khusus SHIFT (satpam) juga tidak dioverride & status tetap 'scheduled'
        $isShiftSatpam = $workPattern && $workPattern->employee_type === 'SHIFT';

        $shouldOverride = $isHoliday && !($workPattern && $workPattern->code === 'SC') && !$isShiftSatpam;

        $data = [
            'uuid' => Str::uuid()->toString(),
            'shift_id' => $shift->id,
            'work_pattern_id' => $workPattern?->id,
            'shift_code' => $shift->code,
            'work_pattern_type' => $workPattern?->employee_type,
            'external_code' => $shouldOverride ? 'L' : $externalCode,
            'is_holiday' => $isHoliday,
            'is_sat' => $isSaturday,
            'is_sun' => $carbonDate->dayOfWeek == 0,
            'is_half_day' => $isHalfDay,
            'status' => ($isHoliday && !$isShiftSatpam) ? 'holiday' : 'scheduled',
            'source' => 'import',
            'created_by' => Auth::id(),
            'synced_at' => now(),
        ];

        $roster = EmployeeShiftRoster::where('employee_id', $employee->id)->where('date', $date)->first();
        
        if ($roster) {
            // Keep uuid on update
            unset($data['uuid']);
            $roster->update($data);
            $this->updated++;
            
            // Debug
            if (isset($isShiftSatpam) && $isShiftSatpam && $isHoliday) {
                Log::info('SATDUSave', [
                    'date' => $date,
                    'employee_id' => $employee->id,
                    'shift_id' => $data['shift_id'] ?? null,
                    'external_code' => $data['external_code'] ?? null,
                    'shift_code' => $data['shift_code'] ?? null,
                    'work_pattern_id' => $data['work_pattern_id'] ?? null,
                    'should_override' => $shouldOverride ?? false,
                    'is_shift_satpam' => $isShiftSatpam ?? false,
                ]);
            }
        } else {
            $data['employee_id'] = $employee->id;
            $data['date'] = $date;
            EmployeeShiftRoster::create($data);
            $this->inserted++;
            
            // Debug
            if (isset($isShiftSatpam) && $isShiftSatpam && $isHoliday) {
                Log::info('SATDUSave', [
                    'date' => $date,
                    'employee_id' => $employee->id,
                    'shift_id' => $data['shift_id'] ?? null,
                    'external_code' => $data['external_code'] ?? null,
                    'shift_code' => $data['shift_code'] ?? null,
                    'work_pattern_id' => $data['work_pattern_id'] ?? null,
                    'should_override' => $shouldOverride ?? false,
                    'is_shift_satpam' => $isShiftSatpam ?? false,
                ]);
            }
        }

        // Verify: baca balik dari DB setelah save (hanya untuk satpam di holiday)
        if (isset($isShiftSatpam) && $isShiftSatpam && $isHoliday) {
            $verify = EmployeeShiftRoster::where('employee_id', $employee->id)
                ->where('date', $date)
                ->first(['id', 'external_code', 'shift_code', 'shift_id', 'updated_at']);
            Log::info('SATDUVerify', [
                'date' => $date,
                'employee_id' => $employee->id,
                'from_db_external_code' => $verify?->external_code,
                'from_db_shift_code' => $verify?->shift_code,
                'from_db_shift_id' => $verify?->shift_id,
                'from_db_updated_at' => $verify?->updated_at?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getInserted()
    {
        return $this->inserted;
    }

    public function getUpdated()
    {
        return $this->updated;
    }
}
