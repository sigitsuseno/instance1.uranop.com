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

            $employee = Employee::where('employee_code', $nip)->first();

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

        $data = [
            'uuid' => Str::uuid()->toString(),
            'shift_id' => $shift->id,
            'work_pattern_id' => $workPattern?->id,
            'shift_code' => $shift->code,
            'work_pattern_type' => $workPattern?->employee_type,
            'external_code' => $externalCode,
            'is_holiday' => $shift->is_dayoff || in_array($date, $this->holidays),
            'is_sat' => $isSaturday,
            'is_sun' => $carbonDate->dayOfWeek == 0,
            'is_half_day' => $isHalfDay,
            'status' => ($shift->is_dayoff || in_array($date, $this->holidays)) ? 'holiday' : 'scheduled',
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
        } else {
            $data['employee_id'] = $employee->id;
            $data['date'] = $date;
            EmployeeShiftRoster::create($data);
            $this->inserted++;
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
