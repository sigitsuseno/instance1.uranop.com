import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../composables/useApi'

export const useCompanyStore = defineStore('company', () => {
  const company = ref(null)
  const loaded = ref(false)
  const loading = ref(false)

  const name = computed(() => company.value?.name || 'Uranop Enterprise')
  const logoUrl = computed(() =>
    company.value?.logo_path ? `/storage/${company.value.logo_path}` : null
  )
  const logoInitial = computed(() => (company.value?.name || 'Uranop').charAt(0).toUpperCase())

  async function fetchCompany(force = false) {
    if (loading.value) return
    if (loaded.value && !force) return
    loading.value = true
    try {
      const data = await api('/api/company')
      company.value = data.data || null
      loaded.value = true
    } catch (e) {
      // Fallback ke default (Uranop Enterprise) bila endpoint gagal
      company.value = null
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  return {
    company,
    loaded,
    loading,
    name,
    logoUrl,
    logoInitial,
    fetchCompany,
  }
})
