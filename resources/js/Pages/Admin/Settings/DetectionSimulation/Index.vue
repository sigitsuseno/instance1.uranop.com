<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseButton from '../../../../Components/BaseButton.vue'
import MultiSearchableSelect from '../../../../Components/MultiSearchableSelect.vue'

const { get, put } = useApi()
const notification = useNotificationStore()

// ==== Period & data ====
const periods = ref([])
const selectedPeriodId = ref('')
const loadingPeriods = ref(false)
const loading = ref(false)
const hasFetched = ref(false)
const rows = ref([])
const windows = ref(null)
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(50)
const meta = ref({ total: 0, detected: 0, not_detected: 0, no_rule: 0 })

// ==== Employee filter ====
const employeeOptions = ref([])
const selectedEmployeeIds = ref([])
const loadingEmployees = ref(false)
const hasEmployeeFilter = computed(() => selectedEmployeeIds.value.length > 0)

// ==== Settings (offcanvas) ====
const showSettings = ref(false)
const loadingSettings = ref(false)
const savingSettings = ref(false)
const settings = ref({
  global_start_time: '04:00',
  global_end_time: '23:59',
  shift_pagi_start_time: '06:00',
  shift_pagi_end_time: '14:00',
  shift_siang_start_time: '14:00',
  shift_siang_end_time: '22:00',
  shift_malam_start_time: '22:00',
  shift_malam_end_time: '06:00',
})

const settingGroups = [
  {
    id: 'global',
    title: 'Global Time Window',
    description: 'Rentang menyeluruh untuk menampilkan scan log karyawan.',
    icon: 'bx bx-globe',
    startKey: 'global_start_time',
    endKey: 'global_end_time',
  },
  {
    id: 'pagi',
    title: 'Shift Pagi',
    description: 'Kode shift P',
    icon: 'bx bx-sun',
    startKey: 'shift_pagi_start_time',
    endKey: 'shift_pagi_end_time',
  },
  {
    id: 'siang',
    title: 'Shift Siang',
    description: 'Kode shift S',
    icon: 'bx bx-cloud-sun',
    startKey: 'shift_siang_start_time',
    endKey: 'shift_siang_end_time',
  },
  {
    id: 'malam',
    title: 'Shift Malam',
    description: 'Kode shift ML',
    icon: 'bx bx-moon',
    startKey: 'shift_malam_start_time',
    endKey: 'shift_malam_end_time',
  },
]

const windowChips = computed(() => {
  if (!windows.value) return []
  return ['global', 'pagi', 'siang', 'malam'].map((key) => ({
    key,
    ...windows.value[key],
  }))
})

// ==== API ====
const fetchPeriods = async () => {
  loadingPeriods.value = true
  try {
    const res = await get('/api/v1/settings/employee-data/pay-periods')
    periods.value = res.data || []
    if (periods.value.length > 0) {
      selectedPeriodId.value = periods.value[0].id
    }
  } catch (e) {
    notification.error('Gagal memuat periode.')
  } finally {
    loadingPeriods.value = false
  }
}

const fetchEmployees = async () => {
  if (!selectedPeriodId.value) {
    employeeOptions.value = []
    return
  }

  loadingEmployees.value = true
  try {
    const params = new URLSearchParams({ period_id: selectedPeriodId.value })
    const res = await get(`/api/v1/settings/detection-simulation/employees?${params}`)
    employeeOptions.value = (res.data || []).map((e) => ({
      value: e.id,
      label: e.name,
      sublabel: e.nip || null,
    }))
  } catch (e) {
    employeeOptions.value = []
    notification.error('Gagal memuat daftar karyawan.')
  } finally {
    loadingEmployees.value = false
  }
}

const fetchSettings = async () => {
  loadingSettings.value = true
  try {
    const res = await get('/api/v1/settings/detection-simulation/settings')
    if (res.data) {
      settings.value = { ...settings.value, ...res.data }
    }
  } catch (e) {
    notification.error('Gagal memuat pengaturan simulasi.')
  } finally {
    loadingSettings.value = false
  }
}

const saveSettings = async () => {
  const missing = settingGroups.some(g => !settings.value[g.startKey] || !settings.value[g.endKey])
  if (missing) {
    notification.warning('Semua jam start dan end wajib diisi.')
    return
  }

  savingSettings.value = true
  try {
    const res = await put('/api/v1/settings/detection-simulation/settings', settings.value)
    if (res.data) {
      settings.value = { ...settings.value, ...res.data }
    }
    notification.success('Pengaturan simulasi deteksi disimpan.')
    showSettings.value = false
    if (hasFetched.value) {
      fetchData(1)
    }
  } catch (e) {
    notification.error(e.message || 'Gagal menyimpan pengaturan.')
  } finally {
    savingSettings.value = false
  }
}

