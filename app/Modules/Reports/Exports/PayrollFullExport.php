<?php

namespace App\Modules\Reports\Exports;

use App\Modules\Reports\Exports\Sheets\KompensasiLengkapSheet;
use App\Modules\Reports\Exports\Sheets\ResumeLengkapSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * "Export Lengkap" laporan payroll — satu file Excel berisi 5 sheet dengan
 * kolom persis seperti file GAJI_KUS_*.xlsx:
 *
 *   1. Gaji Karyawan   — per karyawan (Section A All In + Section B Bulanan Print)
 *   2. Uang Makan      — rekap uang makan / lembur per karyawan
 *   3. Kompensasi ...  — daftar kompensasi kontrak (12 kolom, tanpa POTONGAN)
 *   4. Resume          — 4 blok ringkasan (gaji A, gaji B, uang makan, kompensasi)
 *   5. Rekap Gaji      — rekap gaji + BPJS + uang makan per karyawan
 */
class PayrollFullExport implements WithMultipleSheets
{
    protected array $secA;
    protected array $secB;
    protected string $periodName;

    protected array $umRekap;
    protected string $umLabel;

    protected iterable $contracts;
    protected ?string $compGroup;
    protected int $year;
    protected int $month;

    protected array $resumeAllIn;
    protected array $resumePrint;
    protected array $umResume;
    protected array $kompensasiByPosisi;

    protected array $rekapGaji;
    protected string $dateStart;

    public function __construct(
        array $secA,
        array $secB,
        string $periodName,
        array $umRekap,
        string $umLabel,
        iterable $contracts,
        ?string $compGroup,
        int $year,
        int $month,
        array $resumeAllIn,
        array $resumePrint,
        array $umResume,
        array $kompensasiByPosisi,
        array $rekapGaji,
        string $dateStart
    ) {
        $this->secA              = $secA;
        $this->secB              = $secB;
        $this->periodName        = $periodName;
        $this->umRekap           = $umRekap;
        $this->umLabel           = $umLabel;
        $this->contracts         = $contracts;
        $this->compGroup         = $compGroup;
        $this->year              = $year;
        $this->month             = $month;
        $this->resumeAllIn       = $resumeAllIn;
        $this->resumePrint       = $resumePrint;
        $this->umResume          = $umResume;
        $this->kompensasiByPosisi = $kompensasiByPosisi;
        $this->rekapGaji         = $rekapGaji;
        $this->dateStart         = $dateStart;
    }

    public function sheets(): array
    {
        $kompensasi = new KompensasiLengkapSheet(
            collect($this->contracts),
            $this->year,
            $this->month,
            $this->compGroup ?? 'KOMPENSASI'
        );

        // Nomor baris terakhir tiap sheet — dipakai baris kontrol di sheet Resume.
        $gkGrandTotalRow = count($this->secA) + count($this->secB) + 8;
        $umTotalRow      = count($this->umRekap) + 5;
        $kompRowCount    = $kompensasi->countContracts();
        $kompTotalRow    = $kompRowCount + 4; // judul + header + data + baris kosong + TOTAL

        $gkTotalA = $this->sumGajiBersih($this->secA);
        $gkTotalB = $this->sumGajiBersih($this->secB);
        $umTotal  = array_sum(array_map(fn ($r) => (float) ($r['total'] ?? 0), $this->umRekap));

        $resume = new ResumeLengkapSheet(
            $this->resumeAllIn,
            $this->resumePrint,
            $this->umResume,
            $this->kompensasiByPosisi,
            $this->periodName,
            $this->umLabel,
            $gkTotalA,
            $gkTotalB,
            $gkTotalA + $gkTotalB,
            $umTotal,
            $gkGrandTotalRow,
            $umTotalRow,
            $kompensasi->title(),
            $kompTotalRow
        );

        return [
            new GajiKaryawanExport($this->secA, $this->secB, $this->periodName),
            new UangMakanRekapExport($this->umRekap, $this->umLabel),
            $kompensasi,
            $resume,
            new RekapGajiExport($this->rekapGaji, $this->periodName, $this->dateStart),
        ];
    }

    private function sumGajiBersih(array $rows): float
    {
        return array_sum(array_map(fn ($r) => (float) ($r['gaji_bersih'] ?? 0), $rows));
    }
}
