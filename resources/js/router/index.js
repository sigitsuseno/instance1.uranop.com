import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../Stores/auth'

const Index = () => import('../Pages/Index.vue')
const Login = () => import('../Pages/Auth/Login.vue')

const AdminDashboard = () => import('../Pages/Admin/Dashboard.vue')
const DepartmentsIndex = () => import('../Pages/Admin/Organization/Departments/Index.vue')
const PositionsIndex = () => import('../Pages/Admin/Organization/Positions/Index.vue')
const SalaryGradesIndex = () => import('../Pages/Admin/Organization/SalaryGrades/Index.vue')
const EmployeesIndex = () => import('../Pages/Admin/Employees/Index.vue')
const EmployeeShow = () => import('../Pages/Admin/Employees/Show.vue')
const EmployeeCreate = () => import('../Pages/Admin/Employees/Create.vue')
const EmployeeEdit = () => import('../Pages/Admin/Employees/Edit.vue')
const AttendanceIndex = () => import('../Pages/Admin/Attendance/Index.vue')
const LogImport = () => import('../Pages/Admin/Attendance/LogImport.vue')
const RosterIndex = () => import('../Pages/Admin/Attendance/Roster.vue')
const OvertimeIndex = () => import('../Pages/Admin/Attendance/Overtime/Index.vue')
const LeaveIndex = () => import('../Pages/Admin/Leave/Index.vue')
const LeaveApprovals = () => import('../Pages/Admin/Leave/Approvals.vue')
const LeaveSettings = () => import('../Pages/Admin/Leave/Settings.vue')
const PayrollPeriodsIndex = () => import('../Pages/Admin/Payroll/Periods/Index.vue')
const PayrollPeriodDetail = () => import('../Pages/Admin/Payroll/Periods/Detail.vue')
const PayrollConfigsIndex = () => import('../Pages/Admin/Payroll/Configs/Index.vue')
const PayrollThr = () => import('../Pages/Admin/Payroll/Thr.vue')
const WorkPatternsIndex = () => import('../Pages/Admin/Schedule/WorkPatterns/Index.vue')
const ShiftsIndex = () => import('../Pages/Admin/Schedule/Shifts/Index.vue')
const CalendarsIndex = () => import('../Pages/Admin/Schedule/Calendars/Index.vue')
const ScheduleRoster = () => import('../Pages/Admin/Schedule/Roster/Index.vue')
const ReportsIndex = () => import('../Pages/Admin/Reports/Index.vue')
const SettingsIndex = () => import('../Pages/Admin/Settings/Index.vue')

const SupervisorDashboard = () => import('../Pages/Supervisor/Dashboard.vue')
const SupervisorAttendance = () => import('../Pages/Supervisor/Attendance/Index.vue')
const SupervisorRoster = () => import('../Pages/Supervisor/Attendance/Roster/Index.vue')
const SupervisorPayroll = () => import('../Pages/Supervisor/Payroll/Index.vue')
const SupervisorLeave = () => import('../Pages/Supervisor/Leave/Index.vue')
const SupervisorEmployee = () => import('../Pages/Supervisor/Employee/Index.vue')
const SupervisorReports = () => import('../Pages/Supervisor/Reports/Index.vue')
const SupervisorThr = () => import('../Pages/Supervisor/Payroll/Thr.vue')

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
      { path: 'organization/departments', name: 'departments', component: DepartmentsIndex, meta: { title: 'Departemen' } },
      { path: 'organization/positions', name: 'positions', component: PositionsIndex, meta: { title: 'Jabatan' } },
      { path: 'organization/salary-grades', name: 'salary-grades', component: SalaryGradesIndex, meta: { title: 'Grade Gaji' } },
      { path: 'employees', name: 'employees', component: EmployeesIndex, meta: { title: 'Karyawan' } },
      { path: 'employees/create', name: 'employees.create', component: EmployeeCreate, meta: { title: 'Tambah Karyawan' } },
      { path: 'employees/:id', name: 'employees.show', component: EmployeeShow, meta: { title: 'Detail Karyawan' } },
      { path: 'employees/:id/edit', name: 'employees.edit', component: EmployeeEdit, meta: { title: 'Edit Karyawan' } },
      { path: 'attendance', name: 'attendance', component: AttendanceIndex, meta: { title: 'Absensi' } },
      { path: 'attendance/import', name: 'attendance.import', component: LogImport, meta: { title: 'Import Log' } },
      { path: 'attendance/roster', name: 'attendance.roster', component: RosterIndex, meta: { title: 'Roster' } },
      { path: 'attendance/overtime', name: 'attendance.overtime', component: OvertimeIndex, meta: { title: 'Lembur' } },
      { path: 'leave', name: 'leave', component: LeaveIndex, meta: { title: 'Cuti' } },
      { path: 'leave/approvals', name: 'leave.approvals', component: LeaveApprovals, meta: { title: 'Approval Cuti' } },
      { path: 'leave/settings', name: 'leave.settings', component: LeaveSettings, meta: { title: 'Pengaturan Cuti' } },
      { path: 'payroll', name: 'payroll', component: PayrollPeriodsIndex, meta: { title: 'Generate Gaji' } },
      { path: 'payroll/thr', name: 'payroll.thr', component: PayrollThr, meta: { title: 'THR' } },
      { path: 'payroll/configs', name: 'payroll.configs', component: PayrollConfigsIndex, meta: { title: 'Konfigurasi Payroll' } },
      { path: 'payroll/periods/:id', name: 'payroll.periods.detail', component: PayrollPeriodDetail, meta: { title: 'Detail Periode' } },
      { path: 'schedule/work-patterns', name: 'schedule.work-patterns', component: WorkPatternsIndex, meta: { title: 'Pola Kerja' } },
      { path: 'schedule/shifts', name: 'schedule.shifts', component: ShiftsIndex, meta: { title: 'Shift' } },
      { path: 'schedule/calendars', name: 'schedule.calendars', component: CalendarsIndex, meta: { title: 'Kalender' } },
      { path: 'schedule/roster', name: 'schedule.roster', component: ScheduleRoster, meta: { title: 'Roster' } },
      { path: 'reports', name: 'reports', component: ReportsIndex, meta: { title: 'Laporan' } },
      { path: 'settings', name: 'settings', component: SettingsIndex, meta: { title: 'Pengaturan' } },
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
      { path: 'attendance', name: 'supervisor.attendance', component: SupervisorAttendance, meta: { title: 'Kehadiran' } },
      { path: 'attendance/roster', name: 'supervisor.attendance.roster', component: SupervisorRoster, meta: { title: 'Roster' } },
      { path: 'payroll', name: 'supervisor.payroll', component: SupervisorPayroll, meta: { title: 'Generate Gaji' } },
      { path: 'payroll/thr', name: 'supervisor.payroll.thr', component: SupervisorThr, meta: { title: 'THR' } },
      { path: 'leave', name: 'supervisor.leave', component: SupervisorLeave, meta: { title: 'Cuti' } },
      { path: 'employee', name: 'supervisor.employee', component: SupervisorEmployee, meta: { title: 'Karyawan' } },
      { path: 'reports', name: 'supervisor.reports', component: SupervisorReports, meta: { title: 'Laporan' } },
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

  return true
})

export default router
