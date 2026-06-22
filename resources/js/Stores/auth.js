import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../composables/useApi'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token') || null)

  const isAuthenticated = computed(() => !!token.value)
  const userRole = computed(() => {
    const role = user.value?.roles?.[0]
    if (role && typeof role === 'object') return role.name
    return role || user.value?.user_type || null
  })
  const userName = computed(() => user.value?.name || '')

  const isSuperadmin = computed(() => userRole.value === 'superadmin')
  const isHrmanager = computed(() => userRole.value === 'hrmanager')
  const isAdmManager = computed(() => userRole.value === 'adm_manager')
  const isHrbranch = computed(() => userRole.value === 'hrbranch')
  const isHrAst = computed(() => userRole.value === 'hr_ast')

  const canAccessAdmin = computed(() =>
    ['superadmin', 'hrmanager', 'hr_ast'].includes(userRole.value)
  )

  const canAccessSupervisor = computed(() =>
    ['superadmin', 'adm_manager', 'hrbranch'].includes(userRole.value)
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
    const data = await api('/api/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    })
    setToken(data.token)
    user.value = data.user
    return data
  }

  async function fetchUser() {
    try {
      const data = await api('/api/user')
      user.value = data.data || data
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
    canAccessAdmin,
    canAccessSupervisor,
    login,
    fetchUser,
    logout,
    setToken,
  }
})
