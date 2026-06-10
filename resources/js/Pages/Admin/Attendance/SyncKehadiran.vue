<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Sync Kehadiran</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Sinkronisasi data fingerprint ke attendance prepare — review & approve per tanggal
        </p>
      </div>
      <div class="flex gap-2">
        <BaseButton variant="primary" :loading="isSyncing" @click="handleSync">
          <template #icon-left>
            <svg class="w-4 h-4" :class="{ 'animate-spin': isSyncing }" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2.5">
              <path d="M23 4v6h-6" />
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
            </svg>
          </template>
          Sync Kehadiran
        </BaseButton>
        <BaseButton variant="secondary" :loading="isCompleting" @click="handleLengkapi">
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 11l3 3L22 4" />
            </svg>
          </template>
          Lengkapi
        </BaseButton>
        <BaseButton variant="secondary" @click="handleKunci" :disabled="true" title="Coming soon — sesi selanjutnya">
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
          </template>
          Kunci
        </BaseButton>
      </div>
    </div>

    <!-- Status Banner -->
    <div v-if="syncResult || completingResult || calculateResult" class="mb-4 p-3 rounded-lg text-sm font-medium"
      :class="(syncResult?.success || completingResult?.success || calculateResult?.success) ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
      {{ syncResult?.message || completingResult?.message || calculateResult?.message }}
      <button class="ml-2 underline text-xs"
        @click="syncResult = null; completingResult = null; calculateResult = null">Tutup</button>
    </div>

    <!-- Tab Bar: Jakarta / Ungaran Staff / Ungaran Production -->
    <div class="flex gap-1 mb-6 border-b border-(--border-soft)">
      <button v-for="tab in tabs" :key="tab.key"
        class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition border-b-2 -mb-[1px]"
        :class="activeTab === tab.key
          ? 'bg-(--bg-card) border-(--primary) text-(--primary)'
          : 'bg-transparent border-transparent text-(--text-muted) hover:text-(--text-main) hover:border-(--border-soft)'" @click="activeTab = tab.key">
        {{ tab.label }}
        <span class="ml-1 text-xs opacity-60">({{ tab.code }})</span>
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
      <div v-for="stat in statsCards" :key="stat.label"
        class="bg-(--bg-card) border border-(--border-soft) rounded-lg p-3 text-center">
        <p class="text-2xl font-bold" :class="stat.color">{{ stat.value }}</p>
        <p class="text-xs text-(--text-muted)">{{ stat.label }}</p>
      </div>
    </div>

    <!-- Toolbar: Period + Date Navigation -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <!-- Period Selector -->
        <div class="flex items-center gap-3">
          <label class="text-sm font-medium text-(--text-main) whitespace-nowrap">
            <svg class="w-4 h-4 inline mr-1 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            Periode
          </label>
          <select v-model="selectedPeriod"
            class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary) min-w-[280px]"
            @change="onPeriodChange" :disabled="isLoading">
            <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
          </select>
        </div>

        <!-- Date Navigation -->
        <div class="flex items-center gap-2">
          <button
            class="w-8 h-8 rounded-lg border border-(--border-soft) bg-(--bg-card) flex items-center justify-center text-(--text-secondary) hover:bg-(--bg-elevated) transition disabled:opacity-30"
            :disabled="dateWindowStart <= 0" @click="shiftWindow(-1)">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M15 18l-6-6 6-6" />
            </svg>
          </button>

          <button v-for="(d, i) in visibleDates" :key="d.date"
            class="w-10 h-8 rounded-lg border text-sm font-semibold transition font-mono"
            :class="d.date === activeDate
              ? 'bg-(--text-main) text-white border-(--text-main)'
              : 'bg-(--bg-card) border-(--border-soft) text-(--text-main) hover:border-(--primary) hover:text-(--primary)'" @click="activeDate = d.date">
            {{ d.day }}
          </button>

          <button
            class="w-8 h-8 rounded-lg border border-(--border-soft) bg-(--bg-card) flex items-center justify-center text-(--text-secondary) hover:bg-(--bg-elevated) transition disabled:opacity-30"
            :disabled="dateWindowEnd >= allDates.length" @click="shiftWindow(1)">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 18l6-6-6-6" />
            </svg>
          </button>

          <span class="text-xs text-(--text-muted) ml-2 hidden sm:inline">
            {{ formatDate(activeDate) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
      <div class="flex gap-2">
        <div class="relative">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft)" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <path d="M21 21l-4.35-4.35" />
          </svg>
          <input v-model="searchQuery" type="text" placeholder="Cari karyawan..."
            class="pl-9 pr-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary) w-56"
            @input="filterData" />
        </div>
        <select v-model="filterDepartment"
          class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
          @change="filterData">
          <option value="">Semua Departemen</option>
          <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
        </select>
        <select v-model="filterStatus"
          class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
          @change="filterData">
          <option value="">Semua Status</option>
          <option value="hadir">Hadir</option>
          <option value="absent">Absen</option>
          <option value="cek">Belum Lengkap</option>
        </select>
      </div>
      <div class="flex gap-2 items-center">
        <div v-if="isLoading" class="flex items-center gap-1 text-sm text-(--text-muted)">
          <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.4 31.4" />
          </svg>
          Memuat data...
        </div>
        <div class="text-sm text-(--text-muted)">
          <svg class="w-4 h-4 inline mr-1 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
          Klik cell untuk edit absensi
        </div>
      </div>
    </div>
    <!-- Table -->
    <div
      class="overflow-auto max-h-[65vh] bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm relative custom-scrollbar">
      <table class="w-full text-sm">
        <thead class="sticky top-0 z-20">
          <tr class="border-b border-(--border-soft) bg-(--bg-elevated)">
            <th
              class="sticky left-0 bg-(--bg-elevated) px-4 py-3 text-left text-xs font-medium text-(--text-muted) uppercase tracking-wider z-30 min-w-[160px]">
              Nama
            </th>
            <th v-for="d in visibleDates" :key="d.date"
              class="px-3 py-3 text-center text-xs font-medium text-(--text-muted) uppercase tracking-wider min-w-[110px]"
              :class="{ 'bg-red-50/30 dark:bg-red-900/10': d.isWeekend, 'ring-2 ring-inset ring-(--primary)': d.date === activeDate }">
              <div>{{ d.dateDisplay }}</div>
              <div class="text-[10px]">{{ d.dayName }}</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in filteredEmployees" :key="emp.id"
            class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/30 transition">
            <td class="sticky left-0 bg-(--bg-card) px-4 py-0 font-medium text-(--text-main) z-10">
              <div class="py-2">
                <div class="text-sm font-semibold">{{ emp.name }}</div>
                <div class="text-xs text-(--text-muted)">{{ emp.nip }}</div>
                <div class="text-xs text-(--text-soft)">{{ emp.department }}</div>
              </div>
            </td>

            <!-- Date columns -->
            <td v-for="d in visibleDates" :key="d.date"
              class="px-2 py-0 text-center align-top cursor-pointer hover:bg-(--bg-elevated) transition border-l border-(--border-soft)/30"
              :class="{ 'bg-red-50/20 dark:bg-red-900/5': d.isWeekend }" @click="openEdit(emp, d)">
              <div class="py-2 space-y-[6px]">
                <!-- Check In -->
                <div class="text-xs font-mono"
                  :class="getDayData(emp, d.date).checkIn ? 'text-(--text-main)' : 'text-(--text-muted)'">
                  <span class="text-[10px] text-(--text-soft) mr-0.5">in</span>{{ getDayData(emp, d.date).checkIn ||
                    '--:--'
                  }}
                </div>
                <!-- Check Out -->
                <div class="text-xs font-mono"
                  :class="getDayData(emp, d.date).checkOut ? 'text-(--text-muted)' : 'text-(--text-muted)'">
                  <span class="text-[10px] text-(--text-soft) mr-0.5">out</span>{{ getDayData(emp, d.date).checkOut ||
                    '--:--' }}
                </div>
                <!-- Overtime -->
                <div class="text-[10px]"
                  :class="getDayData(emp, d.date).overtime ? 'text-orange-500' : 'text-(--text-muted)'">
                  {{ getDayData(emp, d.date).overtime || '-' }}
                </div>
                <!-- Status Badge -->
                <span class="inline-block px-1.5 py-0.5 text-[10px] rounded-full font-semibold"
                  :class="getDayData(emp, d.date).statusBadgeClass || statusBadgeClass(getDayData(emp, d.date).status)">
                  {{ getDayData(emp, d.date).statusLabel || statusLabel(getDayData(emp, d.date).status) }}
                </span>
                <!-- Review Status -->
                <span v-if="getDayData(emp, d.date).reviewStatus"
                  class="inline-block px-1.5 py-0.5 text-[10px] rounded-full font-semibold"
                  :class="reviewBadgeClass(getDayData(emp, d.date).reviewStatus)">
                  {{ getDayData(emp, d.date).reviewStatusLabel || reviewLabel(getDayData(emp, d.date).reviewStatus) }}
                </span>
              </div>
            </td>
          </tr>

          <tr v-if="filteredEmployees.length === 0">
            <td :colspan="visibleDates.length + 1" class="px-4 py-12 text-center text-(--text-muted)">
              <svg class="w-8 h-8 mx-auto mb-2 opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.5">
                <path
                  d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-2.586a1 1 0 0 0-.707.293l-2.414 2.414a1 1 0 0 1-.707.293h-3.172a1 1 0 0 1-.707-.293l-2.414-2.414A1 1 0 0 0 6.586 13H4" />
              </svg>
              {{ isLoading ? 'Memuat...' : 'Tidak ada data untuk ditampilkan' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4 flex justify-between items-center text-sm text-(--text-muted)">
      <span>{{ isLoading ? 'Memuat...' : `Menampilkan ${filteredEmployees.length} karyawan` }}</span>
    </div>

    <!-- Edit Modal -->
    <BaseModal v-if="editingCell" :show="!!editingCell" title="Edit Absensi" size="lg" @close="closeEdit">
      <div class="p-3 bg-(--bg-elevated) rounded-lg mb-4">
        <p class="text-sm font-semibold text-(--text-main)">{{ editingCell.employee.name }}</p>
        <p class="text-xs text-(--text-muted) mt-1">
          <svg class="w-3.5 h-3.5 inline mr-1 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          {{ formatDate(editingCell.date.date) }} — {{ editingCell.date.dayName }}
        </p>
      </div>

      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Check-in</label>
            <input v-model="editForm.checkIn" type="time"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
              :disabled="editForm.isLocked" />
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Check-out</label>
            <input v-model="editForm.checkOut" type="time"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
              :disabled="editForm.isLocked" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Status</label>
          <select v-model="editForm.status" :disabled="editForm.isLocked"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)">
            <option value="hadir">Hadir</option>
            <option value="absent">Absen</option>
            <option value="libur">Libur</option>
            <option value="off">Off</option>
            <optgroup label="Cuti / Izin / Sakit">
              <option v-for="lt in leaveTypeOptions" :key="lt.code" :value="lt.code">
                {{ lt.name }}
              </option>
            </optgroup>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Catatan</label>
          <textarea v-model="editForm.notes" rows="2" :disabled="editForm.isLocked"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
            placeholder="Catatan..."></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Review</label>
            <input :value="reviewLabel(editForm.reviewStatus)" readonly
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm opacity-60" />
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Keterlambatan (menit)</label>
            <input :value="editForm.lateMinutes" readonly type="text"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm opacity-60" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Lembur (menit)</label>
          <input :value="editForm.overtimeTotal" readonly type="text"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm opacity-60" />
        </div>
      </div>

      <template #footer>
        <div class="flex gap-3 w-full">
          <BaseButton variant="secondary" @click="closeEdit">Tutup</BaseButton>
          <span class="flex-1"></span>
          <BaseButton variant="primary" :loading="isSaving" :disabled="editForm.isLocked" @click="handleSaveEdit">
            <template #icon-left>
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                <polyline points="17 21 17 13 7 13 7 21" />
                <polyline points="7 3 7 8 15 8" />
              </svg>
            </template>
            Simpan
          </BaseButton>
        </div>
      </template>
    </BaseModal>

    <!-- Lengkapi Modal -->
    <BaseModal v-if="showLengkapiModal" :show="showLengkapiModal" title="Lengkapi Absensi" size="sm"
      @close="showLengkapiModal = false">
      <div class="space-y-4">
        <p class="text-sm text-(--text-muted)">
          Pilih rentang tanggal untuk proses auto-lengkapi. Hanya record yang belum punya check-in/out yang akan diisi.
        </p>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-(--text-main) mb-1">Dari Tanggal</label>
            <input v-model="processStartDate" type="date"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)" />
          </div>
          <div>
            <label class="block text-xs font-medium text-(--text-main) mb-1">Sampai Tanggal</label>
            <input v-model="processEndDate" type="date"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)" />
          </div>
        </div>

        <label v-if="currentAbsentGroup" class="flex items-center gap-2 text-sm cursor-pointer select-none">
          <input v-model="fillAbsent" type="checkbox" class="w-4 h-4 rounded accent-(--primary)" />
          <span class="text-(--text-main)">Lengkapi Absent</span>
          <span class="text-xs text-(--text-muted)">({{ currentAbsentGroup }})</span>
        </label>
      </div>

      <template #footer>
        <div class="flex gap-3 w-full">
          <BaseButton variant="secondary" @click="showLengkapiModal = false">Batal</BaseButton>
          <span class="flex-1"></span>
          <BaseButton variant="primary" :loading="isCompleting" @click="handleProceedLengkapi">
            <template #icon-left>
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4" />
              </svg>
            </template>
            Proses Lengkapi
          </BaseButton>
        </div>
      </template>
    </BaseModal>

    <!-- Sync Modal -->
    <BaseModal v-if="showSyncModal" :show="showSyncModal" title="Sync Kehadiran" size="sm"
      @close="showSyncModal = false">
      <div class="space-y-4">
        <p class="text-sm text-(--text-muted)">
          Pilih rentang tanggal untuk sinkronisasi data fingerprint ke attendance prepare.
        </p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-(--text-main) mb-1">Dari Tanggal</label>
            <input v-model="processStartDate" type="date"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)" />
          </div>
          <div>
            <label class="block text-xs font-medium text-(--text-main) mb-1">Sampai Tanggal</label>
            <input v-model="processEndDate" type="date"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)" />
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-3 w-full">
          <BaseButton variant="secondary" @click="showSyncModal = false">Batal</BaseButton>
          <span class="flex-1"></span>
          <BaseButton variant="primary" :loading="isSyncing" @click="handleProceedSync">
            <template #icon-left>
              <svg class="w-4 h-4" :class="{ 'animate-spin': isSyncing }" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.5">
                <path d="M23 4v6h-6" />
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
              </svg>
            </template>
            Proses Sync
          </BaseButton>
        </div>
      </template>
    </BaseModal>

  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseModal from '@/Components/BaseModal.vue'
