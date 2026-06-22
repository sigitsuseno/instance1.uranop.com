import { defineStore } from 'pinia'
import { useAuthStore } from './auth'

export const usePermissionStore = defineStore('permission', () => {
  const auth = useAuthStore()

  const rolePermissions = {
    superadmin: ['*'],
    hrmanager: [
      'view companies', 'view branches', 'view departments', 'view positions', 'view salary_grades',
      'view employees', 'create employees', 'edit employees', 'delete employees', 'import employees', 'export employees',
      'view attendances', 'import attendances', 'edit attendances', 'manage overtime',
      'view payroll', 'generate payroll', 'export payroll', 'print payslip',
      'view leave', 'manage leave', 'approve leave', 'manage leave types', 'manage leave settings',
      'approve requests', 'reject requests',
      'view supervisor dashboard', 'manage supervisor data', 'export supervisor data',
    ],
    adm_manager: [
      'view supervisor dashboard', 'manage supervisor data', 'export supervisor data',
    ],
    hrbranch: [
      'view supervisor dashboard',
      'view departments', 'view positions', 'view salary_grades',
      'view employees',
      'view attendances', 'import attendances', 'edit attendances',
      'manage overtime', 'approve overtime',
      'view payroll',
      'view leave', 'manage leave', 'approve leave',
      'export supervisor data',
    ],
    hr_ast: [
      'view departments', 'view positions',
      'view employees', 'create employees', 'edit employees',
      'view attendances',
      'view leave',
    ],
  }

  function can(permission) {
    const role = auth.userRole
    if (!role) return false
    const perms = rolePermissions[role] || []
    if (perms.includes('*')) return true
    return perms.includes(permission)
  }

  function canAny(permissions) {
    return permissions.some((p) => can(p))
  }

  function canAll(permissions) {
    return permissions.every((p) => can(p))
  }

  return { can, canAny, canAll }
})
