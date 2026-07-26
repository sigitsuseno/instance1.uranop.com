<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Rekap Gaji</h1>
        <p class="text-sm text-(--text-muted) mt-1">Rekapan gaji karyawan per periode penggajian</p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <!-- Period Select -->
        <select
          v-model="payPeriodId"
          @change="fetchData"
          class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer min-w-[200px]"
        >
          <option value="">Pilih Periode</option>
          <option v-for="p in payPeriods" :key="p.id" :value="p.id">
            {{ p.name }}
          </option>
        </select>

        <!-- Export Excel -->
        <button
          class="h-10 px-4 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 transition-colors disabled:opacity-40"
          @click="exportExcel"
          :disabled="!payPeriodId || allRecords.length === 0 || exporting"
        >
          <i class="bx" :class="exporting ? 'bx-loader-alt animate-spin' : 'bx-export'"></i>
          <span class="hidden sm:inline">{{ exporting ? 'Mengekspor...' : 'Export Excel' }}</span>
        </button>

        <!-- Settings Button -->
        <button
          @click="showSettings = true"
          class="h-10 px-3 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 transition-colors"
          title="Pengaturan Tampilan"
        >
          <i class="bx bx-cog text-lg"></i>
          <span class="hidden sm:inline">Setting</span>
        </button>
      </div>
    </div>

    <!-- Empty: No Period -->
    <div v-if="!payPeriodId" class="text-center py-16 text-(--text-muted)">
      <i class="bx bx-calendar text-4xl block mb-3"></i>
      <p>Pilih periode penggajian terlebih dahulu</p>
    </div>

    <!-- Content: Has Period -->
    <template v-else>
      <!-- Loading -->
      <div v-if="loading" class="text-center py-8 text-(--text-muted)">
        <i class="bx bx-loader-alt animate-spin text-2xl"></i>
        <p class="mt-2">Memuat data...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="allRecords.length === 0" class="text-center py-16 text-(--text-muted)">
        <i class="bx bx-file text-4xl block mb-3"></i>
        <p>Belum ada data rekap gaji untuk periode ini.</p>
        <p v-if="selectedGroups.length === 0" class="text-xs mt-1 text-amber-600">
          ⚠ Tidak ada group karyawan dipilih. Klik tombol <strong>Setting</strong> untuk memilih GRP.
        </p>
      </div>

      <!-- Data Table -->
      <template v-else>
        <!-- Period Info -->
        <div v-if="selectedPeriod" class="px-4 py-3 rounded-md bg-(--primary)/5 border border-(--primary)/20 mb-6 flex flex-wrap items-center gap-3 text-sm shadow-sm">
          <span class="font-bold text-(--primary)">{{ selectedPeriod.name }}</span>
          <span class="text-(--text-muted)">{{ selectedPeriod.date_start }} - {{ selectedPeriod.date_end }}</span>
          <span class="text-(--text-muted) ml-auto">{{ allRecords.length }} Karyawan</span>
        </div>

        <!-- Single Table -->
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden shadow-sm mb-6">
          <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
              <thead>
                <!-- Row 1: Main Headers -->
                <tr class="bg-pink-100 border-b border-(--border-soft)">
                  <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" rowspan="2" style="width:40px">No</th>
                  <th class="px-3 py-2 text-left font-bold text-(--text-main) border-r border-(--border-soft)" rowspan="2" style="min-width:160px">NAMA</th>
                  <th class="px-3 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" rowspan="2" style="min-width:120px">ACCOUNT NO</th>
                  <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" rowspan="2" style="width:70px">STATUS</th>
                  <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" rowspan="2" style="width:50px">L/P</th>
                  <th class="px-3 py-1.5 text-center font-bold text-(--primary) bg-(--primary)/5" :colspan="5">
                    {{ periodLabel }}
                  </th>
                </tr>
                <!-- Row 2: Sub Headers under period -->
                <tr class="bg-pink-50 border-b-2 border-(--border-soft)">
                  <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[120px]">GAJI<br/><span class="text-[9px] font-normal text-(--text-muted)">{{ subPeriodLabel }}</span></th>
                  <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[110px]">TOTAL GAJI</th>
                  <th class="px-3 py-1.5 text-center font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[130px]">BPJS TK<br/><span class="text-[9px] font-normal text-(--text-muted)">(JHT,JKK,JKM)</span></th>
                  <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[110px]">BPJS KESEHATAN</th>
                  <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) whitespace-nowrap min-w-[90px]">UM</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-(--border-soft)">
                <tr
                  v-for="(row, i) in allRecords"
                  :key="row.id"
                  class="transition-colors"
                  :class="[
                    i % 2 === 0 ? 'bg-(--bg-card)' : 'bg-(--bg-main)/40',
                    row.source === 'extra' ? 'bg-purple-50' : ''
                  ]"
                >
                  <td class="px-2.5 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">
                    {{ i + 1 }}
                    <span v-if="row.source === 'extra'" class="text-red-500 font-bold">*</span>
                  </td>
                  <td class="px-3 py-2 font-medium text-(--text-main) border-r border-(--border-soft)">{{ row.name }}</td>
                  <td class="px-3 py-2 text-center font-mono text-(--text-muted) border-r border-(--border-soft)">{{ row.account_no || '-' }}</td>
                  <td class="px-2.5 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ row.status_label || '-' }}</td>
                  <td class="px-2.5 py-2 text-center border-r border-(--border-soft)">{{ row.gender || '-' }}</td>
                  <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.gaji) }}</td>
                  <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.total_gaji) }}</td>
                  <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--danger)/80">{{ fmtNum(row.bpjs_tk) }}</td>
                  <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--danger)/80">{{ fmtNum(row.bpjs_ks) }}</td>
                  <td class="px-3 py-2 text-right font-mono text-(--text-main)">{{ fmtNum(row.uang_makan) }}</td>
                </tr>
              </tbody>

              <!-- Total Row -->
              <tfoot>
                <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                  <td class="px-2.5 py-2.5 text-right border-r border-(--border-soft)" colspan="5">
                    <span class="text-(--primary) uppercase">TOTAL</span>
                  </td>
                  <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.gaji, true) }}</td>
                  <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.total_gaji, true) }}</td>
                  <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft) text-(--danger)">{{ fmtNum(totals.bpjs_tk, true) }}</td>
                  <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft) text-(--danger)">{{ fmtNum(totals.bpjs_ks, true) }}</td>
                  <td class="px-3 py-2.5 text-right font-mono">{{ fmtNum(totals.uang_makan, true) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </template>
    </template>

    <!-- Settings Modal -->
    <ReportSettingsModal
      v-if="showSettings"
      report-type="rekap-gaji"
      report-label="Rekap Gaji"
      :available-groups="availableGroups"
      @close="showSettings = false"
      @saved="onSettingsSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import ReportSettingsModal from '@/Components/ReportPage/ReportSettingsModal.vue'

const { get } = useApi()

// ─── State ───
const loading = ref(false)
const exporting = ref(false)
const payPeriodId = ref('')
const payPeriods = ref([])
const records = ref([])
const showSettings = ref(false)
const availableGroups = ref([])
const selectedGroups = ref([])

// ─── Computed ───

const selectedPeriod = computed(() => {
  return payPeriods.value.find(p => p.id === payPeriodId.value) || null
})

const periodLabel = computed(() => {
  if (!selectedPeriod.value) return ''
  return selectedPeriod.value.name?.toUpperCase() || ''
})

const subPeriodLabel = computed(() => {
  const p = selectedPeriod.value
  if (!p || !p.date_start || !p.date_end) return ''
  const start = new Date(p.date_start)
  const end = new Date(p.date_end)
  const months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER']
  return `${start.getDate()} ${months[start.getMonth()]} - ${end.getDate()} ${months[end.getMonth()]}'${String(end.getFullYear()).slice(-2)}`
})

const allRecords = computed(() => records.value)

const totals = computed(() => {
  const sum = (key) => allRecords.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    gaji: sum('gaji'),
    total_gaji: sum('total_gaji'),
    bpjs_tk: sum('bpjs_tk'),
    bpjs_ks: sum('bpjs_ks'),
    uang_makan: sum('uang_makan'),
  }
})

// ─── Helpers ───

function fmtNum(v, force) {
  if (!force && (v === null || v === undefined || v === 0)) return '-'
  return new Intl.NumberFormat('id-ID').format(Math.round(v || 0))
}

// ─── API Calls ───

async function fetchPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    payPeriods.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Gagal fetch periods:', e)
  }
}

