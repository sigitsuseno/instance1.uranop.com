import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../Stores/auth'

const Index = () => import('../Pages/Index.vue')
const Login = () => import('../Pages/Auth/Login.vue')

const AdminDashboard = () => import('../Pages/Admin/Dashboard.vue')
const CompanyProfile = () => import('../Pages/Admin/Company/Profile.vue')
const AdminsIndex = () => import('../Pages/Admin/Organization/Admins/Index.vue')
const DepartmentsIndex = () => import('../Pages/Admin/Organization/Departments/Index.vue')
const PositionsIndex = () => import('../Pages/Admin/Organization/Positions/Index.vue')
const SalaryGradesIndex = () => import('../Pages/Admin/Organization/SalaryGrades/Index.vue')
const EmployeesIndex = () => import('../Pages/Admin/Employees/Karyawan/Index.vue')
const EmployeeGroupingIndex = () => import('../Pages/Admin/Employees/Grouping/Index.vue')
const EmployeeOrderingIndex = () => import('../Pages/Admin/Employees/Ordering/Index.vue')
const EmployeeImport = () => import('../Pages/Admin/Employees/Import.vue')
const EmployeeContractsIndex = () => import('../Pages/Admin/Employees/Contracts/Index.vue')
const EmployeeContractsImport = () => import('../Pages/Admin/Employees/Contracts/Import.vue')
const EmployeeShow = () => import('../Pages/Admin/Employees/Karyawan/Show.vue')
const EmployeeCreate = () => import('../Pages/Admin/Employees/Karyawan/Create.vue')
const EmployeeEdit = () => import('../Pages/Admin/Employees/Karyawan/Edit.vue')
const SalariesIndex = () => import('../Pages/Admin/Employees/Salaries/Index.vue')
const SalariesCreate = () => import('../Pages/Admin/Employees/Salaries/Create.vue')
const SalariesEdit = () => import('../Pages/Admin/Employees/Salaries/Edit.vue')
const SalariesShow = () => import('../Pages/Admin/Employees/Salaries/Show.vue')
const SalariesImport = () => import('../Pages/Admin/Employees/Salaries/Import.vue')
const PositionHistoriesIndex = () => import('../Pages/Admin/Employees/PositionHistories/Index.vue')
const FamiliesIndex = () => import('../Pages/Admin/Employees/Families/Index.vue')
const DocumentsIndex = () => import('../Pages/Admin/Employees/Documents/Index.vue')
const TerminationsIndex = () => import('../Pages/Admin/Employees/Terminations/Index.vue')
const AttendanceIndex = () => import('../Pages/Admin/Attendance/Index.vue')
const AttendanceRecap = () => import('../Pages/Admin/Attendance/Recap/Index.vue')
const LogImport = () => import('../Pages/Admin/Attendance/LogImport.vue')
const RosterIndex = () => import('../Pages/Admin/Schedule/Roster/Index.vue')
const SyncKehadiran = () => import('../Pages/Admin/Attendance/SyncKehadiran.vue')
const OvertimeIndex = () => import('../Pages/Admin/Attendance/Overtime/Index.vue')
const OvertimeCalculationIndex = () => import('../Pages/Admin/Attendance/OvertimeCalculation/Index.vue')
const OvertimeCalculationDetail = () => import('../Pages/Admin/Attendance/OvertimeCalculation/Detail.vue')
const ConsecutiveIndex = () => import('../Pages/Admin/Attendance/Consecutive/Index.vue')
const LeaveSettings = () => import('../Pages/Admin/Leave/Settings.vue')
// New separate leave pages
const LeaveGenerate = () => import('../Pages/Admin/Leave/Generate.vue')
const LeaveRequests = () => import('../Pages/Admin/Leave/Requests.vue')
const LeaveCancellations = () => import('../Pages/Admin/Leave/Cancellations.vue')
const LeaveBalances = () => import('../Pages/Admin/Leave/Balances.vue')
const LeaveRecap = () => import('../Pages/Admin/Leave/Recap.vue')
const PayrollPeriodsIndex = () => import('../Pages/Admin/Payroll/Periods/Index.vue')
const PayrollPeriodsCrud = () => import('../Pages/Admin/Payroll/Periods/Crud.vue')
const PayrollPeriodDetail = () => import('../Pages/Admin/Payroll/Periods/Detail.vue')
const PayrollGajiKaryawan = () => import('../Pages/Admin/Payroll/GajiKaryawan/Index.vue')
const PayrollConfigsIndex = () => import('../Pages/Admin/Payroll/Configs/Index.vue')
const PayrollSlipIndex = () => import('../Pages/Admin/Payroll/Slip/Index.vue')
const PayrollThr = () => import('../Pages/Admin/Payroll/Thr.vue')
const BpjsKeanggotaanIndex = () => import('../Pages/Admin/Payroll/Bpjs/Keanggotaan/Index.vue')
const BpjsIuranIndex = () => import('../Pages/Admin/Payroll/Bpjs/Iuran/Index.vue')
const BpjsKonfigurasiIndex = () => import('../Pages/Admin/Payroll/Bpjs/Konfigurasi/Index.vue')
const WorkPatternsIndex = () => import('../Pages/Admin/Schedule/WorkPatterns/Index.vue')
const WorkPatternsDetails = () => import('../Pages/Admin/Schedule/WorkPatterns/Details.vue')
const ShiftsIndex = () => import('../Pages/Admin/Schedule/Shifts/Index.vue')
const CalendarsIndex = () => import('../Pages/Admin/Schedule/Calendars/Index.vue')
const CalendarsShow = () => import('../Pages/Admin/Schedule/Calendars/Show.vue')
const ScheduleRoster = () => import('../Pages/Admin/Schedule/Roster/Index.vue')
const RosterGenerate = () => import('../Pages/Admin/Schedule/Roster/Generate.vue')
const RosterImport = () => import('../Pages/Admin/Schedule/Roster/Import.vue')
const ReportsIndex = () => import('../Pages/Admin/Reports/Index.vue')
const KasbonRequests = () => import('../Pages/Admin/Kasbon/Requests.vue')
const KasbonApprovals = () => import('../Pages/Admin/Kasbon/Approvals.vue')
const KasbonRepayments = () => import('../Pages/Admin/Kasbon/Repayments.vue')
const KasbonHistory = () => import('../Pages/Admin/Kasbon/History.vue')
const UangMakanReport = () => import('../Pages/Admin/Reports/UangMakan/Index.vue')
const LaporanLemburIndex = () => import('../Pages/Admin/Reports/Lembur/Index.vue')
const LaporanLemburUangMakan = () => import('../Pages/Admin/Reports/LemburUangMakan/Index.vue')
const LaporanKehadiranIndex = () => import('../Pages/Admin/Reports/Kehadiran/Index.vue')
const LaporanPayrollIndex = () => import('../Pages/Admin/Reports/Payroll/Index.vue')
const LaporanBpjsIndex = () => import('../Pages/Admin/Reports/Bpjs/Index.vue')
const LaporanPphIndex = () => import('../Pages/Admin/Reports/Pph/Index.vue')
const RekabUangMakanIndex = () => import('../Pages/Admin/Reports/RekabUangMakan/Index.vue')
const RekapGajiIndex = () => import('../Pages/Admin/Reports/RekapGaji/Index.vue')
const RekapKerjaIndex = () => import('../Pages/Admin/Reports/RekapKerja/Index.vue')
const RekapPphKompensasiIndex = () => import('../Pages/Admin/Reports/RekapPphKompensasi/Index.vue')
const SettingsIndex = () => import('../Pages/Admin/Settings/Index.vue')
const NotificationsIndex = () => import('../Pages/Admin/Notifications/Index.vue')
const PphConfigIndex = () => import('../Pages/Admin/Payroll/Pph/Index.vue')
const PphEmployeesIndex = () => import('../Pages/Admin/Payroll/Pph/Employees.vue')

