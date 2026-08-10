<template>
  <ReportPageLayout
    title="Laporan Pajak PPh 21"
    description="Rekapitulasi PPh 21 bulanan per karyawan"
    @openSettings="showSettings = true"
  >
    <!-- Actions -->
    <template #actions>
      <button
        class="px-3 py-2 text-sm rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1.5"
        @click="exportExcel"
        :disabled="!rows || rows.length === 0"
      >
        <i class="bx bx-download text-lg"></i>
        <span class="hidden sm:inline">Export Excel</span>
      </button>
    </template>

    <!-- Filter Area -->
    <template #filter>
      <div class="flex flex-wrap gap-3 items-end">
        <div class="min-w-[120px]">
          <label class="block text-xs text-(--text-muted) mb-1 font-medium">Tahun</label>
          <select
            v-model="selectedYear"
            @change="onFilterChange"
            class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
          >
            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="min-w-[200px] flex-1">
          <label class="block text-xs text-(--text-muted) mb-1 font-medium">Cari Karyawan</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Nama atau NIK..."
            @keyup.enter="onFilterChange"
            class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
          />
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            @click="onFilterChange"
            class="px-4 py-2 bg-(--primary) text-white rounded-md hover:opacity-90 transition-colors text-sm flex items-center gap-1.5 shadow-sm"
          >
            <i class="bx bx-search text-base"></i> Filter
          </button>
          <button
            @click="resetFilter"
            class="px-4 py-2 bg-(--bg-elevated) border border-(--border-soft) text-(--text-muted) rounded-md hover:bg-(--border-soft) transition-colors text-sm"
          >
            <i class="bx bx-reset text-base"></i>
          </button>
        </div>
      </div>
    </template>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-16 text-(--text-muted)">
      <i class="bx bx-loader-alt animate-spin text-3xl block mb-3"></i>
      <p>Memuat data PPh 21...</p>
    </div>

    <template v-else>
      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 shadow-sm">
          <div class="text-xs text-(--text-muted) mb-1">Karyawan</div>
          <div class="text-2xl font-bold text-(--text-main)">{{ stats?.total_karyawan ?? 0 }}</div>
        </div>
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 shadow-sm">
          <div class="text-xs text-(--text-muted) mb-1">Total PPh Payroll (Potong Gaji)</div>
          <div class="text-lg font-bold text-(--danger)">{{ fmt(stats?.total_pph_payroll) }}</div>
        </div>
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 shadow-sm">
          <div class="text-xs text-(--text-muted) mb-1">Total PPh Laporan</div>
          <div class="text-lg font-bold text-(--primary)">{{ fmt(stats?.total_pph_report) }}</div>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="!rows || rows.length === 0"
        class="bg-(--bg-card) border border-(--border-soft) rounded-md p-16 text-center shadow-sm"
      >
        <i class="bx bx-receipt text-5xl text-(--text-muted) opacity-30 mb-4 block"></i>
        <p class="text-(--text-muted) text-sm">Tidak ada data PPh untuk tahun ini.</p>
      </div>

      <!-- Roster Table (PPh per bulan) -->
      <div v-else class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden shadow-sm mb-6">
        <!-- Legend -->
        <div class="px-5 py-3 border-b border-(--border-soft) flex flex-wrap items-center gap-4 text-xs bg-(--bg-elevated)">
          <span class="font-semibold text-(--text-muted)">Klik karyawan untuk melihat detail:</span>
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-green-100 border border-green-300"></span>
            <span class="text-(--text-muted)">DTP (Ditanggung Pemerintah)</span>
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-(--danger)/20 border border-(--danger)/40"></span>
            <span class="text-(--text-muted)">PPh Dipotong</span>
          </span>
        </div>

        <div class="max-h-[60vh] overflow-x-auto overflow-y-auto">
          <table class="w-full text-sm border-collapse">
            <thead class="bg-(--bg-elevated) border-b border-(--border-soft) sticky top-0 z-20">
              <tr class="text-xs">
                <th class="px-4 py-3 text-left font-medium text-(--text-muted) sticky top-0 left-0 bg-(--bg-elevated) z-30 min-w-[200px] border-r border-(--border-soft)">
                  Karyawan
                </th>
                <th
                  v-for="(name, idx) in monthNames"
                  :key="idx"
                  class="px-2 py-3 text-right font-medium text-(--text-muted) whitespace-nowrap min-w-[72px] border-r border-(--border-soft)"
                >
                  {{ name }}
                </th>
                <th class="px-3 py-3 text-right font-semibold text-(--primary) whitespace-nowrap min-w-[100px]">
                  Total
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-soft)">
              <tr
                v-for="row in rows"
                :key="row.employee_id"
                class="hover:bg-(--bg-elevated) transition-colors cursor-pointer"
                :class="{ 'ring-1 ring-(--primary) ring-inset bg-(--primary)/5': row.employee_id === selectedEmployeeId }"
                @click="selectEmployee(row)"
              >
                <!-- Employee -->
                <td
                  class="px-4 py-2.5 sticky left-0 bg-(--bg-card) z-10 border-r border-(--border-soft)"
                  :class="{ 'bg-(--primary)/5': row.employee_id === selectedEmployeeId }"
                >
                  <div class="font-medium text-(--text-main) text-sm">{{ row.employee_name }}</div>
                  <div class="text-xs text-(--text-muted) flex items-center gap-1.5">
                    {{ row.employee_code }}
                    <span class="px-1 py-0.5 rounded bg-(--bg-elevated) font-mono text-[10px]">{{ row.ptkp_status }}</span>
                    <i v-if="!row.has_npwp" class="bx bx-id-card text-(--danger)" title="Non-NPWP"></i>
                  </div>
                </td>
                <!-- Monthly PPh -->
                <td
                  v-for="m in 12"
                  :key="m"
                  class="px-2 py-2.5 text-right whitespace-nowrap border-r border-(--border-soft) text-xs"
                  :class="{
                    'bg-green-50 text-green-700': row.monthly_pph[m]?.is_dtp && row.monthly_pph[m]?.has_data,
                    'text-(--text-main)': !row.monthly_pph[m]?.is_dtp && row.monthly_pph[m]?.has_data,
                  }"
                >
                  <template v-if="row.monthly_pph[m]?.has_data">
                    <span v-if="row.monthly_pph[m]?.is_dtp" class="font-medium" title="DTP">DTP</span>
                    <span v-else class="font-medium">{{ fmtShort(row.monthly_pph[m]?.report) }}</span>
                  </template>
                  <span v-else class="text-(--text-soft)">–</span>
                </td>
                <!-- Total -->
                <td class="px-3 py-2.5 text-right font-semibold text-(--primary) whitespace-nowrap">
                  {{ fmt(row.total_pph_report) }}
                </td>
              </tr>
            </tbody>
            <!-- Footer total -->
            <tfoot class="border-t-2 border-(--border-soft) bg-(--bg-elevated) text-xs font-bold sticky bottom-0 z-20">
              <tr>
                <td class="px-4 py-3 text-right text-(--text-muted) sticky bottom-0 left-0 bg-(--bg-elevated) z-30 border-r border-(--border-soft)">TOTAL</td>
                <td v-for="m in 12" :key="m" class="px-2 py-3 text-right text-(--text-main) border-r border-(--border-soft)">
                  {{ fmtShort(monthTotal(m)) }}
                </td>
                <td class="px-3 py-3 text-right text-(--primary)">{{ fmt(stats?.total_pph_payroll) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Detail Area: appears when employee is selected -->
      <div v-if="hasDetail" class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-6">
        <!-- Breakdown Table (spans 2 cols) -->
        <div class="xl:col-span-2 bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden shadow-sm">
          <div class="px-5 py-3 border-b border-(--border-soft) bg-(--bg-elevated) flex items-center justify-between">
            <div>
              <span class="font-semibold text-(--text-main) text-sm">{{ detailEmployee.employee_name }}</span>
              <span class="ml-2 text-xs text-(--text-muted)">{{ detailEmployee.employee_code }}</span>
              <span class="ml-2 px-1.5 py-0.5 rounded bg-(--bg-card) font-mono text-[10px] text-(--text-muted)">{{ detailEmployee.ptkp_status }}</span>
              <i v-if="!detailEmployee.has_npwp" class="bx bx-id-card text-(--danger) ml-2" title="Non-NPWP"></i>
            </div>
            <span class="text-xs text-(--text-muted)">Rincian Bulanan {{ filters?.year }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
              <thead class="sticky top-0 z-10">
                <tr class="bg-(--bg-elevated) border-b border-(--border-soft)">
                  <th class="px-3 py-2.5 text-left font-medium text-(--text-muted) sticky left-0 bg-(--bg-elevated) z-20 min-w-[220px] border-r border-(--border-soft)">
                    Komponen
                  </th>
                  <th
                    v-for="(name, idx) in monthNames"
                    :key="idx"
                    class="px-2 py-2.5 text-right font-medium text-(--text-muted) whitespace-nowrap min-w-[80px] border-r border-(--border-soft)"
                  >
                    {{ name }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <!-- PENGHASILAN -->
                <tr class="bg-orange-500/15">
                  <td :colspan="13" class="px-3 py-1.5 font-bold text-[11px] tracking-wide text-orange-700 sticky left-0">
                    PENGHASILAN
                  </td>
                </tr>
                <tr v-for="br in breakdownIncome" :key="br.key" class="border-b border-(--border-soft) hover:bg-(--bg-elevated)" :class="{ 'font-semibold bg-(--bg-elevated)/40': br.bold }">
                  <td class="px-3 py-2 text-(--text-main) sticky left-0 bg-(--bg-card) z-10 border-r border-(--border-soft)" :class="{ 'bg-(--bg-elevated)/60 font-semibold': br.bold }">{{ br.label }}</td>
                  <td v-for="m in 12" :key="m" class="px-2 py-2 text-right whitespace-nowrap border-r border-(--border-soft)" :class="{ 'text-(--text-muted)': !monthlyDetail[m]?.has_data }">
                    <template v-if="monthlyDetail[m]?.has_data">{{ fmt(monthlyDetail[m]?.[br.field]) }}</template>
                    <span v-else class="text-(--text-soft)">–</span>
                  </td>
                </tr>

                <!-- PAJAK -->
                <tr class="bg-blue-500/15">
                  <td :colspan="13" class="px-3 py-1.5 font-bold text-[11px] tracking-wide text-blue-700 sticky left-0">PAJAK</td>
                </tr>
                <tr v-for="br in breakdownTax" :key="br.key" class="border-b border-(--border-soft) hover:bg-(--bg-elevated)" :class="{ 'font-semibold bg-(--bg-elevated)/40': br.bold }">
                  <td class="px-3 py-2 text-(--text-main) sticky left-0 bg-(--bg-card) z-10 border-r border-(--border-soft)" :class="{ 'bg-(--bg-elevated)/60 font-semibold': br.bold }">{{ br.label }}</td>
                  <td v-for="m in 12" :key="m" class="px-2 py-2 text-right whitespace-nowrap border-r border-(--border-soft)" :class="{ 'text-(--text-muted)': !monthlyDetail[m]?.has_data, 'text-green-600': monthlyDetail[m]?.has_data && br.field === 'pph_report' && monthlyDetail[m]?.is_dtp }">
                    <template v-if="monthlyDetail[m]?.has_data">
                      <span v-if="br.isPct">{{ fmtPct(monthlyDetail[m]?.[br.field]) }}</span>
                      <span v-else-if="br.field === 'pph_report' && monthlyDetail[m]?.is_dtp" title="DTP">DTP</span>
                      <span v-else>{{ fmt(monthlyDetail[m]?.[br.field]) }}</span>
                    </template>
                    <span v-else class="text-(--text-soft)">–</span>
                  </td>
                </tr>

                <!-- PENGURANG -->
                <tr class="bg-green-500/15">
                  <td :colspan="13" class="px-3 py-1.5 font-bold text-[11px] tracking-wide text-green-700 sticky left-0">PENGURANG</td>
                </tr>
                <tr v-for="br in breakdownDeduction" :key="br.key" class="border-b border-(--border-soft) hover:bg-(--bg-elevated)" :class="{ 'font-semibold bg-(--bg-elevated)/40': br.bold }">
                  <td class="px-3 py-2 text-(--text-main) sticky left-0 bg-(--bg-card) z-10 border-r border-(--border-soft)" :class="{ 'bg-(--bg-elevated)/60 font-semibold': br.bold }">{{ br.label }}</td>
                  <td v-for="m in 12" :key="m" class="px-2 py-2 text-right whitespace-nowrap border-r border-(--border-soft)" :class="{ 'text-(--text-muted)': !monthlyDetail[m]?.has_data }">
                    <template v-if="monthlyDetail[m]?.has_data">{{ fmt(monthlyDetail[m]?.[br.field]) }}</template>
                    <span v-else class="text-(--text-soft)">–</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- PPh FINAL Panel -->
        <div class="xl:col-span-1 space-y-3">
          <div class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden shadow-sm">
            <div class="px-4 py-3 bg-orange-500/10 border-b border-orange-500/20 flex items-start justify-between gap-2">
              <div>
                <h3 class="font-semibold text-sm text-orange-700">Perhitungan Pajak PPh FINAL</h3>
                <p class="text-xs text-orange-600/70 mt-0.5">Desember — Tarif Progresif</p>
              </div>
              <span v-if="decIsComplete" class="text-[10px] px-1.5 py-0.5 rounded bg-green-100 text-green-700 font-semibold whitespace-nowrap">✓ Lengkap</span>
              <span v-else class="text-[10px] px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700 font-semibold whitespace-nowrap">{{ decMonthsCount }}/12 bln</span>
            </div>

            <!-- Warning belum lengkap -->
            <div v-if="hasDetail && !decIsComplete" class="flex items-center gap-2 mx-3 mt-3 px-3 py-2 rounded bg-yellow-50 border border-yellow-200 text-xs text-yellow-700">
              <i class="bx bx-error-circle flex-shrink-0"></i>
              <span>Data belum lengkap ({{ decMonthsCount }}/12 bulan). Nilai bersifat estimasi.</span>
            </div>

            <div class="text-xs">
              <!-- PENGHASILAN -->
              <div class="px-3 py-1.5 font-bold text-[11px] tracking-wide bg-yellow-400/80 text-yellow-900">PENGHASILAN</div>
              <div class="divide-y divide-(--border-soft)">
                <div class="flex justify-between px-3 py-2">
                  <span class="text-(--text-muted)">Gaji pokok setahun</span>
                  <span class="font-medium text-(--text-main)">{{ fmt(dec.gaji_pokok_setahun) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2">
                  <span class="text-(--text-muted)">Uang tunjangan setahun</span>
                  <span class="font-medium text-(--text-main)">{{ fmt(dec.tunjangan_setahun) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2">
                  <span class="text-(--text-muted)">Uang komisi/lembur/bonus/THR</span>
                  <span class="font-medium text-(--text-main)">{{ fmt(dec.lembur_bonus_thr) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2 bg-yellow-400/20 font-semibold">
                  <span class="text-(--text-main)">Penghasilan bruto/kotor setahun</span>
                  <span class="text-(--text-main)">{{ fmt(dec.bruto_setahun) }}</span>
                </div>
              </div>

              <!-- PENGURANG -->
              <div class="px-3 py-1.5 font-bold text-[11px] tracking-wide bg-green-500/25 text-green-800 mt-0.5">PENGURANG</div>
              <div class="divide-y divide-(--border-soft)">
                <div class="flex justify-between px-3 py-2">
                  <span class="text-(--text-muted)">Biaya jabatan</span>
                  <span class="font-medium text-(--text-main)">{{ fmt(dec.biaya_jabatan) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2">
                  <span class="text-(--text-muted)">JHT (Jaminan Hari Tua) tahunan</span>
                  <span class="font-medium text-(--text-main)">{{ fmt(dec.jht_tahunan) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2">
                  <span class="text-(--text-muted)">JP (Jaminan Pensiun) tahunan</span>
                  <span class="font-medium text-(--text-main)">{{ fmt(dec.jp_tahunan) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2 bg-green-500/15 font-semibold">
                  <span class="text-(--text-main)">Penghasilan netto/bersih tahunan</span>
                  <span class="text-(--text-main)">{{ fmt(dec.netto_setahun) }}</span>
                </div>
              </div>

              <!-- Perhitungan Pajak Setahun -->
              <div class="px-3 py-1.5 font-bold text-[11px] tracking-wide bg-green-400/40 text-green-900 mt-0.5">Perhitungan Pajak Setahun</div>
              <div class="divide-y divide-(--border-soft)">
                <div class="flex justify-between px-3 py-2">
                  <span class="text-(--text-muted)">PTKP (Penghasilan Tidak Kena Pajak)</span>
                  <span class="font-medium text-(--text-main)">{{ fmt(dec.ptkp_value) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2">
                  <span class="text-(--text-muted)">PKP (Penghasilan Kena Pajak)</span>
                  <span class="font-medium text-(--text-main)">{{ fmt(dec.pkp) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2 font-semibold bg-green-400/20">
                  <span class="text-(--text-main)">PPh 21 setahun</span>
                  <span class="text-(--text-main)">{{ fmt(dec.pph_setahun) }}</span>
                </div>
              </div>

              <!-- PPh Jan-Nov & Desember -->
              <div class="divide-y divide-(--border-soft) mt-2 border-t border-(--border-soft)">
                <div class="flex justify-between px-3 py-2">
                  <span class="text-(--text-muted)">PPh 21 yang dibayarkan bulan Jan-Nov</span>
                  <span class="font-medium text-(--text-main)">{{ fmt(dec.pph_jan_nov) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2 font-bold" :class="decIsComplete ? 'bg-orange-500/8' : 'bg-yellow-50'">
                  <span :class="decIsComplete ? 'text-orange-700' : 'text-yellow-700'">
                    PPh 21 yang dibayarkan bulan Desember
                    <span v-if="!decIsComplete && decMonthsCount > 0" class="font-normal text-[10px]"> (estimasi)</span>
                  </span>
                  <span :class="decIsComplete ? 'text-orange-700' : 'text-yellow-700'">{{ fmt(dec.pph_desember) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- NPWP / NIK box -->
          <div v-if="detailEmployee?.nik" class="bg-(--bg-card) border border-(--border-soft) rounded-md p-3 text-xs shadow-sm">
            <div class="text-(--text-muted) mb-1 font-medium">No. NPWP (NIK)</div>
            <div class="font-mono text-(--text-main) tracking-wider text-sm">{{ detailEmployee.nik }}</div>
          </div>
        </div>
      </div>
    </template>

    <!-- Settings Modal -->
    <ReportSettingsModal
      v-if="showSettings"
      report-type="pph"
      report-label="Laporan PPh 21"
      :available-groups="groupCodes"
      @close="showSettings = false"
      @saved="onSettingsSaved"
    >
      <template #config>
        <PphSettings />
      </template>
    </ReportSettingsModal>
  </ReportPageLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import ReportPageLayout from '@/Components/ReportPage/ReportPageLayout.vue'
import ReportSettingsModal from '@/Components/ReportPage/ReportSettingsModal.vue'
import PphSettings from '@/Components/ReportPage/settings/PphSettings.vue'

const { get } = useApi()

// State
const showSettings = ref(false)
const loading = ref(true)
const selectedYear = ref(new Date().getFullYear())
const searchQuery = ref('')
const selectedEmployeeId = ref(null)

const rows = ref([])
const stats = ref({ total_karyawan: 0, total_pph_payroll: 0, total_pph_report: 0 })
const monthNames = ref([])
const availableYears = ref([])
const filters = ref({ year: new Date().getFullYear(), search: '', employee_id: null })
const detailEmployee = ref(null)
const monthlyDetail = ref({})
const decemberBreakdown = ref({})
const selectedGroups = ref([])
const availableGroups = ref([])

// Computed
const groupCodes = computed(() => availableGroups.value.map(g => g.code))
const hasDetail = computed(() => !!detailEmployee.value)
const dec = computed(() => decemberBreakdown.value ?? {})
const decIsComplete = computed(() => dec.value?.is_complete ?? false)
const decMonthsCount = computed(() => dec.value?.months_with_data ?? 0)

// Breakdown row definitions
const breakdownIncome = [
  { key: 'income_gaji_pokok', field: 'gaji_pokok', label: 'Gaji pokok' },
  { key: 'income_tunjangan', field: 'tunjangan', label: 'Uang tunjangan' },
  { key: 'income_lembur_bonus', field: 'lembur_bonus_thr', label: 'Uang komisi/lembur/bonus/THR' },
  { key: 'income_jkk', field: 'jkk', label: 'JKK (Jaminan Kecelakaan Kerja)' },
  { key: 'income_jkm', field: 'jkm', label: 'JKM (Jaminan Kematian)' },
  { key: 'income_bpjs_kes', field: 'bpjs_kes_perusahaan', label: 'BPJS Kesehatan dari perusahaan' },
  { key: 'income_gross', field: 'gross_income', label: 'Penghasilan bruto', bold: true },
]

const breakdownTax = [
  { key: 'tax_rate', field: 'pph_rate', label: 'Tarif pajak', isPct: true },
  { key: 'tax_pph', field: 'pph_report', label: 'PPh 21', bold: true },
]

const breakdownDeduction = [
  { key: 'ded_jht', field: 'jht_karyawan', label: 'JHT (Jaminan Hari Tua)' },
  { key: 'ded_jp', field: 'jp_karyawan', label: 'JP (Jaminan Pensiun)' },
  { key: 'ded_bpjs_kes', field: 'bpjs_kes_karyawan', label: 'BPJS Kesehatan dari karyawan' },
  { key: 'ded_net', field: 'net_salary', label: 'Penghasilan netto', bold: true },
]

// Methods
function fmt(val) {
  if (!val && val !== 0) return '–'
  return 'Rp' + Number(val).toLocaleString('id-ID')
}

function fmtShort(val) {
  if (!val && val !== 0) return '–'
  const n = Number(val)
  if (n >= 1000000) return 'Rp' + (n / 1000000).toFixed(1) + 'jt'
  if (n >= 1000) return 'Rp' + (n / 1000).toFixed(0) + 'rb'
  return 'Rp' + n.toString()
}

function fmtPct(val) {
  if (!val && val !== 0) return '–'
  return Number(val).toFixed(2) + '%'
}

function monthTotal(m) {
  return (rows.value || []).reduce((s, r) => {
    const d = r.monthly_pph?.[m]
    // Bulan DTP tidak dipotong dari gaji, jadi tidak ikut dalam total payroll
    if (!d?.has_data || d.is_dtp) return s
    return s + (d.report || 0)
  }, 0)
}

async function fetchData() {
  loading.value = true
  try {
    const params = new URLSearchParams({ year: selectedYear.value })
    if (searchQuery.value) params.set('search', searchQuery.value)
    if (selectedEmployeeId.value) params.set('employee_id', selectedEmployeeId.value)
    if (selectedGroups.value.length > 0) params.set('groups', selectedGroups.value.join(','))

    const res = await get(`/api/v1/reports/pph?${params}`)
    rows.value = res.rows || []
    stats.value = res.stats || { total_karyawan: 0, total_pph_payroll: 0, total_pph_report: 0 }
    monthNames.value = res.monthNames || []
    availableYears.value = res.availableYears || []
    filters.value = res.filters || {}
    detailEmployee.value = res.detailEmployee || null
    monthlyDetail.value = res.monthlyDetail || {}
    decemberBreakdown.value = res.december_breakdown || {}
  } catch (e) {
    console.error('Gagal fetch PPh:', e)
    rows.value = []
  } finally {
    loading.value = false
  }
}

function onFilterChange() {
  selectedEmployeeId.value = null
  detailEmployee.value = null
  monthlyDetail.value = {}
  decemberBreakdown.value = {}
  fetchData()
}

function resetFilter() {
  searchQuery.value = ''
  selectedEmployeeId.value = null
  detailEmployee.value = null
  monthlyDetail.value = {}
  decemberBreakdown.value = {}
  fetchData()
}

function selectEmployee(row) {
  if (selectedEmployeeId.value === row.employee_id) {
    // Deselect
    selectedEmployeeId.value = null
    detailEmployee.value = null
    monthlyDetail.value = {}
    decemberBreakdown.value = {}
    fetchData()
    return
  }
  selectedEmployeeId.value = row.employee_id
  fetchData()
}

function exportExcel() {
  const params = new URLSearchParams({
    year: selectedYear.value,
    search: searchQuery.value,
    export: '1',
  })
  if (selectedGroups.value.length > 0) params.set('groups', selectedGroups.value.join(','))

  const token = localStorage.getItem('token')
  const url = `/api/v1/reports/pph?${params}`

  fetch(url, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    },
  })
    .then(res => {
      if (!res.ok) throw new Error('Export failed')
      return res.blob()
    })
    .then(blob => {
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `Laporan_PPh21_${selectedYear.value}.xlsx`
      document.body.appendChild(a)
      a.click()
      URL.revokeObjectURL(a.href)
      a.remove()
    })
    .catch(err => {
      console.error('Export gagal:', err)
      alert('Gagal mengekspor Excel. Pastikan data tersedia.')
    })
}

async function fetchGroups() {
  try {
    const res = await get('/api/v1/settings/employee-data/groups')
    const allGroups = res.data || []
    availableGroups.value = allGroups.map(g => ({ code: g.code, name: g.name }))
  } catch (err) {
    console.error('Gagal fetch groups:', err)
  }
}

async function fetchSavedConfig() {
  try {
    const res = await get('/api/v1/settings/report-configs/pph')
    const saved = res.data?.employee_groups || res.employee_groups || []
    if (saved.length > 0) selectedGroups.value = saved
  } catch (e) { /* no config yet */ }
}

function onSettingsSaved({ employee_groups }) {
  showSettings.value = false
  selectedGroups.value = employee_groups
  fetchData()
}

onMounted(() => {
  fetchGroups()
  fetchSavedConfig()
  fetchData()
})
</script>
