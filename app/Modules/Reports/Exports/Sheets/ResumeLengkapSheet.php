<?php

namespace App\Modules\Reports\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet "Resume" untuk Export Lengkap — 21 kolom (A–U), 4 blok:
 *   1. RESUME GAJI — A. Karyawan All In
 *   2. RESUME GAJI — B. Karyawan Bulanan Print
 *   3. RESUME UANG MAKAN & LEMBUR  (per bagian)
 *   4. RESUME UANG KOMPENSASI      (per posisi)
 *
 * Layout & rumus mengikuti file GAJI_KUS_*.xlsx, termasuk baris kontrol yang
 * membandingkan total tiap blok dengan sheet sumbernya.
 *
 * WithStrictNullComparison dipakai supaya nilai 0 tetap ditulis ke sel
 * (tanpa ini Maatwebsite melewati 0 dan sel jadi kosong — beda dengan sample).
 */
class ResumeLengkapSheet implements FromArray, WithEvents, WithStyles, WithColumnWidths, WithTitle, WithStrictNullComparison
{
    private const LAST_COL = 'U';

    protected array $allIn;
    protected array $print;
    protected array $umResume;
    protected array $kompensasi;
    protected string $periodLabel;
    protected string $umLabel;

    protected float $gkTotalA;
    protected float $gkTotalB;
    protected float $gkGrandTotal;
    protected float $umTotal;

    /** Referensi silang ke total di sheet lain (baris terakhir tiap sheet). */
    protected int $gkLastRow;
    protected int $umLastRow;
    protected string $kompSheetTitle;
    protected int $kompLastRow;

    public function __construct(
        array $allIn,
        array $print,
        array $umResume,
        array $kompensasi,
        string $periodLabel,
        string $umLabel,
        float $gkTotalA,
        float $gkTotalB,
        float $gkGrandTotal,
        float $umTotal,
        int $gkLastRow,
        int $umLastRow,
        string $kompSheetTitle,
        int $kompLastRow
    ) {
        $this->allIn          = array_values($allIn);
        $this->print          = array_values($print);
        $this->umResume       = array_values($umResume);
        $this->kompensasi     = array_values($kompensasi);
        $this->periodLabel    = $periodLabel;
        $this->umLabel        = $umLabel;
        $this->gkTotalA       = $gkTotalA;
        $this->gkTotalB       = $gkTotalB;
        $this->gkGrandTotal   = $gkGrandTotal;
        $this->umTotal        = $umTotal;
        $this->gkLastRow      = $gkLastRow;
        $this->umLastRow      = $umLastRow;
        $this->kompSheetTitle = $kompSheetTitle;
        $this->kompLastRow    = $kompLastRow;
    }

    public function title(): string
    {
        return 'Resume';
    }

    /** Baris kosong selebar A–U. */
    private static function blank(): array
    {
        return array_fill(0, 21, null);
    }

    /** Baris yang hanya berisi nilai di kolom tertentu (huruf kolom => nilai). */
    private static function rowAt(array $cells): array
    {
        $row = array_fill(0, 21, null);
        foreach ($cells as $col => $value) {
            $row[self::colIndex($col) - 1] = $value;
        }
        return $row;
    }

