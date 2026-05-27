## LOCAL

- environment = laragon
- link = http://instance1.uranop.com.test

## vps

- environment = cloudpanel, (ubuntu 24)
- link = https://instance1.uranop.com

## kriteria migrasi:

1. aplikasi yang dulu adalah multi tenancy, sekarang adalah multi instance, nah kita akan membuat instance.
2. tipe instance isolasi, sekarang tidak ada company_id dan Branch Id, namun kedua tabel itu tetap ada dan berelasi ke tabel Instance (company dan branch hanya tabel data).
   Aplikasi ini adalah **Isolated Multi-Instance HRIS** dengan karakteristik:

```
Owner
  └── Company (header/grouping label saja)
        └── Branch = Instance (operasional aktual)
              └── 1 Instance = 1 Branch = 1 DB = 1 Deploy Laravel
```

3. User roles ada superadmin, hrmanager, adm_manager, hr_ast. hrbranch dan admin (khusus untuk dashboard Supervisor) (ada saran ?)
4. UI ada 2 dashboard

## UI Structure

```
resources/js/
├── Components/              <-- GLOBAL UI COMPONENTS (Agnostik / Tanpa Business Logic)
│   ├── BaseButton.vue
│   ├── BaseModal.vue
│   ├── TextInput.vue
│   └── Table/
│       ├── DataTable.vue
│       └── Pagination.vue
├── Layouts/                 <-- GLOBAL LAYOUTS
│   ├── AuthenticatedLayout.vue
│   └── GuestLayout.vue
├── Pages/                   <-- INERTIA PAGES (Mencerminkan Modul Backend)
│   ├── Admin/ (aplikasi real)
│   │   ├── Dashboard.vue
│   │   └── {Module}/            <-- Contoh: Payroll/
│   │       ├── Index.vue        <-- Halaman Utama Modul
│   │       ├── Show.vue
│   │       ├── Components/      <-- MODUL-SPECIFIC COMPONENTS (Hanya dipakai di modul ini)
│   │       │   ├── SalarySlipModal.vue
│   │       │   └── TaxSummaryCard.vue
│   │       └── SubModule/       <-- Contoh: Kalkulasi/
│   │           ├── Index.vue
│   │           └── Components/  <-- SUBMODUL-SPECIFIC COMPONENTS
│   │               └── CalculationFormulaForm.vue
│   ├── Supervisor/ (Aplikasi untuk external auditor di aplikasi lama adalah module AuditSection)
│   │   ├── Dashboard.vue
│   │   └── {Module}/            <-- Contoh: Payroll/
│   │       ├── Index.vue        <-- Halaman Utama Modul
│   │       ├── Show.vue
│   │       ├── Components/      <-- MODUL-SPECIFIC COMPONENTS (Hanya dipakai di modul ini)
│   │       │   ├── SalarySlipModal.vue
│   │       │   └── TaxSummaryCard.vue
│   │       └── SubModule/       <-- Contoh: Kalkulasi/
│   │           ├── Index.vue
│   │           └── Components/  <-- SUBMODUL-SPECIFIC COMPONENTS
│   │               └── CalculationFormulaForm.vue
├── Composables/             <-- GLOBAL VUE COMPOSABLES (Custom Hooks)
│   ├── useCurrency.js
│   └── usePermission.js
├── app.js                   <-- Inertia Bootstrapper
└── bootstrap.js             <-- Axios / Echo Config

```