import { useApi } from '../../../composables/useApi'

const { get, post } = useApi()

// ── State ──
const isSyncing = ref(false)
const isCompleting = ref(false)
const isCalculating = ref(false)
const isLoading = ref(true)
const isSaving = ref(false)
const syncResult = ref(null)
const completingResult = ref(null)
const calculateResult = ref(null)
const selectedPeriod = ref('current')
const activeDate = ref(new Date().toISOString().split('T')[0])
const activeTab = ref('jkt')
const fillAbsent = ref(false)
const dateWindowStart = ref(0)
const searchQuery = ref('')
const filterDepartment = ref('')
const filterStatus = ref('')
const editingCell = ref(null)
const apiError = ref(null)
const showLengkapiModal = ref(false)
const showSyncModal = ref(false)
const showHitungLemburModal = ref(false)
const processStartDate = ref('')
const processEndDate = ref('')

// Leave type options untuk edit modal
const leaveTypeOptions = ref([])

const editForm = ref({
  checkIn: '',
  checkOut: '',
  status: '',
  reviewStatus: '',
  lateMinutes: 0,
  overtimeTotal: 0,
  notes: '',
  isLocked: false,
})

// Data dari API
const employees = ref([])
const attendanceData = ref({})
const departments = ref([])
const employeeGroups = ref({}) // { employee_id: [reference_codes] }