async function fetchGroups() {
  try {
    const res = await get('/api/v1/reports/rekap-gaji/groups')
    const groups = res.data || []
    availableGroups.value = groups.map(g => g.code).filter(Boolean).sort()
  } catch (e) {
    console.error('Gagal fetch groups:', e)
  }
}

async function fetchSavedConfig() {
  try {
    const res = await get('/api/v1/settings/report-configs/rekap-gaji')
    const data = res.data || res
    if (data.employee_groups?.length > 0) {
      selectedGroups.value = data.employee_groups
    }
  } catch (e) {
    selectedGroups.value = []
  }
}

async function fetchData() {
  if (!payPeriodId.value) {
    records.value = []
    return
  }

  loading.value = true
  try {
    const groupParam = selectedGroups.value.length > 0
      ? `&groups=${selectedGroups.value.join(',')}`
      : ''

    const res = await get(`/api/v1/reports/rekap-gaji?period_id=${payPeriodId.value}${groupParam}`)
    const data = res.data || []

    records.value = data.map(r => mapRecord(r))
  } catch (e) {
    console.error('Gagal fetch rekap gaji:', e)
    records.value = []
  } finally {
    loading.value = false
  }
}

function mapRecord(r) {
  return {
    id: r.id,
    name: r.name || '-',
    account_no: r.account_no || '-',
    status_label: r.status_label || '-',
    gender: r.gender || '-',
    gaji: parseFloat(r.gaji) || 0,
    total_gaji: parseFloat(r.total_gaji) || 0,
    bpjs_tk: parseFloat(r.bpjs_tk) || 0,
    bpjs_ks: parseFloat(r.bpjs_ks) || 0,
    uang_makan: parseFloat(r.uang_makan) || 0,
    source: r.source || null,
  }
}

