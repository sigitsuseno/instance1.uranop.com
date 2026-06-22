<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="sm:flex sm:justify-between sm:items-end border-b border-(--border-soft) pb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Roster Shift Kerja</h1>
        <p class="text-xs text-(--text-muted) mt-1 flex items-center gap-2">
          <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          Periode Roster: <span class="font-bold text-(--text-main)">25 {{ monthLabels[prevMonthIndex] }} {{ prevMonthYear }}</span> s/d <span class="font-bold text-(--text-main)">24 {{ monthLabels[selectedMonth - 1] }} {{ selectedYear }}</span>
        </p>
      </div>

      <!-- Header Actions & Filters -->
      <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto mt-4 sm:mt-0">
        <!-- Month Select -->
        <div>
          <input
            type="month"
            v-model="monthFilter"
            @change="handleMonthChange"
            class="h-10 px-3 rounded-md bg-(--bg-card) border border-(--border-strong) text-sm text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
          />
        </div>

        <!-- Department Filter -->
        <div>
          <select
            v-model="departmentFilter"
            class="h-10 px-3 rounded-md bg-(--bg-card) border border-(--border-strong) text-sm text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
          >
            <option :value="null">Semua Departemen</option>
            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Action Bar -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-3 flex gap-3 items-center flex-wrap justify-between">
      <div class="flex items-center gap-2 border-r border-(--border-soft) pr-3">
        <button
          @click="showImportModal = true"
          class="h-10 px-4 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 font-semibold rounded-md flex items-center text-xs transition-colors"
        >
          Import Excel
        </button>

        <router-link
          :to="{ name: 'supervisor.schedule.roster.generate' }"
          class="h-10 px-4 bg-(--primary) hover:bg-(--primary-hover) text-white font-semibold rounded-md flex items-center text-xs transition-colors"
        >
          Generate Roster
        </router-link>
      </div>

      <!-- Search Input -->
      <div class="flex-1 max-w-xs relative">
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Cari nama atau NIK..."
          class="w-full h-10 pl-3 pr-4 rounded-md bg-(--bg-card) border border-(--border-strong) text-xs text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
        />
      </div>

      <!-- Color Legend Reference -->
      <div class="flex items-center gap-3 overflow-x-auto py-1 no-scrollbar text-xs">
        <div class="flex items-center gap-1 bg-(--bg-elevated) px-2 py-1 rounded">
          <span class="w-4 h-4 rounded bg-blue-500 text-white flex items-center justify-center font-bold text-[8px]">PG</span>
          <span class="text-(--text-muted)">Pagi</span>
        </div>
        <div class="flex items-center gap-1 bg-(--bg-elevated) px-2 py-1 rounded">
          <span class="w-4 h-4 rounded bg-yellow-500 text-white flex items-center justify-center font-bold text-[8px]">SG</span>
          <span class="text-(--text-muted)">Siang</span>
        </div>
        <div class="flex items-center gap-1 bg-(--bg-elevated) px-2 py-1 rounded">
          <span class="w-4 h-4 rounded bg-purple-500 text-white flex items-center justify-center font-bold text-[8px]">ML</span>
          <span class="text-(--text-muted)">Malam</span>
        </div>
        <div class="flex items-center gap-1 bg-(--bg-elevated) px-2 py-1 rounded">
          <span class="w-4 h-4 rounded bg-rose-500 text-white flex items-center justify-center font-bold text-[8px]">L</span>
          <span class="text-(--text-muted)">Libur</span>
        </div>
      </div>
    </div>

    <!-- Main Schedule Table Card -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden relative">
      <div class="overflow-x-auto scrollbar-thin max-h-[500px]">
        <table class="w-full border-collapse text-left text-xs table-fixed" :style="{ minWidth: (250 + (daysInMonthRange.length * 40)) + 'px' }">
          <thead class="bg-(--bg-elevated) border-b border-(--border-soft) sticky top-0 z-20">
            <tr>
              <th class="w-48 px-4 py-3 bg-(--bg-elevated) sticky left-0 z-30 shadow-[1px_0_0_0_rgba(0,0,0,0.05)] dark:shadow-[1px_0_0_0_rgba(255,255,255,0.05)]">
                Karyawan & Organisasi
              </th>
              
              <!-- Daily headers -->
              <th
                v-for="(day, idx) in daysInMonthRange"
                :key="idx"
                class="w-[40px] px-0 py-2 text-center border-r border-(--border-soft)/50"
              >
                <div class="flex flex-col items-center">
                  <span class="text-[9px] text-(--text-muted)" :class="{ 'text-red-500': day.dow === 0 }">
                    {{ dayNameAbbr[day.dow] }}
                  </span>
                  <span class="font-bold text-xs" :class="{ 'text-red-500': day.dow === 0 }">
                    {{ day.day }}
                  </span>
                </div>
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-(--border-soft)">
            <tr v-if="filteredRoster.length === 0">
              <td :colspan="daysInMonthRange.length + 1" class="py-16 text-center text-(--text-muted)">
                Belum ada data roster untuk periode ini
              </td>
            </tr>

            <tr v-for="emp in filteredRoster" :key="emp.id" class="hover:bg-(--bg-elevated)/20 transition-colors group">
              <!-- Employee Sticky Column -->
              <td class="px-4 py-3 bg-(--bg-card) group-hover:bg-(--bg-elevated)/10 sticky left-0 z-10 shadow-[1px_0_0_0_rgba(0,0,0,0.05)] dark:shadow-[1px_0_0_0_rgba(255,255,255,0.05)] truncate">
                <div class="font-bold text-(--text-main)">{{ emp.name }}</div>
                <div class="text-[10px] text-(--text-muted) flex items-center gap-1 mt-0.5">
                  <span class="font-mono bg-(--bg-elevated) px-1 rounded">{{ emp.nik }}</span>
                  <span class="truncate">{{ emp.department }}</span>
                </div>
              </td>

              <!-- Daily Shift Cells -->
              <td
                v-for="(day, idx) in daysInMonthRange"
                :key="idx"
                @click="openOverrideModal(emp, day, idx)"
                class="px-0 py-1 text-center border-r border-(--border-soft)/30 cursor-pointer hover:bg-(--primary-glow)/10 transition-colors"
              >
                <div class="flex justify-center items-center h-8">
                  <span
                    v-if="emp.schedule[idx]"
                    class="w-[28px] h-[28px] rounded flex items-center justify-center font-bold text-[9px] shadow-sm"
                    :style="getShiftStyle(emp.schedule[idx])"
                  >
                    {{ emp.schedule[idx].external_code }}
                  </span>
                  <span v-else class="text-gray-300">-</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer Stats -->
      <div class="px-4 py-3 bg-(--bg-elevated)/20 border-t border-(--border-soft) flex items-center justify-between text-xs text-(--text-muted)">
        <div>
          Menampilkan <span class="font-bold text-(--text-main)">{{ filteredRoster.length }}</span> karyawan roster.
        </div>
      </div>
    </div>

    <!-- ====== IMPORT MODAL ====== -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showImportModal"
        class="fixed inset-0 z-40 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center pointer-events-none p-4"
        @click.self="closeImportModal"
      >
        <div class="bg-(--bg-card) rounded-xl shadow-2xl w-full max-w-lg pointer-events-auto max-h-[90vh] overflow-y-auto">
          <!-- Header -->
          <div class="flex justify-between items-center px-6 py-4 border-b border-(--border-soft)">
            <h3 class="text-lg font-semibold text-(--text-main)">Import Roster Excel</h3>
            <button
              class="text-(--text-muted) hover:text-(--text-main) transition-colors"
              @click="closeImportModal"
              :disabled="importing"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 space-y-4">
            <div>
              <h4 class="text-sm font-semibold text-(--text-main) mb-1">Format File</h4>
              <p class="text-xs text-(--text-muted)">
                Upload file Excel (.xlsx) format matriks roster. <strong>Baris 2</strong> header: NIP, WP, 1-31.
              </p>
            </div>

            <!-- Periode -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-(--text-main) mb-1">Bulan</label>
                <select
                  v-model.number="importMonth"
                  class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main)"
                >
                  <option v-for="(m, i) in monthLabels" :key="i" :value="i + 1">{{ m }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-(--text-main) mb-1">Tahun</label>
                <input
                  v-model.number="importYear"
                  type="number"
                  class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main)"
                  min="2020" max="2050"
                />
              </div>
            </div>

            <!-- Drop Zone -->
            <div
              class="border-2 border-dashed border-(--border-soft) rounded-md p-6 text-center cursor-pointer hover:border-(--primary)/50 hover:bg-(--primary)/5 transition-colors"
              :class="{ 'border-(--primary) bg-(--primary)/5': importDragOver }"
              @click="triggerImportInput"
              @dragover.prevent="importDragOver = true"
              @dragleave.prevent="importDragOver = false"
              @drop.prevent="handleImportDrop"
            >
              <input ref="importFileInput" type="file" accept=".xlsx,.csv" class="hidden" @change="handleImportFile" />
              <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto mb-2 text-(--text-muted)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p class="text-sm text-(--text-main)">
                Seret file ke sini, atau <span class="text-(--primary) font-medium">klik untuk pilih</span>
              </p>
              <p class="text-xs text-(--text-muted) mt-1">Format: .xlsx, .csv</p>
            </div>

            <!-- Selected File -->
            <div v-if="importFile" class="flex items-center gap-3 p-3 rounded-md bg-(--bg-elevated)">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-(--primary) shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0 2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 8z" />
                <polyline points="14 2 14 8 20 8" />
              </svg>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-(--text-main) truncate">{{ importFile.name }}</p>
                <p class="text-xs text-(--text-muted)">{{ formatBytes(importFile.size) }}</p>
              </div>
              <button class="text-(--text-muted) hover:text-(--danger) transition-colors" @click="clearImportFile" :disabled="importing">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18" />
                  <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
              </button>
            </div>

            <!-- Import Result -->
            <div
              v-if="importResult"
              class="p-3 rounded-md text-sm"
              :class="importResult.success ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'"
            >
              <p class="font-medium">{{ importResult.message }}</p>
              <p v-if="importResult.inserted !== undefined" class="mt-1 text-xs opacity-80">
                {{ importResult.inserted }} baru, {{ importResult.updated }} diperbarui
              </p>
              <div v-if="importResult.errors && importResult.errors.length" class="mt-2 max-h-32 overflow-y-auto">
                <p class="text-xs font-medium mb-1">Peringatan ({{ importResult.errors.length }}):</p>
                <p v-for="(err, ei) in importResult.errors.slice(0, 5)" :key="ei" class="text-xs opacity-70">— {{ err }}</p>
                <p v-if="importResult.errors.length > 5" class="text-xs opacity-50">...dan {{ importResult.errors.length - 5 }} lainnya</p>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-(--border-soft) flex justify-end gap-3">
            <button
              class="px-4 py-2 text-sm font-medium rounded-md border border-(--border-soft) text-(--text-main) hover:bg-(--bg-elevated) transition-colors"
              @click="closeImportModal"
              :disabled="importing"
            >Batal</button>
            <button
              class="px-4 py-2 text-sm font-medium rounded-md bg-(--primary) text-white hover:bg-(--primary-hover) transition-colors flex items-center gap-2"
              :disabled="!importFile || !importMonth || !importYear || importing"
              @click="handleImport"
            >
              <span v-if="importing" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              {{ importing ? 'Mengimport...' : 'Upload & Import' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Overlay Loading (full screen) -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="importing"
        class="fixed inset-0 z-50 bg-gray-900/70 backdrop-blur-sm flex items-center justify-center"
      >
        <div class="bg-(--bg-card) rounded-xl p-8 shadow-2xl flex flex-col items-center gap-4">
          <span class="inline-block w-12 h-12 border-4 border-(--primary) border-t-transparent rounded-full animate-spin"></span>
          <p class="text-sm font-semibold text-(--text-main)">Sedang mengimport data...</p>
          <p class="text-xs text-(--text-muted)">Memproses file roster, mohon tunggu sebentar</p>
        </div>
      </div>
    </Transition>

    <!-- Override Shift Cell Modal -->
    <BaseModal :show="showModal" title="Update Jadwal Kerja" size="sm" @close="showModal = false">
      <div class="space-y-4" v-if="editingCell">
        <div class="bg-(--bg-elevated)/50 p-3 rounded-md border border-(--border-soft)">
          <div class="font-bold text-sm text-(--text-main)">{{ editingCell.employeeName }}</div>
          <div class="text-xs text-(--text-muted) mt-0.5">NIK: {{ editingCell.employeeNik }}</div>
          <div class="text-xs font-semibold text-(--primary) mt-1">
            {{ formatDateLabel(editingCell.date) }}
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-(--text-main) mb-2">Pilih Shift Pengganti</label>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="s in store.shifts"
              :key="s.id"
              @click="applyOverride(s)"
              class="flex flex-col items-center justify-center p-3 rounded-md border border-(--border-soft) hover:border-(--primary) hover:bg-(--primary-glow)/5 transition-all text-center"
            >
              <span class="w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold text-white shadow-sm" :style="{ backgroundColor: s.color }">
                {{ s.code }}
              </span>
              <span class="text-[10px] font-bold text-(--text-main) mt-1.5">{{ s.name }}</span>
            </button>
            
            <button
              @click="applyOverride({ code: 'L', name: 'Libur', is_off: true })"
              class="flex flex-col items-center justify-center p-3 rounded-md border-2 border-rose-100 bg-rose-50/40 hover:bg-rose-50 hover:border-rose-300 transition-all col-span-2 text-center"
            >
              <span class="w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold text-white bg-rose-500 shadow-sm">
                L
              </span>
              <span class="text-[10px] font-bold text-rose-700 mt-1.5">LIBUR (OFF DAY)</span>
            </button>
          </div>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showModal = false" class="w-full">Batal</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useScheduleStore } from '../../../../Stores/schedule'
import { useApi } from '../../../../composables/useApi'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'

const store = useScheduleStore()

const monthFilter = ref('2026-06')
const selectedYear = ref(2026)
const selectedMonth = ref(6)

onMounted(async () => {
  store.fetchShifts()
  store.fetchRosterForPeriod(selectedYear.value, selectedMonth.value)
  
  try {
    const { get } = useApi()
    const res = await get('/api/organization/departments/options')
    departments.value = res.data || []
  } catch (error) {
    console.error('Failed to fetch departments', error)
  }
})

watch([selectedYear, selectedMonth], ([y, m]) => {
  store.fetchRosterForPeriod(y, m)
})

const searchQuery = ref('')
const departmentFilter = ref(null)

const showModal = ref(false)
const editingCell = ref(null)

// ─── Import State ────────────────────────────
const showImportModal = ref(false)
const importFileInput = ref(null)
const importFile = ref(null)
const importDragOver = ref(false)
const importMonth = ref(new Date().getMonth() + 1)
const importYear = ref(new Date().getFullYear())
const importing = ref(false)
const importResult = ref(null)

const monthLabels = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]
const dayNameAbbr = ['Mg', 'Sn', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

const departments = ref([])

const prevMonthIndex = computed(() => {
  return selectedMonth.value === 1 ? 11 : selectedMonth.value - 2
})

const prevMonthYear = computed(() => {
  return selectedMonth.value === 1 ? selectedYear.value - 1 : selectedYear.value
})

const daysInMonthRange = computed(() => {
  return store.getDaysInMonthRange(selectedYear.value, selectedMonth.value)
})

const rosterList = computed(() => {
  return store.getRosterForPeriod(selectedYear.value, selectedMonth.value)
})

const filteredRoster = computed(() => {
  return rosterList.value.filter(emp => {
    const matchesSearch = String(emp.name || '').toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                          String(emp.nik || '').toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesDept = !departmentFilter.value || emp.department_id === departmentFilter.value
    return matchesSearch && matchesDept
  })
})

function handleMonthChange() {
  if (!monthFilter.value) return
  const [y, m] = monthFilter.value.split('-')
  selectedYear.value = parseInt(y)
  selectedMonth.value = parseInt(m)
}

function getShiftStyle(scheduleDay) {
  if (scheduleDay.is_off) {
    return { backgroundColor: '#ef4444', color: '#ffffff' } // red
  }
  // Lookup shift in store to get color
  const shift = store.shifts.find(s => s.id === scheduleDay.shift_id)
  return {
    backgroundColor: shift ? shift.color : '#64748b',
    color: '#ffffff'
  }
}

function openOverrideModal(employee, dayInfo, dayIndex) {
  editingCell.value = {
    employeeId: employee.id,
    employeeName: employee.name,
    employeeNik: employee.nik,
    date: dayInfo.date,
    dayIndex,
    currentShift: employee.schedule[dayIndex]
  }
  showModal.value = true
}

async function applyOverride(newShift) {
  if (!editingCell.value) return
  
  const d = editingCell.value.date
  const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

  const payload = {
    employee_id: editingCell.value.employeeId,
    date: dateStr,
    shift_id: newShift.id || null,
    is_off: !!newShift.is_off
  }

  try {
    await store.overrideRosterCell(payload)
    // Refresh data dari server — lebih reliable daripada local patch
    await store.fetchRosterForPeriod(selectedYear.value, selectedMonth.value)
  } catch (error) {
    alert('Gagal mengupdate jadwal: ' + (error.message || 'Unknown error'))
  }
  
  showModal.value = false
  editingCell.value = null
}

function formatDateLabel(date) {
  return date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

// ─── Import Functions ────────────────────────
function closeImportModal() {
  if (importing.value) return
  showImportModal.value = false
  importFile.value = null
  importResult.value = null
}

function triggerImportInput() {
  importFileInput.value?.click()
}

function handleImportDrop(e) {
  importDragOver.value = false
  const file = e.dataTransfer.files[0]
  if (file) { importFile.value = file; importResult.value = null }
}

function handleImportFile(e) {
  const file = e.target.files[0]
  if (file) { importFile.value = file; importResult.value = null }
}

function clearImportFile() {
  importFile.value = null
  importResult.value = null
  if (importFileInput.value) importFileInput.value.value = ''
}

async function handleImport() {
  if (!importFile.value) return

  importing.value = true
  importResult.value = null

  try {
    const formData = new FormData()
    formData.append('file', importFile.value)
    formData.append('month', importMonth.value)
    formData.append('year', importYear.value)

    const { post } = useApi()
    const response = await post('/api/schedule/roster/import', formData)

    importResult.value = {
      success: true,
      message: response.message || 'Import berhasil!',
      inserted: response.inserted,
      updated: response.updated,
      errors: response.errors || [],
    }

    // Refresh data
    await store.fetchRosterForPeriod(selectedYear.value, selectedMonth.value)
  } catch (err) {
    importResult.value = {
      success: false,
      message: err.message || 'Gagal mengimport file.',
    }
  } finally {
    importing.value = false
  }
}

function formatBytes(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
</script>