// ── Tabs ──
const tabs = [
  { key: 'jkt', label: 'Jakarta', code: 'GRP-JKT', groupCodes: ['GRP-JKT'], absentGroup: 'GRP-JKT' },
  { key: 'ung-staff', label: 'Ungaran Staff', code: 'GRP-ALLIN, GD, SS, SPR', groupCodes: ['GRP-ALLIN', 'GRP-GD', 'GRP-SS', 'GRP-SPR'], absentGroup: 'GRP-SPR' },
  { key: 'ung-prod', label: 'Ungaran Production', code: 'GRP-PS1', groupCodes: ['GRP-PS1'], absentGroup: null },
]

// ── Periode (dari pay_periods API) ──

const payPeriods = ref([])

async function fetchPayPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    const list = res.data || []
    payPeriods.value = list.map(p => ({
      id: p.id,
      name: p.name,
      start: p.start_date,
      end: p.end_date,
      label: `${p.name} (${p.start_date} - ${p.end_date})`,
    }))
    // Set selected ke periode pertama (default)
    if (payPeriods.value.length > 0 && selectedPeriod.value === 'current') {
      selectedPeriod.value = payPeriods.value[0].id
    }
  } catch (e) {
    console.error('Gagal fetch pay periods:', e)
    payPeriods.value = []
  }
}