function onSettingsSaved({ employee_groups }) {
  showSettings.value = false
  selectedGroups.value = employee_groups
  fetchData()
}

async function exportExcel() {
  if (!payPeriodId.value || allRecords.value.length === 0) return

  exporting.value = true
  try {
    const groupParam = selectedGroups.value.length > 0
      ? `&groups=${selectedGroups.value.join(',')}`
      : ''

    const token = localStorage.getItem('token')
    const url = `/api/v1/reports/rekap-gaji/export?period_id=${payPeriodId.value}${groupParam}`

    const response = await fetch(url, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      },
    })

    if (!response.ok) {
      const err = await response.json().catch(() => ({}))
      throw new Error(err.message || 'Export gagal')
    }

    const blob = await response.blob()

    // Ambil filename dari Content-Disposition
    let filename = 'Rekap_Gaji.xlsx'
    const disposition = response.headers.get('Content-Disposition')
    if (disposition) {
      const match = disposition.match(/filename\*?=(?:UTF-8'')?([^;]+)/)
      if (match) filename = decodeURIComponent(match[1].replace(/"/g, ''))
    }

    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = filename
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(a.href)
  } catch (e) {
    console.error('Gagal export:', e)
    alert('Gagal export Excel: ' + (e.message || 'Unknown error'))
  } finally {
    exporting.value = false
  }
}

// ─── Init ───
onMounted(async () => {
  await Promise.all([fetchPeriods(), fetchGroups()])
  await fetchSavedConfig()
})
</script>

<style scoped>
table {
  border-collapse: collapse;
}
</style>
