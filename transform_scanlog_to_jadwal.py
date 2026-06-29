"""
Transform Scanlog → Jadwal Matrix Excel
========================================
Membaca file scanlog produksi (format: PIN, NIP, Nama, Jabatan, Departemen, Kantor, Tanggal, Scan1-4)
dan menghasilkan output matrix jadwal seperti Jadwal Januari.xlsx.

Kode shift per tanggal:
  P  = Pagi   (scan-in jam 05:00 - 11:59)
  S  = Siang  (scan-in jam 12:00 - 16:59)
  ML = Malam  (scan-in jam 17:00 - 04:59)
  M  = Minggu (hari Minggu)
  L  = Libur  (tanggal merah / hari libur nasional)

Usage:
  python transform_scanlog_to_jadwal.py <source_xlsx> [output_xlsx]

Contoh:
  python transform_scanlog_to_jadwal.py "sample_data/4. 25 DES-24 JAN'26 PRODUKSI.xlsx"
"""

import sys
import os
from datetime import date, datetime, timedelta
from collections import defaultdict, OrderedDict
import openpyxl
from openpyxl.styles import Font, Alignment, Border, Side, PatternFill
from openpyxl.utils import get_column_letter

# ============================================================
# KONFIGURASI
# ============================================================

# Daftar hari libur nasional (format: date(year, month, day))
# Bisa ditambah / disesuaikan sesuai kebutuhan
HOLIDAYS = {
    date(2025, 12, 25),  # Natal
    date(2026, 1, 1),    # Tahun Baru
}

# Periode output: 25 Desember - 24 Januari (standar jadwal bulanan)
# Format: (start_day, start_month, end_day, end_month)
# Otomatis detect tahun dari data scanlog
PERIOD_START_DAY = 25
PERIOD_START_MONTH = 12
PERIOD_END_DAY = 24
PERIOD_END_MONTH = 1

# ============================================================
# FUNGSI UTAMA
# ============================================================

def parse_date_from_string(date_str):
    """Parse tanggal dari format DD-MM-YYYY"""
    try:
        return datetime.strptime(date_str.strip(), '%d-%m-%Y').date()
    except (ValueError, AttributeError):
        return None


def get_shift_from_scan_time(scan_time):
    """
    Menentukan shift berdasarkan jam scan-in.
    
    P  = Pagi  05:00 - 11:59
    S  = Siang 12:00 - 16:59
    ML = Malam 17:00 - 04:59
    """
    if not scan_time:
        return ''
    
    try:
        h = int(scan_time.strip().split(':')[0])
        if 5 <= h < 12:
            return 'P'
        elif 12 <= h < 17:
            return 'S'
        else:
            return 'ML'
    except (ValueError, IndexError):
        return ''


def get_code_for_date(scan_time, current_date):
    """
    Menentukan kode untuk suatu tanggal berdasarkan scan dan kalender.
    
    Prioritas:
    1. Hari Minggu → M
    2. Hari Libur Nasional → L
    3. Ada scan → P/S/ML dari jam scan
    4. Tidak ada scan → '' (kosong, isi manual)
    """
    # Cek Minggu
    if current_date.weekday() == 6:  # Sunday = 6
        return 'M'
    
    # Cek Libur Nasional
    if current_date in HOLIDAYS:
        return 'L'
    
    # Cek shift dari scan
    if scan_time:
        return get_shift_from_scan_time(scan_time)
    
    # Tidak ada scan → kosong
    return ''


def load_scanlog(filepath):
    """
    Baca file scanlog Excel dan kembalikan:
    - employees: OrderedDict {nip: {'nama': str, 'dates': {date: [scan1, scan2, ...]}}}
    - tahun_akhir: tahun dari bulan Januari data scan (untuk menentukan tahun periode)
      Karena periode 25 Des - 24 Jan, tahun diambil dari bulan Januari.
      Contoh: data ada 2025-12-26 dan 2026-01-15 → tahun_akhir = 2026
    """
    wb = openpyxl.load_workbook(filepath, data_only=True)
    ws = wb.active  # Ambil sheet pertama
    
    employees = OrderedDict()
    tahun_akhir = None
    
    for row in ws.iter_rows(min_row=2, max_row=ws.max_row, values_only=True):
        pin, nip, nama, jabatan, dept, kantor, tanggal, s1, s2, s3, s4 = row
        
        # Skip header / invalid rows
        if nip is None:
            continue
        
        try:
            nip_str = str(int(nip))
        except (ValueError, TypeError):
            continue
        
        # Parse nama
        nama_str = str(nama).strip() if nama else ''
        
        if nip_str not in employees:
            employees[nip_str] = {'nama': nama_str, 'dates': {}}
        elif nama_str and not employees[nip_str]['nama']:
            employees[nip_str]['nama'] = nama_str
        
        # Parse tanggal
        if isinstance(tanggal, str):
            dt = parse_date_from_string(tanggal)
        elif hasattr(tanggal, 'date'):
            dt = tanggal.date()
        elif isinstance(tanggal, datetime):
            dt = tanggal.date()
        else:
            continue
        
        if dt is None:
            continue
        
        # Track tahun terbesar (untuk menentukan tahun periode)
        # Karena periode 25 Des - 24 Jan, tahun diambil dari bulan Januari
        if tahun_akhir is None or dt.year > tahun_akhir:
            tahun_akhir = dt.year
        # Jika tahun sama tapi bulan Januari, itu tanda periode tahun berikutnya
        if dt.month == 1 and dt.year > (tahun_akhir or 0):
            tahun_akhir = dt.year
        
        # Parse scan times
        scans = []
        for s in [s1, s2, s3, s4]:
            if s is not None and str(s).strip():
                scans.append(str(s).strip())
        
        if scans:
            # Simpan scan pertama sebagai penentu shift
            # Jika sudah ada scan untuk tanggal ini, ambil scan paling pagi
            existing = employees[nip_str]['dates'].get(dt)
            if existing:
                # Ambil scan paling pagi dari kedua set
                all_scans = existing + scans
                all_scans.sort()
                employees[nip_str]['dates'][dt] = all_scans
            else:
                employees[nip_str]['dates'][dt] = scans
    
    wb.close()
    
    # Urutkan employees by NIP
    sorted_employees = OrderedDict(
        sorted(employees.items(), key=lambda x: int(x[0]))
    )
    
    return sorted_employees, tahun_akhir or 2026