const fetchData = async (page = 1) => {
  if (!selectedPeriodId.value) {
    notification.warning('Pilih periode terlebih dahulu.')
    return
  }

  loading.value = true
  currentPage.value = page
  try {
    const params = new URLSearchParams({
      period_id: selectedPeriodId.value,
      page,
      per_page: perPage.value,
    })

    // Hanya tampilkan baris milik karyawan yang dicentang di combobox.
    selectedEmployeeIds.value.forEach((id) => params.append('employee_ids[]', id))

    const res = await get(`/api/v1/settings/detection-simulation?${params}`)
    rows.value = res.data?.rows || []
    windows.value = res.data?.windows || null
    if (res.data?.settings) {
      settings.value = { ...settings.value, ...res.data.settings }
    }

    const m = res.meta || {}
    currentPage.value = m.current_page || 1
    lastPage.value = m.last_page || 1
    meta.value = {
      total: m.total || 0,
      detected: m.detected || 0,
      not_detected: m.not_detected || 0,
      no_rule: m.no_rule || 0,
    }
    hasFetched.value = true
  } catch (e) {
    notification.error(e.message || 'Gagal mengambil data simulasi.')
  } finally {
    loading.value = false
  }
}

// Timer untuk auto-refresh saat centang karyawan (debounce agar mencentang
// beberapa nama sekaligus tidak memicu banyak request).
let refetchTimer = null

watch(selectedPeriodId, () => {
  clearTimeout(refetchTimer)
  hasFetched.value = false
  rows.value = []
  windows.value = null
  currentPage.value = 1
  lastPage.value = 1
  meta.value = { total: 0, detected: 0, not_detected: 0, no_rule: 0 }
  selectedEmployeeIds.value = []
  fetchEmployees()
})

watch(selectedEmployeeIds, () => {
  if (!hasFetched.value) return
  clearTimeout(refetchTimer)
  refetchTimer = setTimeout(() => fetchData(1), 350)
})

onBeforeUnmount(() => clearTimeout(refetchTimer))

// ==== Helpers ====
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(`${dateStr}T00:00:00`).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

const crossesMidnight = (row) => {
  if (!row.window_start || !row.window_end) return false
  return row.window_end < row.window_start
}

const groupBadgeClass = (group) => {
  if (group === 'pagi') return 'bg-(--primary)/10 text-(--primary)'
  if (group === 'siang') return 'bg-(--warning)/10 text-(--warning)'
  if (group === 'malam') return 'bg-(--bg-elevated) text-(--text-main)'
  return 'bg-(--bg-elevated) text-(--text-muted)'
}

const statusOf = (row) => {
  if (row.detected === true) {
    return { label: 'Terdeteksi', icon: 'bx bx-check-circle', class: 'bg-(--success)/10 text-(--success)' }
  }
  if (row.detected === false) {
    return { label: 'Tidak Terdeteksi', icon: 'bx bx-x-circle', class: 'bg-(--danger)/10 text-(--danger)' }
  }
  return { label: 'Tanpa Aturan', icon: 'bx bx-minus-circle', class: 'bg-(--bg-elevated) text-(--text-muted)' }
}

