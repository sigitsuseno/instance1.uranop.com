<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Resume Kehadiran</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Rekap kehadiran karyawan per periode untuk input ke payroll
        </p>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton 
          variant="primary" 
          :loading="isGenerating" 
          :disabled="!canRegenerate || auth.isManajemen" 
          @click="auth.isManajemen ? null : handleGenerate()"
          :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed' : ''"
        >
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/>
              <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
            </svg>
          </template>
          Generate Resume
        </BaseButton>
        <span v-if="!canRegenerate" class="text-xs text-(--text-muted) italic">(data sudah di-approve)</span>
        <BaseButton
          variant="secondary"
          :disabled="!selectedPeriod || auth.isManajemen"
          :loading="isExporting"
          @click="auth.isManajemen ? null : handleExport()"
          :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed' : ''"
        >
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
          </template>
          Export Excel
        </BaseButton>
        <BaseButton
          :variant="isApproving ? 'primary' : 'secondary'"
          :class="[isApproving ? '!bg-green-600' : '', auth.isManajemen ? 'opacity-50 cursor-not-allowed' : '']"
          @click="auth.isManajemen ? null : toggleApproveMode()"
          :disabled="auth.isManajemen"
        >
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </template>
          {{ isApproving ? 'Batal Approve' : 'Approve' }}
        </BaseButton>
        <BaseButton
          v-if="isApproving && checkedIds.length > 0"
          variant="primary"
          class="!bg-green-600"
          :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed' : ''"
          :loading="isProcessing"
          @click="auth.isManajemen ? null : handleProses()"
          :disabled="auth.isManajemen"
        >
          <template #icon-left>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
          </template>
          Proses ({{ checkedIds.length }})
        </BaseButton>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Periode -->
        <div>
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Periode Payroll</label>
          <select v-model="selectedPeriod" @change="fetchList"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm">
            <option value="">— Pilih Periode —</option>
            <option v-for="p in payPeriods" :key="p.id" :value="p.id">
              {{ p.name }} ({{ p.start_date }} — {{ p.end_date }})
            </option>
          </select>
        </div>

        <!-- Departemen -->
        <div>
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Departemen</label>
          <select v-model="filterDepartment" @change="fetchList"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm">
            <option value="">Semua Departemen</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>

        <!-- Search -->
        <div>
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Cari Karyawan</label>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-(--text-soft)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input v-model="filterSearch" type="text" placeholder="Nama atau NIK..."
              class="w-full pl-9 pr-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm"
              @keyup.enter="fetchList" />
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-end gap-2">
          <BaseButton variant="ghost" size="sm" @click="fetchList">Filter</BaseButton>
          <BaseButton v-if="filterSearch || filterDepartment" variant="secondary" size="sm" @click="resetFilter">
            Reset
          </BaseButton>
        </div>
      </div>

      <!-- Baris kedua: per_page + select all shortcut -->
      <div class="flex items-center gap-4 mt-3 pt-3 border-t border-(--border-soft)">
        <div class="flex items-center gap-2">
          <label class="text-xs text-(--text-muted)">Tampilkan</label>
          <select v-model="perPage" @change="fetchList"
            class="px-2 py-1 border border-(--border-soft) rounded bg-(--bg-card) text-(--text-main) text-sm">
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
            <option :value="200">200</option>
            <option :value="500">500</option>
          </select>
        </div>
        <span v-if="isApproving" class="text-xs text-(--text-muted)">
          Centang semua di header untuk pilih {{ records.filter(r => r.status !== 'locked').length }} data di halaman ini
        </span>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-(--border-soft) bg-(--bg-elevated)">
            <th v-if="isApproving" class="px-3 py-3 text-center w-10">
              <input type="checkbox" :checked="allChecked" @change="toggleAll" class="w-4 h-4 rounded" />
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-(--text-muted) uppercase">Karyawan</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">H. Kerja</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Deduct</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Cuti</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Izin</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Sakit</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Absen</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Terlambat</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">LM</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">LM Count</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Lembur</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">OT Count</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in records" :key="r.id" class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/50">
            <td v-if="isApproving" class="px-3 py-3 text-center">
              <input type="checkbox" :checked="checkedIds.includes(r.id)" :disabled="r.status === 'locked'" @change="toggleCheck(r.id)" class="w-4 h-4 rounded" />
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
              <div class="font-medium text-(--text-main)">{{ r.employee?.name }}</div>
              <div class="text-xs text-(--text-muted)">{{ r.employee?.employee_code }} · {{ r.employee?.department?.name }}</div>
            </td>
            <td class="px-4 py-3 text-center font-medium">{{ r.hari_kerja }}</td>
            <td class="px-4 py-3 text-center text-red-600">{{ fmtInt(r.deduct_day) }}</td>
            <td class="px-4 py-3 text-center text-blue-600">{{ fmtInt(r.cuti) }}</td>
            <td class="px-4 py-3 text-center text-orange-600">{{ fmtInt(r.izin) }}</td>
            <td class="px-4 py-3 text-center text-teal-600">{{ fmtInt(r.sakit) }}</td>
            <td class="px-4 py-3 text-center text-red-500 font-bold">{{ r.absen }}</td>
            <td class="px-4 py-3 text-center">{{ r.late_minutes > 0 ? r.late_minutes + ' mnt' : '-' }}</td>
            <td class="px-4 py-3 text-center text-orange-600">{{ formatMinutes(r.lm) }}</td>
            <td class="px-4 py-3 text-center text-indigo-600 font-medium">{{ formatMinutes(r.lm_count) }}</td>
            <td class="px-4 py-3 text-center text-orange-600">{{ formatMinutes(r.lembur) }}</td>
            <td class="px-4 py-3 text-center text-indigo-600 font-medium">{{ formatMinutes(r.lembur_count) }}</td>
          </tr>
          <tr v-if="records.length === 0 && !isLoading">
            <td :colspan="isApproving ? 13 : 12" class="px-4 py-12 text-center text-(--text-muted)">
              <div v-if="!selectedPeriod">Silakan pilih periode untuk melihat data.</div>
              <div v-else>Belum ada data untuk periode ini. Klik <strong>"Generate Resume"</strong> untuk menghitung.</div>
            </td>
          </tr>
          <tr v-if="isLoading">
            <td :colspan="isApproving ? 13 : 12" class="px-4 py-12 text-center text-(--text-muted)">Memuat data...</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4 text-sm text-(--text-muted) text-center" v-if="pagination.last_page > 1">
      <div class="flex items-center justify-center gap-2">
        <button :disabled="pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)"
          class="px-3 py-1 border rounded disabled:opacity-30">&larr;</button>
        <span>Hlm {{ pagination.current_page }}/{{ pagination.last_page }}</span>
        <button :disabled="pagination.current_page >= pagination.last_page" @click="goToPage(pagination.current_page + 1)"
          class="px-3 py-1 border rounded disabled:opacity-30">&rarr;</button>
      </div>
    </div>
    <div class="mt-2 text-xs text-(--text-muted) text-center" v-if="pagination.total > 0">
      Total: {{ pagination.total }} data
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import { useApi } from '../../../../composables/useApi'
import { useAuth } from '../../../../composables/useAuth'


