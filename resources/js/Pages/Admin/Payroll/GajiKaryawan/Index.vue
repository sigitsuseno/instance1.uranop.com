<template>
  <div>
    <!-- Header Section -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Gaji Karyawan</h1>
        <p class="text-sm text-(--text-muted) mt-1">Rekap penggajian karyawan per periode</p>
      </div>
      <div class="flex items-center gap-3">
        <select
          v-model="selectedPeriodId"
          class="px-4 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent"
          @change="onPeriodChange"
        >
          <option value="">Pilih Periode</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">
            {{ p.name }}
          </option>
        </select>
        <BaseButton
          variant="primary"
          :disabled="!selectedPeriodId || generating"
          @click="handleGenerate"
        >
          <template #icon-left>
            <IconRefresh class="w-4 h-4" />
          </template>
          Generate
        </BaseButton>
        <BaseButton
          variant="success"
          :disabled="!selectedPeriodId"
          @click="handleExport"
        >
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Export
        </BaseButton>
      </div>
    </div>

    <!-- Period Info Banner + Tab Segment -->
    <div v-if="selectedPeriod" class="mb-4 px-4 py-3 rounded-lg bg-(--primary)/5 border border-(--primary)/20">
      <div class="flex flex-wrap items-center gap-4 text-sm">
        <span class="font-semibold text-(--primary)">{{ selectedPeriod.name }}</span>
        <span class="text-(--text-muted)">{{ selectedPeriod.date_range }}</span>
        <Badge :variant="selectedPeriod.is_split ? 'warning' : 'success'">
          {{ selectedPeriod.is_split ? 'Split Periode' : 'Periode Normal' }}
        </Badge>
        <span class="text-(--text-muted)">{{ records.length }} karyawan</span>
      </div>
      <div v-if="selectedPeriod.is_split" class="flex gap-2 mt-3 pt-3 border-t border-(--primary)/20">
        <button
          v-for="seg in ['A', 'B']"
          :key="seg"
          @click="switchSegment(seg)"
          class="px-4 py-1.5 rounded-md text-xs font-medium transition-colors"
          :class="activeSegment === seg
            ? 'bg-(--primary) text-white'
            : 'bg-(--bg-elevated) text-(--text-muted) hover:text-(--text-main)'"
        >
          Seg-{{ seg === 'A' ? '1' : '2' }}
        </button>
      </div>
    </div>

    <!-- Salary Table -->
    <BaseCard v-if="selectedPeriod" class="overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="bg-(--bg-elevated)">
              <th rowspan="2" class="sticky-col z-20 bg-(--bg-elevated) border border-(--border-soft) px-2 py-2 text-center font-semibold" style="left:0; width:36px; min-width:36px;">No</th>
              <th rowspan="2" class="sticky-col z-20 bg-(--bg-elevated) border border-(--border-soft) px-2 py-2 text-center font-semibold" style="left:36px; width:64px; min-width:64px;">ID No</th>
              <th rowspan="2" class="sticky-col-last z-20 bg-(--bg-elevated) border border-(--border-soft) px-2 py-2 text-center font-semibold" style="left:100px; min-width:140px;">NAMA</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">L/P</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap min-w-[100px]">BAGIAN</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap min-w-[100px]">JABATAN</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">THN MSK</th>

              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">PREMI</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">GAJI<br>POKOK</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">TJ.<br>MK</th>
              <th :colspan="5" class="border border-(--border-soft) px-2 py-2 text-center font-bold text-(--primary) bg-(--primary)/5 whitespace-nowrap">
                {{ periodLabel }}
              </th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">REVISI</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">TUNJA-<br>NGAN</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">PR.<br>HADIR</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">PBLT</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">TOTAL</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">BPJS<br>TK</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">BPJS<br>KES</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">BPJS<br>PEN</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">CASH<br>BON</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">PPH</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-bold text-(--primary) bg-(--primary)/5 whitespace-nowrap">TRIMA</th>
            </tr>
            <tr class="bg-(--bg-elevated)">
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">HK</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">LM</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">LBR<br>JAM</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">GAJI</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold whitespace-nowrap">LEMBUR</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="records.length === 0">
              <td :colspan="26" class="px-4 py-12 text-center text-(--text-muted)">
                Belum ada data gaji. Klik "Generate" untuk menghitung.
              </td>
            </tr>
            <tr
              v-for="(record, idx) in records"
              :key="record.id"
              class="hover:bg-(--bg-elevated)/70 transition-colors"
              :class="idx % 2 === 0 ? 'bg-(--bg-main)' : 'bg-(--bg-subtle)'"
            >
              <td class="sticky-col z-10 border border-(--border-soft) px-2 py-1.5 text-center" style="left:0; width:36px; min-width:36px;">{{ idx + 1 }}</td>
              <td class="sticky-col z-10 border border-(--border-soft) px-2 py-1.5 text-center font-mono text-xs" style="left:36px; width:64px; min-width:64px;">{{ record.employee_code }}</td>
              <td class="sticky-col-last z-10 border border-(--border-soft) px-2 py-1.5 font-medium" style="left:100px; min-width:140px;">{{ record.name }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-center">{{ record.gender }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5">{{ record.department }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5">{{ record.position }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-center">{{ record.join_year }}</td>

              <td class="border border-(--border-soft) px-2 py-1.5 text-right">{{ formatCurrency(record.premi) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right">{{ formatCurrency(record.gaji_pokok) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right">{{ formatCurrency(record.tj_masa_kerja) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-center">{{ record.hari_kerja }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-center">{{ record.lm }}j</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-center">{{ record.lembur_count }}j</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right">{{ formatCurrency(record.gaji) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right">{{ formatCurrency(record.upah_lembur) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right">{{ formatCurrency(record.revisi) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right">{{ formatCurrency(record.tunjangan) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right">{{ formatCurrency(record.premi_hadir) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right">{{ formatCurrency(record.pblt) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right font-semibold">{{ formatCurrency(record.total) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right text-(--danger)">{{ formatCurrency(record.bpjs_tk) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right text-(--danger)">{{ formatCurrency(record.bpjs_ks) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right text-(--danger)">{{ formatCurrency(record.bpjs_pen) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right text-(--danger)">{{ formatCurrency(record.cashbon) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right text-(--danger)">{{ formatCurrency(record.pph) }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-right font-bold text-(--primary)">{{ formatCurrency(record.gaji_bersih) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="records.length > 0">
            <tr class="bg-(--bg-elevated) font-bold text-xs">
              <td colspan="10" class="sticky-col-last z-10 border border-(--border-soft) px-2 py-2 text-right" style="left:0; min-width:140px;">TOTAL</td>
              <td class="border border-(--border-soft) px-2 py-2 text-center">{{ totals.hari_kerja }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-center">{{ totals.lm }}j</td>
              <td class="border border-(--border-soft) px-2 py-2 text-center">{{ totals.lembur_count }}j</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.gaji) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.upah_lembur) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.revisi) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.tunjangan) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.premi_hadir) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.pblt) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.total) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right text-(--danger)">{{ formatCurrency(totals.bpjs_tk) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right text-(--danger)">{{ formatCurrency(totals.bpjs_ks) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right text-(--danger)">{{ formatCurrency(totals.bpjs_pen) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right text-(--danger)">{{ formatCurrency(totals.cashbon) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right text-(--danger)">{{ formatCurrency(totals.pph) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right text-(--primary)">{{ formatCurrency(totals.gaji_bersih) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </BaseCard>

    <!-- Empty State -->
    <BaseCard v-else class="py-16">
      <div class="text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-(--bg-elevated) flex items-center justify-center">
          <IconFileInvoice class="w-8 h-8 text-(--text-muted)" />
        </div>
        <h3 class="text-lg font-semibold text-(--text-main) mb-2">Pilih Periode</h3>
        <p class="text-sm text-(--text-muted)">Pilih periode dari dropdown di atas untuk melihat rekap gaji karyawan</p>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseCard from '@/Components/BaseCard.vue'
import Badge from '@/Components/Badge.vue'
import { IconDownload, IconFileInvoice, IconRefresh } from '@/Components/Icons/index.js'
import { useApi } from '@/composables/useApi'

const { get, post } = useApi()

const periods = ref([])
const selectedPeriodId = ref('')
const records = ref([])
const generating = ref(false)
const activeSegment = ref(null)

const selectedPeriod = computed(() => {
  return periods.value.find(p => p.id === selectedPeriodId.value)
})

const periodLabel = computed(() => {
  if (!selectedPeriod.value) return ''
  const start = new Date(selectedPeriod.value.start_date)
  const end = new Date(selectedPeriod.value.end_date)
  const months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER']
  return `${start.getDate()} ${months[start.getMonth()]} - ${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear().toString().slice(-2)}`
})

const totals = computed(() => {
  const sum = (key) => records.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    hari_kerja: records.value.reduce((acc, r) => acc + (parseInt(r.hari_kerja) || 0), 0),
    lm: records.value.reduce((acc, r) => acc + (parseInt(r.lm) || 0), 0),
    lembur_count: records.value.reduce((acc, r) => acc + (parseInt(r.lembur_count) || 0), 0),
    gaji: sum('gaji'),
    upah_lembur: sum('upah_lembur'),
    revisi: sum('revisi'),
    tunjangan: sum('tunjangan'),
    premi_hadir: sum('premi_hadir'),
    pblt: sum('pblt'),
    total: sum('total'),
    bpjs_tk: sum('bpjs_tk'),
    bpjs_ks: sum('bpjs_ks'),
    bpjs_pen: sum('bpjs_pen'),
    cashbon: sum('cashbon'),
    pph: sum('pph'),
    gaji_bersih: sum('gaji_bersih'),
  }
})

function formatCurrency(value) {
  if (!value && value !== 0) return '-'
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value)
}

async function fetchPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    periods.value = res.data || []
  } catch (error) {
    console.error('Error fetching periods', error)
  }
}

async function fetchRecords() {
  if (!selectedPeriodId.value) {
    records.value = []
    return
  }
  try {
    let url = `/api/v1/payroll/gaji-karyawan?period_id=${selectedPeriodId.value}`
    if (activeSegment.value) {
      url += `&segment=${activeSegment.value}`
    }
    const res = await get(url)
    records.value = res.data || []
  } catch (error) {
    console.error('Error fetching records', error)
    records.value = []
  }
}

function switchSegment(seg) {
  activeSegment.value = seg
  fetchRecords()
}

function onPeriodChange() {
  const period = periods.value.find(p => p.id === selectedPeriodId.value)
  activeSegment.value = period?.is_split ? 'A' : null
  fetchRecords()
}

async function handleGenerate() {
  if (!selectedPeriodId.value) return
  generating.value = true
  try {
    await post(`/api/v1/attendance/recap/generate`, { period_id: selectedPeriodId.value })
    await fetchRecords()
  } catch (error) {
    console.error('Error generating', error)
  } finally {
    generating.value = false
  }
}

function handleExport() {
  alert('Export coming soon')
}

onMounted(() => {
  fetchPeriods()
})
</script>

<style scoped>
.sticky-col {
  position: sticky;
  background-color: inherit;
}
.sticky-col-last {
  position: sticky;
  box-shadow: 4px 0 8px -4px rgba(0, 0, 0, 0.12);
  background-color: inherit;
}
</style>