const SupervisorDashboard = () => import('../Pages/Supervisor/Dashboard.vue')
const SupervisorAttendance = () => import('../Pages/Supervisor/Attendance/Index.vue')
const SupervisorAttendanceImport = () => import('../Pages/Supervisor/Attendance/Import.vue')
const SupervisorAttendanceAutologShow = () => import('../Pages/Supervisor/Attendance/Autolog/Show.vue')
const SupervisorAttendanceSync = () => import('../Pages/Supervisor/Attendance/SyncKehadiran.vue')
const SupervisorAttendanceOvertime = () => import('../Pages/Supervisor/Attendance/Overtime/Index.vue')
const SupervisorAttendanceRecap = () => import('../Pages/Supervisor/Attendance/Recap.vue')
const SupervisorAttendanceSnapshot = () => import('../Pages/Supervisor/Attendance/Snapshot.vue')
const SupervisorRoster = () => import('../Pages/Supervisor/Attendance/Roster/Index.vue')
const SupervisorPayroll = () => import('../Pages/Supervisor/Payroll/Index.vue')
const SupervisorPayrollSlip = () => import('../Pages/Supervisor/Payroll/Slip.vue')
const SupervisorLeaveGenerate = () => import('../Pages/Supervisor/Leave/Generate.vue')
const SupervisorLeaveRequests = () => import('../Pages/Supervisor/Leave/Requests.vue')
const SupervisorLeaveCancellations = () => import('../Pages/Supervisor/Leave/Cancellations.vue')
const SupervisorLeaveBalances = () => import('../Pages/Supervisor/Leave/Balances.vue')
const SupervisorLeaveRecap = () => import('../Pages/Supervisor/Leave/Recap.vue')
const SupervisorEmployee = () => import('../Pages/Supervisor/Employee/Index.vue')
const SupervisorReports = () => import('../Pages/Supervisor/Reports/Index.vue')
const SupervisorThr = () => import('../Pages/Supervisor/Payroll/Thr.vue')
const SupervisorScheduleWorkPatterns = () => import('../Pages/Supervisor/Schedule/WorkPatterns/Index.vue')
const SupervisorScheduleWorkPatternsDetails = () => import('../Pages/Supervisor/Schedule/WorkPatterns/Details.vue')
const SupervisorScheduleShifts = () => import('../Pages/Supervisor/Schedule/Shifts/Index.vue')
const SupervisorScheduleRosterIndex = () => import('../Pages/Supervisor/Schedule/Roster/Index.vue')
const SupervisorScheduleRosterGenerate = () => import('../Pages/Supervisor/Schedule/Roster/Generate.vue')
const SupervisorScheduleRosterImport = () => import('../Pages/Supervisor/Schedule/Roster/Import.vue')

