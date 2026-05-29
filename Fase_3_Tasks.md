# Daftar Pekerjaan Fase 3: Data Master, Config, Setting, Konstanta

Berikut adalah urutan pengerjaan untuk Fase 3 secara berurutan:

## Backend (Database & Model)
1. **System Settings:** 
   - Buat Migration untuk tabel `system_settings`
   - Buat Model `SystemSetting`
2. **Salary Grades:** 
   - Buat Migration untuk tabel `salary_grades` dan `salary_grade_histories`
   - Buat Model `SalaryGrade` dan `SalaryGradeHistory`
3. **Employee Data:** 
   - Buat Migration untuk tabel `employee_groups` dan `employee_titles`
   - Buat Model `EmployeeGroup` dan `EmployeeTitle`
4. **Salary Components:** 
   - Buat Migration untuk tabel `salary_components`
   - Buat Model `SalaryComponent`
5. **Tax & BPJS Configs:** 
   - Buat Migration untuk tabel `bpjs_configs`, `pph_configs`, `ptkp_rates`, `ter_rates`, dan `progressive_rates`
   - Buat Model terkait (misal: `BpjsConfig`, `PphConfig`, `PtkpRate`, `TerRate`, `ProgressiveRate`)
6. **Overtime Rules:** 
   - Buat Migration untuk tabel `overtime_rules`
   - Buat Model `OvertimeRule`
7. **Service Year Allowances:** 
   - Buat Migration untuk tabel `service_year_allowances`
   - Buat Model `ServiceYearAllowance`

## API (Controller & Routes)
8. **Controller:** 
   - Buat `SettingsApiController` untuk menghandle CRUD semua pengaturan dan data master di atas.
9. **Routes:** 
   - Daftarkan endpoint API di `app/Modules/Settings/Routes/api.php`.

## Frontend (Integrasi API)
10. **Settings Page:** 
    - Integrasikan halaman `Settings/Index.vue` dengan API yang sudah dibuat (menggantikan data mock).
11. **Payroll Configs Page:** 
    - Integrasikan halaman `Payroll/Configs/Index.vue` dengan API nyata.
12. **Salary Grades Page:** 
    - Integrasikan halaman `SalaryGrades/Index.vue` dengan API nyata.
