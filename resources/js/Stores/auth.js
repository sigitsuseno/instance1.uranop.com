import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../composables/useApi'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token') || null)

  const isAuthenticated = computed(() => !!token.value)
  const userRole = computed(() => user.value?.role || null)
  const userName = computed(() => user.value?.name || '')

  const isSuperadmin = computed(() => userRole.value === 'superadmin')
  const isHrmanager = computed(() => userRole.value === 'hrmanager')
  const isAdmManager = computed(() => userRole.value === 'adm_manager')
  const isHrbranch = computed(() => userRole.value === 'hrbranch')
  const isHrAst = computed(() => userRole.value === 'hr_ast')
  const isAdmin = computed(() => userRole.value === 'admin')

  const canAccessAdmin = computed(() =>
    ['superadmin', 'hrmanager', 'hrbranch', 'hr_ast'].includes(userRole.value)
  )

  const canAccessSupervisor = computed(() =>
    ['superadmin', 'adm_manager', 'admin'].includes(userRole.value)
  )

  function setToken(value) {
    token.value = value
    if (value) {
      localStorage.setItem('token', value)
    } else {
      localStorage.removeItem('token')
    }
  }

  async function login(credentials) {
    const data = {
      token: 'dummy-token-123',
      user: {
        id: 1,
        name: 'Super Admin Dummy',
        email: credentials.email,
        role: 'superadmin'
      }
    }
    setToken(data.token)
    user.value = data.user
    return data
  }

  async function fetchUser() {
    try {
      const data = {
        id: 1,
        name: 'Super Admin Dummy',
        email: 'superadmin@uranop.com',
        role: 'superadmin'
      }
      user.value = data
    } catch {
      setToken(null)
      user.value = null
    }
  }

  async function logout() {
    try {
      await api('/api/logout', { method: 'POST' })
    } catch {
      // ignore
    }
    setToken(null)
    user.value = null
  }

  return {
    user,
    token,
    isAuthenticated,
    userRole,
    userName,
    isSuperadmin,
    isHrmanager,
    isAdmManager,
    isHrbranch,
    isHrAst,
    isAdmin,
    canAccessAdmin,
    canAccessSupervisor,
    login,
    fetchUser,
    logout,
    setToken,
  }
})
