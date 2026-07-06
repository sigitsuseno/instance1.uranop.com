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
        <button 
          @click="openSettings"
          class="h-10 px-3 text-sm rounded-lg border border-(--border-soft) bg-(--bg-card) hover:bg-(--bg-elevated) flex items-center gap-1.5 transition-colors"
          title="Pengaturan Lembur"
        >
          <i class="bx bx-cog text-lg text-(--text-muted)"></i>
          <span class="hidden sm:inline">Setting</span>
        </button>

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

        <button 
          @click="handleExport"
          :disabled="isExporting"
          class="h-10 px-4 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 flex items-center gap-2 transition-colors shadow-sm disabled:opacity-50"
        >
          <i class="bx bx-file text-lg" v-if="!isExporting"></i>
          <i class="bx bx-loader-alt bx-spin text-lg" v-else></i>
          <span class="hidden sm:inline">Export Excel</span>
        </button>
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

    <!-- Modal Setting -->
    <BaseModal :show="showSettings" @close="showSettings = false" title="Pengaturan Kalkulasi Lembur">
      <div class="space-y-5" v-if="overtimeConfig">
        
        <div>
          <label class="block text-sm font-semibold text-(--text-main) mb-1">Pemilihan Rumus Lembur</label>
          <div class="space-y-3 p-3 bg-(--bg-elevated) rounded-lg border border-(--border-soft)">
            <div class="flex flex-col sm:flex-row gap-3">
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">Pola FIXED</label>
                <select v-model="settingForm.formula_fixed" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
                  <option value="rumus_1">Rumus 1 (Scan In - Out)</option>
                  <option value="rumus_2">Rumus 2 (Schedule Out - Out)</option>
                </select>
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">FLEX-SHIFT (Kode S)</label>
                <select v-model="settingForm.formula_flex_s" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
                  <option value="rumus_1">Rumus 1 (Scan In - Out)</option>
                  <option value="rumus_2">Rumus 2 (Schedule Out - Out)</option>
                </select>
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">FLEX-SHIFT (Kode P)</label>
                <select v-model="settingForm.formula_flex_p" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
                  <option value="rumus_1">Rumus 1 (Scan In - Out)</option>
                  <option value="rumus_2">Rumus 2 (Schedule Out - Out)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-(--text-main) mb-1">Karyawan Khusus</label>
          <div class="space-y-3 p-3 bg-(--bg-elevated) rounded-lg border border-(--border-soft)">
            <div class="flex flex-col sm:flex-row gap-3">
              <div class="flex-[2]">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">ID Karyawan</label>
                <input v-model="settingForm.special_ids" type="text" placeholder="Misal: 102, 105" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">Pilih Rumus</label>
                <select v-model="settingForm.special_formula" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
                  <option value="rumus_1">Rumus 1</option>
                  <option value="rumus_2">Rumus 2</option>
                </select>
              </div>
            </div>
            <p class="text-[11px] text-(--text-muted) mt-1">Karyawan dengan ID di atas akan mengabaikan pola kerja dan dipaksa menggunakan rumus yang dipilih.</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-(--text-main) mb-1">Aturan Teknisi Khusus (KRY-TKN)</label>
          <div class="space-y-3 p-3 bg-(--bg-elevated) rounded-lg border border-(--border-soft)">
            <div>
              <label class="block text-xs font-medium text-(--text-muted) mb-1">ID Karyawan Teknisi</label>
              <input v-model="settingForm.technician_ids" type="text" placeholder="Misal: 31, 115, 174" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
            </div>
            <div class="flex gap-3">
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">Berlaku Sejak</label>
                <input v-model="settingForm.technician_start_date" type="date" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">Maks Lembur Libur (Menit)</label>
                <input v-model="settingForm.technician_max_minutes" type="number" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-(--text-main) mb-1">Jam Kerja Default (Menit)</label>
          <div class="space-y-3 p-3 bg-(--bg-elevated) rounded-lg border border-(--border-soft)">
            <div class="flex gap-3">
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">FIXED - Weekday</label>
                <input v-model="settingForm.hours_fixed_wd" type="number" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">FIXED - Sabtu</label>
                <input v-model="settingForm.hours_fixed_sat" type="number" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
              </div>
            </div>
            <div class="flex gap-3 mt-2">
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">FLEX-SHIFT - Weekday</label>
                <input v-model="settingForm.hours_flex_wd" type="number" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">FLEX-SHIFT - Sabtu</label>
                <input v-model="settingForm.hours_flex_sat" type="number" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
              </div>
            </div>
            <div class="flex gap-3 mt-2">
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">FLEX-SHIFT - PL (8 Jam)</label>
                <input v-model="settingForm.hours_flex_pl" type="number" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-(--text-muted) mb-1">FLEX-SHIFT - PL2 (9 Jam)</label>
                <input v-model="settingForm.hours_flex_pl2" type="number" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-(--text-main) mb-1">Kode Shift Tanpa Telat</label>
          <p class="text-xs text-(--text-muted) mb-2">Kode `external_code` shift yang `late_minutes` diabaikan (selalu 0). Pisahkan koma.</p>
          <input v-model="settingForm.zero_late_codes" type="text" placeholder="Misal: S, P" class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
        </div>

        <div>
          <label class="block text-sm font-semibold text-(--text-main) mb-1">Pembulatan Lembur</label>
          <p class="text-xs text-(--text-muted) mb-2">Interval pembulatan dan toleransi. Default: interval 30 menit, toleransi 5 menit. Contoh: interval=30, toleransi=10 → 0-19→0, 20-49→30, 50-79→60.</p>
          <div class="flex gap-3">
            <div class="flex-1">
              <label class="block text-xs font-medium text-(--text-muted) mb-1">Interval (menit)</label>
              <input v-model.number="settingForm.rounding_interval" type="number" min="1" max="60" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
            </div>
            <div class="flex-1">
              <label class="block text-xs font-medium text-(--text-muted) mb-1">Toleransi (menit)</label>
              <input v-model.number="settingForm.rounding_threshold" type="number" min="0" max="30" class="w-full px-3 py-2 border border-(--border-soft) rounded-md bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
          <BaseButton variant="ghost" @click="showSettings = false">Batal</BaseButton>
          <BaseButton variant="primary" :loading="savingConfig" @click="saveConfig">Simpan Pengaturan</BaseButton>
        </div>
      </div>
      <div v-else class="py-8 text-center text-sm text-(--text-muted)">
        Memuat pengaturan...
      </div>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/Components/BaseButton.vue'
