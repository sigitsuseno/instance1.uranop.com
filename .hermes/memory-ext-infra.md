# Memory Extension — Infrastruktur & Client

> File ini adalah perpanjangan memory Paijo. Dibaca saat diperlukan.

## Server

| Nama | IP | SSH | Dashboard |
|------|-----|-----|-----------|
| Mini PC Coolify | 10.10.10.122 | imat / imat | https://coolify.uranop.id/ |
| VPS HRIS | 10.10.10.19 | megahris / paijo21ok | - |

> SSH ke VPS sering timeout. VPS Linux case-sensitive.

## Client

- **PT KEMILAU UNGARAN SUKSES** — percetakan Karangjati
- Template payroll 18 kolom: No, ID, NAMA, BAGIAN, L/P, THN MASUK, MASA KERJA, STATUS, JML ANAK, ACCOUNT NO, PREMI, GAJI POKOK, TJ. MASA KERJA, HK, L/M, LBR JAM, GAJI, LEM

## Konvensi Laporan Resume (10 Jun 2026)

- **Format matrix per hari, 1 periode** — berlaku untuk Laporan Lembur & Uang Makan
- Kolom: No | Bagian | L | P | [per tgl: Hari Kerja | Overtime] | Total HK | Total OT | Total Terima
- Backend: `buildResumeData()` di `UangMakanReportController` & `LaporanLemburController`
- Frontend: `TabResume.vue` matrix table
- Export: `ResumeExport.php` (generic, title configurable via constructor param 4)
- Print: `renderResumePrintHtml()` A3 landscape, 2-row header (date + sub-headers)
