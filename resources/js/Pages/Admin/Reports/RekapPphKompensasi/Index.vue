<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Rekap PPH & Kompensasi</h1>
        <p class="text-sm text-(--text-muted) mt-1">Rekapan PPH 21 dan kompensasi karyawan per periode penggajian</p>
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

        <!-- Export PPH -->
        <button
          class="h-10 px-4 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 transition-colors disabled:opacity-40"
          @click="exportPph"
          :disabled="!payPeriodId || pphData.length === 0 || exporting"
        >
          <i class="bx" :class="exporting === 'pph' ? 'bx-loader-alt animate-spin' : 'bx-export'"></i>
          <span class="hidden sm:inline">{{ exporting === 'pph' ? 'Mengekspor...' : 'Export PPH' }}</span>
        </button>

        <!-- Export Kompensasi -->
        <button
          class="h-10 px-4 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 transition-colors disabled:opacity-40"
          @click="exportKompensasi"
          :disabled="!payPeriodId || filteredKompensasiData.length === 0 || exporting"
        >
          <i class="bx" :class="exporting === 'kompensasi' ? 'bx-loader-alt animate-spin' : 'bx-export'"></i>
          <span class="hidden sm:inline">{{ exporting === 'kompensasi' ? 'Mengekspor...' : 'Export Kompensasi' }}</span>
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
      <div v-if="loading" class="text-center py-16 text-(--text-muted)">
        <i class="bx bx-loader-alt animate-spin text-3xl block mb-3"></i>
        <p>Memuat data...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="pphData.length === 0 && kompensasiData.length === 0" class="text-center py-16 text-(--text-muted)">
        <i class="bx bx-file text-4xl block mb-3"></i>
        <p>Belum ada data untuk periode ini.</p>
        <p v-if="selectedGroups.length === 0" class="text-xs mt-1 text-amber-600">
          ⚠ Tidak ada group karyawan dipilih. Klik tombol <strong>Setting</strong> untuk memilih GRP.
        </p>
      </div>

      <!-- Data Tables -->
      <template v-else>
        <!-- Period Info -->
        <div v-if="selectedPeriod" class="px-4 py-3 rounded-md bg-(--primary)/5 border border-(--primary)/20 mb-6 flex flex-wrap items-center gap-3 text-sm shadow-sm">
          <span class="font-bold text-(--primary)">{{ selectedPeriod.name }}</span>
          <span class="text-(--text-muted)">{{ selectedPeriod.date_start }} - {{ selectedPeriod.date_end }}</span>
          <span class="text-(--text-muted) ml-auto">{{ pphData.length }} Karyawan</span>
        </div>

        <!-- ═══════════════ SECTION A: PPH ═══════════════ -->
        <div class="mb-8">
          <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 bg-(--primary)/10 rounded-md flex items-center justify-center">
              <span class="text-(--primary) font-bold text-sm">A</span>
            </div>
            <h2 class="text-base font-semibold text-(--text-main)">REKAP PPH 21</h2>
          </div>

          <div class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
              <table class="w-full text-xs border-collapse">
                <thead>
                  <tr class="bg-pink-100 border-b border-(--border-soft)">
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="width:40px">No</th>
                    <th class="px-3 py-2 text-left font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:160px">NAMA BANK</th>
                    <th class="px-3 py-2 text-left font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:160px">PERHITUNGAN PPH<br/><span class="text-[9px] font-normal text-(--text-muted)">(NAMA KTP)</span></th>
                    <th class="px-3 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:140px">NIK</th>
                    <th class="px-3 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:160px">NIK TKU</th>
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="width:50px">L/P</th>
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="width:70px">STATUS</th>
                    <th class="px-3 py-2 text-right font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:120px">TOTAL GAJI</th>
                    <th class="px-3 py-2 text-right font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:100px">UM</th>
                    <th class="px-3 py-2 text-right font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:120px">BPJS TK<br/><span class="text-[9px] font-normal text-(--text-muted)">(JKK,JKM)</span></th>
                    <th class="px-3 py-2 text-right font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:120px">BPJS<br/>KESEHATAN</th>
                    <th class="px-3 py-2 text-right font-bold text-(--text-main)" style="min-width:100px">PPH</th>
                  </tr>
                </thead>

                <tbody class="divide-y divide-(--border-soft)">
                  <tr
                    v-for="(row, i) in pphData"
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
                    <td class="px-3 py-2 font-medium text-(--text-main) border-r border-(--border-soft)">
                      {{ row.name }}
                    </td>
                    <td class="px-3 py-2 text-(--text-main) border-r border-(--border-soft)">{{ row.name }}</td>
                    <td class="px-3 py-2 text-center font-mono text-(--text-muted) border-r border-(--border-soft)">{{ row.nik }}</td>
                    <td class="px-3 py-2 text-center font-mono text-(--text-muted) border-r border-(--border-soft)">{{ row.nik_tku }}</td>
                    <td class="px-2.5 py-2 text-center border-r border-(--border-soft)">{{ row.gender }}</td>
                    <td class="px-2.5 py-2 text-center border-r border-(--border-soft)">{{ row.status_label }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.total_gaji) }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.um) }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--danger)/80">{{ fmtNum(row.bpjs_tk) }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--danger)/80">{{ fmtNum(row.bpjs_ks) }}</td>
                    <td class="px-3 py-2 text-right font-mono text-(--text-main)">{{ fmtNum(row.pph) }}</td>
                  </tr>
                </tbody>

                <tfoot>
                  <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                    <td class="px-2.5 py-2.5 text-right border-r border-(--border-soft)" colspan="7">
                      <span class="text-(--primary) uppercase">TOTAL</span>
                    </td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(pphTotals.total_gaji, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(pphTotals.um, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft) text-(--danger)">{{ fmtNum(pphTotals.bpjs_tk, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft) text-(--danger)">{{ fmtNum(pphTotals.bpjs_ks, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono">{{ fmtNum(pphTotals.pph, true) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- ═══════════════ SECTION B: KOMPENSASI ═══════════════ -->
        <div>
          <div class="flex flex-wrap items-center gap-3 mb-4">
            <div class="w-8 h-8 bg-amber-100 rounded-md flex items-center justify-center">
              <span class="text-amber-600 font-bold text-sm">B</span>
            </div>
            <h2 class="text-base font-semibold text-(--text-main)">KOMPENSASI</h2>
            <div class="flex-1"></div>
            <div class="flex flex-wrap items-center gap-3">
              <span class="text-xs font-medium text-(--text-muted)">Tanggal bayar:</span>
              <label
                v-for="d in availablePaidDates"
                :key="d"
                class="flex items-center gap-1.5 text-xs cursor-pointer select-none"
              >
                <input
                  type="checkbox"
                  :value="d"
                  v-model="checkedPaidDates"
                  class="w-3.5 h-3.5 rounded text-amber-600 focus:ring-amber-500 border-(--border-soft)"
                />
                <span class="font-medium text-(--text-main)">{{ fmtDate(d) }}</span>
              </label>
            </div>
          </div>

          <div class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
              <table class="w-full text-xs border-collapse">
                <thead>
                  <tr class="bg-amber-50 border-b border-(--border-soft)">
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="width:40px">No</th>
                    <th class="px-3 py-2 text-left font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:160px">NAMA BANK</th>
                    <th class="px-3 py-2 text-left font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:160px">PERHITUNGAN PPH<br/><span class="text-[9px] font-normal text-(--text-muted)">(NAMA KTP)</span></th>
                    <th class="px-3 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:140px">NIK</th>
                    <th class="px-3 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:160px">NIK TKU</th>
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="width:70px">STATUS</th>
                    <th class="px-3 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:110px">TANGGAL<br/>BAYAR</th>
                    <th class="px-3 py-2 text-right font-bold text-(--text-main)" style="min-width:140px">TOTAL<br/>KOMPENSASI</th>
                  </tr>
                </thead>

                <tbody class="divide-y divide-(--border-soft)">
                  <tr v-if="filteredKompensasiData.length === 0">
                    <td colspan="8" class="px-3 py-6 text-center text-(--text-muted)">
                      Tidak ada data untuk tanggal pembayaran terpilih.
                    </td>
                  </tr>
                  <tr
                    v-for="(row, i) in filteredKompensasiData"
                    :key="row.id"
                    class="transition-colors"
                    :class="i % 2 === 0 ? 'bg-(--bg-card)' : 'bg-(--bg-main)/40'"
                  >
                    <td class="px-2.5 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ i + 1 }}</td>
                    <td class="px-3 py-2 font-medium text-(--text-main) border-r border-(--border-soft)">{{ row.name }}</td>
                    <td class="px-3 py-2 text-(--text-main) border-r border-(--border-soft)">{{ row.name }}</td>
                    <td class="px-3 py-2 text-center font-mono text-(--text-muted) border-r border-(--border-soft)">{{ row.nik }}</td>
                    <td class="px-3 py-2 text-center font-mono text-(--text-muted) border-r border-(--border-soft)">{{ row.nik_tku }}</td>
                    <td class="px-2.5 py-2 text-center border-r border-(--border-soft)">{{ row.status_label }}</td>
                    <td class="px-3 py-2 text-center font-mono text-(--text-muted) border-r border-(--border-soft)">{{ fmtDate(row.paid_at) }}</td>
                    <td class="px-3 py-2 text-right font-mono text-(--text-main)">{{ fmtNum(row.total_kompensasi) }}</td>
                  </tr>
                </tbody>

                <tfoot>
                  <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                    <td class="px-2.5 py-2.5 text-right border-r border-(--border-soft)" colspan="7">
                      <span class="text-(--primary) uppercase">TOTAL</span>
                    </td>
                    <td class="px-3 py-2.5 text-right font-mono">{{ fmtNum(kompensasiTotals.total_kompensasi, true) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </template>
    </template>

    <!-- Settings Modal -->
    <ReportSettingsModal
      v-if="showSettings"
      report-type="rekap-pph-kompensasi"
      report-label="Rekap PPH & Kompensasi"
      :available-groups="availableGroups"
      :extra-data="{ extraEmployees }"
      @close="showSettings = false"
      @saved="onSettingsSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import ReportSettingsModal from '@/Components/ReportPage/ReportSettingsModal.vue'

const { get } = useApi()

// ─── State ───
const loading = ref(false)
const exporting = ref(false)
const payPeriodId = ref('')
const payPeriods = ref([])
const showSettings = ref(false)
const availableGroups = ref([])
const selectedGroups = ref([])
const extraEmployees = ref([])
const selectedExtraIds = ref([])
const pphData = ref([])
const kompensasiData = ref([])

// ─── Computed ───
const selectedPeriod = computed(() => {
  return payPeriods.value.find(p => p.id === payPeriodId.value) || null
})

const pphTotals = computed(() => {
  const sum = (key) => pphData.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    total_gaji: sum('total_gaji'),
    um:         sum('um'),
    bpjs_tk:    sum('bpjs_tk'),
    bpjs_ks:    sum('bpjs_ks'),
    pph:        sum('pph'),
  }
})

const availablePaidDates = computed(() => {
  return [...new Set(kompensasiData.value.map(r => r.paid_at).filter(Boolean))].sort()
})

const checkedPaidDates = ref([])

watch(kompensasiData, (rows) => {
  checkedPaidDates.value = [...new Set(rows.map(r => r.paid_at).filter(Boolean))].sort()
}, { immediate: true })

const filteredKompensasiData = computed(() => {
  if (checkedPaidDates.value.length === 0) return []
  return kompensasiData.value.filter(r => checkedPaidDates.value.includes(r.paid_at))
})

const kompensasiTotals = computed(() => {
  const sum = (key) => filteredKompensasiData.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    total_kompensasi: sum('total_kompensasi'),
  }
})

// ─── Helpers ───
function fmtNum(v, force) {
  if (!force && (v === null || v === undefined || v === 0)) return '-'
  return new Intl.NumberFormat('id-ID').format(Math.round(v || 0))
}

function fmtNumDec(v) {
  if (v === null || v === undefined || v === 0) return '-'
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v)
}

function fmtDate(v) {
  if (!v) return '-'
  const [y, m, d] = String(v).split('-')
  return `${d}-${m}-${y}`
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
    const res = await get('/api/v1/reports/rekap-pph-kompensasi/groups')
    const groups = res.data?.data || res.data || []
    availableGroups.value = groups.map(g => g.code).filter(Boolean).sort()
  } catch (e) {
    console.error('Gagal fetch groups:', e)
  }
}

async function fetchExtraEmployees() {
  try {
    const res = await get('/api/v1/settings/extra-employees')
    extraEmployees.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Gagal fetch extra employees:', e)
    extraEmployees.value = []
  }
}

async function fetchSavedConfig() {
  try {
    const res = await get('/api/v1/settings/report-configs/rekap-pph-kompensasi')
    const data = res.data || res
    if (data.employee_groups?.length > 0) {
      selectedGroups.value = data.employee_groups
    }
    if (data.config?.extra_employee_ids?.length > 0) {
      selectedExtraIds.value = data.config.extra_employee_ids
    }
  } catch (e) {
    selectedGroups.value = []
    selectedExtraIds.value = []
  }
}

async function fetchData() {
  if (!payPeriodId.value) {
    pphData.value = []
    kompensasiData.value = []
    return
  }

  loading.value = true
  try {
    const groupParam = selectedGroups.value.length > 0
      ? `&groups=${selectedGroups.value.join(',')}`
      : ''

    const extraParam = `&extra_ids=${selectedExtraIds.value.join(',')}`

    const res = await get(`/api/v1/reports/rekap-pph-kompensasi?period_id=${payPeriodId.value}${groupParam}${extraParam}`)

    pphData.value = res.pph || []
    kompensasiData.value = res.kompensasi || []
  } catch (e) {
    console.error('Gagal fetch data:', e)
    pphData.value = []
    kompensasiData.value = []
  } finally {
    loading.value = false
  }
}

function onSettingsSaved({ employee_groups, config }) {
  showSettings.value = false
  selectedGroups.value = employee_groups
  selectedExtraIds.value = config?.extra_employee_ids || []
  fetchData()
}

// ─── Export ───
function exportUrl(type) {
  const params = new URLSearchParams()
  params.set('period_id', payPeriodId.value)
  if (selectedGroups.value.length > 0) {
    params.set('groups', selectedGroups.value.join(','))
  }
  if (selectedExtraIds.value.length > 0) {
    params.set('extra_ids', selectedExtraIds.value.join(','))
  }
  if (type === 'kompensasi' && checkedPaidDates.value.length > 0) {
    params.set('paid_dates', checkedPaidDates.value.join(','))
  }
  const endpoint = type === 'pph' ? 'export-pph' : 'export-kompensasi'
  return `/api/v1/reports/rekap-pph-kompensasi/${endpoint}?${params.toString()}`
}

function exportPph() {
  if (pphData.value.length === 0) return
  exporting.value = 'pph'
  window.open(exportUrl('pph'), '_blank')
  setTimeout(() => { exporting.value = false }, 500)
}

function exportKompensasi() {
  if (filteredKompensasiData.value.length === 0) return
  exporting.value = 'kompensasi'
  window.open(exportUrl('kompensasi'), '_blank')
  setTimeout(() => { exporting.value = false }, 500)
}

// ─── Init ───
onMounted(async () => {
  await Promise.all([fetchPeriods(), fetchGroups(), fetchExtraEmployees()])
  await fetchSavedConfig()
})
</script>

<style scoped>
table {
  border-collapse: collapse;
}
</style>
