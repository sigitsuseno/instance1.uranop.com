<template>
  <div class="p-4 sm:p-6 lg:p-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <router-link to="/admin/attendance/sync" class="text-(--text-muted) hover:text-(--text-main) transition">
            <i class="bx bx-arrow-back text-xl"></i>
          </router-link>
          <h1 class="text-2xl font-bold text-(--text-main)">Perhitungan Overtime</h1>
        </div>
        <p class="text-sm text-(--text-muted)">
          <i class="bx bx-calendar-alt mr-1"></i>
          Periode: {{ formatDateRange() }}
        </p>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <BaseButton 
          variant="primary"
          @click="auth.isManajemen ? null : handleCalculate()"
          :disabled="auth.isManajemen"
          :loading="isCalculating"
          class="shadow-sm flex items-center gap-2"
          :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed' : ''"
        >
          <template #icon-left>
            <i class="bx bx-calculator text-lg"></i>
          </template>
          Calculate Overtime
        </BaseButton>
      </div>
    </div>

    <!-- Status Banner -->
    <div v-if="calculateResult" class="mb-4 p-3 rounded-lg text-sm font-medium"
      :class="calculateResult.success ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
      {{ calculateResult.message }}
      <button class="ml-2 underline text-xs" @click="calculateResult = null">Tutup</button>
    </div>

    <!-- Period Selection Card -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6">
      <div class="flex flex-col sm:flex-row gap-3">
        <div>
          <label class="block text-xs font-medium text-(--text-muted) mb-1">
            <i class="bx bx-calendar mr-1"></i>
            Tanggal Mulai
          </label>
          <input 
            type="date"
            v-model="startDate"
            class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-(--text-muted) mb-1">
            <i class="bx bx-calendar mr-1"></i>
            Tanggal Akhir
          </label>
          <input 
            type="date"
            v-model="endDate"
            class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
          />
        </div>
        <div class="flex items-end gap-2">
          <BaseButton 
            variant="primary"
            @click="applyPeriod"
            class="transition text-sm"
          >
            <template #icon-left>
              <i class="bx bx-check mr-1"></i>
            </template>
            Terapkan
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="relative w-full sm:w-64">
          <i class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft)"></i>
          <input 
            v-model="searchQuery"
            type="text"
            placeholder="Cari karyawan..."
            class="w-full pl-9 pr-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
            @keyup.enter="submitFilter"
          />
        </div>
        
        <button 
          v-if="searchQuery"
          @click="resetFilter"
          class="p-2 rounded-lg border border-(--border-soft) hover:bg-(--bg-elevated) transition"
        >
          <i class="bx bx-x text-lg text-(--text-muted)"></i>
        </button>
      </div>

      <div class="flex items-center gap-2">
        <div v-if="isLoading" class="flex items-center gap-1 text-sm text-(--text-muted)">
          <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.4 31.4" />
          </svg>
          Memuat data...
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm">
      <table class="w-full text-sm text-left">
        <thead class="bg-(--bg-elevated) border-b border-(--border-soft)">
          <tr>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs">Karyawan</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Total Hadir</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">LM</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Total L/M</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Lembur HB</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Total LHB</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Total Lembur</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-(--border-soft)">
          <tr 
            v-for="employee in employees" 
            :key="employee.id"
            class="hover:bg-(--bg-elevated)/50 transition"
          >
            <td class="px-4 py-3">
              <div class="font-medium text-(--text-main)">{{ employee.employee_name }}</div>
              <div class="text-xs text-(--text-muted)">{{ employee.employee_code }} &bull; {{ employee.department || '-' }}</div>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center justify-center px-2 py-1 rounded-md bg-(--bg-elevated) text-(--text-main) text-xs font-semibold">
                {{ employee.total_hadir }} Hari
              </span>
            </td>
            <td class="px-4 py-3 text-center text-(--text-main)">
              {{ formatMinutes(employee.lm) }}
            </td>
            <td class="px-4 py-3 text-center text-orange-600 dark:text-orange-400">
              {{ formatMinutes(employee.total_lm) }}
            </td>
            <td class="px-4 py-3 text-center text-(--text-main)">
              {{ formatMinutes(employee.lembur_hb) }}
            </td>
            <td class="px-4 py-3 text-center text-orange-600 dark:text-orange-400">
              {{ formatMinutes(employee.total_lhb) }}
            </td>
            <td class="px-4 py-3 text-center font-semibold text-(--primary)">
              {{ formatMinutes(employee.total_lembur) }}
            </td>
            <td class="px-4 py-3 text-center">
              <router-link 
                :to="`/admin/attendance/overtime-calculation/${employee.id}?start_date=${startDate}&end_date=${endDate}`"
                class="inline-flex items-center gap-1 px-3 py-1.5 bg-(--bg-elevated) hover:bg-gray-200 dark:hover:bg-gray-700 text-(--text-main) rounded-lg transition text-xs font-medium border border-(--border-soft)"
              >
                <i class="bx bx-list-ul"></i> Detail
              </router-link>
            </td>
          </tr>
          
          <tr v-if="!isLoading && employees.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-(--text-muted)">
              <i class="bx bx-folder-open text-3xl mb-2 block text-(--text-soft)"></i>
              Tidak ada data karyawan ditemukan.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4 flex justify-between items-center" v-if="pagination.total > 0">
      <div class="text-sm text-(--text-muted)">
        Menampilkan {{ pagination.from || 0 }} - {{ pagination.to || 0 }} dari {{ pagination.total || 0 }} karyawan
      </div>
      <div class="flex gap-2">
        <button 
          :disabled="!pagination.links?.prev"
          @click="fetchData(pagination.current_page - 1)"
          class="px-3 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) hover:bg-(--bg-elevated) transition inline-flex items-center gap-1 text-sm bg-(--bg-card) disabled:opacity-50"
        >
          <i class="bx bx-chevron-left"></i> Sebelumnya
        </button>
        <button 
          :disabled="!pagination.links?.next"
          @click="fetchData(pagination.current_page + 1)"
          class="px-3 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) hover:bg-(--bg-elevated) transition inline-flex items-center gap-1 text-sm bg-(--bg-card) disabled:opacity-50"
        >
          Selanjutnya <i class="bx bx-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/Components/BaseButton.vue'
