<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Manual Sync</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Deteksi manual check_in & check_out — koreksi jam scan, lalu simpan
        </p>
      </div>
    </div>

    <!-- Status Banner -->
    <div v-if="resultBanner" class="mb-4 p-3 rounded-lg text-sm font-medium"
      :class="resultBanner.success ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
      {{ resultBanner.message }}
      <button class="ml-2 underline text-xs" @click="resultBanner = null">Tutup</button>
    </div>

    <!-- Filter Bar -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-6 mb-6">
      <!-- Period + Date Range -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Period -->
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Periode</label>
          <select v-model="selectedPeriod" :disabled="isLoading"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
            <option value="">-- Pilih Periode --</option>
            <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
          </select>
        </div>

        <!-- Start Date -->
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Dari Tanggal</label>
          <input v-model="startDate" type="date" :min="periodStart" :max="periodEnd"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)" />
        </div>

        <!-- End Date -->
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Sampai Tanggal</label>
          <input v-model="endDate" type="date" :min="periodStart" :max="periodEnd"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)" />
        </div>
      </div>

      <!-- Buttons + Search -->
      <div class="mt-4 pt-4 border-t border-(--border-soft) flex items-center gap-3">
        <BaseButton variant="primary" :loading="isLoading" :disabled="!selectedPeriod" @click="fetchData">
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
            </svg>
          </template>
          Fetch Data
        </BaseButton>
        <div class="relative flex-1 max-w-xs" ref="dropdownRef">
          <div class="relative">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft) pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
            </svg>
            <input
              v-model="employeeSearch"
              type="text"
              placeholder="Cari karyawan..."
              class="pl-9 pr-8 py-2 w-full border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
              @focus="showEmployeeDropdown = true"
              @click="showEmployeeDropdown = true"
            />
            <button v-if="selectedEmployee" @click.stop="clearEmployee"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-(--text-soft) hover:text-(--text-main)">
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
            <svg v-else @click.stop="toggleDropdown"
              class="w-3.5 h-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 text-(--text-soft) cursor-pointer"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </div>
          <!-- Dropdown -->
          <div v-if="showEmployeeDropdown"
            class="absolute z-20 mt-1 w-full bg-(--bg-card) border border-(--border-soft) rounded-lg shadow-lg max-h-56 overflow-y-auto">
            <div v-if="uniqueEmployees.length === 0"
              class="px-3 py-2 text-sm text-(--text-muted) italic">
              Belum ada data — klik Fetch Data dulu
            </div>
            <div v-else-if="filteredEmployees.length === 0"
              class="px-3 py-2 text-sm text-(--text-muted)">
              Tidak ditemukan
            </div>
            <button
              v-for="emp in filteredEmployees.slice(0, 30)"
              :key="emp.id"
              @click="selectEmployee(emp)"
              class="w-full text-left px-3 py-2 text-sm hover:bg-(--bg-elevated) transition flex items-center justify-between"
              :class="{ 'bg-(--primary)/10 text-(--primary) font-medium': selectedEmployee?.id === emp.id }"
            >
              <span>{{ emp.name }} <span class="text-(--text-muted) text-xs ml-1">({{ emp.nip }})</span></span>
              <svg v-if="selectedEmployee?.id === emp.id" class="w-4 h-4 text-(--primary) shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </button>
          </div>
        </div>
        <BaseButton variant="secondary" :loading="isLoadingDisplay" :disabled="!selectedPeriod" @click="displayData">
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2" /><line x1="3" y1="9" x2="21" y2="9" /><line x1="9" y1="21" x2="9" y2="9" />
            </svg>
          </template>
          Tampilkan Data
        </BaseButton>
        <BaseButton variant="secondary" :loading="isPushing" :disabled="!selectedPeriod" @click="pushPrepare">
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M22 2L11 13" /><polygon points="22 2 15 22 11 13 2 9 22 2" />
            </svg>
          </template>
          Push Prepare
        </BaseButton>
      </div>
    </div>

    <!-- Table -->
    <div v-if="syncData.length > 0" class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-(--text-main)">
          Data Scan
          <span class="text-sm text-(--text-muted) ml-2">
            ({{ filteredData.length }} record · {{ uniqueEmployeesCount }} karyawan · Hal {{ currentPage }}/{{ lastPage || 1 }} · Total {{ totalRecords }} karyawan)
          </span>
        </h3>
        <BaseButton variant="primary" :loading="isSavingAll" @click="saveAll">
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
              <polyline points="17 21 17 13 7 13 7 21" /><polyline points="7 3 7 8 15 8" />
            </svg>
          </template>
          Save Halaman Ini
        </BaseButton>
      </div>

      <div class="overflow-auto max-h-[60vh] rounded-lg border border-(--border-soft)">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b-2 border-(--border-soft) text-left sticky top-0 bg-(--bg-card) z-10 shadow-sm">
              <th class="px-2 py-2 text-xs font-medium text-(--text-muted) uppercase whitespace-nowrap">NIP</th>
              <th class="px-2 py-2 text-xs font-medium text-(--text-muted) uppercase whitespace-nowrap">Nama</th>
              <th class="px-2 py-2 text-xs font-medium text-(--text-muted) uppercase whitespace-nowrap">Tanggal</th>
              <th class="px-2 py-2 text-xs font-medium text-(--text-muted) uppercase whitespace-nowrap">WP</th>
              <th class="px-2 py-2 text-xs font-medium text-(--text-muted) uppercase whitespace-nowrap">Shift</th>
              <th class="px-2 py-2 text-xs font-medium text-(--text-muted) uppercase whitespace-nowrap">EXCP</th>
              <th class="px-2 py-2 text-xs font-medium text-(--text-muted) uppercase whitespace-nowrap min-w-[300px]\">Jam Scan</th>
              <th class="px-2 py-2 text-xs font-medium text-(--text-muted) uppercase whitespace-nowrap">Auto Detect</th>
              <th class="px-2 py-2 text-xs font-medium text-(--text-muted) uppercase whitespace-nowrap">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-for="(row, idx) in filteredData" :key="row.id"
              :class="rowStatusBg(row)">
              <td class="px-2 py-2 text-(--text-main) font-mono text-xs">{{ row.employee.nip }}</td>
              <td class="px-2 py-2 text-(--text-main) font-medium">{{ row.employee.name }}</td>
              <td class="px-2 py-2 text-(--text-muted) text-xs whitespace-nowrap">{{ formatDate(row.date) }}</td>

              <!-- WP -->
              <td class="px-2 py-2">
                <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 whitespace-nowrap">
                  {{ row.wp?.employee_type || '-' }}
                </span>
              </td>

              <!-- Shift -->
              <td class="px-2 py-2 text-(--text-main) text-xs whitespace-nowrap">
                {{ row.shift?.external_code || row.shift?.code || '-' }}
              </td>

              <!-- EXCP -->
              <td class="px-2 py-2 text-xs text-center">
                <span v-if="row.excp" class="px-1.5 py-0.5 rounded text-xs font-bold"
                  :class="excpBadgeClass(row.excp)">
                  {{ row.excp }}
                </span>
                <span v-else class="text-(--text-muted)">-</span>
              </td>

              <!-- Jam Scan: Radio IN + Radio OUT (horizontal) -->
              <td class="px-2 py-2">
                <div class="space-y-2">
                  <!-- Check In -->
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-xs font-medium text-(--text-main) w-6 shrink-0">IN:</span>
                    <label v-for="log in row.logs" :key="'in_r_' + row.id + '_' + log.raw_log_id"
                      class="flex items-center gap-0.5 cursor-pointer text-xs text-(--text-muted) hover:text-(--text-main) shrink-0"
                      :class="{ 'text-(--primary)! font-medium': getSel(row.id).selectedIn === String(log.raw_log_id) }">
                      <input type="radio" :value="String(log.raw_log_id)" v-model="getSel(row.id).selectedIn"
                        :name="'in_' + row.id" class="w-3 h-3 accent-(--primary)" />
                      {{ log.scan_time }}
                    </label>
                    <label class="flex items-center gap-0.5 cursor-pointer text-xs text-(--text-muted) hover:text-(--text-main) shrink-0"
                      :class="{ 'text-(--primary)! font-medium': getSel(row.id).selectedIn === 'null' }">
                      <input type="radio" value="null" v-model="getSel(row.id).selectedIn"
                        :name="'in_' + row.id" class="w-3 h-3 accent-(--primary)" />
                      —
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer text-xs text-(--text-muted) shrink-0"
                      :class="{ 'text-(--primary)! font-medium': getSel(row.id).selectedIn === 'manual' }">
                      <input type="radio" value="manual" v-model="getSel(row.id).selectedIn"
                        :name="'in_' + row.id" class="w-3 h-3 accent-(--primary)" />
                      <input v-if="getSel(row.id).selectedIn === 'manual'" v-model="getSel(row.id).manualInTime" type="time"
                        class="w-20 px-1 py-0.5 text-xs border border-(--border-soft) rounded bg-(--bg-elevated) text-(--text-main) focus:outline-none focus:ring-1 focus:ring-(--primary)" />
                      <span v-else class="text-(--text-soft) italic">lainnya...</span>
                    </label>
                  </div>

                  <!-- Check Out -->
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-xs font-medium text-(--text-main) w-6 shrink-0">OUT:</span>
                    <label v-for="log in row.logs" :key="'out_r_' + row.id + '_' + log.raw_log_id"
                      class="flex items-center gap-0.5 cursor-pointer text-xs text-(--text-muted) hover:text-(--text-main) shrink-0"
                      :class="{ 'text-(--primary)! font-medium': getSel(row.id).selectedOut === String(log.raw_log_id) }">
                      <input type="radio" :value="String(log.raw_log_id)" v-model="getSel(row.id).selectedOut"
                        :name="'out_' + row.id" class="w-3 h-3 accent-(--primary)" />
                      {{ log.scan_time }}
                    </label>
                    <label class="flex items-center gap-0.5 cursor-pointer text-xs text-(--text-muted) hover:text-(--text-main) shrink-0"
                      :class="{ 'text-(--primary)! font-medium': getSel(row.id).selectedOut === 'null' }">
                      <input type="radio" value="null" v-model="getSel(row.id).selectedOut"
                        :name="'out_' + row.id" class="w-3 h-3 accent-(--primary)" />
                      —
                    </label>
                    <label class="flex items-center gap-1 cursor-pointer text-xs text-(--text-muted) shrink-0"
                      :class="{ 'text-(--primary)! font-medium': getSel(row.id).selectedOut === 'manual' }">
                      <input type="radio" value="manual" v-model="getSel(row.id).selectedOut"
                        :name="'out_' + row.id" class="w-3 h-3 accent-(--primary)" />
                      <input v-if="getSel(row.id).selectedOut === 'manual'" v-model="getSel(row.id).manualOutTime" type="time"
                        class="w-20 px-1 py-0.5 text-xs border border-(--border-soft) rounded bg-(--bg-elevated) text-(--text-main) focus:outline-none focus:ring-1 focus:ring-(--primary)" />
                      <span v-else class="text-(--text-soft) italic">lainnya...</span>
                    </label>
                  </div>
                </div>
              </td>

              <!-- Auto Detect -->
              <td class="px-2 py-2">
                <div class="text-xs space-y-0.5">
                  <div class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 shrink-0"></span>
                    <span class="text-(--text-soft)">
                      IN: {{ row.check_in ? formatTime(row.check_in) : '-' }}
                    </span>
                  </div>
                  <div class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                    <span class="text-(--text-soft)">
                      OUT: {{ row.check_out ? formatTime(row.check_out) : '-' }}
                    </span>
                  </div>
                </div>
              </td>

              <!-- Action -->
              <td class="px-2 py-2">
                <div class="flex flex-col gap-1">
                  <BaseButton variant="secondary" size="xs" :loading="getSel(row.id)._saving" @click="saveRow(row, idx)">
                    <template #icon-left>
                      <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                        <polyline points="17 21 17 13 7 13 7 21" /><polyline points="7 3 7 8 15 8" />
                      </svg>
                    </template>
                    Save
                  </BaseButton>
                  <span class="text-xs px-1.5 py-0.5 rounded text-center font-medium"
                    :class="statusBadgeClass(row.status)">
                    {{ statusLabel(row.status) }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="filteredData.length === 0 && syncData.length > 0"
        class="text-sm text-(--text-muted) text-center py-8">
        Tidak ada data yang cocok dengan filter.
      </p>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="mt-6 flex items-center justify-between border-t border-(--border-soft) pt-4">
        <div class="text-xs text-(--text-muted)">
          {{ totalRecords }} total karyawan
        </div>
        <div class="flex items-center gap-1">
          <button
            @click="goToPage(1)"
            :disabled="currentPage <= 1"
            class="px-2 py-1 text-xs rounded border border-(--border-soft) hover:bg-(--bg-elevated) disabled:opacity-40 disabled:cursor-not-allowed"
            title="Halaman pertama">
            ««
          </button>
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage <= 1"
            class="px-3 py-1 text-xs rounded border border-(--border-soft) hover:bg-(--bg-elevated) disabled:opacity-40 disabled:cursor-not-allowed">
            « Sebelumnya
          </button>

          <!-- Page numbers -->
          <template v-for="p in visiblePages" :key="p">
            <span v-if="p === '...'" class="px-1 text-xs text-(--text-muted)">...</span>
            <button
              v-else
              @click="goToPage(p)"
              class="w-8 h-8 text-xs rounded border"
              :class="p === currentPage
                ? 'bg-(--primary) text-white border-(--primary) font-medium'
                : 'border-(--border-soft) hover:bg-(--bg-elevated)'"
            >{{ p }}</button>
          </template>

          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage >= lastPage"
            class="px-3 py-1 text-xs rounded border border-(--border-soft) hover:bg-(--bg-elevated) disabled:opacity-40 disabled:cursor-not-allowed">
            Selanjutnya »
          </button>
          <button
            @click="goToPage(lastPage)"
            :disabled="currentPage >= lastPage"
            class="px-2 py-1 text-xs rounded border border-(--border-soft) hover:bg-(--bg-elevated) disabled:opacity-40 disabled:cursor-not-allowed"
            title="Halaman terakhir">
            »»
          </button>
        </div>
        <div class="flex items-center gap-2 text-xs text-(--text-muted)">
          <span>Karyawan/hal:</span>
          <select v-model.number="perPage" @change="onPerPageChange"
            class="px-2 py-1 border border-(--border-soft) rounded bg-(--bg-card) text-(--text-main)">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Empty -->
    <div v-if="!isLoading && syncData.length === 0 && hasLoaded"
      class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-12 text-center">
      <svg class="w-12 h-12 mx-auto mb-3 text-(--text-soft)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" />
        <line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />
      </svg>
      <p class="text-(--text-muted)">Tidak ada data roster untuk rentang ini.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import BaseButton from '@/Components/BaseButton.vue'
import { useApi } from '../../../composables/useApi'

const { get, post } = useApi()

// ── State ──
const isLoading = ref(false)
const isLoadingDisplay = ref(false)
const isSavingAll = ref(false)
const isPushing = ref(false)
const hasLoaded = ref(false)
const resultBanner = ref(null)

const selectedPeriod = ref('')
const startDate = ref('')
const endDate = ref('')
const employeeSearch = ref('')
const selectedEmployee = ref(null)
const showEmployeeDropdown = ref(false)
const payPeriods = ref([])
const syncData = ref([])

// ── Pagination State ──
const currentPage = ref(1)
const perPage = ref(10)
const totalRecords = ref(0)
const lastPage = ref(1)

// Selection state DIPISAH agar klik radio tidak trigger re-sort/re-filter
const selections = ref({})

// ── Helpers: get/set selection ──

function getSel(rowId) {
  if (!selections.value[rowId]) {
    selections.value[rowId] = {
      selectedIn: 'null',
      selectedOut: 'null',
      manualInTime: '',
      manualOutTime: '',
      _saving: false,
    }
  }
  return selections.value[rowId]
}

function initRowSelection(row) {
  const sel = getSel(row.id)
  // Check In
  if (row.is_manual_in) {
    sel.selectedIn = 'manual'
    sel.manualInTime = row.is_manual_in
  } else if (row.check_in_log_id) {
    sel.selectedIn = String(row.check_in_log_id)
  } else {
    sel.selectedIn = 'null'
  }
  // Check Out
  if (row.is_manual_out) {
    sel.selectedOut = 'manual'
    sel.manualOutTime = row.is_manual_out
  } else if (row.check_out_log_id) {
    sel.selectedOut = String(row.check_out_log_id)
  } else {
    sel.selectedOut = 'null'
  }
  sel._saving = false
}

// ── Period helpers ──

const periodMeta = computed(() => {
  const p = payPeriods.value.find(p => p.id == selectedPeriod.value)
  return p || null
})

const periodStart = computed(() => periodMeta.value?.start || '')
const periodEnd = computed(() => periodMeta.value?.end || '')

// Auto-set date range when period changes
watch(selectedPeriod, (val) => {
  if (val && periodMeta.value) {
    if (!startDate.value || startDate.value < periodMeta.value.start || startDate.value > periodMeta.value.end) {
      startDate.value = periodMeta.value.start
    }
    if (!endDate.value || endDate.value < periodMeta.value.start || endDate.value > periodMeta.value.end) {
      endDate.value = periodMeta.value.end
    }
  }
})

// ── Filtered & Sorted Data ──

const filteredData = computed(() => {
  let data = syncData.value
  if (selectedEmployee.value) {
    data = data.filter(row => row.employee?.id === selectedEmployee.value.id)
  }
  return data
})

// Hitung karyawan unik di halaman ini
const uniqueEmployeesCount = computed(() => {
  const ids = new Set(filteredData.value.map(r => r.employee?.id).filter(Boolean))
  return ids.size
})

// ── Pagination helpers ──

const visiblePages = computed(() => {
  const pages = []
  const last = lastPage.value
  const current = currentPage.value

  if (last <= 7) {
    for (let i = 1; i <= last; i++) pages.push(i)
    return pages
  }

  pages.push(1)
  if (current > 3) pages.push('...')

  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  for (let i = start; i <= end; i++) pages.push(i)

  if (current < last - 2) pages.push('...')
  pages.push(last)

  return pages
})

function goToPage(page) {
  if (page < 1 || page > lastPage.value || page === currentPage.value) return
  loadDataInternal('display', page)
}

function onPerPageChange() {
  currentPage.value = 1
  loadDataInternal('display', 1)
}

// ── Employee Dropdown ──

const uniqueEmployees = computed(() => {
  const seen = new Map()
  for (const row of syncData.value) {
    const emp = row.employee
    if (emp?.id && !seen.has(emp.id)) {
      seen.set(emp.id, { id: emp.id, nip: emp.nip, name: emp.name })
    }
  }
  return [...seen.values()]
})

const filteredEmployees = computed(() => {
  if (!employeeSearch.value) return uniqueEmployees.value
  const q = employeeSearch.value.toLowerCase()
  return uniqueEmployees.value.filter(e =>
    e.name.toLowerCase().includes(q) || (e.nip || '').toLowerCase().includes(q)
  )
})

function toggleDropdown() {
  showEmployeeDropdown.value = !showEmployeeDropdown.value
}

function selectEmployee(emp) {
  selectedEmployee.value = emp
  employeeSearch.value = emp.name
  showEmployeeDropdown.value = false
}

function clearEmployee() {
  selectedEmployee.value = null
  employeeSearch.value = ''
  showEmployeeDropdown.value = false
}

// Click-outside handler
const dropdownRef = ref(null)

function onWindowClick(e) {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    showEmployeeDropdown.value = false
  }
}