const { get, post } = useApi()
const auth = useAuth()

// ── State ──
const isLoading = ref(false)
const isGenerating = ref(false)
const isProcessing = ref(false)
const isExporting = ref(false)
const payPeriods = ref([])
const departments = ref([])
const records = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const selectedPeriod = ref('')
const filterDepartment = ref('')
const filterSearch = ref('')
const perPage = ref(50)

// ── Approve Mode ──
const isApproving = ref(false)
const checkedIds = ref([])

// Tombol Generate cuma disable kalau ada record locked & user bukan superadmin/hrmanager
const hasLockedRecords = computed(() => records.value.some(r => r.status === 'locked'))
const canRegenerate = computed(() => auth.isSuperadmin || auth.isHrmanager || !hasLockedRecords.value)

// ── Init ──
onMounted(async () => {
  await Promise.all([fetchPayPeriods(), fetchDepartments()])
})

async function fetchPayPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    payPeriods.value = res.data || []
    if (payPeriods.value.length && !selectedPeriod.value) {
      selectedPeriod.value = payPeriods.value[0].id
      fetchList()
    }
  } catch (e) { console.error(e) }
}

async function fetchDepartments() {
  try {
    const res = await get('/api/organization/departments?per_page=100')
    departments.value = res.data || []
  } catch (e) { console.error(e) }
}

