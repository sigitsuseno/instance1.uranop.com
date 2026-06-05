## penentuan lembur

1.  FIXED (rosters->work_pattern_type === 'FIXED')
    - lembur holiday = jarak dari check_in - sampai check_out maksimal lembur 8 jam
    - lembur minggu = jarak dari check_in - sampai check_out maksimal lembur 8 jam
    - lembur hari kerja = jarak dari schedule_out - sampai check_out

2.  FLEX-SHIFT (rosters->work_pattern_type === 'FLEX-SHIFT')
    - lembur holiday = jarak dari check_in - sampai check_out maksimal lembur 8 jam
    - lembur minggu = jarak dari check_in - sampai check_out maksimal lembur 8 jam

        a. (rosters->external_code === 'P')

    - lembur hari kerja = jarak dari schedule_out - sampai check_out

    b. (rosters->external_code === 'S')
    b.1. (jika check_in < (schedule_in + 30 menit (toleransi)) dan jarak check_in - schedule_in lebih dari 30 menit)

         - lembur hari kerja = dari check_in - sampai schedule_in

    b.2. (jika check_in < (schedule_in + 30 menit (toleransi)) dan jarak check_in - schedule_in kurang dari 30 menit)

         - lembur hari kerja = dari schedule_out - sampai check_out

    b.3. (jika check_in > (schedule_in + 2 jam / ))

         - lembur hari kerja = dari schedule_out - sampai check_out

3.  SHIFT (rosters->work_pattern_type === 'SHIFT')
    - hari sabtu (hari kerja biasa) = lembur => 2 jam,
    - lembur holiday = jarak dari check_in - sampai check_out maksimal lembur 8 jam,
    - lembur holiday sabtu = jarak dari check_in - sampai check_out maksimal lembur 8 jam

Hitungan lembur berdasarkan overtime_rules