onMounted(() => {
  window.addEventListener('click', onWindowClick)
})
onUnmounted(() => {
  window.removeEventListener('click', onWindowClick)
})

// ── API ──

async function fetchPayPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    const list = Array.isArray(res) ? res : (res.data || [])
    payPeriods.value = list.map(p => ({
      id: p.id,
      name: p.name,
      start: p.start_date,
      end: p.end_date,
      label: `${p.name} (${p.start_date} — ${p.end_date})`,
    }))
    if (payPeriods.value.length > 0 && !selectedPeriod.value) {
      selectedPeriod.value = payPeriods.value[0].id
    }
  } catch (e) {
    console.error('Gagal fetch pay periods:', e)
  }
}

async function loadDataInternal(mode, page = 1) {
  if (!selectedPeriod.value) return
  if (!startDate.value || !endDate.value) {
    resultBanner.value = { success: false, message: 'Pilih rentang tanggal terlebih dahulu.' }
    return
  }

  if (mode === 'fetch') {
    isLoading.value = true
  } else {
    isLoadingDisplay.value = true
  }
  resultBanner.value = null

  try {
    const params = new URLSearchParams({
      period_id: selectedPeriod.value,
      start_date: startDate.value,
      end_date: endDate.value,
      mode: mode,
      page: String(page),
      per_page: String(perPage.value),
    })

    // Tambahkan search filter jika ada employee terpilih
    if (selectedEmployee.value) {
      params.set('employee_id', String(selectedEmployee.value.id))
    }

    const res = await get(`/api/v1/attendance/manual-sync/data?${params}`)
    const raw = res.data || []

    // Update pagination info
    if (res.pagination) {
      currentPage.value = res.pagination.current_page
      lastPage.value = res.pagination.last_page
      totalRecords.value = res.pagination.total
    }

    syncData.value = raw.map(item => {
      initRowSelection(item)
      return item
    })

    // Tampilkan fetch stats kalau ada
    if (res.message && mode === 'fetch') {
      resultBanner.value = { success: true, message: res.message }
    } else if (mode === 'display') {
      resultBanner.value = null
    }

    hasLoaded.value = true
  } catch (e) {
    console.error('Gagal load data:', e)
    resultBanner.value = { success: false, message: 'Gagal memuat data: ' + (e.message || 'Unknown error') }
    hasLoaded.value = true
  } finally {
    if (mode === 'fetch') {
      isLoading.value = false
    } else {
      isLoadingDisplay.value = false
    }
  }
}

