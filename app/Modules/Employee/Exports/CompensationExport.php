<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Exports\Sheets\CompensationBankSheet;
use App\Modules\Employee\Exports\Sheets\CompensationMainSheet;
use App\Modules\Employee\Models\EmployeeContract;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CompensationExport implements WithMultipleSheets
{
    protected $month;
    protected $year;
    protected $periode;
    protected $isLatest;
    protected $groups;

    public function __construct($month, $year, $periode, $isLatest = false, array $groups = [])
    {
        $this->month = $month;
        $this->year = $year;
        $this->periode = $periode;
        $this->isLatest = $isLatest;
        $this->groups = $groups;
    }

    public function sheets(): array
    {
        $compensationService = new \App\Modules\Employee\Services\CompensationPeriodService();

        try {
            $dateInfo = $compensationService->calculateCompensationDates($this->year, $this->month, $this->periode);
        } catch (\Exception $e) {
            $dateInfo = null;
        }

        $contracts = new Collection();

        if ($dateInfo) {
            $query = EmployeeContract::with(['employee'])
                ->whereBetween('end_date', [
                    $dateInfo['start']->format('Y-m-d'),
                    $dateInfo['end']->format('Y-m-d'),
                ]);

            // Filter opsional: hanya tampilkan kontrak terakhir (is_latest = true)
            if ($this->isLatest) {
                $query->where('is_latest', true);
            }

            // Filter opsional: batasi ke group kompensasi tertentu (comp_group)
            if (! empty($this->groups)) {
                $query->whereIn('comp_group', $this->groups);
            }

            $contracts = $query
                ->orderBy('end_date', 'asc')
                ->get();
        }

        $year = (int) $this->year;
        $month = (int) $this->month;

        return [
            new CompensationMainSheet($contracts, $year, $month),
            new CompensationBankSheet($contracts, $year, $month),
        ];
    }
}
