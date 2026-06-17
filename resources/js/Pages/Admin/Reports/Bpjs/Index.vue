<template>
  <ReportPageLayout
    title="Laporan BPJS"
    description="Rekapitulasi iuran BPJS Ketenagakerjaan &amp; Kesehatan per periode"
    @openSettings="showSettings = true"
  >
    <!-- Filter Area -->
    <template #filter>
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex-1 min-w-[200px] max-w-xs">
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Periode Penggajian</label>
          <select
            v-model="payPeriodId"
            @change="fetchData"
            class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm"
          >
            <option value="">Pilih Periode...</option>
            <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[200px] max-w-xs">
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Cari Karyawan</label>
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Nama atau kode..."
              class="w-full pl-10 pr-4 py-2 bg-(--bg-input) border border-(--border-soft) rounded-lg text-sm"
              @input="fetchData"
            />
            <span class="absolute left-3 top-2.5 text-(--text-muted)">🔍</span>
          </div>
        </div>
      </div>
    </template>

    <!-- Actions -->
    <template #actions>
      <button
        class="px-3 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5"
        @click="exportExcel"
        :disabled="records.length === 0"
      >
        <i class="bx bx-export text-lg"></i>
        <span class="hidden sm:inline">Export Excel</span>
      </button>
    </template>

    <!-- Pilih Periode Dulu -->
    <div v-if="!payPeriodId" class="text-center py-16 text-(--text-muted)">
      <i class="bx bx-calendar text-4xl block mb-3"></i>
      <p>Pilih periode penggajian terlebih dahulu</p>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
          <div class="text-xs text-(--text-muted) mb-1">Total Karyawan</div>
          <div class="text-xl font-bold text-(--text-main)">{{ records.length }}</div>
        </div>
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
          <div class="text-xs text-(--text-muted) mb-1">Beban Perusahaan</div>
          <div class="text-xl font-bold text-blue-600">{{ fmt(totals.employerGrandTotal) }}</div>
        </div>
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
          <div class="text-xs text-(--text-muted) mb-1">Potongan Karyawan</div>
          <div class="text-xl font-bold text-orange-600">{{ fmt(totals.employeeGrandTotal) }}</div>
        </div>
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
          <div class="text-xs text-(--text-muted) mb-1">Total Iuran BPJS</div>
          <div class="text-xl font-bold text-green-600">{{ fmt(totals.grandTotal) }}</div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-8 text-(--text-muted)">
        <i class="bx bx-loader-alt animate-spin text-2xl"></i>
        <p class="mt-2">Memuat data...</p>
      </div>

      <!-- Tabel Detail -->
      <BaseCard v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border-collapse">
            <thead>
              <!-- Header Row 1: Group utama -->
              <tr class="text-[10px] uppercase text-(--text-muted) tracking-wider border-b border-(--border-soft)">
                <th rowspan="2" class="p-2 font-medium text-left sticky left-0 bg-(--bg-card) z-10 w-8">No</th>
                <th rowspan="2" class="p-2 font-medium text-left sticky left-[32px] bg-(--bg-card) z-10 min-w-[160px]">Nama</th>
                <th rowspan="2" class="p-2 font-medium text-left min-w-[70px]">NIP</th>
                <th rowspan="2" class="p-2 font-medium text-left min-w-[60px]">Group</th>
                <th rowspan="2" class="p-2 font-medium text-center min-w-[60px]">Thn<br/>Masuk</th>
                <th rowspan="2" class="p-2 font-medium text-center min-w-[45px]">MK<br/>(bln)</th>
                <th rowspan="2" class="p-2 font-medium text-right min-w-[100px]">Gaji<br/>Pokok</th>
                <th rowspan="2" class="p-2 font-medium text-right min-w-[80px]">Tunj.<br/>MK</th>
                <th rowspan="2" class="p-2 font-medium text-right min-w-[90px]">Tun-<br/>jangan</th>
                <th rowspan="2" class="p-2 font-medium text-right min-w-[100px]">Dasar<br/>BPJS</th>
                <th rowspan="2" class="p-2 font-medium text-left min-w-[130px]">KPJ BPJS<br/>TK</th>
                <th rowspan="2" class="p-2 font-medium text-left min-w-[130px]">KPJ BPJS<br/>Kesehatan</th>
                <!-- Perusahaan: 6 kolom -->
                <th colspan="6" class="p-1.5 text-center bg-blue-50/40 text-blue-700 border-b border-blue-200">
                  BPJS Dibayar Perusahaan
                </th>
                <!-- Karyawan: 4 kolom -->
                <th colspan="4" class="p-1.5 text-center bg-orange-50/40 text-orange-700 border-b border-orange-200">
                  BPJS Dibayar Karyawan
                </th>
                <th rowspan="2" class="p-2 font-medium text-right min-w-[100px]">Total<br/>BPJS</th>
              </tr>
              <!-- Header Row 2: Sub-kolom -->
              <tr class="text-[10px] text-(--text-soft) border-b border-(--border-soft)">
                <!-- Perusahaan sub -->
                <th class="px-1.5 py-1 text-right bg-blue-50/20 min-w-[75px]">JHT</th>
                <th class="px-1.5 py-1 text-right bg-blue-50/20 min-w-[70px]">JKM</th>
                <th class="px-1.5 py-1 text-right bg-blue-50/20 min-w-[70px]">JKK</th>
                <th class="px-1.5 py-1 text-right bg-blue-50/30 font-medium min-w-[75px]">Total TK</th>
                <th class="px-1.5 py-1 text-right bg-blue-50/20 min-w-[75px]">Pensiun</th>
                <th class="px-1.5 py-1 text-right bg-blue-50/20 min-w-[85px]">Kesehatan</th>
                <!-- Karyawan sub -->
                <th class="px-1.5 py-1 text-right bg-orange-50/20 min-w-[75px]">JHT</th>
                <th class="px-1.5 py-1 text-right bg-orange-50/20 min-w-[75px]">Pensiun</th>
                <th class="px-1.5 py-1 text-right bg-orange-50/20 min-w-[85px]">Kesehatan</th>
                <th class="px-1.5 py-1 text-right bg-orange-50/30 font-medium min-w-[80px]">Total Kry</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-soft)">
              <tr v-if="records.length === 0">
                <td colspan="23" class="p-8 text-center text-(--text-muted)">Belum ada data iuran untuk periode ini.</td>
              </tr>
              <tr v-for="(r, i) in records" :key="r.id" class="hover:bg-(--bg-hover)">
                <!-- Info Karyawan -->
                <td class="p-2 text-center text-xs text-(--text-muted) sticky left-0 bg-(--bg-card) z-10">{{ i + 1 }}</td>
                <td class="p-2 sticky left-[32px] bg-(--bg-card) z-10">
                  <div class="font-medium text-xs">{{ r.employee?.name }}</div>
                </td>
                <td class="p-2 text-xs text-(--text-muted)">{{ r.employee?.nip || r.employee?.employee_code }}</td>
                <td class="p-2 text-xs">{{ r.group }}</td>
                <td class="p-2 text-xs text-center">{{ r.join_year }}</td>
                <td class="p-2 text-xs text-center">{{ r.masa_kerja }}</td>
                <td class="p-2 text-right text-xs">{{ fmt(r.gaji_pokok) }}</td>
                <td class="p-2 text-right text-xs">{{ fmt(r.tj_masa_kerja) }}</td>
                <td class="p-2 text-right text-xs">{{ fmt(r.tunjangan) }}</td>
                <td class="p-2 text-right text-xs font-medium">{{ fmt(r.bpjs_base_salary) }}</td>
                <td class="p-2 text-xs font-mono text-[10px]">{{ r.kpj_tk || '-' }}</td>
                <td class="p-2 text-xs font-mono text-[10px]">{{ r.kpj_ks || '-' }}</td>
                <!-- Perusahaan -->
                <td class="p-1.5 text-right text-blue-700 bg-blue-50/10 text-xs">{{ fmt(r.employer_jht) }}</td>
                <td class="p-1.5 text-right text-blue-700 bg-blue-50/10 text-xs">{{ fmt(r.employer_jkm) }}</td>
                <td class="p-1.5 text-right text-blue-700 bg-blue-50/10 text-xs">{{ fmt(r.employer_jkk) }}</td>
                <td class="p-1.5 text-right text-blue-800 bg-blue-50/20 font-medium text-xs">{{ fmt(r.employer_tk_total) }}</td>
                <td class="p-1.5 text-right text-blue-700 bg-blue-50/10 text-xs">{{ fmt(r.employer_jp) }}</td>
                <td class="p-1.5 text-right text-blue-700 bg-blue-50/10 text-xs">{{ fmt(r.employer_kesehatan) }}</td>
                <!-- Karyawan -->
                <td class="p-1.5 text-right text-orange-700 bg-orange-50/10 text-xs">{{ fmt(r.employee_jht) }}</td>
                <td class="p-1.5 text-right text-orange-700 bg-orange-50/10 text-xs">{{ fmt(r.employee_jp) }}</td>
                <td class="p-1.5 text-right text-orange-700 bg-orange-50/10 text-xs">{{ fmt(r.employee_kesehatan) }}</td>
                <td class="p-1.5 text-right text-orange-800 bg-orange-50/20 font-medium text-xs">{{ fmt(r.employee_total) }}</td>
                <!-- Grand Total -->
                <td class="p-2 text-right font-semibold text-xs">{{ fmt(r.grand_total) }}</td>
              </tr>
            </tbody>
            <!-- Total Row -->
            <tfoot v-if="records.length > 0">
              <tr class="border-t-2 border-(--border-soft) bg-(--bg-elevated) font-semibold">
                <td class="p-2 sticky left-0 bg-(--bg-elevated) z-10" colspan="2">TOTAL</td>
                <td class="p-2"></td>
                <td class="p-2"></td>
                <td class="p-2"></td>
                <td class="p-2"></td>
                <td class="p-2 text-right text-xs">{{ fmt(totals.gaji_pokok) }}</td>
                <td class="p-2 text-right text-xs">{{ fmt(totals.tj_masa_kerja) }}</td>
                <td class="p-2 text-right text-xs">{{ fmt(totals.tunjangan) }}</td>
                <td class="p-2 text-right text-xs">{{ fmt(totals.bpjs_base_salary) }}</td>
                <td class="p-2"></td>
                <td class="p-2"></td>
                <!-- Perusahaan -->
                <td class="p-1.5 text-right text-blue-800 text-xs">{{ fmt(totals.employer_jht) }}</td>
                <td class="p-1.5 text-right text-blue-800 text-xs">{{ fmt(totals.employer_jkm) }}</td>
                <td class="p-1.5 text-right text-blue-800 text-xs">{{ fmt(totals.employer_jkk) }}</td>
                <td class="p-1.5 text-right text-blue-900 text-xs">{{ fmt(totals.employer_tk_total) }}</td>
                <td class="p-1.5 text-right text-blue-800 text-xs">{{ fmt(totals.employer_jp) }}</td>
                <td class="p-1.5 text-right text-blue-800 text-xs">{{ fmt(totals.employer_kesehatan) }}</td>
                <!-- Karyawan -->
                <td class="p-1.5 text-right text-orange-800 text-xs">{{ fmt(totals.employee_jht) }}</td>
                <td class="p-1.5 text-right text-orange-800 text-xs">{{ fmt(totals.employee_jp) }}</td>
                <td class="p-1.5 text-right text-orange-800 text-xs">{{ fmt(totals.employee_kesehatan) }}</td>
                <td class="p-1.5 text-right text-orange-900 text-xs">{{ fmt(totals.employee_total) }}</td>
                <td class="p-2 text-right text-xs">{{ fmt(totals.grandTotal) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </BaseCard>
    </template>

    <!-- Settings Modal -->
    <ReportSettingsModal
      v-if="showSettings"
      report-type="bpjs"
      report-label="Laporan BPJS"
      :available-groups="groupCodes"
      @close="showSettings = false"
      @saved="onSettingsSaved"
    >
      <template #config>
        <BpjsSettings />
      </template>
    </ReportSettingsModal>
  </ReportPageLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import ReportPageLayout from '@/Components/ReportPage/ReportPageLayout.vue'
import ReportSettingsModal from '@/Components/ReportPage/ReportSettingsModal.vue'
import BpjsSettings from '@/Components/ReportPage/settings/BpjsSettings.vue'
import BaseCard from '@/Components/BaseCard.vue'

const { get } = useApi()

// State
const showSettings = ref(false)
const loading = ref(false)
const payPeriodId = ref('')
const payPeriods = ref([])
const search = ref('')
const records = ref([])
const selectedGroups = ref([])
const availableGroups = ref([])

// Computed
const groupCodes = computed(() => availableGroups.value.map(g => g.code))

const totals = computed(() => {
  const r = records.value
  const sum = (key) => r.reduce((s, x) => s + toNum(x[key]), 0)

  const emp_jht = sum('employer_jht')
  const emp_jkm = sum('employer_jkm')
  const emp_jkk = sum('employer_jkk')
  const emp_jp = sum('employer_jp')
  const emp_ks = sum('employer_kesehatan')
  const emp_tk_total = emp_jht + emp_jkm + emp_jkk
  const emp_grand = emp_tk_total + emp_jp + emp_ks

  const ee_jht = sum('employee_jht')
  const ee_jp = sum('employee_jp')
  const ee_ks = sum('employee_kesehatan')
  const ee_total = ee_jht + ee_jp + ee_ks

  return {
    gaji_pokok: sum('gaji_pokok'),
    tj_masa_kerja: sum('tj_masa_kerja'),
    tunjangan: sum('tunjangan'),
    bpjs_base_salary: sum('bpjs_base_salary'),
    employer_jht: emp_jht,
    employer_jkm: emp_jkm,
    employer_jkk: emp_jkk,
    employer_tk_total: emp_tk_total,
    employer_jp: emp_jp,
    employer_kesehatan: emp_ks,
    employerGrandTotal: emp_grand,
    employee_jht: ee_jht,
    employee_jp: ee_jp,
    employee_kesehatan: ee_ks,
    employee_total: ee_total,
    employeeGrandTotal: ee_total,
    grandTotal: emp_grand + ee_total,
  }
})

// Methods
function fmt(v) {
  if (v === null || v === undefined || v === 0) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v)
}

function toNum(v) {
  return parseFloat(v) || 0
}

async function fetchData() {
  if (!payPeriodId.value) {
    records.value = []
    return
  }
  loading.value = true
  try {
    const params = new URLSearchParams({ pay_period_id: payPeriodId.value })
    if (selectedGroups.value.length > 0) {
      params.set('groups', selectedGroups.value.join(','))
    }
    const res = await get(`/api/v1/bpjs/reports?${params}`)
    records.value = res.data?.details || res.details || []
  } catch (e) {
    console.error('Gagal fetch data BPJS:', e)
    records.value = []
  } finally {
    loading.value = false
  }
}

async function fetchPayPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    payPeriods.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Gagal fetch periods:', e)
  }
}

async function fetchGroups() {
  try {
    const res = await get('/api/v1/settings/employee-data/groups')
    const allGroups = res.data || []
    // Filter hanya group dengan label "BPJS GROUP"
    availableGroups.value = allGroups
      .filter(g => g.group_label === 'BPJS GROUP')
      .map(g => ({ code: g.code, name: g.name }))
  } catch (err) {
    console.error('Gagal fetch groups:', err)
  }
}

async function fetchSavedConfig() {
  try {
    const res = await get('/api/v1/settings/report-configs/bpjs')
    const saved = res.data?.employee_groups || res.employee_groups || []
    if (saved.length > 0) selectedGroups.value = saved
  } catch (e) { /* no config yet */ }
}

function onSettingsSaved({ employee_groups }) {
  showSettings.value = false
  selectedGroups.value = employee_groups
  fetchData()
}

function exportExcel() {
  alert('Export Excel akan tersedia setelah backend selesai.')
}

onMounted(() => {
  fetchPayPeriods()
  fetchGroups()
  fetchSavedConfig()
})
</script>