async function fetchData() {
  currentPage.value = 1
  await loadDataInternal('fetch', 1)
}

async function displayData() {
  await loadDataInternal('display', currentPage.value)
}

function buildCheckId(selected, manualTime) {
  if (selected === 'manual' && manualTime) return `manual:${manualTime}`
  if (selected === 'null' || !selected || selected === 'null') return null
  return parseInt(selected)
}

async function saveRow(row, idx) {
  const sel = getSel(row.id)
  sel._saving = true
  resultBanner.value = null

  try {
    const res = await post('/api/v1/attendance/manual-sync/save', {
      mode: 'single',
      items: [{
        id: row.id,
        check_in_log_id: buildCheckId(sel.selectedIn, sel.manualInTime),
        check_out_log_id: buildCheckId(sel.selectedOut, sel.manualOutTime),
      }],
    })

    // Update status lokal
    if (res.success !== false) {
      const hasIn = sel.selectedIn !== 'null' && sel.selectedIn !== 'manual' ? true
        : (sel.selectedIn === 'manual' && sel.manualInTime) ? true : false
      const hasOut = sel.selectedOut !== 'null' && sel.selectedOut !== 'manual' ? true
        : (sel.selectedOut === 'manual' && sel.manualOutTime) ? true : false
      row.status = (hasIn && hasOut) ? 'lengkap' : 'perhatian'
    }

    resultBanner.value = { success: res.success !== false, message: res.message || 'Tersimpan.' }
  } catch (e) {
    resultBanner.value = { success: false, message: 'Gagal: ' + (e.message || 'Unknown error') }
  } finally {
    sel._saving = false
  }
}