import { useApi } from '../../../../composables/useApi'
import { useAuth } from '../../../../composables/useAuth'

const { get, post } = useApi()
const auth = useAuth()
const route = useRoute()
const router = useRouter()

// ── State ──
const isCalculating = ref(false)
const isLoading = ref(true)
const calculateResult = ref(null)

const startDate = ref(route.query.start_date || new Date().toISOString().split('T')[0].slice(0, 8) + '01')
const endDate = ref(route.query.end_date || new Date().toISOString().split('T')[0])
const searchQuery = ref(route.query.search || '')

const employees = ref([])
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
  from: 0,
  to: 0,
  prev: null,
  next: null
})

// ── Methods ──
function formatDateRange() {
  if (!startDate.value || !endDate.value) return ''
  const start = new Date(startDate.value)
  const end = new Date(endDate.value)
  const formatOptions = { day: 'numeric', month: 'short', year: 'numeric' }
  return `${start.toLocaleDateString('id-ID', formatOptions)} - ${end.toLocaleDateString('id-ID', formatOptions)}`
}

function formatMinutes(minutes) {
  if (!minutes || minutes === 0) return '0'
  return Number((minutes / 60).toFixed(2)).toString()
}

async function fetchData(page = 1) {
  isLoading.value = true
  try {
    const res = await get(`/api/v1/attendance/prepare/overtime-summary?start_date=${startDate.value}&end_date=${endDate.value}&search=${searchQuery.value}&page=${page}`)
    employees.value = res.data || []
    if (res.meta) {
      pagination.value = res.meta
    }
  } catch (e) {
    console.error('Gagal fetch data lembur:', e)
  } finally {
    isLoading.value = false
  }
}

function applyPeriod() {
  router.push({
    query: {
      ...route.query,
      start_date: startDate.value,
      end_date: endDate.value,
      search: searchQuery.value
    }
  })
  fetchData(1)
}

function submitFilter() {
  applyPeriod()
}

function resetFilter() {
  searchQuery.value = ''
  applyPeriod()
}

async function handleCalculate() {
  if (!startDate.value || !endDate.value) {
    alert('Silakan pilih rentang tanggal terlebih dahulu.')
    return
  }
  
  if (!confirm('Kalkulasi ulang multiplier overtime untuk semua data pending di periode ini?')) return
  
  isCalculating.value = true
  calculateResult.value = null

  try {
    const res = await post('/api/v1/attendance/prepare/hitung-lembur', {
      start_date: startDate.value,
      end_date: endDate.value,
    })
    
    calculateResult.value = {
      success: res.success || true,
      message: res.message || 'Hitung lembur selesai.'
    }
    
    if (res.success !== false) {
      fetchData(pagination.value.current_page)
    }
  } catch (error) {
    const msg = error.response?.data?.message || error.message || 'Unknown error'
    calculateResult.value = {
      success: false,
      message: 'Gagal mengalkulasi: ' + msg
    }
  } finally {
    isCalculating.value = false
  }
}

// ── Init ──
onMounted(() => {
  fetchData()
})
</script>