def generate_date_range(tahun):
    """
    Generate list tanggal dari 25 Desember tahun-1 s/d 24 Januari tahun.
    Contoh: tahun=2026 → 25 Des 2025 s/d 24 Jan 2026
    """
    start = date(tahun - 1, 12, 25)
    end = date(tahun, 1, 24)
    
    dates = []
    current = start
    while current <= end:
        dates.append(current)
        current += timedelta(days=1)
    
    return dates


def build_matrix(employees, date_range):
    """
    Bangun matrix: {nip: {date: code}}
    """
    matrix = OrderedDict()
    
    for nip, emp in employees.items():
        matrix[nip] = {
            'nama': emp['nama'],
            'wp': '',  # WP diisi manual
            'codes': {}
        }
        
        for dt in date_range:
            # Dapatkan scan pertama (paling pagi) untuk tanggal ini
            scans = emp['dates'].get(dt, [])
            scan1 = scans[0] if scans else ''
            
            code = get_code_for_date(scan1, dt)
            matrix[nip]['codes'][dt] = code
    
    return matrix


def get_month_name(date_range):
    """Ambil nama bulan dari periode (biasanya bulan Januari)"""
    # Periode 25 Des - 24 Jan → "Januari 2026"
    # Ambil bulan dari tanggal terakhir
    bulan_map = {
        1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
        5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
        9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
    }
    last_date = date_range[-1]
    return f"{bulan_map[last_date.month]} {last_date.year}"


def write_output(matrix, date_range, output_path):
    """Tulis matrix ke file Excel dengan format Jadwal"""
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = "Sheet2"  # Sesuai format target
    
    # Style definitions
    header_font = Font(name='Calibri', size=11, bold=True)
    title_font = Font(name='Calibri', size=14, bold=True)
    normal_font = Font(name='Calibri', size=10)
    center_align = Alignment(horizontal='center', vertical='center')
    left_align = Alignment(horizontal='left', vertical='center')
    thin_border = Border(
        left=Side(style='thin'),
        right=Side(style='thin'),
        top=Side(style='thin'),
        bottom=Side(style='thin')
    )
    
    # Sunday & holiday fill
    sunday_fill = PatternFill(start_color='D9E2F3', end_color='D9E2F3', fill_type='solid')  # Biru muda
    header_fill = PatternFill(start_color='4472C4', end_color='4472C4', fill_type='solid')  # Biru
    header_font_white = Font(name='Calibri', size=10, bold=True, color='FFFFFF')
    wp_fill = PatternFill(start_color='FFF2CC', end_color='FFF2CC', fill_type='solid')  # Kuning muda (WP editable)
    
    num_cols = 3 + len(date_range)  # NIP + NAMA + WP + dates
    
    # === ROW 1: Title ===
    title = get_month_name(date_range)
    ws.merge_cells(start_row=1, start_column=1, end_row=1, end_column=num_cols)
    cell = ws.cell(row=1, column=1, value=title)
    cell.font = title_font
    cell.alignment = center_align
    
    # === ROW 2: Headers ===
    headers = ['NIP', 'NAMA LENGKAP', 'WP']
    for dt in date_range:
        headers.append(str(dt.day))
    
    for col_idx, header in enumerate(headers, 1):
        cell = ws.cell(row=2, column=col_idx, value=header)
        cell.font = header_font_white
        cell.fill = header_fill
        cell.alignment = center_align
        cell.border = thin_border
    
    # === DATA ROWS (starting row 3) ===
    for row_idx, (nip, emp) in enumerate(matrix.items(), 3):
        # NIP
        cell = ws.cell(row=row_idx, column=1, value=int(nip))
        cell.font = normal_font
        cell.alignment = center_align
        cell.border = thin_border
        
        # NAMA
        cell = ws.cell(row=row_idx, column=2, value=emp['nama'])
        cell.font = normal_font
        cell.alignment = left_align
        cell.border = thin_border
        
        # WP (kosong, isi manual)
        cell = ws.cell(row=row_idx, column=3, value='')
        cell.font = normal_font
        cell.alignment = center_align
        cell.border = thin_border
        cell.fill = wp_fill  # Kuning = perlu diisi manual
        
        # Date codes
        for col_offset, dt in enumerate(date_range):
            col_idx = 4 + col_offset
            code = emp['codes'].get(dt, '')
            
            cell = ws.cell(row=row_idx, column=col_idx, value=code)
            cell.font = normal_font
            cell.alignment = center_align
            cell.border = thin_border
            
            # Warna khusus
            if code == 'M':
                cell.fill = sunday_fill
            elif code == 'L':
                cell.fill = PatternFill(start_color='F4B4C2', end_color='F4B4C2', fill_type='solid')  # Merah muda
    
    # === COLUMN WIDTHS ===
    ws.column_dimensions['A'].width = 8   # NIP
    ws.column_dimensions['B'].width = 30  # NAMA
    ws.column_dimensions['C'].width = 6   # WP
    for i in range(4, num_cols + 1):
        ws.column_dimensions[get_column_letter(i)].width = 4.5  # Date columns
    
    # === FREEZE PANES ===
    ws.freeze_panes = 'D3'  # Freeze NIP, NAMA, WP + header
    
    # === AUTO FILTER ===
    ws.auto_filter.ref = f'A2:{get_column_letter(num_cols)}2'
    
    # Save
    wb.save(output_path)
    wb.close()
    
    return output_path