async function saveAll() {
  if (filteredData.value.length === 0) return
  if (!confirm(`Simpan ${filteredData.value.length} record di halaman ini?`)) return

  isSavingAll.value = true
  resultBanner.value = null

  try {
    const items = filteredData.value.map(row => {
      const sel = getSel(row.id)
      return {
        id: row.id,
        check_in_log_id: buildCheckId(sel.selectedIn, sel.manualInTime),
        check_out_log_id: buildCheckId(sel.selectedOut, sel.manualOutTime),
      }
    })

    const res = await post('/api/v1/attendance/manual-sync/save', { mode: 'all', items })

    if (res.success !== false) {
      filteredData.value.forEach(row => {
        const sel = getSel(row.id)
        const hasIn = sel.selectedIn !== 'null' && sel.selectedIn !== 'manual' ? true
          : (sel.selectedIn === 'manual' && sel.manualInTime) ? true : false
        const hasOut = sel.selectedOut !== 'null' && sel.selectedOut !== 'manual' ? true
          : (sel.selectedOut === 'manual' && sel.manualOutTime) ? true : false
        row.status = (hasIn && hasOut) ? 'lengkap' : 'perhatian'
      })
    }

    resultBanner.value = { success: res.success !== false, message: res.message || 'Save All berhasil.' }
  } catch (e) {
    resultBanner.value = { success: false, message: 'Gagal: ' + (e.message || 'Unknown error') }
  } finally {
    isSavingAll.value = false
  }
}

