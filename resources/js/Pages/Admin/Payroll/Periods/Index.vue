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
        <!-- ⚠️ NONAKTIF 2026-08-07 — tombol Generate (recap/generate) flow LAMA.
             Halaman ini legacy (route payroll/periods, judul "Gaji Karyawan (Legacy)") — dipakai untuk
             buat/list periode & preview, tapi GENERATE tidak dipakai lagi.
             recap/generate TIDAK handle is_split (selalu 1 segmen utuh, menghapus record split) dan
             digantikan alur Payroll → Gaji Karyawan: Simpan → Finalisasi → Lock/Unlock.
             TODO: hapus tombol & fungsi handleGenerate saat perbaikan besar (lih. logic_payroll_baru.md §6/#7). -->
        <BaseButton
          variant="primary"
          :disabled="true"
          @click="null"
          title="Nonaktif — digantikan alur Simpan/Finalisasi/Lock di menu Payroll → Gaji Karyawan"
          class="opacity-50 cursor-not-allowed"
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

    <!-- Period Info Banner -->
    <div v-if="selectedPeriod" class="mb-4 px-4 py-3 rounded-lg bg-(--primary)/5 border border-(--primary)/20">
      <div class="flex items-center gap-4 text-sm">
        <span class="font-semibold text-(--primary)">{{ selectedPeriod.name }}</span>
        <span class="text-(--text-muted)">{{ selectedPeriod.date_range }}</span>
        <Badge :variant="selectedPeriod.is_split ? 'warning' : 'success'">
          {{ selectedPeriod.is_split ? 'Split Periode' : 'Periode Normal' }}
        </Badge>
        <span class="ml-auto text-(--text-muted)">
          {{ records.length }} karyawan
        </span>
      </div>

      <!-- Tab Segment (only if split) -->
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
        <table class="w-full text-xs border-collapse">
          <thead>
            <tr class="bg-(--bg-elevated)">
              <th rowspan="2" class="sticky left-0 z-10 bg-(--bg-elevated) border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-10">No</th>
              <th rowspan="2" class="sticky left-10 z-10 bg-(--bg-elevated) border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-16">ID No</th>
              <th rowspan="2" class="sticky left-26 z-10 bg-(--bg-elevated) border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) min-w-[140px]">NAMA</th>
              <th colspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main)">BAGIAN / JABATAN</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-10">L/P</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-16">THN MASUK<br>KARYAWAN</th>

              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-20">PREMI</th>
              <th :colspan="7" class="border border-(--border-soft) px-2 py-2 text-center font-bold text-(--primary) bg-(--primary)/5">
                {{ periodLabel }}
              </th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-20">REVISI</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-20">TUNJANGAN</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-20">PREMI<br>HADIR</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-16">PBLT</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-24">TOTAL</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-24">BPJS<br>TENAGA KERJA</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-24">BPJS<br>KESEHATAN</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-20">BPJS<br>PENSIUN</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-16">CASHBON</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-16">PPH</th>
              <th rowspan="2" class="border border-(--border-soft) px-2 py-2 text-center font-bold text-(--primary) bg-(--primary)/5 w-24">TOTAL<br>TERIMA</th>
            </tr>
            <tr class="bg-(--bg-elevated)">
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-20">GAJI POKOK<br>{{ periodYear }}</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-20">TJ. MASA<br>KERJA</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-12">HK</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-12">LM</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-16">LBR JAM</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-20">GAJI</th>
              <th class="border border-(--border-soft) px-2 py-2 text-center font-semibold text-(--text-main) w-16">LEMBUR</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(record, idx) in records"
              :key="record.id"
              class="hover:bg-(--bg-elevated) transition-colors"
              :class="idx % 2 === 0 ? 'bg-(--bg-main)' : 'bg-(--bg-subtle)'"
            >
              <td class="sticky left-0 z-10 border border-(--border-soft) px-2 py-1.5 text-center bg-inherit">{{ idx + 1 }}</td>
              <td class="sticky left-10 z-10 border border-(--border-soft) px-2 py-1.5 text-center bg-inherit font-mono text-xs">{{ record.employee_code }}</td>
              <td class="sticky left-26 z-10 border border-(--border-soft) px-2 py-1.5 bg-inherit font-medium">{{ record.name }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-center">{{ record.department }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-center">{{ record.position }}</td>
              <td class="border border-(--border-soft) px-2 py-1.5 text-center">{{ record.gender }}</td>
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
              <td class="border border-(--border-soft) px-2 py-1.5 text-right font-bold text-(--primary) bg-(--primary)/5">{{ formatCurrency(record.gaji_bersih) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="records.length > 0">
            <tr class="bg-(--bg-elevated) font-bold">
              <td colspan="7" class="border border-(--border-soft) px-2 py-2 text-right">TOTAL</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.premi) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.gaji_pokok) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right">{{ formatCurrency(totals.tj_masa_kerja) }}</td>
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
        <h3 class="text-lg font-semibold text-(--text-main) mb-2">Belum Ada Periode Dipilih</h3>
        <p class="text-sm text-(--text-muted) mb-4">Pilih periode dari dropdown di atas untuk melihat rekap gaji karyawan</p>
        <BaseButton variant="primary" @click="openCreateModal">
          <template #icon-left>
            <IconPlus class="w-4 h-4" />
          </template>
          Buat Periode Baru
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Create Period Modal -->
    <BaseModal :show="showCreateModal" title="Buat Periode Baru" @close="showCreateModal = false">
      <div class="space-y-4">
        <TextInput v-model="form.name" label="Nama Periode" placeholder="Contoh: Juni 2026" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.start_date" label="Tanggal Mulai" type="date" />
          <TextInput v-model="form.end_date" label="Tanggal Selesai" type="date" />
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" id="is_split" v-model="form.is_split" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
          <label for="is_split" class="text-sm font-medium text-(--text-main)">Split Periode</label>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showCreateModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleCreate" :disabled="loading">Simpan</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseCard from '@/Components/BaseCard.vue'
import BaseModal from '@/Components/BaseModal.vue'
import Badge from '@/Components/Badge.vue'
import TextInput from '@/Components/TextInput.vue'
import { IconPlus, IconDownload, IconFileInvoice, IconRefresh } from '@/Components/Icons/index.js'
import { useApi } from '@/composables/useApi'

const { get, post } = useApi()

const periods = ref([])
const selectedPeriodId = ref('')
const records = ref([])
const loading = ref(false)
const generating = ref(false)
const showCreateModal = ref(false)
const activeSegment = ref(null)

const form = ref({
  name: '',
  start_date: '',
  end_date: '',
  is_split: false,
})

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

const periodYear = computed(() => {
  if (!selectedPeriod.value) return ''
  return new Date(selectedPeriod.value.end_date).getFullYear().toString().slice(-2)
})

const totals = computed(() => {
  const sum = (key) => records.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    premi: sum('premi'),
    gaji_pokok: sum('gaji_pokok'),
    tj_masa_kerja: sum('tj_masa_kerja'),
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
  // Auto-select Seg-1 if split, else clear segment
  const period = periods.value.find(p => p.id === selectedPeriodId.value)
  activeSegment.value = period?.is_split ? 'A' : null
  fetchRecords()
}

// ⚠️ DEPRECATED / NONAKTIF 2026-08-07 — flow lama, tombol di-disable.
// recap/save hanya menulis att_records (snapshot on-the-fly) — TIDAK menyentuh pay_records.
// Alur resmi: Payroll → Gaji Karyawan: Simpan → Finalisasi → Lock/Unlock.
// TODO: hapus fungsi & tombol saat perbaikan besar (lih. logic_payroll_baru.md §6/#7).
async function handleGenerate() {
  if (!selectedPeriodId.value) return
  generating.value = true
  try {
    await post(`/api/v1/attendance/recap/save`, { period_id: selectedPeriodId.value })
    await fetchRecords()
  } catch (error) {
    console.error('Error generating', error)
  } finally {
    generating.value = false
  }
}

function handleExport() {
  if (!selectedPeriodId.value) return
  const params = new URLSearchParams({ period_id: selectedPeriodId.value, tab: 'all-in' })
  if (activeSegment.value) {
    params.append('segment', activeSegment.value)
  }
  window.open(`/api/v1/laporan/payroll/detail/export?${params.toString()}`, '_blank')
}

function openCreateModal() {
  form.value = { name: '', start_date: '', end_date: '', is_split: false }
  showCreateModal.value = true
}

async function handleCreate() {
  loading.value = true
  try {
    await post('/api/v1/payroll/periods', form.value)
    showCreateModal.value = false
    await fetchPeriods()
  } catch (error) {
    console.error('Error creating period', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchPeriods()
})
</script>
