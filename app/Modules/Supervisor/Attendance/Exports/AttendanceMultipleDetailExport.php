<?php

namespace App\Modules\Supervisor\Attendance\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceMultipleDetailExport implements WithMultipleSheets
{
    protected array $employeesData;

    /**
     * @param array $employeesData List per karyawan:
     *      [
     *          [
     *              'employee'     => ['name','code','department','position'],
     *              'rows'         => [ [NIP, Nama, Hari/Tanggal, Actual In, Actual Out, Lembur, Count], ... ],
     *                             Count = att_prepares lm_count (Minggu/libur) / overtime_count (hari kerja), '-' jika 0
     *              'periodStart'  => string,
     *              'periodEnd'    => string,
     *              'totalOvertime'=> float,
     *          ], ...
     *      ]
     */
    public function __construct(array $employeesData)
    {
        $this->employeesData = $employeesData;
    }

    public function sheets(): array
    {
        $sheets = [];
        $usedTitles = [];

        foreach ($this->employeesData as $data) {
            $sheet = new AttendanceDetailExport(
                data: $data['rows'] ?? [],
                employee: $data['employee'] ?? [],
                periodStart: $data['periodStart'] ?? '',
                periodEnd: $data['periodEnd'] ?? '',
                totalOvertime: $data['totalOvertime'] ?? 0,
                sheetTitle: $this->uniqueTitle($data['employee']['name'] ?? 'Karyawan', $usedTitles),
            );

            $sheets[] = $sheet;
        }

        return $sheets;
    }

    /**
     * Sanitasi judul sheet (max 31 char, tanpa \ / ? * [ ] : ) dan jaga keunikan.
     */
    protected function uniqueTitle(string $name, array &$usedTitles): string
    {
        $clean = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $name);
        $clean = trim((string) $clean);

        if ($clean === '') {
            $clean = 'Karyawan';
        }

        $title = mb_substr($clean, 0, 31);

        if (in_array($title, $usedTitles, true)) {
            $base = rtrim(mb_substr($title, 0, 28));
            $i = 2;
            do {
                $candidate = $base . ' (' . $i . ')';
                $i++;
            } while (in_array($candidate, $usedTitles, true));
            $title = $candidate;
        }

        $usedTitles[] = $title;

        return $title;
    }
}