const pageNumbers = computed(() => {
  const pages = []
  const maxVisible = 5
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
  const end = Math.min(lastPage.value, start + maxVisible - 1)
  if (end - start < maxVisible - 1) {
    start = Math.max(1, end - maxVisible + 1)
  }
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

const goToPage = (page) => {
  if (page < 1 || page > lastPage.value || page === currentPage.value) return
  fetchData(page)
}

onMounted(() => {
  fetchPeriods()
  fetchSettings()
})
</script>

<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <div class="mb-6 shrink-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Simulasi Deteksi</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Uji pencocokan scan log terhadap window shift sebelum dipakai di proses kehadiran
        </p>
      </div>
      <BaseButton variant="secondary" @click="showSettings = true">
        <template #icon-left>
          <i class="bx bx-slider-alt text-lg"></i>
        </template>
        Pengaturan Halaman
      </BaseButton>
    </div>

    <!-- Window summary -->
    <div v-if="windowChips.length" class="mb-4 flex flex-wrap items-center gap-2">
      <span
        v-for="chip in windowChips"
        :key="chip.key"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md border text-xs"
        :class="chip.key === 'global'
          ? 'border-(--border-strong) bg-(--bg-card) text-(--text-main)'
          : 'border-(--border-soft) bg-(--bg-card) text-(--text-muted)'"
      >
        <i :class="['bx', chip.key === 'global' ? 'bx-globe' : 'bx-time-five', 'text-sm']"></i>
        <span class="font-medium">{{ chip.label }}</span>
        <span class="font-mono">{{ chip.start }} – {{ chip.end }}</span>
        <span
          v-if="chip.end < chip.start"
          class="px-1 rounded bg-(--bg-elevated) text-(--text-soft)"
          title="Melewati tengah malam"
        >H+1</span>
      </span>
    </div>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-end gap-3">
      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-medium text-(--text-muted)">Periode</label>
        <select
          v-model="selectedPeriodId"
          :disabled="loadingPeriods"
          class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) min-w-[220px]"
        >
          <option v-if="loadingPeriods" value="">Memuat periode...</option>
          <option v-else-if="periods.length === 0" value="">Tidak ada periode</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">
            {{ p.name || `${p.period_month}/${p.period_year}` }}
          </option>
        </select>
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-medium text-(--text-muted)">Karyawan</label>
        <MultiSearchableSelect
          v-model="selectedEmployeeIds"
          :options="employeeOptions"
          :loading="loadingEmployees"
          :disabled="!selectedPeriodId || loadingEmployees"
          placeholder="Semua karyawan"
          class="w-[240px]"
        />
      </div>

      <BaseButton :loading="loading" :disabled="!selectedPeriodId" @click="fetchData(1)">
        <template #icon-left>
          <i class="bx bx-refresh text-lg"></i>
        </template>
        Fetch Data
      </BaseButton>

      <div v-if="hasFetched" class="flex flex-wrap items-center gap-2 sm:ml-auto">
        <span class="px-2.5 py-1 rounded-md bg-(--bg-elevated) text-(--text-muted) text-xs">
          {{ meta.total.toLocaleString('id-ID') }} baris
        </span>
        <span class="px-2.5 py-1 rounded-md bg-(--success)/10 text-(--success) text-xs">
          Terdeteksi: {{ meta.detected.toLocaleString('id-ID') }}
        </span>
        <span class="px-2.5 py-1 rounded-md bg-(--danger)/10 text-(--danger) text-xs">
          Tidak: {{ meta.not_detected.toLocaleString('id-ID') }}
        </span>
        <span v-if="meta.no_rule > 0" class="px-2.5 py-1 rounded-md bg-(--bg-elevated) text-(--text-muted) text-xs">
          Tanpa aturan: {{ meta.no_rule.toLocaleString('id-ID') }}
        </span>
      </div>
    </div>

    <!-- Result -->
    <div class="flex-1 min-h-0 rounded-md border border-(--border-soft) bg-(--bg-card) overflow-hidden flex flex-col">
      <div v-if="loading" class="flex-1 p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-sm text-(--text-muted)">Mengambil scan log dan mencocokkan window shift...</p>
      </div>

      <div v-else-if="!hasFetched" class="flex-1 p-12 flex flex-col items-center justify-center text-center">
        <i class="bx bx-scan text-4xl text-(--text-soft) mb-3"></i>
        <p class="text-sm font-medium text-(--text-main)">Belum ada data</p>
        <p class="text-sm text-(--text-muted) mt-1 max-w-md">
          Pilih periode lalu klik <span class="font-medium">Fetch Data</span> untuk melihat hasil
          pencocokan scan log dengan window shift.
        </p>
      </div>

      <div v-else-if="rows.length === 0" class="flex-1 p-12 flex flex-col items-center justify-center text-center">
        <i class="bx text-4xl text-(--text-soft) mb-3" :class="hasEmployeeFilter ? 'bx-user-x' : 'bx-calendar-x'"></i>
        <p class="text-sm font-medium text-(--text-main)">
          {{ hasEmployeeFilter ? 'Tidak ada baris untuk karyawan terpilih' : 'Tidak ada roster' }}
        </p>
        <p class="text-sm text-(--text-muted) mt-1">
          <template v-if="hasEmployeeFilter">
            Karyawan yang dipilih tidak punya roster pada periode ini. Bersihkan pilihan karyawan
            untuk melihat semua.
          </template>
          <template v-else>
            Periode ini belum punya data jadwal karyawan.
          </template>
        </p>
      </div>

      <div v-else class="flex-1 min-h-0 overflow-auto">
        <table class="w-full text-sm">
          <thead class="sticky top-0 z-10">
            <tr class="border-b border-(--border-soft) bg-(--bg-elevated)">
              <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">Tanggal</th>
              <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">NIP / Nama</th>
              <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">Shift</th>
              <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">Scan Log</th>
              <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">Window Shift</th>
              <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">Scan Global</th>
              <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/50 transition-colors"
            >
              <td class="px-4 py-2.5 whitespace-nowrap text-(--text-main)">
                {{ formatDate(row.date) }}
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <div class="font-mono text-xs text-(--text-muted)">{{ row.nip }}</div>
                <div class="font-medium text-(--text-main)">{{ row.name }}</div>
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <span
                    v-if="row.external_code"
                    class="px-1.5 py-0.5 rounded text-xs font-semibold"
                    :class="groupBadgeClass(row.shift_group)"
                  >
                    {{ row.external_code }}
                  </span>
                  <span v-else class="text-xs text-(--text-soft)">-</span>
                  <span class="text-xs text-(--text-muted)">
                    {{ row.shift_group_label || (row.shift_name || 'Tanpa aturan') }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-2.5 text-(--text-muted) max-w-[220px]">
                <div class="truncate font-mono text-xs" :title="row.scan_log || 'Tidak ada scan'">
                  {{ row.scan_log || '-' }}
                </div>
              </td>
              <!-- Hasil deteksi: scan dicocokkan ke window shift karyawan -->
              <td class="px-4 py-2.5 max-w-[220px]">
                <template v-if="row.detected !== null">
                  <div
                    class="truncate font-mono text-xs"
                    :class="row.detected ? 'text-(--success)' : 'text-(--danger)'"
                    :title="row.matched_log || 'Tidak ada scan di window shift'"
                  >
                    {{ row.detected ? row.matched_log : 'Tidak terdeteksi' }}
                  </div>
                  <div class="text-[11px] text-(--text-soft) mt-0.5">
                    {{ row.rule_label }} ·
                    <span class="font-mono">{{ row.window_start }} – {{ row.window_end }}</span>
                    <span
                      v-if="crossesMidnight(row)"
                      class="ml-1 px-1 rounded bg-(--bg-elevated) font-mono"
                      title="Melewati tengah malam"
                    >H+1</span>
                  </div>
                </template>
                <span v-else class="text-xs text-(--text-soft)">Tanpa aturan</span>
              </td>
              <!-- Scan yang masuk Global Time Window -->
              <td class="px-4 py-2.5 text-(--text-muted) max-w-[220px]">
                <div
                  class="truncate font-mono text-xs"
                  :class="row.global_count > 0 ? 'text-(--text-main)' : 'text-(--text-soft)'"
                  :title="row.global_log || 'Tidak ada scan di global window'"
                >
                  {{ row.global_log || '-' }}
                </div>
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <span
                  class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
                  :class="statusOf(row).class"
                >
                  <i :class="['bx', statusOf(row).icon, 'text-sm']"></i>
                  {{ statusOf(row).label }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="hasFetched && lastPage > 1"
        class="shrink-0 flex items-center justify-between px-4 py-3 border-t border-(--border-soft) bg-(--bg-elevated)/30"
      >
        <span class="text-xs text-(--text-muted)">
          Halaman {{ currentPage }} dari {{ lastPage }} ({{ meta.total.toLocaleString('id-ID') }} baris)
        </span>
        <div class="flex items-center gap-1">
          <button
            @click="goToPage(1)"
            :disabled="currentPage === 1"
            class="h-8 px-2 rounded text-xs transition-colors"
            :class="currentPage === 1 ? 'text-(--text-soft) cursor-not-allowed' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
          >
            ««
          </button>
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="h-8 px-2 rounded text-xs transition-colors"
            :class="currentPage === 1 ? 'text-(--text-soft) cursor-not-allowed' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
          >
            «
          </button>
          <button
            v-for="p in pageNumbers"
            :key="p"
            @click="goToPage(p)"
            class="h-8 px-2.5 rounded text-xs font-medium transition-colors"
            :class="p === currentPage ? 'bg-(--primary) text-white' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
          >
            {{ p }}
          </button>
          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === lastPage"
            class="h-8 px-2 rounded text-xs transition-colors"
            :class="currentPage === lastPage ? 'text-(--text-soft) cursor-not-allowed' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
          >
            »
          </button>
          <button
            @click="goToPage(lastPage)"
            :disabled="currentPage === lastPage"
            class="h-8 px-2 rounded text-xs transition-colors"
            :class="currentPage === lastPage ? 'text-(--text-soft) cursor-not-allowed' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
          >
            »»
          </button>
        </div>
      </div>
    </div>

    <!-- Offcanvas: Pengaturan Halaman -->
    <Teleport to="body">
      <transition name="sim-fade">
        <div
          v-if="showSettings"
          class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-40"
          @click="showSettings = false"
        ></div>
      </transition>

      <transition name="sim-slide">
        <aside
          v-if="showSettings"
          class="fixed top-0 right-0 h-full w-full sm:w-[440px] z-50 flex flex-col bg-(--bg-card) border-l border-(--border-soft) shadow-xl"
        >
          <div class="shrink-0 flex items-start justify-between gap-4 px-6 py-4 border-b border-(--border-soft)">
            <div>
              <h2 class="text-base font-semibold text-(--text-main)">Pengaturan Halaman</h2>
              <p class="text-xs text-(--text-muted) mt-1">
                Window waktu untuk pencocokan scan log simulasi deteksi
              </p>
            </div>
            <button
              type="button"
              class="w-8 h-8 flex items-center justify-center rounded-md text-(--text-muted) hover:bg-(--bg-elevated) hover:text-(--text-main) transition-colors"
              @click="showSettings = false"
            >
              <i class="bx bx-x text-xl"></i>
            </button>
          </div>

          <div class="flex-1 min-h-0 overflow-y-auto px-6 py-4 space-y-4">
            <div v-if="loadingSettings" class="py-12 flex flex-col items-center justify-center">
              <div class="w-8 h-8 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-3"></div>
              <p class="text-sm text-(--text-muted)">Memuat pengaturan...</p>
            </div>

            <template v-else>
              <div
                v-for="group in settingGroups"
                :key="group.id"
                class="rounded-md border p-4"
                :class="group.id === 'global'
                  ? 'border-(--border-strong) bg-(--bg-elevated)/40'
                  : 'border-(--border-soft) bg-(--bg-card)'"
              >
                <div class="flex items-start gap-3 mb-3">
                  <i :class="['bx', group.icon, 'text-xl text-(--primary)']"></i>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-(--text-main)">{{ group.title }}</p>
                    <p class="text-xs text-(--text-muted) mt-0.5">{{ group.description }}</p>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-(--text-muted)">Start time</label>
                    <input
                      v-model="settings[group.startKey]"
                      type="time"
                      class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary)"
                    />
                  </div>
                  <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-(--text-muted)">End time</label>
                    <input
                      v-model="settings[group.endKey]"
                      type="time"
                      class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary)"
                    />
                  </div>
                </div>
              </div>

              <p class="flex items-start gap-2 px-1 text-xs text-(--text-muted)">
                <i class="bx bx-info-circle text-base shrink-0"></i>
                <span>
                  Shift dikenali dari <span class="font-mono">external_code</span> jadwal:
                  <span class="font-semibold">P</span> = pagi,
                  <span class="font-semibold">S</span> = siang,
                  <span class="font-semibold">ML</span> = malam.
                  Pagi memakai scan pada tanggal roster; siang &amp; malam memakai data mulai 12 jam
                  setelah tanggal roster. Pada hari Minggu/libur, pola kerja non-SHIFT memakai
                  rentang tetap <span class="font-mono">06:00–18:00</span>.
                  Bila end time lebih kecil dari start time, window dianggap melewati tengah malam (H+1).
                </span>
              </p>
            </template>
          </div>

          <div class="shrink-0 flex items-center justify-end gap-2 px-6 py-4 border-t border-(--border-soft)">
            <BaseButton variant="ghost" :disabled="savingSettings" @click="showSettings = false">
              Batal
            </BaseButton>
            <BaseButton :loading="savingSettings" :disabled="loadingSettings" @click="saveSettings">
              Simpan Pengaturan
            </BaseButton>
          </div>
        </aside>
      </transition>
    </Teleport>
  </div>
</template>

<style scoped>
.sim-fade-enter-active,
.sim-fade-leave-active {
  transition: opacity 0.2s ease;
}

.sim-fade-enter-from,
.sim-fade-leave-to {
  opacity: 0;
}

.sim-slide-enter-active,
.sim-slide-leave-active {
  transition: transform 0.25s ease;
}

.sim-slide-enter-from,
.sim-slide-leave-to {
  transform: translateX(100%);
}
</style>