async function pushPrepare() {
  if (!selectedPeriod.value) return
  if (!confirm(`Push semua record dalam rentang ${startDate.value} — ${endDate.value} ke att_prepares?`)) return

  isPushing.value = true
  resultBanner.value = null

  try {
    const res = await post('/api/v1/attendance/manual-sync/push-prepare', {
      period_id: selectedPeriod.value,
      start_date: startDate.value,
      end_date: endDate.value,
    })

    resultBanner.value = {
      success: res.success !== false,
      message: res.message || 'Push Prepare selesai.',
    }
  } catch (e) {
    resultBanner.value = { success: false, message: 'Gagal: ' + (e.message || 'Unknown error') }
  } finally {
    isPushing.value = false
  }
}

// ── Helpers ──

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' })
}

function formatTime(dateTimeStr) {
  if (!dateTimeStr) return '-'
  return dateTimeStr.substring(11, 16)
}

function excpBadgeClass(excp) {
  if (!excp) return ''
  // Leave codes: CT, S, I, etc. → blue bg
  if (/^[A-Z]+$/.test(excp)) return 'bg-blue-100 text-blue-800'
  // Consecutive worked: 7H, 14H → green bg
  if (excp.endsWith('H')) return 'bg-green-100 text-green-800'
  // Consecutive absent: 3A, 5A → red bg
  if (excp.endsWith('A')) return 'bg-red-100 text-red-800'
  return ''
}

function rowStatusBg(row) {
  if (row.is_sunday || row.is_holiday) return 'bg-red-50'
  if (row.status === 'lengkap') return 'bg-green-50/20'
  if (row.status === 'perhatian') return 'bg-yellow-50/20'
  return 'hover:bg-(--bg-elevated)/30'
}

function statusBadgeClass(status) {
  switch (status) {
    case 'lengkap': return 'bg-green-100 text-green-700'
    case 'perhatian': return 'bg-yellow-100 text-yellow-700'
    case 'draft': return 'bg-gray-100 text-gray-600'
    default: return 'bg-gray-100 text-gray-600'
  }
}

function statusLabel(status) {
  switch (status) {
    case 'lengkap': return 'Lengkap'
    case 'perhatian': return 'Perhatian'
    case 'draft': return 'Draft'
    default: return status || '-'
  }
}

// ── Init ──
fetchPayPeriods()
</script>