function getPeriodDates() {
  const p = payPeriods.value.find(p => p.id == selectedPeriod.value)
  if (p) return p
  // Fallback ke periode pertama jika selected invalid
  if (payPeriods.value.length > 0) return payPeriods.value[0]
  // Last resort: bulan berjalan (tanggal 1 s/d hari ini)
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
  const end = now.toISOString().split('T')[0]
  return { start, end }
}

// Generate all dates for period
const allDates = computed(() => {
  const { start, end } = getPeriodDates()
  const dates = []
  const current = new Date(start)
  const last = new Date(end)
  const dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']
  const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

  while (current <= last) {
    const d = new Date(current)
    const dateStr = d.toISOString().split('T')[0]
    dates.push({
      date: dateStr,
      day: d.getDate(),
      dayName: dayNames[d.getDay()],
      dateDisplay: `${d.getDate()} ${monthNames[d.getMonth()]}`,
      isWeekend: d.getDay() === 0 || d.getDay() === 6,
    })
    current.setDate(current.getDate() + 1)
  }
  return dates
})

// 5-date sliding window
const WINDOW_SIZE = 5
const dateWindowEnd = computed(() => Math.min(dateWindowStart.value + WINDOW_SIZE, allDates.value.length))

const visibleDates = computed(() => {
  return allDates.value.slice(dateWindowStart.value, dateWindowEnd.value)
})

