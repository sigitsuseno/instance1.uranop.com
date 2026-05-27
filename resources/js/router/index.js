import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../Stores/auth'

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
    path: '/admin',
    redirect: '/',
  },
  {
    path: '/',
    component: () => import('../Layouts/AuthenticatedLayout.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      { path: '', name: 'admin.dashboard', component: AdminDashboard },
      { path: 'organization/departments', name: 'departments', component: DepartmentsIndex },
      { path: 'organization/positions', name: 'positions', component: PositionsIndex },
      { path: 'organization/salary-grades', name: 'salary-grades', component: SalaryGradesIndex },
      { path: 'employees', name: 'employees', component: EmployeesIndex },
      { path: 'employees/create', name: 'employees.create', component: EmployeeCreate },
      { path: 'employees/:id', name: 'employees.show', component: EmployeeShow },
      { path: 'employees/:id/edit', name: 'employees.edit', component: EmployeeEdit },
      { path: 'attendance', name: 'attendance', component: AttendanceIndex },
      { path: 'attendance/import', name: 'attendance.import', component: LogImport },
      { path: 'attendance/roster', name: 'attendance.roster', component: RosterIndex },
      { path: 'attendance/overtime', name: 'attendance.overtime', component: OvertimeIndex },
      { path: 'leave', name: 'leave', component: LeaveIndex },
      { path: 'leave/approvals', name: 'leave.approvals', component: LeaveApprovals },
      { path: 'leave/settings', name: 'leave.settings', component: LeaveSettings },
      { path: 'payroll', name: 'payroll', component: PayrollPeriodsIndex },
      { path: 'payroll/thr', name: 'payroll.thr', component: PayrollThr },
      { path: 'payroll/configs', name: 'payroll.configs', component: PayrollConfigsIndex },
      { path: 'payroll/periods/:id', name: 'payroll.periods.detail', component: PayrollPeriodDetail },
      { path: 'schedule/work-patterns', name: 'schedule.work-patterns', component: WorkPatternsIndex },
      { path: 'schedule/shifts', name: 'schedule.shifts', component: ShiftsIndex },
      { path: 'schedule/calendars', name: 'schedule.calendars', component: CalendarsIndex },
      { path: 'schedule/roster', name: 'schedule.roster', component: ScheduleRoster },
      { path: 'reports', name: 'reports', component: ReportsIndex },
      { path: 'settings', name: 'settings', component: SettingsIndex },
    ],
  },

  {
    path: '/supervisor',
    component: () => import('../Layouts/AuthenticatedLayout.vue'),
    meta: { requiresAuth: true, requiresSupervisor: true },
    children: [
      { path: '', name: 'supervisor.dashboard', component: SupervisorDashboard },
      { path: 'attendance', name: 'supervisor.attendance', component: SupervisorAttendance },
      { path: 'attendance/roster', name: 'supervisor.attendance.roster', component: SupervisorRoster },
      { path: 'payroll', name: 'supervisor.payroll', component: SupervisorPayroll },
      { path: 'payroll/thr', name: 'supervisor.payroll.thr', component: SupervisorThr },
      { path: 'leave', name: 'supervisor.leave', component: SupervisorLeave },
      { path: 'employee', name: 'supervisor.employee', component: SupervisorEmployee },
      { path: 'reports', name: 'supervisor.reports', component: SupervisorReports },
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
    return '/'
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return '/login'
  }

  if (to.meta.requiresAdmin && auth.isAuthenticated && !auth.canAccessAdmin) {
    return '/supervisor'
  }

  if (to.meta.requiresSupervisor && auth.isAuthenticated && !auth.canAccessSupervisor) {
    return '/'
  }

  return true
})

export default router
