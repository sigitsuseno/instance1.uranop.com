<!-- resources/js/Pages/Supervisor/Reports/Index.vue -->
<!-- Laporan Kehadiran Supervisor — A. Karyawan Allin | B. Karyawan Bulanan -->
<!-- Data dari tabel attendance_autologs -->

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../../../composables/useApi'

const route = useRoute()
const router = useRouter()
const { get } = useApi()

// ── State ────────────────────────────────────
const loading = ref(false)
const exporting = ref(false)
const periods = ref([])
const dates = ref([])
const sections = ref([])
const selectedPeriodId = ref(null)
const companyName = ref('')

// ── Computed ─────────────────────────────────
const selectedPeriodLabel = computed(() => {
  const p = periods.value.find(x => x.id === selectedPeriodId.value)
  return p ? `${p.name} (${p.start_date} → ${p.end_date})` : ''
})

const totalKaryawan = computed(() => {
  let total = 0
  sections.value.forEach(s => { total += s.data.length })
  return total
})

// ── Data Fetching ────────────────────────────
async function fetchData() {
  loading.value = true
  const params = new URLSearchParams()
  if (selectedPeriodId.value) {
    const p = periods.value.find(x => x.id === selectedPeriodId.value)
    if (p) {
      params.set('start_date', p.start_date)
      params.set('end_date', p.end_date)
    }
  }
  router.replace({ query: { period_id: selectedPeriodId.value || '' } })
  try {
    const res = await get(`/api/v1/supervisor/reports/absensi?${params}`)
    if (res.success) {
      periods.value = res.data.periods
      dates.value = res.data.dates
      sections.value = res.data.sections
      companyName.value = res.data.company
    }
  } catch (e) {
    console.error('Gagal ambil data laporan:', e)
  } finally {
    loading.value = false
  }
}

// ── Status Helpers ───────────────────────────
function getStatusClass(status) {
  switch (status) {
    case 'H':   return 'text-green-600 dark:text-green-400 font-bold'
    case 'L':   return 'text-blue-600 dark:text-blue-400 font-bold'
    case 'A':   return 'text-red-600 dark:text-red-400 font-bold'
    case 'C':   return 'text-amber-600 dark:text-amber-400 font-bold'
    case 'I':   return 'text-purple-600 dark:text-purple-400 font-bold'
    case 'S':   return 'text-orange-600 dark:text-orange-400 font-bold'
    case 'Off': return 'text-(--text-soft)'
    default:    return 'text-(--text-muted)'
  }
}

function formatJam(menit) {
  if (!menit || menit === 0) return '-'
  return (menit / 60).toFixed(1)
}

// ── Print ────────────────────────────────────
function handlePrint() {
  window.print()
}