function shiftWindow(dir) {
  const newStart = dateWindowStart.value + (dir * WINDOW_SIZE)
  if (newStart >= 0 && newStart < allDates.value.length) {
    dateWindowStart.value = newStart
    if (visibleDates.value.length > 0) {
      activeDate.value = visibleDates.value[0].date
    }
  }
}

function onPeriodChange() {
  dateWindowStart.value = 0
  if (allDates.value.length > 0) {
    activeDate.value = allDates.value[0].date
  }
  fetchData()
}

// ── API Calls ──

async function fetchEmployees() {
  try {
    const { start, end } = getPeriodDates()
    // Guard: jangan fetch kalau tanggal kosong (harusnya ga terjadi setelah perbaikan fallback)
    if (!start || !end) {
      console.error('fetchEmployees: start/end kosong, skip fetch')
      employees.value = []
      return
    }
    // Backend limits per_page to max 100, so paginate through all pages
    let allEmployees = []
    let page = 1
    let hasMore = true

    while (hasMore) {
      const res = await get(`/api/v1/employees?period_start=${start}&period_end=${end}&per_page=100&page=${page}`)
      // EmployeeListResource::collection() returns { data: [...], links: {...}, meta: {...} }
      const empList = Array.isArray(res.data) ? res.data : (res.data?.data || [])
      allEmployees = allEmployees.concat(empList)

      // Check if there are more pages
      const meta = res.meta || {}
      const lastPage = meta.last_page || res.last_page || 1
      hasMore = page < lastPage
      page++
    }

    employees.value = allEmployees.map(e => ({
      id: e.id,
      name: e.name,
      nip: e.employee_code || '',
      department: e.department?.name || '',
      department_id: e.department?.id || null,
    }))

    // Extract unique departments
    departments.value = [...new Set(employees.value.map(e => e.department).filter(Boolean))].sort()
  } catch (e) {
    console.error('Gagal fetch employees:', e)
    apiError.value = 'Gagal memuat data karyawan: ' + e.message
  }
}

async function fetchEmployeeGroups() {
  try {
    const res = await get('/api/v1/attendance/prepare/employee-groups')
    // Endpoint returns { groups: { employee_id: [reference_codes] } }
    employeeGroups.value = res.groups || {}
  } catch (e) {
    console.error('Gagal fetch employee groups:', e)
    employeeGroups.value = {}
  }
}

async function fetchLeaveTypes() {
  try {
    const res = await get('/api/v1/leave/types?active_only=1')
    const types = Array.isArray(res.data) ? res.data : (res.data?.data || [])
    leaveTypeOptions.value = types.map(t => ({
      code: t.code?.toLowerCase() || '',
      name: t.name || t.code,
    }))
  } catch (e) {
    console.error('Gagal fetch leave types:', e)
    leaveTypeOptions.value = []
  }
}

