<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Rekap Kerja</h1>
        <p class="text-sm text-(--text-muted) mt-1">Rekapan kerja karyawan per periode (ALL IN, Bulanan Print, Uang Makan)</p>
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
      <div v-else-if="sections.all_in.length === 0 && sections.bulanan_print.length === 0 && sections.uang_makan.length === 0" class="text-center py-16 text-(--text-muted)">
        <i class="bx bx-file text-4xl block mb-3"></i>
        <p>Belum ada data rekap kerja untuk periode ini.</p>
        <p v-if="selectedGroups.length === 0" class="text-xs mt-1 text-amber-600">
          ⚠ Tidak ada group karyawan dipilih. Klik tombol <strong>Setting</strong> untuk memilih GRP.
        </p>
      </div>

      <!-- Data Sections -->
      <template v-else>
        <!-- Period Info -->
        <div v-if="selectedPeriod" class="px-4 py-3 rounded-md bg-(--primary)/5 border border-(--primary)/20 mb-6 flex flex-wrap items-center gap-3 text-sm shadow-sm">
          <span class="font-bold text-(--primary)">{{ selectedPeriod.name }}</span>
          <span class="text-(--text-muted)">{{ selectedPeriod.date_start }} - {{ selectedPeriod.date_end }}</span>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION A: ALL IN -->
        <!-- ============================================================ -->
        <div v-if="sections.all_in.length > 0" class="mb-8">
          <h2 class="text-base font-bold text-(--text-main) mb-3 px-1 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">A</span>
            ALL IN
          </h2>

          <div class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
              <table class="w-full text-xs border-collapse">
                <thead>
                  <tr class="bg-blue-50 border-b border-(--border-soft)">
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="width:40px">No</th>
                    <th class="px-3 py-2 text-left font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:140px">BAGIAN</th>
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="width:60px">JML</th>
                    <th class="px-3 py-2 text-center font-bold text-(--primary) bg-(--primary)/5" :colspan="4">
                      {{ periodLabel }}
                    </th>
                    <th class="px-3 py-2 text-center font-bold text-(--text-main) border-l border-(--border-soft)" style="min-width:110px">BPJS<br/><span class="text-[9px] font-normal text-(--text-muted)">(TK + KS)</span></th>
                  </tr>
                  <tr class="bg-blue-50/50 border-b-2 border-(--border-soft)">
                    <th class="px-2.5 py-1.5 border-r border-(--border-soft)"></th>
                    <th class="px-3 py-1.5 border-r border-(--border-soft)"></th>
                    <th class="px-2.5 py-1.5 border-r border-(--border-soft)"></th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[120px]">GAJI</th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[110px]">LEMBUR</th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[110px]">TOTAL</th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) whitespace-nowrap min-w-[110px]"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-(--border-soft)">
                  <tr
                    v-for="(row, i) in sections.all_in"
                    :key="'a-'+i"
                    class="transition-colors"
                    :class="i % 2 === 0 ? 'bg-(--bg-card)' : 'bg-(--bg-main)/40'"
                  >
                    <td class="px-2.5 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ i + 1 }}</td>
                    <td class="px-3 py-2 font-medium text-(--text-main) border-r border-(--border-soft)">{{ row.bagian }}</td>
                    <td class="px-2.5 py-2 text-center font-medium border-r border-(--border-soft)">{{ row.jml }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.gaji) }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.lembur) }}</td>
                    <td class="px-3 py-2 text-right font-mono font-bold border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.total) }}</td>
                    <td class="px-3 py-2 text-right font-mono text-(--danger)/80">{{ fmtNum(row.bpjs) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                    <td class="px-2.5 py-2.5 text-right border-r border-(--border-soft)" colspan="3">
                      <span class="text-(--primary) uppercase">TOTAL BULANAN</span>
                    </td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.all_in.gaji, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.all_in.lembur, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.all_in.total, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono text-(--danger)">{{ fmtNum(totals.all_in.bpjs, true) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION B: BULANAN PRINT -->
        <!-- ============================================================ -->
        <div v-if="sections.bulanan_print.length > 0" class="mb-8">
          <h2 class="text-base font-bold text-(--text-main) mb-3 px-1 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs font-bold">B</span>
            BULANAN PRINT
          </h2>

          <div class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
              <table class="w-full text-xs border-collapse">
                <thead>
                  <tr class="bg-green-50 border-b border-(--border-soft)">
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="width:40px">No</th>
                    <th class="px-3 py-2 text-left font-bold text-(--text-main) border-r border-(--border-soft)" style="min-width:140px">BAGIAN</th>
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" style="width:60px">JML</th>
                    <th class="px-3 py-2 text-center font-bold text-(--primary) bg-(--primary)/5" :colspan="4">
                      {{ periodLabel }}
                    </th>
                    <th class="px-3 py-2 text-center font-bold text-(--text-main) border-l border-(--border-soft)" style="min-width:110px">BPJS<br/><span class="text-[9px] font-normal text-(--text-muted)">(TK + KS)</span></th>
                  </tr>
                  <tr class="bg-green-50/50 border-b-2 border-(--border-soft)">
                    <th class="px-2.5 py-1.5 border-r border-(--border-soft)"></th>
                    <th class="px-3 py-1.5 border-r border-(--border-soft)"></th>
                    <th class="px-2.5 py-1.5 border-r border-(--border-soft)"></th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[120px]">GAJI</th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[110px]">LEMBUR</th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[110px]">TOTAL</th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) whitespace-nowrap min-w-[110px]"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-(--border-soft)">
                  <tr
                    v-for="(row, i) in sections.bulanan_print"
                    :key="'b-'+i"
                    class="transition-colors"
                    :class="i % 2 === 0 ? 'bg-(--bg-card)' : 'bg-(--bg-main)/40'"
                  >
                    <td class="px-2.5 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ i + 1 }}</td>
                    <td class="px-3 py-2 font-medium text-(--text-main) border-r border-(--border-soft)">{{ row.bagian }}</td>
                    <td class="px-2.5 py-2 text-center font-medium border-r border-(--border-soft)">{{ row.jml }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.gaji) }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.lembur) }}</td>
                    <td class="px-3 py-2 text-right font-mono font-bold border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.total) }}</td>
                    <td class="px-3 py-2 text-right font-mono text-(--danger)/80">{{ fmtNum(row.bpjs) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                    <td class="px-2.5 py-2.5 text-right border-r border-(--border-soft)" colspan="3">
                      <span class="text-(--primary) uppercase">TOTAL BULANAN</span>
                    </td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.bulanan_print.gaji, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.bulanan_print.lembur, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.bulanan_print.total, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono text-(--danger)">{{ fmtNum(totals.bulanan_print.bpjs, true) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION C: UANG MAKAN -->
        <!-- ============================================================ -->
        <div v-if="sections.uang_makan.length > 0" class="mb-8">
          <h2 class="text-base font-bold text-(--text-main) mb-3 px-1 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-xs font-bold">C</span>
            UANG MAKAN
          </h2>

          <div class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
              <table class="w-full text-xs border-collapse">
                <thead>
                  <tr class="bg-amber-50 border-b border-(--border-soft)">
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" rowspan="2" style="width:40px">No</th>
                    <th class="px-3 py-2 text-left font-bold text-(--text-main) border-r border-(--border-soft)" rowspan="2" style="min-width:140px">BAGIAN</th>
                    <th class="px-2.5 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)" rowspan="2" style="width:60px">JML</th>
                    <th class="px-3 py-1.5 text-center font-bold text-(--text-main) border-r border-(--border-soft)">UANG MAKAN</th>
                    <th class="px-3 py-1.5 text-center font-bold text-(--text-main) border-r border-(--border-soft)">LEMBUR SABTU</th>
                    <th class="px-3 py-1.5 text-center font-bold text-(--text-main) border-r border-(--border-soft)">LEMBUR MINGGU</th>
                    <th class="px-3 py-1.5 text-center font-bold text-(--text-main) border-r border-(--border-soft)">INSENTIF</th>
                    <th class="px-3 py-1.5 text-center font-bold text-(--text-main)">TOTAL</th>
                  </tr>
                  <tr class="bg-amber-50/50 border-b-2 border-(--border-soft)">
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[110px]"></th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[110px]"></th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[110px]"></th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) border-r border-(--border-soft) whitespace-nowrap min-w-[100px]"></th>
                    <th class="px-3 py-1.5 text-right font-semibold text-(--text-main) whitespace-nowrap min-w-[110px]"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-(--border-soft)">
                  <tr
                    v-for="(row, i) in sections.uang_makan"
                    :key="'c-'+i"
                    class="transition-colors"
                    :class="i % 2 === 0 ? 'bg-(--bg-card)' : 'bg-(--bg-main)/40'"
                  >
                    <td class="px-2.5 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ i + 1 }}</td>
                    <td class="px-3 py-2 font-medium text-(--text-main) border-r border-(--border-soft)">{{ row.bagian }}</td>
                    <td class="px-2.5 py-2 text-center font-medium border-r border-(--border-soft)">{{ row.jml }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.uang_makan) }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.lembur_sabtu) }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.lembur_minggu) }}</td>
                    <td class="px-3 py-2 text-right font-mono border-r border-(--border-soft) text-(--text-main)">{{ fmtNum(row.insentif) }}</td>
                    <td class="px-3 py-2 text-right font-mono font-bold text-(--text-main)">{{ fmtNum(row.total) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                    <td class="px-2.5 py-2.5 text-right border-r border-(--border-soft)" colspan="3">
                      <span class="text-(--primary) uppercase">TOTAL BULANAN</span>
                    </td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.uang_makan.uang_makan, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.uang_makan.lembur_sabtu, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.uang_makan.lembur_minggu, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono border-r border-(--border-soft)">{{ fmtNum(totals.uang_makan.insentif, true) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono">{{ fmtNum(totals.uang_makan.total, true) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </template>
    </template>

    <!-- Settings Modal -->
    <RekapKerjaSettingsModal
      v-if="showSettings"
      report-type="rekap-kerja"
      report-label="Rekap Kerja"
      :available-groups="availableGroups"
      :extra-employees="extraEmployees"
      :saved-extra-ids="selectedExtraIds"
      @close="showSettings = false"
      @saved="onSettingsSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import RekapKerjaSettingsModal from './RekapKerjaSettingsModal.vue'

const { get } = useApi()

// ─── State ───
const loading = ref(false)
const payPeriodId = ref('')
const payPeriods = ref([])
const showSettings = ref(false)
const availableGroups = ref([])
const selectedGroups = ref([])
const printGroups = ref([])
const extraEmployees = ref([])
const selectedExtraIds = ref([])
const sections = ref({
  all_in: [],
  bulanan_print: [],
  uang_makan: [],
})

// ─── Computed ───

const selectedPeriod = computed(() => {
  return payPeriods.value.find(p => p.id === payPeriodId.value) || null
})

const periodLabel = computed(() => {
  if (!selectedPeriod.value) return ''
  return selectedPeriod.value.name?.toUpperCase() || ''
})

const totals = computed(() => {
  const sumAllIn = (key) => sections.value.all_in.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  const sumPrint = (key) => sections.value.bulanan_print.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  const sumUm = (key) => sections.value.uang_makan.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)

  return {
    all_in: {
      gaji: sumAllIn('gaji'),
      lembur: sumAllIn('lembur'),
      total: sumAllIn('total'),
      bpjs: sumAllIn('bpjs'),
    },
    bulanan_print: {
      gaji: sumPrint('gaji'),
      lembur: sumPrint('lembur'),
      total: sumPrint('total'),
      bpjs: sumPrint('bpjs'),
    },
    uang_makan: {
      uang_makan: sumUm('uang_makan'),
      lembur_sabtu: sumUm('lembur_sabtu'),
      lembur_minggu: sumUm('lembur_minggu'),
      insentif: sumUm('insentif'),
      total: sumUm('total'),
    },
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
    const res = await get('/api/v1/reports/rekap-kerja/groups')
    const groups = res.data || []
    availableGroups.value = groups.map(g => g.code).filter(Boolean).sort()
  } catch (e) {
    console.error('Gagal fetch groups:', e)
  }
}

async function fetchSavedConfig() {
  try {
    const res = await get('/api/v1/settings/report-configs/rekap-kerja')
    const data = res.data || res
    if (data.employee_groups?.length > 0) {
      selectedGroups.value = data.employee_groups
    }
    if (data.config?.print_groups?.length > 0) {
      printGroups.value = data.config.print_groups
    }
    if (data.config?.extra_employee_ids?.length > 0) {
      selectedExtraIds.value = data.config.extra_employee_ids
    }
  } catch (e) {
    selectedGroups.value = []
    printGroups.value = []
    selectedExtraIds.value = []
  }
}

async function fetchExtraEmployees() {
  try {
    const res = await get('/api/v1/reports/rekap-kerja/extra-employees')
    extraEmployees.value = res.data || []
  } catch (e) {
    console.error('Gagal fetch extra employees:', e)
    extraEmployees.value = []
  }
}

async function fetchData() {
  if (!payPeriodId.value) {
    sections.value = { all_in: [], bulanan_print: [], uang_makan: [] }
    return
  }

  loading.value = true
  try {
    const groupParam = selectedGroups.value.length > 0
      ? `&groups=${selectedGroups.value.join(',')}`
      : ''

    const printParam = printGroups.value.length > 0
      ? `&print_groups=${printGroups.value.join(',')}`
      : ''

    const res = await get(`/api/v1/reports/rekap-kerja?period_id=${payPeriodId.value}${groupParam}${printParam}`)
    const data = res.data || res

    sections.value = {
      all_in: (data.sections?.all_in || []).map(mapRow),
      bulanan_print: (data.sections?.bulanan_print || []).map(mapRow),
      uang_makan: (data.sections?.uang_makan || []).map(mapRowUm),
    }
  } catch (e) {
    console.error('Gagal fetch rekap kerja:', e)
    sections.value = { all_in: [], bulanan_print: [], uang_makan: [] }
  } finally {
    loading.value = false
  }
}

function mapRow(r) {
  return {
    bagian: r.bagian || '-',
    jml: parseInt(r.jml) || 0,
    gaji: parseFloat(r.gaji) || 0,
    lembur: parseFloat(r.lembur) || 0,
    total: parseFloat(r.total) || 0,
    bpjs: parseFloat(r.bpjs) || 0,
  }
}

function mapRowUm(r) {
  return {
    bagian: r.bagian || '-',
    jml: parseInt(r.jml) || 0,
    uang_makan: parseFloat(r.uang_makan) || 0,
    lembur_sabtu: parseFloat(r.lembur_sabtu) || 0,
    lembur_minggu: parseFloat(r.lembur_minggu) || 0,
    insentif: parseFloat(r.insentif) || 0,
    total: parseFloat(r.total) || 0,
  }
}

function onSettingsSaved({ employee_groups, config }) {
  showSettings.value = false
  selectedGroups.value = employee_groups
  printGroups.value = config?.print_groups || []
  selectedExtraIds.value = config?.extra_employee_ids || []
  fetchData()
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