    private static function colIndex(string $col): int
    {
        $index = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - 64);
        }
        return $index;
    }

    public function array(): array
    {
        $rows = [];

        // ═══ Judul ═══
        $rows[] = self::rowAt(['A' => 'PT. KEMILAU UNGARAN SUKSES']);            // 1
        $rows[] = self::rowAt(['A' => strtoupper($this->periodLabel)]);            // 2
        $rows[] = self::blank();                                                   // 3
        $rows[] = self::rowAt(['A' => 'RESUME GAJI']);                             // 4

        // ═══ Blok 1: RESUME GAJI — A. KARYAWAN ALL IN ═══
        $rows[] = self::rowAt(['A' => 'A. KARYAWAN ALL IN']);                      // 5
        $headers = $this->headerRows();
        $rows[] = $headers[0];                                                     // 6
        $rows[] = $headers[1];                                                     // 7

        $dataStartA = count($rows) + 1;
        foreach ($this->allIn as $i => $r) {
            $rowNo  = count($rows) + 1;
            $rows[] = $this->gajiRow($r, $i + 1, $rowNo, false);
        }
        $dataEndA = count($rows);

        $totalARow = count($rows) + 1;
        $rows[] = $this->gajiTotalRow('TOTAL A. KARYAWAN ALL IN', $dataStartA, $dataEndA);

        // Baris kontrol: bandingkan dengan total sheet Gaji Karyawan (Section A).
        $rows[] = self::rowAt(['U' => $this->gkTotalA]);
        $checkA = count($rows);
        $rows[] = self::rowAt(['U' => "=U{$checkA}-U{$totalARow}"]);

        $rows[] = self::blank();
        $rows[] = self::blank();

        // ═══ Blok 2: RESUME GAJI — B. KARYAWAN BULANAN PRINT ═══
        $rows[] = self::rowAt(['A' => 'B. KARYAWAN BULANAN PRINT']);
        $rows[] = $headers[0];
        $rows[] = $headers[1];

        $dataStartB = count($rows) + 1;
        foreach ($this->print as $i => $r) {
            $rowNo  = count($rows) + 1;
            $rows[] = $this->gajiRow($r, $i + 1, $rowNo, true);
        }
        $dataEndB = count($rows);

        $totalBRow = count($rows) + 1;
        $rows[] = $this->gajiTotalRow('TOTAL B. KARYAWAN BULANAN PRINT', $dataStartB, $dataEndB);

        // Baris kontrol: bandingkan dengan total sheet Gaji Karyawan (Section B).
        $rows[] = self::rowAt(['U' => $this->gkTotalB]);
        $checkB = count($rows);
        $rows[] = self::rowAt(['U' => "=U{$checkB}-U{$totalBRow}"]);

        $rows[] = self::blank();

        // ═══ TOTAL gabungan A + B ═══
        $grandRow = count($rows) + 1;
        $grandCells = ['B' => 'TOTAL'];
        foreach (range('F', 'U') as $col) {
            $grandCells[$col] = "={$col}{$totalARow}+{$col}{$totalBRow}";
        }
        $rows[] = self::rowAt($grandCells);

        $rows[] = self::rowAt(['U' => $this->gkGrandTotal]);
        $checkG = count($rows);
        $rows[] = self::rowAt(['U' => "=U{$checkG}-U{$grandRow}"]);

        // ═══ Blok 3: RESUME UANG MAKAN & LEMBUR ═══
        $rows[] = self::rowAt(['A' => 'RESUME UANG MAKAN & LEMBUR — ' . strtoupper($this->umLabel)]);
        $rows[] = self::blank();
        $rows[] = self::rowAt([
            'A' => 'No', 'B' => 'BAGIAN',
            'F' => 'UANG MAKAN', 'G' => 'LEMBUR SABTU', 'H' => 'LEMBUR MINGGU',
            'I' => 'INSENTIF', 'J' => 'PBLT', 'K' => 'REVISI', 'U' => 'TOTAL',
        ]);

        $umStart = count($rows) + 1;
        foreach ($this->umResume as $i => $r) {
            $rowNo  = count($rows) + 1;
            $rows[] = self::rowAt([
                'A' => $i + 1,
                'B' => $r['bagian'] ?? '-',
                'F' => (float) ($r['uang_makan'] ?? 0),
                'G' => (float) ($r['lembur_sabtu'] ?? 0),
                'H' => (float) ($r['lembur_minggu'] ?? 0),
                'I' => (float) ($r['insentif'] ?? 0),
                'J' => (float) ($r['pblt'] ?? 0),
                'K' => (float) ($r['revisi'] ?? 0),
                'U' => "=SUM(F{$rowNo}:K{$rowNo})",
            ]);
        }
        $umEnd = count($rows);

        $umTotalRow = count($rows) + 1;
        $umCells = ['A' => 'TOTAL'];
        foreach (['F', 'G', 'H', 'I', 'J', 'K', 'U'] as $col) {
            $umCells[$col] = "=SUM({$col}{$umStart}:{$col}{$umEnd})";
        }
        $rows[] = self::rowAt($umCells);

        $rows[] = self::rowAt(['U' => $this->umTotal]);
        $checkU = count($rows);
        $rows[] = self::rowAt(['U' => "=U{$checkU}-U{$umTotalRow}"]);

        $rows[] = self::blank();
        $rows[] = self::blank();

        // ═══ Blok 4: RESUME UANG KOMPENSASI ═══
        $rows[] = self::rowAt(['A' => 'RESUME UANG KOMPENSASI']);
        $rows[] = self::blank();
        $rows[] = self::rowAt([
            'A' => 'NO', 'B' => 'POSISI',
            'C' => 'TOTAL KARYAWAN (L)', 'D' => 'TOTAL KARYAWAN (P)',
            'U' => 'TOTAL KOMPENSASI',
        ]);

        $kStart = count($rows) + 1;
        foreach ($this->kompensasi as $i => $r) {
            $rows[] = self::rowAt([
                'A' => $i + 1,
                'B' => $r['posisi'] ?? '-',
                'C' => (int) ($r['l'] ?? 0),
                'D' => (int) ($r['p'] ?? 0),
                'U' => (float) ($r['total'] ?? 0),
            ]);
        }
        $kEnd = count($rows);

        $kTotalRow = count($rows) + 1;
        $rows[] = self::rowAt([
            'A' => 'TOTAL',
            'C' => "=SUM(C{$kStart}:C{$kEnd})",
            'D' => "=SUM(D{$kStart}:D{$kEnd})",
            'U' => "=SUM(U{$kStart}:U{$kEnd})",
        ]);

        // ═══ Penutup ═══
        $rows[] = self::blank();
        $rows[] = self::blank();
        $rows[] = self::blank();
        $rows[] = self::blank();

        $rows[] = self::rowAt(['A' => 'TOTAL', 'U' => "=U{$totalARow}+U{$totalBRow}+U{$umTotalRow}+U{$kTotalRow}"]);
        $rows[] = self::rowAt([
            'U' => "='Gaji Karyawan'!AB{$this->gkLastRow}+'Uang Makan'!P{$this->umLastRow}"
                 . "+'{$this->kompSheetTitle}'!L{$this->kompLastRow}",
        ]);

        return $rows;
    }

    /** Baris header dua tingkat (No..TOTAL TERIMA) — kolom G sengaja kosong seperti sample. */
    private function headerRows(): array
    {
        return [
            self::rowAt([
                'A' => 'No', 'B' => 'BAGIAN', 'C' => 'JML KARYAWAN',
                'F' => 'GAJI', 'H' => 'REVISI', 'I' => 'TJ. MASA KERJA',
                'J' => 'TUNJANGAN', 'K' => 'PREMI HADIR', 'L' => 'PBLT',
                'M' => 'TOTAL', 'N' => 'LEMBUR', 'O' => 'TOTAL + lembur',
                'P' => 'BPJS TENAGA KERJA', 'Q' => 'BPJS KESEHATAN', 'R' => 'BPJS PENSIUN',
                'S' => 'CASHBON', 'T' => 'REVISI PPH', 'U' => 'TOTAL TERIMA',
            ]),
            self::rowAt(['C' => 'L', 'D' => 'P', 'E' => 'Total']),
        ];
    }

    /**
     * @param  bool  $includeLembur  Blok 2 (Karyawan Bulanan Print) menghitung TOTAL TERIMA
     *                               sebagai SUM(L:N) — lembur ikut dibayarkan. Blok 1 (All In)
     *                               memakai SUM(L:M) karena lemburnya sudah termasuk gaji all-in.
     *                               Sama seperti file sample.
     */
    private function gajiRow(array $r, int $no, int $rowNo, bool $includeLembur): array
    {
        // Baris "Karyawan Tambahan" (ExtraEmployee) tidak punya posisi dan komponen
        // gajinya hanya gaji pokok. Karena sheet ini tidak punya kolom GAJI POKOK,
        // nominalnya ditaruh di kolom GAJI — sama seperti file sample yang
        // menggabungkannya ke baris UMUM. Tanpa ini kolom TOTAL (=SUM(F:K)) akan
        // kehilangan nilainya dan baris kontrol TOTAL A tidak cocok dengan sheet
        // Gaji Karyawan.
        if (($r['bagian'] ?? null) === 'Karyawan Tambahan') {
            return self::rowAt([
                'A' => $no,
                'B' => $r['bagian'],
                'C' => (int) ($r['jml_karyawan_l'] ?? 0),
                'D' => (int) ($r['jml_karyawan_p'] ?? 0),
                'E' => (int) ($r['jml_karyawan_total'] ?? 0),
                'F' => (float) ($r['total_plus_lembur'] ?? 0),
                'L' => (float) ($r['pblt'] ?? 0),
                'N' => (float) ($r['lembur'] ?? 0),
                'P' => (float) ($r['bpjs_tk'] ?? 0),
                'Q' => (float) ($r['bpjs_ks'] ?? 0),
                'R' => (float) ($r['bpjs_pen'] ?? 0),
                'S' => (float) ($r['cashbon'] ?? 0),
                'T' => (float) ($r['revisi_pph'] ?? 0),
                'M' => "=SUM(F{$rowNo}:K{$rowNo})",
                'O' => "=SUM(M{$rowNo}:N{$rowNo})",
                // ExtraEmployee: lembur selalu 0 → rumusnya sama untuk kedua blok.
                'U' => "=SUM(L{$rowNo}:M{$rowNo})-SUM(P{$rowNo}:T{$rowNo})",
            ]);
        }

        return self::rowAt([
            'A' => $no,
            'B' => $r['bagian'] ?? '-',
            'C' => (int) ($r['jml_karyawan_l'] ?? 0),
            'D' => (int) ($r['jml_karyawan_p'] ?? 0),
            'E' => (int) ($r['jml_karyawan_total'] ?? 0),
            'F' => (float) ($r['gaji'] ?? 0),
            'H' => (float) ($r['revisi'] ?? 0),
            'I' => (float) ($r['tj_masa_kerja'] ?? 0),
            'J' => (float) ($r['tunjangan'] ?? 0),
            'K' => (float) ($r['premi_hadir'] ?? 0),
            'L' => (float) ($r['pblt'] ?? 0),
            'N' => (float) ($r['lembur'] ?? 0),
            'P' => (float) ($r['bpjs_tk'] ?? 0),
            'Q' => (float) ($r['bpjs_ks'] ?? 0),
            'R' => (float) ($r['bpjs_pen'] ?? 0),
            'S' => (float) ($r['cashbon'] ?? 0),
            'T' => (float) ($r['revisi_pph'] ?? 0),
            // M = SUM(F:K) — TOTAL di luar lembur (kolom G memang kosong)
            'M' => "=SUM(F{$rowNo}:K{$rowNo})",
            'O' => "=SUM(M{$rowNo}:N{$rowNo})",
            'U' => $includeLembur
                ? "=SUM(L{$rowNo}:N{$rowNo})-SUM(P{$rowNo}:T{$rowNo})"
                : "=SUM(L{$rowNo}:M{$rowNo})-SUM(P{$rowNo}:T{$rowNo})",
        ]);
    }

    private function gajiTotalRow(string $label, int $start, int $end): array
    {
        $cells = ['B' => $label];
        foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'] as $col) {
            $cells[$col] = "=SUM({$col}{$start}:{$col}{$end})";
        }
        return self::rowAt($cells);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 22, 'C' => 14, 'D' => 14, 'E' => 8,
            'F' => 15, 'G' => 12, 'H' => 12, 'I' => 14, 'J' => 12,
            'K' => 14, 'L' => 10, 'M' => 15, 'N' => 12, 'O' => 16,
            'P' => 16, 'Q' => 16, 'R' => 14, 'S' => 12, 'T' => 12, 'U' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = self::LAST_COL;
                $lastRow = $sheet->getHighestRow();
                $border  = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ];

                $sheet->getParent()->getDefaultStyle()->applyFromArray([
                    'font' => ['name' => 'Calibri', 'size' => 10],
                ]);

                // ── Judul & label blok: merge penuh + bold ──
                foreach ([1, 2] as $r) {
                    $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
                }
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setSize(11);
                $sheet->getStyle("A1:A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A4:{$lastCol}4");
                $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ── Cari baris penting berdasarkan isi kolom A/B ──
                $sectionRows  = [];   // A5 / A26 — label section
                $header2Rows  = [];   // header 2 tingkat (blok 1 & 2): baris "No / BAGIAN"
                $header1Rows  = [];   // header 1 tingkat (blok 3 & 4)
                $umTitleRow   = null; // "RESUME UANG MAKAN & LEMBUR"
                $kTitleRow    = null; // "RESUME UANG KOMPENSASI"
                $totalRows    = [];   // baris "TOTAL ..."

                for ($r = 1; $r <= $lastRow; $r++) {
                    $a = $sheet->getCell("A{$r}")->getValue();
                    $b = $sheet->getCell("B{$r}")->getValue();

                    if (is_string($b) && str_starts_with($b, 'TOTAL')) {
                        $totalRows[] = $r;
                        continue;
                    }
                    if (!is_string($a)) {
                        continue;
                    }

                    if (str_starts_with($a, 'A. KARYAWAN') || str_starts_with($a, 'B. KARYAWAN')) {
                        $sectionRows[] = $r;
                    } elseif ($a === 'No' || $a === 'NO') {
                        // Header 2 tingkat bila baris berikutnya adalah sub-header L/P/Total.
                        $next = $sheet->getCell("C" . ($r + 1))->getValue();
                        if ($next === 'L') {
                            $header2Rows[] = $r;
                        } else {
                            $header1Rows[] = $r;
                        }
                    } elseif (str_starts_with($a, 'RESUME UANG MAKAN')) {
                        $umTitleRow = $r;
                    } elseif ($a === 'RESUME UANG KOMPENSASI') {
                        $kTitleRow = $r;
                    } elseif ($a === 'TOTAL') {
                        $totalRows[] = $r;
                    }
                }

                // Section & judul blok
                foreach ($sectionRows as $r) {
                    $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(11);
                }
                if ($umTitleRow) {
                    $sheet->mergeCells("A{$umTitleRow}:I{$umTitleRow}");
                    $sheet->getStyle("A{$umTitleRow}")->getFont()->setBold(true)->setSize(12);
                }
                if ($kTitleRow) {
                    $sheet->mergeCells("A{$kTitleRow}:E{$kTitleRow}");
                    $sheet->getStyle("A{$kTitleRow}")->getFont()->setBold(true)->setSize(12);
                }

                // Header dua tingkat (blok 1 & 2) — HANYA di sini merge vertikal boleh,
                // karena baris berikutnya memang sub-header, bukan baris data.
                foreach ($header2Rows as $r) {
                    $sheet->mergeCells("C{$r}:E{$r}");              // JML KARYAWAN
                    foreach (['A', 'B', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'] as $col) {
                        $sheet->mergeCells("{$col}{$r}:{$col}" . ($r + 1));
                    }
                    $sheet->getStyle("A{$r}:{$lastCol}" . ($r + 1))->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 9],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true,
                        ],
                    ] + $border);
                    $sheet->getRowDimension($r)->setRowHeight(20);
                }

                // Header satu tingkat (blok 3 & 4) — tanpa merge vertikal.
                foreach ($header1Rows as $r) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 9],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true,
                        ],
                    ] + $border);
                }

                // Baris TOTAL: bold + merge label
                foreach ($totalRows as $r) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2'],
                        ],
                    ] + $border);
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // Format angka kolom finansial blok 1–4
                $sheet->getStyle("F1:{$lastCol}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("C1:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');

                // Freeze di bawah judul
                $sheet->freezePane('A6');

                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $sheet->getPageMargins()->setTop(0.5)->setRight(0.3)->setBottom(0.5)->setLeft(0.3);
            },
        ];
    }
}