async function fetchPrepareData() {
  const { start, end } = getPeriodDates()
  try {
    // Use large per_page to fetch all records for the period (~250 employees × 30 days)
    const res = await get(`/api/v1/attendance/prepare/list?start_date=${start}&end_date=${end}&per_page=10000`)

    // Controller returns { data: <paginator>, meta: {...} }
    // Paginator serializes as { current_page, data: [...], ... }
    // So actual records are at res.data.data (paginator's inner data)
    let prepareList = []
    if (res.data?.data && Array.isArray(res.data.data)) {
      // res.data is the paginator object, res.data.data is the records array
      prepareList = res.data.data
    } else if (Array.isArray(res.data)) {
      prepareList = res.data
    } else {
      prepareList = []
    }

    // Build attendance data map: { [employee_id]: { [date]: { ... } } }
    const data = {}
    for (const p of prepareList) {
      const empId = p.employee_id
      if (!data[empId]) data[empId] = {}

      // Date comes as 'YYYY-MM-DD' from the cast
      const dateStr = p.date?.split('T')[0] || p.date
      const checkIn = p.check_in ? p.check_in.split('T')[1]?.substring(0, 5) : null
      const checkOut = p.check_out ? p.check_out.split('T')[1]?.substring(0, 5) : null

      // Calculate overtime display — pakai raw overtime/LM (sebelum multiplier)
      let overtimeStr = null
      const totalOT = (p.overtime || 0) + (p.lm || 0)
      if (totalOT > 0) {
        overtimeStr = totalOT >= 60
          ? `${Math.floor(totalOT / 60)}j ${totalOT % 60}m`
          : `${totalOT}m`
      }

      data[empId][dateStr] = {
        checkIn,
        checkOut,
        overtime: overtimeStr,
        overtimeRaw: p.overtime || 0,
        lmRaw: p.lm || 0,
        overtimeCount: p.overtime_count || 0,
        lmCount: p.lm_count || 0,
        lateMinutes: p.late_minutes || 0,
        status: p.status || 'absent',
        statusLabel: p.status_label || statusLabel(p.status),
        statusBadgeClass: p.status_badge_class || '',
        reviewStatus: p.review_status || 'cek',
        reviewStatusLabel: p.review_status_label || '',
        isLocked: p.is_locked || false,
        notes: p.notes || '',
        id: p.id,
      }
    }

    attendanceData.value = data
  } catch (e) {
    console.error('Gagal fetch prepare data:', e)
    // Don't set apiError here — might be empty if no sync has been run
    attendanceData.value = {}
  }
}

async function fetchStats() {
  const { start, end } = getPeriodDates()
  try {
    const res = await get(`/api/v1/attendance/prepare/stats?start_date=${start}&end_date=${end}`)
    // Controller returns { data: { total, hadir, ... } }
    const stats = res.data || res || {}

    // Store stats for computed
    const total = stats.total || 0
    const hadir = (stats.hadir || 0)
    const absent = stats.absent || 0
    const libur = stats.libur || 0
    const off = stats.off || 0
    // leaveTotal = semua record yang bukan hadir/absent/libur/off (yaitu kode leave type)
    const leaveTotal = total - hadir - absent - libur - off

    statsData.value = {
      total,
      hadir,
      absent,
      leaveTotal: leaveTotal > 0 ? leaveTotal : 0,
      libur,
      off,
      cek: stats.cek || 0,
      lengkap: stats.lengkap || 0,
      locked: stats.locked || 0,
    }
  } catch (e) {
    console.error('Gagal fetch stats:', e)
    statsData.value = null
  }
}

const statsData = ref(null)

async function fetchData() {
  isLoading.value = true
  apiError.value = null
  try {
    if (payPeriods.value.length === 0) {
      await fetchPayPeriods()
    }
    if (employees.value.length === 0) {
      await Promise.all([fetchEmployees(), fetchEmployeeGroups()])
    }
    if (leaveTypeOptions.value.length === 0) {
      await fetchLeaveTypes()
    }
    await Promise.all([fetchPrepareData(), fetchStats()])
  } catch (e) {
    console.error('fetchData error:', e)
  } finally {
    isLoading.value = false
  }
}