// ── Fetch List ──
async function fetchList(page = 1) {
  if (!selectedPeriod.value) {
    records.value = []
    return
  }
  isLoading.value = true
  try {
    const params = new URLSearchParams({
      period_id: selectedPeriod.value,
      per_page: String(perPage.value),
      page: String(page),
    })
    if (filterSearch.value) params.set('search', filterSearch.value)
    if (filterDepartment.value) params.set('department_id', filterDepartment.value)

    const res = await get(`/api/v1/attendance/recap?${params}`)
    records.value = res.data || []
    pagination.value = {
      current_page: res.current_page || 1,
      last_page: res.last_page || 1,
      total: res.total || 0,
    }
  } catch (e) { console.error(e) }
  finally { isLoading.value = false }
}

function goToPage(page) { fetchList(page) }

function resetFilter() {
  filterSearch.value = ''
  filterDepartment.value = ''
  fetchList()
}

// ── Generate ──
async function handleGenerate() {
  if (!selectedPeriod.value) {
    alert('Silakan pilih periode terlebih dahulu.')
    return
  }
  if (!confirm('Generate resume kehadiran untuk periode ini? Data lama akan diperbarui.')) return

  isGenerating.value = true
  try {
    const res = await post('/api/v1/attendance/recap/generate', { period_id: selectedPeriod.value })
    alert(res.message)
    await fetchList()
  } catch (e) {
    alert('Gagal generate: ' + (e.response?.data?.message || e.message))
  }
  finally { isGenerating.value = false }
}

// ── Export ──
async function handleExport() {
  if (!selectedPeriod.value) return

  isExporting.value = true
  try {
    const token = localStorage.getItem('token')
    const params = new URLSearchParams({ period_id: selectedPeriod.value })
    if (filterSearch.value) params.set('search', filterSearch.value)
    if (filterDepartment.value) params.set('department_id', filterDepartment.value)

    const response = await fetch(`/api/v1/attendance/recap/export?${params}`, {
      headers: { 'Authorization': `Bearer ${token}` },
    })

    if (!response.ok) throw new Error('Gagal export')

    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Resume_Kehadiran.xlsx`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (e) {
    alert('Gagal export Excel: ' + e.message)
  }
  finally { isExporting.value = false }
}

// ── Approve Mode ──
function toggleApproveMode() {
  isApproving.value = !isApproving.value
  checkedIds.value = []
}

function toggleCheck(id) {
  const idx = checkedIds.value.indexOf(id)
  if (idx >= 0) checkedIds.value.splice(idx, 1)
  else checkedIds.value.push(id)
}

const allChecked = computed(() => {
  const unlocked = records.value.filter(r => r.status !== 'locked')
  return unlocked.length > 0 && unlocked.every(r => checkedIds.value.includes(r.id))
})

function toggleAll() {
  if (allChecked.value) {
    checkedIds.value = []
  } else {
    checkedIds.value = records.value.filter(r => r.status !== 'locked').map(r => r.id)
  }
}

async function handleProses() {
  if (!checkedIds.value.length) return
  if (!confirm(`Proses ${checkedIds.value.length} data ke payroll? Record akan di-lock.`)) return

  isProcessing.value = true
  try {
    const res = await post('/api/v1/attendance/recap/approve', { ids: checkedIds.value })
    alert(res.message)
    checkedIds.value = []
    await fetchList()
  } catch (e) {
    alert('Gagal approve: ' + (e.response?.data?.message || e.message))
  }
  finally { isProcessing.value = false }
}

// ── Helpers ──
function fmtInt(val) {
  if (val === null || val === undefined || val === '') return '-'
  const n = Number(val)
  // Kalo .00, tampilin bulat; kalo ada pecahan, tampilin 1 desimal
  return n % 1 === 0 ? n : n.toFixed(1)
}
function formatMinutes(minutes) {
  if (!minutes || minutes === 0) return '-'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return m > 0 ? `${h}.${Math.round(m / 6)}j` : `${h}j`
}
</script>