# ============================================================
# MAIN
# ============================================================

def main():
    if len(sys.argv) < 2:
        print("Usage: python transform_scanlog_to_jadwal.py <source_xlsx> [output_xlsx]")
        print('Contoh: python transform_scanlog_to_jadwal.py "sample_data/4. 25 DES-24 JAN\'26 PRODUKSI.xlsx"')
        sys.exit(1)
    
    source_path = sys.argv[1]
    
    if not os.path.exists(source_path):
        print(f"ERROR: File tidak ditemukan: {source_path}")
        sys.exit(1)
    
    # Default output
    if len(sys.argv) >= 3:
        output_path = sys.argv[2]
    else:
        base = os.path.splitext(os.path.basename(source_path))[0]
        output_path = f"JADWAL_OUTPUT_{base}.xlsx"
    
    print(f"📂 Source : {source_path}")
    print(f"📂 Output : {output_path}")
    print()
    
    # 1. Load scanlog
    print("🔍 Membaca scanlog...")
    employees, tahun = load_scanlog(source_path)
    print(f"   ✅ {len(employees)} karyawan ditemukan")
    
    # 2. Generate date range
    date_range = generate_date_range(tahun)
    print(f"   ✅ Periode: {date_range[0]} s/d {date_range[-1]} ({len(date_range)} hari)")
    
    # 3. Build matrix
    print("🔧 Membangun matrix jadwal...")
    matrix = build_matrix(employees, date_range)
    
    # Statistik
    total_cells = len(matrix) * len(date_range)
    filled = sum(1 for emp in matrix.values() for code in emp['codes'].values() if code)
    empty = total_cells - filled
    
    p_count = sum(1 for emp in matrix.values() for code in emp['codes'].values() if code == 'P')
    s_count = sum(1 for emp in matrix.values() for code in emp['codes'].values() if code == 'S')
    ml_count = sum(1 for emp in matrix.values() for code in emp['codes'].values() if code == 'ML')
    m_count = sum(1 for emp in matrix.values() for code in emp['codes'].values() if code == 'M')
    l_count = sum(1 for emp in matrix.values() for code in emp['codes'].values() if code == 'L')
    
    print(f"   ✅ Matrix: {len(matrix)} karyawan × {len(date_range)} hari = {total_cells} cells")
    print(f"   📊 P={p_count} | S={s_count} | ML={ml_count} | M={m_count} | L={l_count} | Kosong={empty}")
    
    # 4. Write output
    print("💾 Menulis output Excel...")
    write_output(matrix, date_range, output_path)
    print(f"   ✅ Selesai: {output_path}")
    
    # 5. Summary
    print()
    print("=" * 55)
    print("📋 RINGKASAN")
    print("=" * 55)
    print(f"  Karyawan       : {len(matrix)}")
    print(f"  Periode        : {date_range[0]} s/d {date_range[-1]}")
    print(f"  Hari Minggu    : {sum(1 for d in date_range if d.weekday() == 6)}")
    print(f"  Hari Libur     : {len([d for d in date_range if d in HOLIDAYS])}")
    print(f"  WP (Work Pat.) : ⚠️  HARUS DIISI MANUAL (kolom kuning)")
    print(f"  Output file    : {output_path}")


if __name__ == '__main__':
    main()