const routes = [
  {
    path: '/login',
    name: 'login',
    component: Login,
    meta: { guest: true },
  },

  {
    path: '/',
    name: 'landing',
    component: Index,
    meta: { guest: true },
  },

  {
    path: '/admin',
    component: () => import('../Layouts/RootLayout.vue'),
    children: [
      {
        path: '',
        component: () => import('../Layouts/Admin/AdminLayout.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
        children: [
          { path: '', name: 'admin.dashboard', component: AdminDashboard, meta: { title: 'Dashboard' } },
      { path: 'organization/company-profile', name: 'company-profile', component: CompanyProfile, meta: { title: 'Profil Perusahaan' } },
      { path: 'organization/admins', name: 'admins', component: AdminsIndex, meta: { title: 'Pengaturan Admin' } },
      { path: 'organization/departments', name: 'departments', component: DepartmentsIndex, meta: { title: 'Departemen' } },
      { path: 'organization/positions', name: 'positions', component: PositionsIndex, meta: { title: 'Jabatan' } },
      { path: 'organization/salary-grades', name: 'salary-grades', component: SalaryGradesIndex, meta: { title: 'Grade Gaji' } },
      { path: 'employees', name: 'employees', component: EmployeesIndex, meta: { title: 'Karyawan' } },
      { path: 'employees/import', name: 'employees.import', component: EmployeeImport, meta: { title: 'Import Karyawan' } },
      { path: 'employees/contracts', name: 'employees.contracts', component: EmployeeContractsIndex, meta: { title: 'Kontrak Kerja' } },
      { path: 'employees/contracts/import', name: 'employees.contracts.import', component: EmployeeContractsImport, meta: { title: 'Import Kontrak' } },
      { path: 'employees/kompensasi', name: 'employees.kompensasi', component: () => import('../Pages/Admin/Employees/Kompensasi/Index.vue'), meta: { title: 'Kompensasi Kontrak' } },
      { path: 'employees/grouping', name: 'employees.grouping', component: EmployeeGroupingIndex, meta: { title: 'Grouping Karyawan' } },
      { path: 'employees/ordering', name: 'employees.ordering', component: EmployeeOrderingIndex, meta: { title: 'Urutan Karyawan' } },
      { path: 'employees/create', name: 'employees.create', component: EmployeeCreate, meta: { title: 'Tambah Karyawan' } },
      { path: 'employees/:id', name: 'employees.show', component: EmployeeShow, meta: { title: 'Detail Karyawan' } },
      { path: 'employees/:id/edit', name: 'employees.edit', component: EmployeeEdit, meta: { title: 'Edit Karyawan' } },
      { path: 'employees/salaries', name: 'employees.salaries', component: SalariesIndex, meta: { title: 'Gaji Karyawan' } },
      { path: 'employees/salaries/create', name: 'employees.salaries.create', component: SalariesCreate, meta: { title: 'Tambah Gaji' } },
      { path: 'employees/salaries/import', name: 'employees.salaries.import', component: SalariesImport, meta: { title: 'Import Gaji' } },
      { path: 'employees/salaries/:id', name: 'employees.salaries.show', component: SalariesShow, meta: { title: 'Detail Gaji' } },
      { path: 'employees/salaries/:id/edit', name: 'employees.salaries.edit', component: SalariesEdit, meta: { title: 'Edit Gaji' } },
      { path: 'employees/position-histories', name: 'employees.position-histories', component: PositionHistoriesIndex, meta: { title: 'Riwayat Pekerjaan' } },
      { path: 'employees/families', name: 'employees.families', component: FamiliesIndex, meta: { title: 'Keluarga & Tanggungan' } },
      { path: 'employees/documents', name: 'employees.documents', component: DocumentsIndex, meta: { title: 'Dokumen' } },
      { path: 'employees/terminations', name: 'employees.terminations', component: TerminationsIndex, meta: { title: 'Resign & PHK' } },
      { path: 'attendance/recap', name: 'attendance.recap', component: AttendanceRecap, meta: { title: 'Resume Kehadiran' } },
      { path: 'attendance/import', name: 'attendance.import', component: LogImport, meta: { title: 'Import Log' } },
      { path: 'attendance/cek-log', name: 'attendance.cek-log', component: () => import('../Pages/Admin/Attendance/CekLog.vue'), meta: { title: 'Cek Log Kehadiran' } },
      { path: 'attendance/roster', name: 'attendance.roster', component: RosterIndex, meta: { title: 'Roster' } },
      { path: 'attendance/sync', name: 'attendance.sync', component: SyncKehadiran, meta: { title: 'Sync Kehadiran' } },
      { path: 'attendance/overtime', name: 'attendance.overtime', component: OvertimeIndex, meta: { title: 'Lembur' } },
      { path: 'attendance/overtime-calculation', name: 'attendance.overtime-calculation', component: OvertimeCalculationIndex, meta: { title: 'Perhitungan Lembur' } },
      { path: 'attendance/overtime-calculation/:id', name: 'attendance.overtime-calculation.detail', component: OvertimeCalculationDetail, meta: { title: 'Detail Perhitungan Lembur' } },
      { path: 'attendance/consecutive', name: 'attendance.consecutive', component: ConsecutiveIndex, meta: { title: 'Consecutive Day' } },
      { path: 'leave/settings', name: 'leave.settings', component: LeaveSettings, meta: { title: 'Pengaturan Cuti' } },
      // New separate leave pages
      { path: 'leave/generate', name: 'leave.generate', component: LeaveGenerate, meta: { title: 'Generate Cuti Tahunan' } },
      { path: 'leave/requests', name: 'leave.requests', component: LeaveRequests, meta: { title: 'Pengajuan Cuti' } },
      { path: 'leave/cancellations', name: 'leave.cancellations', component: LeaveCancellations, meta: { title: 'Pembatalan Cuti' } },
      { path: 'leave/balances', name: 'leave.balances', component: LeaveBalances, meta: { title: 'Saldo Cuti' } },
      { path: 'leave/recap', name: 'leave.recap', component: LeaveRecap, meta: { title: 'Rekap Cuti' } },
      { path: 'kasbon/requests', name: 'kasbon.requests', component: KasbonRequests, meta: { title: 'Pengajuan Kasbon' } },
      { path: 'kasbon/approvals', name: 'kasbon.approvals', component: KasbonApprovals, meta: { title: 'Persetujuan Kasbon' } },
      { path: 'kasbon/repayments', name: 'kasbon.repayments', component: KasbonRepayments, meta: { title: 'Pelunasan Kasbon' } },
      { path: 'kasbon/history', name: 'kasbon.history', component: KasbonHistory, meta: { title: 'Riwayat Kasbon' } },
      { path: 'payroll/periods', name: 'payroll.periods', component: PayrollPeriodsIndex, meta: { title: 'Gaji Karyawan (Legacy)' } },
      { path: 'payroll/periods/crud', name: 'payroll.periods.crud', component: PayrollPeriodsCrud, meta: { title: 'Penggajian' } },
      { path: 'payroll/gaji-karyawan', name: 'payroll.gaji-karyawan', component: PayrollGajiKaryawan, meta: { title: 'Gaji Karyawan' } },
      { path: 'payroll/periods/:id', name: 'payroll.periods.detail', component: PayrollPeriodDetail, meta: { title: 'Detail Periode' } },
      { path: 'payroll/slip', name: 'payroll.slip', component: PayrollSlipIndex, meta: { title: 'Slip Gaji' } },
      { path: 'payroll/thr', name: 'payroll.thr', component: PayrollThr, meta: { title: 'THR' } },
      { path: 'payroll/configs', name: 'payroll.configs', component: PayrollConfigsIndex, meta: { title: 'Konfigurasi Payroll' } },
      { path: 'payroll/bpjs/keanggotaan', name: 'payroll.bpjs.keanggotaan', component: BpjsKeanggotaanIndex, meta: { title: 'Keanggotaan BPJS' } },
      { path: 'payroll/bpjs/iuran', name: 'payroll.bpjs.iuran', component: BpjsIuranIndex, meta: { title: 'Iuran BPJS' } },
      { path: 'payroll/bpjs/konfigurasi', name: 'payroll.bpjs.konfigurasi', component: BpjsKonfigurasiIndex, meta: { title: 'Konfigurasi BPJS' } },
      { path: 'pph/ter', name: 'pph.ter', component: PphConfigIndex, meta: { title: 'Pengelolaan PPh 21' } },
      { path: 'pph/tahunan', name: 'pph.tahunan', component: PphConfigIndex, meta: { title: 'Pengelolaan PPh 21' } },
      { path: 'pph/employees', name: 'pph.employees', component: PphEmployeesIndex, meta: { title: 'Pajak Karyawan' } },
      { path: 'schedule/work-patterns', name: 'schedule.work-patterns', component: WorkPatternsIndex, meta: { title: 'Pola Kerja' } },
      { path: 'schedule/work-patterns/:id/details', name: 'schedule.work-patterns.details', component: WorkPatternsDetails, meta: { title: 'Detail Pola Kerja' } },
      { path: 'schedule/shifts', name: 'schedule.shifts', component: ShiftsIndex, meta: { title: 'Shift' } },
      { path: 'schedule/calendars', name: 'schedule.calendars', component: CalendarsIndex, meta: { title: 'Kalender' } },
      { path: 'schedule/calendars/:id', name: 'schedule.calendars.show', component: CalendarsShow, meta: { title: 'Detail Kalender' } },
      { path: 'schedule/roster', name: 'schedule.roster', component: ScheduleRoster, meta: { title: 'Roster' } },
      { path: 'schedule/roster/generate', name: 'schedule.roster.generate', component: RosterGenerate, meta: { title: 'Generate Roster' } },
      { path: 'schedule/roster/import', name: 'schedule.roster.import', component: RosterImport, meta: { title: 'Import Roster' } },
      { path: 'reports', name: 'reports', component: ReportsIndex, meta: { title: 'Laporan' } },
      { path: 'reports/uang-makan', name: 'reports.uang-makan', component: UangMakanReport, meta: { title: 'Laporan Uang Makan' } },
      { path: 'reports/lembur', name: 'reports.lembur', component: LaporanLemburIndex, meta: { title: 'Laporan Lembur' } },
      { path: 'reports/lembur-uang-makan', name: 'reports.lembur-uang-makan', component: LaporanLemburUangMakan, meta: { title: 'Laporan Lembur & Uang Makan' } },
      { path: 'reports/kehadiran', name: 'reports.kehadiran', component: LaporanKehadiranIndex, meta: { title: 'Laporan Kehadiran' } },
      { path: 'reports/payroll', name: 'reports.payroll', component: LaporanPayrollIndex, meta: { title: 'Laporan Payroll' } },
      { path: 'reports/bpjs', name: 'reports.bpjs', component: LaporanBpjsIndex, meta: { title: 'Laporan BPJS' } },
      { path: 'reports/pph', name: 'reports.pph', component: LaporanPphIndex, meta: { title: 'Rekap PPh 21' } },
      { path: 'reports/rekab-uang-makan', name: 'reports.rekab-uang-makan', component: RekabUangMakanIndex, meta: { title: 'Rekab Uang Makan' } },
      { path: 'reports/rekap-gaji', name: 'reports.rekap-gaji', component: RekapGajiIndex, meta: { title: 'Rekap Gaji' } },
      { path: 'reports/rekap-kerja', name: 'reports.rekap-kerja', component: RekapKerjaIndex, meta: { title: 'Rekap Kerja' } },
      { path: 'reports/rekap-pph-kompensasi', name: 'reports.rekap-pph-kompensasi', component: RekapPphKompensasiIndex, meta: { title: 'Rekap PPH & Kompensasi' } },
      { path: 'settings', name: 'settings', component: SettingsIndex, meta: { title: 'Pengaturan' } },
      { path: 'notifications', name: 'notifications', component: NotificationsIndex, meta: { title: 'Notifikasi' } },
    ],
  },
    ],
  },

  {
    path: '/supervisor',
    component: () => import('../Layouts/Supervisor/SupervisorLayout.vue'),
    meta: { requiresAuth: true, requiresSupervisor: true },
    children: [
      { path: '', name: 'supervisor.dashboard', component: SupervisorDashboard, meta: { title: 'Dashboard Supervisor' } },
      { path: 'attendance', name: 'supervisor.attendance', component: SupervisorAttendance, meta: { title: 'Data Absensi' } },
      { path: 'attendance/autolog/:id', name: 'supervisor.attendance.autolog', component: SupervisorAttendanceAutologShow, meta: { title: 'Detail Autolog Absensi' } },
      { path: 'attendance/import', name: 'supervisor.attendance.import', component: SupervisorAttendanceImport, meta: { title: 'Import Kehadiran' } },
      { path: 'attendance/sync', name: 'supervisor.attendance.sync', component: SupervisorAttendanceSync, meta: { title: 'Sync Kehadiran' } },
      { path: 'attendance/overtime', name: 'supervisor.attendance.overtime', component: SupervisorAttendanceOvertime, meta: { title: 'Lembur Staf' } },
      { path: 'attendance/recap', name: 'supervisor.attendance.recap', component: SupervisorAttendanceRecap, meta: { title: 'Rekap Absensi' } },
      { path: 'attendance/snapshot', name: 'supervisor.attendance.snapshot', component: SupervisorAttendanceSnapshot, meta: { title: 'Snapshot Absensi' } },
      { path: 'attendance/roster', name: 'supervisor.attendance.roster', component: SupervisorRoster, meta: { title: 'Roster Autolog', requiresSuperadmin: true } },
      { path: 'payroll', name: 'supervisor.payroll', component: SupervisorPayroll, meta: { title: 'Gaji Karyawan' } },
      { path: 'payroll/slip', name: 'supervisor.payroll.slip', component: SupervisorPayrollSlip, meta: { title: 'Slip Gaji' } },
      { path: 'payroll/thr', name: 'supervisor.payroll.thr', component: SupervisorThr, meta: { title: 'Perhitungan THR' } },
      { path: 'leave/generate', name: 'supervisor.leave.generate', component: SupervisorLeaveGenerate, meta: { title: 'Generate Cuti Tahunan' } },
      { path: 'leave/requests', name: 'supervisor.leave.requests', component: SupervisorLeaveRequests, meta: { title: 'Pengajuan Cuti' } },
      { path: 'leave/cancellations', name: 'supervisor.leave.cancellations', component: SupervisorLeaveCancellations, meta: { title: 'Pembatalan Cuti' } },
      { path: 'leave/balances', name: 'supervisor.leave.balances', component: SupervisorLeaveBalances, meta: { title: 'Saldo Cuti' } },
      { path: 'leave/recap', name: 'supervisor.leave.recap', component: SupervisorLeaveRecap, meta: { title: 'Rekap Cuti' } },
      { path: 'employee-data', name: 'supervisor.employee', component: SupervisorEmployee, meta: { title: 'Karyawan' } },
      { path: 'master', name: 'supervisor.master', component: () => import('../Pages/Supervisor/Master/Index.vue'), meta: { title: 'Data Master' } },
      { path: 'master/bpjs', name: 'supervisor.master.bpjs', component: () => import('../Pages/Supervisor/Master/BpjsConfig/Index.vue'), meta: { title: 'Konfigurasi BPJS' } },
      { path: 'master/pph', name: 'supervisor.master.pph', component: () => import('../Pages/Supervisor/Master/PphConfig/Index.vue'), meta: { title: 'Konfigurasi PPh' } },
      { path: 'master/thr', name: 'supervisor.master.thr', component: () => import('../Pages/Supervisor/Master/ThrConfig/Index.vue'), meta: { title: 'Konfigurasi THR' } },
      { path: 'master/scan-detection', name: 'supervisor.master.scan-detection', component: () => import('../Pages/Supervisor/Master/ScanDetection/Index.vue'), meta: { title: 'Aturan Jam Kerja & Denda' } },
      { path: 'master/departments', name: 'supervisor.master.departments', component: () => import('../Pages/Supervisor/Master/Departments/Index.vue'), meta: { title: 'Departemen' } },
      { path: 'master/positions', name: 'supervisor.master.positions', component: () => import('../Pages/Supervisor/Master/Positions/Index.vue'), meta: { title: 'Jabatan' } },
      { path: 'master/leave-settings', name: 'supervisor.master.leave-settings', component: () => import('../Pages/Supervisor/Master/LeaveSettings/Index.vue'), meta: { title: 'Pengaturan Cuti' } },
      
      // Employee Data
      { path: 'employee-data', name: 'supervisor.employee', component: () => import('../Pages/Supervisor/Employee/Index.vue'), meta: { title: 'Data Karyawan Hub' } },
      
      { path: 'employee-data/karyawan', name: 'supervisor.employee.karyawan', component: () => import('../Pages/Supervisor/Employee/Karyawan/Index.vue'), meta: { title: 'Karyawan' } },
      { path: 'employee-data/karyawan/create', name: 'supervisor.employee.karyawan.create', component: () => import('../Pages/Supervisor/Employee/Karyawan/Create.vue'), meta: { title: 'Tambah Karyawan' } },
      { path: 'employee-data/karyawan/:id', name: 'supervisor.employee.karyawan.show', component: () => import('../Pages/Supervisor/Employee/Karyawan/Show.vue'), meta: { title: 'Detail Karyawan' } },
      { path: 'employee-data/karyawan/:id/edit', name: 'supervisor.employee.karyawan.edit', component: () => import('../Pages/Supervisor/Employee/Karyawan/Edit.vue'), meta: { title: 'Edit Karyawan' } },

      { path: 'employee-data/kontrak-kerja', name: 'supervisor.employee.contracts', component: () => import('../Pages/Supervisor/Employee/Contracts/Index.vue'), meta: { title: 'Kontrak Kerja' } },
      { path: 'employee-data/kontrak-kerja/import', name: 'supervisor.employee.contracts.import', component: () => import('../Pages/Supervisor/Employee/Contracts/Import.vue'), meta: { title: 'Import Kontrak' } },

      { path: 'employee-data/gaji-karyawan', name: 'supervisor.employee.salaries', component: () => import('../Pages/Supervisor/Employee/Salaries/Index.vue'), meta: { title: 'Gaji Karyawan' } },
      { path: 'employee-data/gaji-karyawan/create', name: 'supervisor.employee.salaries.create', component: () => import('../Pages/Supervisor/Employee/Salaries/Create.vue'), meta: { title: 'Tambah Gaji' } },
      { path: 'employee-data/gaji-karyawan/import', name: 'supervisor.employee.salaries.import', component: () => import('../Pages/Supervisor/Employee/Salaries/Import.vue'), meta: { title: 'Import Gaji' } },
      { path: 'employee-data/gaji-karyawan/:id', name: 'supervisor.employee.salaries.show', component: () => import('../Pages/Supervisor/Employee/Salaries/Show.vue'), meta: { title: 'Detail Gaji' } },
      { path: 'employee-data/gaji-karyawan/:id/edit', name: 'supervisor.employee.salaries.edit', component: () => import('../Pages/Supervisor/Employee/Salaries/Edit.vue'), meta: { title: 'Edit Gaji' } },

      { path: 'employee-data/kompensasi', name: 'supervisor.employee.compensation', component: () => import('../Pages/Supervisor/Employee/Kompensasi/Index.vue'), meta: { title: 'Kompensasi' } },

      { path: 'employee-data/bpjs-karyawan', name: 'supervisor.employee.bpjs', component: () => import('../Pages/Supervisor/Employee/Bpjs/Index.vue'), meta: { title: 'BPJS Karyawan' } },

      { path: 'employee-data/pph-karyawan', name: 'supervisor.employee.pph', component: () => import('../Pages/Supervisor/Employee/Pph/Employees.vue'), meta: { title: 'PPh Karyawan' } },

      { path: 'reports', name: 'supervisor.reports', component: SupervisorReports, meta: { title: 'Laporan' } },

      { path: 'schedule/work-patterns', name: 'supervisor.schedule.work-patterns', component: SupervisorScheduleWorkPatterns, meta: { title: 'Pola Kerja' } },
      { path: 'schedule/work-patterns/:id/details', name: 'supervisor.schedule.work-patterns.details', component: SupervisorScheduleWorkPatternsDetails, meta: { title: 'Detail Pola Kerja' } },
      { path: 'schedule/shifts', name: 'supervisor.schedule.shifts', component: SupervisorScheduleShifts, meta: { title: 'Shift' } },
      { path: 'schedule/roster', name: 'supervisor.schedule.roster', component: SupervisorScheduleRosterIndex, meta: { title: 'Roster' } },
      { path: 'schedule/roster/generate', name: 'supervisor.schedule.roster.generate', component: SupervisorScheduleRosterGenerate, meta: { title: 'Generate Roster' } },
      { path: 'schedule/roster/import', name: 'supervisor.schedule.roster.import', component: SupervisorScheduleRosterImport, meta: { title: 'Import Roster' } },
    ],
  },

  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.isAuthenticated && !auth.user) {
    await auth.fetchUser()
  }

  if (to.meta.guest && auth.isAuthenticated) {
    if (auth.canAccessAdmin) return '/admin'
    return '/supervisor'
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return '/login'
  }

  if (to.meta.requiresAdmin && auth.isAuthenticated && !auth.canAccessAdmin) {
    return '/supervisor'
  }

  if (to.meta.requiresSupervisor && auth.isAuthenticated && !auth.canAccessSupervisor) {
    return '/admin'
  }

  if (to.meta.requiresSuperadmin && auth.isAuthenticated && !auth.isSuperadmin) {
    return '/supervisor'
  }

  return true
})

export default router
