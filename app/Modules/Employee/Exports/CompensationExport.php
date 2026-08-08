<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Exports\Sheets\CompensationBankSheet;
use App\Modules\Employee\Exports\Sheets\CompensationMainSheet;
use App\Modules\Employee\Exports\Sheets\CompensationResumeSheet;
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
            $query = EmployeeContract::with(['employee']);

            if (! empty($this->groups)) {
                // Export per group: ambil SEMUA kontrak anggota group yang sudah
                // dibayar dalam rentang pembayaran (konsisten dengan export-groups),
                // tanpa memotong berdasarkan end_date atau is_latest lagi.
                $query->whereIn('comp_group', $this->groups)
                    ->whereNotNull('compensation_paid_at')
                    ->whereBetween('compensation_paid_at', [
                        $dateInfo['start']->format('Y-m-d'),
                        $dateInfo['end']->copy()->addDays(7)->format('Y-m-d'),
                    ]);
            } else {
                // Fallback tanpa group (mis. supervisor): kontrak yang end_date-nya
                // jatuh dalam rentang periode.
                $query->whereBetween('end_date', [
                    $dateInfo['start']->format('Y-m-d'),
                    $dateInfo['end']->format('Y-m-d'),
                ]);

                // Filter opsional: hanya tampilkan kontrak terakhir (is_latest = true)
                if ($this->isLatest) {
                    $query->where('is_latest', true);
                }
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
            new CompensationResumeSheet($contracts, $year, $month),
        ];
    }
}