// ── Sync ──

async function handleSync() {
  const { start, end } = getPeriodDates()
  processStartDate.value = start
  processEndDate.value = end
  showSyncModal.value = true
}

async function handleProceedSync() {
  isSyncing.value = true
  syncResult.value = null
  showSyncModal.value = false

  try {
    const res = await post('/api/v1/attendance/prepare/sync', {
      start_date: processStartDate.value,
      end_date: processEndDate.value,
    })

    syncResult.value = {
      success: res.success,
      message: res.message || 'Sync selesai.',
    }

    await fetchData()
  } catch (e) {
    syncResult.value = {
      success: false,
      message: 'Sync gagal: ' + (e.message || 'Unknown error'),
    }
  } finally {
    isSyncing.value = false
  }
}

// ── Computed ──

const filteredEmployees = computed(() => {
  let result = employees.value

  // Filter by active tab's group codes
  const currentTab = tabs.find(t => t.key === activeTab.value)
  if (currentTab && currentTab.groupCodes.length > 0) {
    result = result.filter(e => {
      const codes = employeeGroups.value[e.id] || []
      return currentTab.groupCodes.some(gc => codes.includes(gc))
    })
  }

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(e => e.name.toLowerCase().includes(q) || e.nip.toLowerCase().includes(q))
  }
  if (filterDepartment.value) {
    result = result.filter(e => e.department === filterDepartment.value)
  }

  // Filter by status on the active date
  if (filterStatus.value) {
    result = result.filter(e => {
      const dayData = getDayData(e, activeDate.value)
      if (filterStatus.value === 'cek') {
        return !dayData.checkIn || !dayData.checkOut
      }
      return dayData.status === filterStatus.value
    })
  }

  return result
})

const statsCards = computed(() => {
  const s = statsData.value
  if (!s) {
    return [
      { label: 'Total', value: '?', color: 'text-(--text-muted)' },
      { label: 'Hadir', value: '?', color: 'text-(--text-muted)' },
      { label: 'Absen', value: '?', color: 'text-(--text-muted)' },
      { label: 'Cuti/Izin/Sakit', value: '?', color: 'text-(--text-muted)' },
      { label: 'Belum Lengkap', value: '?', color: 'text-(--text-muted)' },
      { label: 'Terkunci', value: '?', color: 'text-(--text-muted)' },
    ]
  }

  return [
    { label: 'Total', value: s.total, color: 'text-(--text-main)' },
    { label: 'Hadir', value: s.hadir, color: 'text-green-600 dark:text-green-400' },
    { label: 'Absen', value: s.absent, color: 'text-red-600 dark:text-red-400' },
    { label: 'Cuti/Izin/Sakit', value: s.leaveTotal || 0, color: 'text-blue-600 dark:text-blue-400' },
    { label: 'Belum Lengkap', value: s.cek, color: 'text-orange-600 dark:text-orange-400' },
    { label: 'Terkunci', value: s.locked, color: 'text-gray-500' },
  ]
})

// ── Helpers ──

function getDayData(emp, dateStr) {
  return attendanceData.value[emp.id]?.[dateStr] || {
    checkIn: null,
    checkOut: null,
    overtime: null,
    status: 'absent',
    reviewStatus: '',
  }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
  const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
  return `${dayNames[d.getDay()]}, ${d.getDate()} ${monthNames[d.getMonth()]} ${d.getFullYear()}`
}

function statusLabel(status) {
  const map = {
    hadir: 'Hadir',
    absent: 'Absen',
    libur: 'Libur',
    off: 'Off',
  }
  return map[status] || status || '-'
}

function statusBadgeClass(status) {
  const map = {
    hadir: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    absent: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    libur: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400',
    off: 'bg-gray-200 text-gray-500',
  }
  return map[status] || 'bg-blue-100 text-blue-800'
}

function reviewLabel(status) {
  const map = { cek: 'Cek', perhatian: '!Perhatian', lengkap: 'Lengkap' }
  return map[status] || status || ''
}

function reviewBadgeClass(status) {
  const map = {
    cek: 'bg-yellow-100 text-yellow-800',
    perhatian: 'bg-orange-100 text-orange-800',
    lengkap: 'bg-green-100 text-green-800',
  }
  return map[status] || 'bg-gray-100 text-gray-800'
}

// ── Edit Modal ──