import BaseModal from '@/Components/BaseModal.vue'
import { useApi } from '../../../../composables/useApi'
import { useAuth } from '../../../../composables/useAuth'
import { useNotificationStore } from '../../../../Stores/notification'

const { get, post, put } = useApi()
const auth = useAuth()
const route = useRoute()
const router = useRouter()
const notification = useNotificationStore()

// ── State ──
const isCalculating = ref(false)
const isLoading = ref(true)
const isExporting = ref(false)
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

// Setting State
const showSettings = ref(false)
const savingConfig = ref(false)
const overtimeConfig = ref(null)
const settingForm = ref({
  special_ids: '',
  special_formula: 'rumus_1',
  formula_fixed: 'rumus_1',
  formula_flex_s: 'rumus_1',
  formula_flex_p: 'rumus_1',
  technician_ids: '',
  technician_start_date: '',
  technician_max_minutes: 1200,
  zero_late_codes: '',
  hours_fixed_wd: 540,
  hours_fixed_sat: 360,
  hours_flex_wd: 480,
  hours_flex_sat: 360,
  hours_flex_pl: 480,
  hours_flex_pl2: 540,
  rounding_threshold: 10,
  rounding_interval: 30
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

async function handleExport() {
  isExporting.value = true
  try {
    const token = localStorage.getItem('token')
    const params = new URLSearchParams({ 
      start_date: startDate.value,
      end_date: endDate.value
    })
    if (searchQuery.value) params.set('search', searchQuery.value)

    const response = await fetch(`/api/v1/attendance/prepare/overtime-summary/export?${params}`, {
      headers: { 'Authorization': `Bearer ${token}` },
    })

    if (!response.ok) throw new Error('Gagal export data')

    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Rekap_Hitung_Lembur_${startDate.value}_${endDate.value}.xlsx`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (e) {
    alert('Gagal export Excel: ' + e.message)
  } finally {
    isExporting.value = false
  }
}

// ── Settings Config ──
async function fetchConfig() {
  try {
    const res = await get('/api/v1/payroll/configs/attendance_overtime_setting')
    const conf = res.config || {}
    overtimeConfig.value = conf
    
    // Parse to form string
    settingForm.value.special_ids = (conf.special_employees?.ids || []).join(', ')
    settingForm.value.special_formula = conf.special_employees?.formula || 'rumus_1'
    settingForm.value.formula_fixed = conf.formulas?.FIXED || 'rumus_1'
    settingForm.value.formula_flex_s = conf.formulas?.FLEX_S || 'rumus_1'
    settingForm.value.formula_flex_p = conf.formulas?.FLEX_P || 'rumus_1'
    settingForm.value.technician_ids = (conf.technician_rule?.employee_ids || []).join(', ')
    settingForm.value.technician_start_date = conf.technician_rule?.start_date || '2026-06-01'
    settingForm.value.technician_max_minutes = conf.technician_rule?.max_holiday_minutes || 1200
    settingForm.value.zero_late_codes = (conf.zero_late_shift_codes || []).join(', ')

    settingForm.value.hours_fixed_wd = conf.work_hours?.FIXED?.weekday ?? 540
    settingForm.value.hours_fixed_sat = conf.work_hours?.FIXED?.saturday ?? 360
    settingForm.value.hours_flex_wd = conf.work_hours?.['FLEX-SHIFT']?.weekday ?? 480
    settingForm.value.hours_flex_sat = conf.work_hours?.['FLEX-SHIFT']?.saturday ?? 360
    settingForm.value.hours_flex_pl = conf.work_hours?.PL?.weekday ?? 480
    settingForm.value.hours_flex_pl2 = conf.work_hours?.PL2?.weekday ?? 540
    
    settingForm.value.rounding_threshold = conf.rounding_threshold ?? 10
    settingForm.value.rounding_interval = conf.rounding_interval ?? 30
  } catch (error) {
    console.error('Error fetching config', error)
  }
}

function openSettings() {
  showSettings.value = true
  fetchConfig()
}

async function saveConfig() {
  savingConfig.value = true
  try {
    const parseIds = (str) => str.split(',').map(s => s.trim()).filter(s => s).map(s => isNaN(s) ? s : Number(s))
    const parseStrList = (str) => str.split(',').map(s => s.trim()).filter(s => s)

    const payload = {
      rounding_threshold: Number(settingForm.value.rounding_threshold),
      rounding_interval: Number(settingForm.value.rounding_interval),
      formulas: {
        'FIXED': settingForm.value.formula_fixed,
        'FLEX_S': settingForm.value.formula_flex_s,
        'FLEX_P': settingForm.value.formula_flex_p
      },
      special_employees: {
        ids: parseIds(settingForm.value.special_ids),
        formula: settingForm.value.special_formula
      },
      technician_rule: {
        employee_ids: parseIds(settingForm.value.technician_ids),
        start_date: settingForm.value.technician_start_date,
        max_holiday_minutes: Number(settingForm.value.technician_max_minutes)
      },
      zero_late_shift_codes: parseStrList(settingForm.value.zero_late_codes),
      work_hours: {
        'FIXED': {
          weekday: Number(settingForm.value.hours_fixed_wd),
          saturday: Number(settingForm.value.hours_fixed_sat)
        },
        'FLEX-SHIFT': {
          weekday: Number(settingForm.value.hours_flex_wd),
          saturday: Number(settingForm.value.hours_flex_sat)
        },
        'SHIFT': {
          weekday: 480,
          saturday: 360
        },
        'PL': {
          weekday: Number(settingForm.value.hours_flex_pl),
          saturday: 360
        },
        'PL2': {
          weekday: Number(settingForm.value.hours_flex_pl2),
          saturday: 360
        }
      }
    }

    await put('/api/v1/payroll/configs/attendance_overtime_setting', { config: payload })
    notification.success('Pengaturan berhasil disimpan.')
    showSettings.value = false
    
    // Trigger recalculation notice
    setTimeout(() => {
      calculateResult.value = {
        success: true,
        message: 'Pengaturan berhasil diperbarui. Silakan lakukan Calculate Overtime ulang untuk menerapkan efek perubahannya.'
      }
    }, 500)
    
  } catch (error) {
    console.error('Error saving config', error)
    notification.error('Gagal menyimpan pengaturan.')
  } finally {
    savingConfig.value = false
  }
}

// ── Init ──
onMounted(() => {
  fetchData()
})
</script>