// ── Export Excel ─────────────────────────────
function handleExport() {
  exporting.value = true
  const token = localStorage.getItem('token')
  const params = new URLSearchParams()
  if (selectedPeriodId.value) {
    const p = periods.value.find(x => x.id === selectedPeriodId.value)
    if (p) {
      params.set('start_date', p.start_date)
      params.set('end_date', p.end_date)
    }
  }
  const url = `/api/v1/supervisor/reports/absensi/export?${params.toString()}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => {
      if (!r.ok) throw new Error('Export failed')
      return r.blob()
    })
    .then(blob => {
      const link = document.createElement('a')
      link.href = URL.createObjectURL(blob)
      link.setAttribute('download', 'Laporan_Absensi_Allin_Bulanan.xlsx')
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(link.href)
    })
    .catch(err => {
      console.error('Gagal export:', err)
      alert('Gagal export Excel.')
    })
    .finally(() => {
      exporting.value = false
    })
}

// ── Init ─────────────────────────────────────
onMounted(() => {
  if (route.query.period_id) {
    selectedPeriodId.value = Number(route.query.period_id)
  }
  fetchData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h3 class="text-xl font-semibold text-(--text-main)">Laporan Kehadiran</h3>
        <p class="text-sm text-(--text-muted) mt-0.5">Rekapitulasi absensi harian — Allin & Bulanan</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          :disabled="!totalKaryawan || exporting"
          @click="handleExport"
          class="print-hide flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-sm font-medium transition-colors"
        >
          <i v-if="exporting" class="bx bx-loader-alt bx-spin text-lg"></i>
          <i v-else class="bx bx-download text-lg"></i>
          {{ exporting ? 'Mengexport...' : 'Export Excel' }}
        </button>
        <button
          :disabled="!totalKaryawan"
          @click="handlePrint"
          class="print-hide flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-sm font-medium transition-colors"
        >
          <i class="bx bx-printer text-lg"></i> Print
        </button>
      </div>
    </div>

    <!-- Filter Periode -->
    <div class="print-hide bg-(--bg-card) border border-(--border-soft) rounded-md p-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div class="min-w-[280px]">
          <label class="block text-xs font-semibold text-(--text-muted) mb-1.5 uppercase tracking-wide">Periode Payroll</label>
          <select
            v-model="selectedPeriodId"
            @change="fetchData"
            class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) outline-none"
          >
            <option :value="null" disabled>Pilih Periode</option>
            <option
              v-for="p in periods"
              :key="p.id"
              :value="p.id"
            >{{ p.name }} ({{ p.start_date }} → {{ p.end_date }})</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Info Bar -->
    <div class="flex flex-wrap items-center gap-2 text-sm text-(--text-muted)">
      <span>{{ totalKaryawan }} karyawan</span>
      <span class="text-(--text-soft)">·</span>
      <span>{{ dates.length }} hari</span>
      <template v-if="selectedPeriodLabel">
        <span class="text-(--text-soft)">·</span>
        <span>{{ selectedPeriodLabel }}</span>
      </template>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-4 text-xs text-(--text-muted)">
      <span><b class="text-green-600">H</b> Hadir</span>
      <span><b class="text-red-600">A</b> Absen</span>
      <span><b class="text-blue-600">L</b> Libur Masuk</span>
      <span><b class="text-(--text-soft)">Off</b> Libur</span>
      <span><b class="text-amber-600">C</b> Cuti</span>
      <span><b class="text-purple-600">I</b> Izin</span>
      <span><b class="text-orange-600">S</b> Sakit</span>
      <span class="text-(--text-soft)">|</span>
      <span class="text-(--text-soft)">Jam: OT (hari kerja) / LM (libur)</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-(--text-muted)">
      <i class="bx bx-loader-alt bx-spin text-2xl"></i>
      <p class="mt-2">Memuat data...</p>
    </div>

    <!-- Empty -->
    <div
      v-else-if="!totalKaryawan"
      class="bg-(--bg-card) border border-(--border-soft) rounded-md p-12 text-center"
    >
      <p class="text-(--text-muted) text-lg">Belum ada data untuk ditampilkan.</p>
      <p class="text-(--text-soft) text-sm mt-1">Pilih periode payroll terlebih dahulu.</p>
    </div>

    <!-- Sections -->
    <template v-else>
      <div v-for="section in sections" :key="section.code" class="mb-8">
        <!-- Section Header -->
        <div class="flex items-center gap-3 mb-3 px-1">
          <h3 class="text-base font-bold text-(--text-main)">{{ section.label }}</h3>
          <span class="text-xs text-(--text-muted) bg-(--bg-elevated) px-2 py-0.5 rounded-full">{{ section.data.length }} karyawan</span>
        </div>

        <!-- Kosong -->
        <div
          v-if="!section.data.length"
          class="bg-(--bg-card) border border-(--border-soft) rounded-md p-8 text-center mb-4"
        >
          <p class="text-(--text-muted) text-sm">Tidak ada data untuk kelompok ini.</p>
        </div>

        <!-- Table -->
        <div
          v-else
          class="bg-(--bg-card) border border-(--border-soft) rounded-md shadow-sm overflow-hidden"
        >
          <div class="overflow-x-auto max-h-[60vh]">
            <table class="min-w-max border-collapse">
              <thead class="sticky top-0 z-20">
                <!-- Tanggal row -->
                <tr class="bg-(--bg-elevated)">
                  <th class="sticky left-0 z-30 bg-(--bg-elevated) px-3 py-2.5 text-left text-xs font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.06)] w-20" rowspan="2">NIP</th>
                  <th class="sticky z-30 bg-(--bg-elevated) px-3 py-2.5 text-left text-xs font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.06)]" style="left:100px;min-width:180px;" rowspan="2">Nama</th>
                  <th
                    v-for="d in dates"
                    :key="d.date"
                    colspan="2"
                    class="px-1.5 py-2 text-center text-[10px] font-bold uppercase border-b border-l border-(--border-soft)"
                    :class="d.is_weekend ? 'bg-red-50 dark:bg-red-950/30 text-red-600' : 'text-(--text-muted)'"
                    :title="d.day_name + ', ' + d.date"
                  >{{ d.day }}<div class="text-[8px] font-normal mt-0.5 leading-none">{{ d.day_name }}</div></th>
                </tr>
                <!-- Sub-header: St | Jam -->
                <tr class="bg-(--bg-elevated)">
                  <template v-for="d in dates" :key="'sub-' + d.date">
                    <th
                      class="px-1 py-1 text-center text-[9px] font-semibold uppercase border-b border-l border-(--border-soft) text-(--text-muted)"
                      :class="d.is_weekend ? 'bg-red-50 dark:bg-red-950/30' : ''"
                    >St</th>
                    <th
                      class="px-1 py-1 text-center text-[9px] font-semibold uppercase border-b border-l border-(--border-soft) text-(--text-muted)"
                      :class="d.is_weekend ? 'bg-red-50 dark:bg-red-950/30' : ''"
                    >{{ d.is_weekend ? 'LM' : 'OT' }}</th>
                  </template>
                </tr>
              </thead>
              <tbody class="divide-y divide-(--border-soft)">
                <tr
                  v-for="row in section.data"
                  :key="row.id"
                  class="hover:bg-(--bg-elevated) transition-colors group"
                >
                  <td class="sticky left-0 z-10 bg-(--bg-card) group-hover:bg-(--bg-elevated) px-3 py-2 font-mono text-xs text-(--text-muted) border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.04)] transition-colors">{{ row.employee_code }}</td>
                  <td
                    class="sticky z-10 bg-(--bg-card) group-hover:bg-(--bg-elevated) px-3 py-2 text-sm text-(--text-main) border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.04)] transition-colors truncate font-medium"
                    style="left:100px"
                  >{{ row.name }}</td>
                  <template v-for="d in dates" :key="d.date">
                    <!-- Status -->
                    <td
                      class="px-1 py-2 text-center text-xs border-b border-l border-(--border-soft)"
                      :class="row.days[d.date]?.is_holiday ? 'bg-red-50/30 dark:bg-red-950/15' : ''"
                      :title="d.day_name + ', ' + d.date + (row.days[d.date]?.holiday_name ? ' — ' + row.days[d.date].holiday_name : '')"
                    >
                      <span :class="getStatusClass(row.days[d.date]?.status)">{{ row.days[d.date]?.status || '-' }}</span>
                    </td>
                    <!-- Jam Lembur -->
                    <td
                      class="px-1 py-2 text-center text-[10px] border-b border-l border-(--border-soft) text-(--text-soft)"
                      :class="row.days[d.date]?.is_holiday ? 'bg-red-50/30 dark:bg-red-950/15' : ''"
                    >
                      {{ d.is_weekend ? formatJam(row.days[d.date]?.lm) : formatJam(row.days[d.date]?.lembur) }}
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style>
@media print {
  aside, nav, header, .sidebar, .navbar, .topbar, .header-nav, footer {
    display: none !important;
  }
  .print-hide {
    display: none !important;
  }
  .ml-55, .ml-20 {
    margin-left: 0 !important;
  }
  body, .min-h-screen {
    padding: 0 !important;
    margin: 0 !important;
  }
  main, .p-6 {
    padding: 5mm !important;
    min-height: auto !important;
  }
  .overflow-x-auto, .overflow-y-auto {
    overflow: visible !important;
  }
  [class*="max-h-"] {
    max-height: none !important;
  }
  table {
    font-size: 7px !important;
  }
  .shadow-sm, [class*="shadow-"] {
    box-shadow: none !important;
  }
  .sticky {
    position: static !important;
  }
  table, tr, td, th {
    page-break-inside: auto;
  }
  tr {
    page-break-inside: avoid;
  }
}
</style>