function openEdit(emp, dateObj) {
  const dayData = getDayData(emp, dateObj.date)
  const totalOT = (dayData.overtimeRaw || 0) + (dayData.lmRaw || 0)

  editingCell.value = { employee: emp, date: dateObj }
  editForm.value = {
    prepareId: dayData.id || null,
    checkIn: dayData.checkIn || '',
    checkOut: dayData.checkOut || '',
    status: dayData.status || 'absent',
    reviewStatus: dayData.reviewStatus || 'cek',
    lateMinutes: dayData.lateMinutes || 0,
    overtimeTotal: totalOT,
    notes: dayData.notes || '',
    isLocked: dayData.isLocked || false,
  }
}

function closeEdit() {
  editingCell.value = null
}

async function handleSaveEdit() {
  isSaving.value = true
  try {
    const res = await post('/api/v1/attendance/prepare/lengkapi', {
      records: [{
        id: editForm.value.prepareId,
        check_in: editForm.value.checkIn || null,
        check_out: editForm.value.checkOut || null,
        status: editForm.value.status,
        notes: editForm.value.notes,
      }],
    })

    if (res.success) {
      // Update local data instantly
      const empId = editingCell.value.employee.id
      const dateStr = editingCell.value.date.date
      if (attendanceData.value[empId]?.[dateStr]) {
        attendanceData.value[empId][dateStr].checkIn = editForm.value.checkIn
        attendanceData.value[empId][dateStr].checkOut = editForm.value.checkOut
        attendanceData.value[empId][dateStr].status = editForm.value.status
        attendanceData.value[empId][dateStr].reviewStatus = 'lengkap'
        attendanceData.value[empId][dateStr].notes = editForm.value.notes
      }
      closeEdit()
    }
  } catch (e) {
    console.error('Gagal simpan edit:', e)
  } finally {
    isSaving.value = false
  }
}

// ── Lengkapi ──

const currentAbsentGroup = computed(() => {
  const tab = tabs.find(t => t.key === activeTab.value)
  return tab?.absentGroup || null
})

function handleLengkapi() {
  // Buka modal — default range: periode start → hari ini
  const { start } = getPeriodDates()
  const today = new Date().toISOString().split('T')[0]

  if (!processStartDate.value) processStartDate.value = start
  if (!processEndDate.value) processEndDate.value = today

  showLengkapiModal.value = true
}

async function handleProceedLengkapi() {
  isCompleting.value = true
  completingResult.value = null
  showLengkapiModal.value = false

  const start = processStartDate.value
  const end = processEndDate.value
  const currentTab = tabs.find(t => t.key === activeTab.value)
  let totalMessage = ''

  try {
    // 1. Lengkapi normal (semua group codes di tab)
    const res = await post('/api/v1/attendance/prepare/auto-lengkapi', {
      group_codes: currentTab?.groupCodes || [],
      start_date: start,
      end_date: end,
      fill_absent: false,
    })
    totalMessage = res.message || ''

    // 2. Kalau checkbox dicentang & ada absentGroup → lengkapi absent khusus grup itu
    if (fillAbsent.value && currentAbsentGroup.value) {
      try {
        const absentRes = await post('/api/v1/attendance/prepare/auto-lengkapi', {
          group_codes: [currentAbsentGroup.value],
          start_date: start,
          end_date: end,
          fill_absent: true,
        })
        totalMessage += ' | ' + (absentRes.message || '')
      } catch (absentErr) {
        const msg = absentErr?.response?.data?.message || absentErr.message || ''
        totalMessage += ' | ' + (msg.includes('Tidak ada karyawan')
          ? '⚠️ ' + msg + ' — lewati'
          : '⚠️ Gagal isi absent: ' + msg)
      }
    }

    completingResult.value = {
      success: res.success,
      message: totalMessage || 'Lengkapi selesai.',
    }

    if (res.success) {
      await fetchData()
    }
  } catch (e) {
    const msg = e?.response?.data?.message || e.message || 'Unknown error'
    completingResult.value = {
      success: false,
      message: 'Lengkapi gagal: ' + msg,
    }
  } finally {
    isCompleting.value = false
  }
}

// ── Kunci ──
function handleKunci() { /* TODO: sesi selanjutnya */ }

function filterData() { /* computed handles this */ }

// ── Init ──

// Watch for period change to refetch
watch(() => selectedPeriod.value, () => {
  fetchData()
})

// Initial load
fetchData()
</script>
