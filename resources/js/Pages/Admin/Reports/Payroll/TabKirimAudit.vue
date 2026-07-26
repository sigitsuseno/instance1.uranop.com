<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Laporan Kirim Audit</h1>
        <p class="text-sm text-(--text-muted) mt-1">Daftar transfer gaji karyawan (Supervisor Breakdown)</p>
      </div>

      <!-- Actions Toolbar -->
      <div class="flex flex-wrap items-center gap-3">
        <select
          v-model="selectedPeriodId"
          class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer"
          @change="onPeriodChange"
        >
          <option value="">Pilih Periode</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">
            {{ p.name }}
          </option>
        </select>

        <template v-if="selectedPeriod?.is_split">
          <select v-model="activeSegment" @change="fetchRecords" class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer">
            <option value="A">Segment 1</option>
            <option value="B">Segment 2</option>
          </select>
        </template>

        <!-- Export Button -->
        <BaseButton
          variant="success"
          :disabled="!selectedPeriodId || (sectionAData.length === 0 && sectionBData.length === 0)"
          @click="exportExcel"
        >
          <template #icon-left>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
          </template>
          Export Excel
        </BaseButton>
      </div>
    </div>

    <template v-if="selectedPeriod">
      <!-- Section A -->
      <BaseCard padding="p-0" class="overflow-hidden border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
        <div class="px-4 py-3 border-b border-(--border-soft) flex items-center justify-between bg-(--bg-main)/30">
          <div>
            <h3 class="text-sm font-semibold text-(--text-main) uppercase">A. KARYAWAN ALLIN</h3>
            <p class="text-xs text-(--text-muted) mt-0.5">
              Total {{ sectionAData.length }} Data Transfer
            </p>
          </div>
        </div>

        <div class="overflow-x-auto max-h-[50vh]">
          <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-(--bg-elevated) sticky top-0 z-20">
              <tr>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Penerima</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Norek</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Singkatan Nama Bank</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Cabang</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-right font-bold text-(--text-muted) uppercase">Nominal</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Tanggal Transaksi</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Keterangan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-soft)">
              <tr v-if="sectionAData.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-(--text-muted)">
                  Tidak ada data.
                </td>
              </tr>
              <tr v-for="(item, idx) in sectionAData" :key="'a-' + idx" class="hover:bg-(--bg-elevated) transition-colors">
                <td class="px-4 py-2 text-(--text-main) font-medium">{{ item.bank_account_name && item.bank_account_name !== '-' ? item.bank_account_name : item.name }}</td>
                <td class="px-4 py-2 text-(--text-main) font-mono">{{ item.bank_account_number }}</td>
                <td class="px-4 py-2 text-(--text-main)">{{ item.bank_name }}</td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
                <td class="px-4 py-2 text-right font-bold text-blue-600">{{ formatNumber(item.gaji_bersih) }}</td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
              </tr>
            </tbody>
            <tfoot v-if="sectionAData.length > 0">
              <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                <td colspan="4" class="px-4 py-3 text-right">TOTAL A</td>
                <td class="px-4 py-3 text-right font-bold text-blue-600">{{ formatNumber(totalA) }}</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </BaseCard>

      <!-- Section B -->
      <BaseCard padding="p-0" class="overflow-hidden border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
        <div class="px-4 py-3 border-b border-(--border-soft) flex items-center justify-between bg-(--bg-main)/30">
          <div>
            <h3 class="text-sm font-semibold text-(--text-main) uppercase">B. KARYAWAN BULANAN PRINT</h3>
            <p class="text-xs text-(--text-muted) mt-0.5">
              Total {{ sectionBData.length }} Data Transfer
            </p>
          </div>
        </div>

        <div class="overflow-x-auto max-h-[50vh]">
          <table class="w-full text-sm whitespace-nowrap">
            <thead class="bg-(--bg-elevated) sticky top-0 z-20">
              <tr>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Penerima</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Norek</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Singkatan Nama Bank</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Cabang</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-right font-bold text-(--text-muted) uppercase">Nominal</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Tanggal Transaksi</th>
                <th class="border-b border-(--border-soft) px-4 py-3 text-left font-bold text-(--text-muted) uppercase">Keterangan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-soft)">
              <tr v-if="sectionBData.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-(--text-muted)">
                  Tidak ada data.
                </td>
              </tr>
              <tr v-for="(item, idx) in sectionBData" :key="'b-' + idx" class="hover:bg-(--bg-elevated) transition-colors">
                <td class="px-4 py-2 text-(--text-main) font-medium">{{ item.bank_account_name && item.bank_account_name !== '-' ? item.bank_account_name : item.name }}</td>
                <td class="px-4 py-2 text-(--text-main) font-mono">{{ item.bank_account_number }}</td>
                <td class="px-4 py-2 text-(--text-main)">{{ item.bank_name }}</td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
                <td class="px-4 py-2 text-right font-bold text-blue-600">{{ formatNumber(item.gaji_bersih) }}</td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
              </tr>
            </tbody>
            <tfoot v-if="sectionBData.length > 0">
              <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                <td colspan="4" class="px-4 py-3 text-right">TOTAL B</td>
                <td class="px-4 py-3 text-right font-bold text-blue-600">{{ formatNumber(totalB) }}</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </BaseCard>

      <!-- Grand Total -->
      <BaseCard v-if="sectionAData.length > 0 || sectionBData.length > 0" padding="p-4" class="border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
        <div class="flex items-center justify-end gap-6 text-sm">
          <div class="text-right">
            <span class="text-(--text-muted)">Subtotal A (All In)</span>
            <div class="font-bold text-blue-600">{{ formatNumber(totalA) }}</div>
          </div>
          <div class="text-right">
            <span class="text-(--text-muted)">Subtotal B (Print)</span>
            <div class="font-bold text-blue-600">{{ formatNumber(totalB) }}</div>
          </div>
          <div class="w-px h-10 bg-(--border-soft)"></div>
          <div class="text-right">
            <span class="text-(--text-muted) font-semibold">Grand Total</span>
            <div class="font-bold text-lg text-blue-700">{{ formatNumber(totalA + totalB) }}</div>
          </div>
        </div>
      </BaseCard>
    </template>

    <BaseCard v-else class="py-16 border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
      <div class="text-center space-y-4 max-w-sm mx-auto">
        <div class="w-16 h-16 mx-auto rounded-full bg-(--bg-elevated) flex items-center justify-center text-(--text-muted)">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="2" y1="10" x2="22" y2="10"></line>
          </svg>
        </div>
        <div>
          <h3 class="text-lg font-bold text-(--text-main)">Pilih Periode</h3>
          <p class="text-sm text-(--text-muted) mt-1">Pilih periode dari dropdown di atas untuk melihat daftar transfer bank</p>
        </div>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import * as XLSX from 'xlsx'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

const { get } = useApi()
const notification = useNotificationStore()

const periods = ref([])
const selectedPeriodId = ref('')
const records = ref([])
const activeSegment = ref(null)

const selectedPeriod = computed(() => {
  return periods.value.find(p => p.id === selectedPeriodId.value)
})

const sectionAData = computed(() => {
  return records.value.filter(r => r.section === 'A')
})

const sectionBData = computed(() => {
  return records.value.filter(r => r.section === 'B')
})

const totalA = computed(() => {
  return sectionAData.value.reduce((acc, curr) => acc + (parseFloat(curr.gaji_bersih) || 0), 0)
})

const totalB = computed(() => {
  return sectionBData.value.reduce((acc, curr) => acc + (parseFloat(curr.gaji_bersih) || 0), 0)
})

function formatNumber(value) {
  if (!value && value !== 0) return '-'
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)
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
    let url = `/api/v1/supervisor/payroll/breakdown?period_id=${selectedPeriodId.value}`
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

async function onPeriodChange() {
  const period = periods.value.find(p => p.id === selectedPeriodId.value)
  activeSegment.value = period?.is_split ? 'A' : null
  await fetchRecords()
}

function exportExcel() {
  if (sectionAData.value.length === 0 && sectionBData.value.length === 0) {
    notification.error('Tidak ada data untuk di-export')
    return
  }

  const dataToExport = [
    ...sectionAData.value.map(item => ({
      'SECTION': 'A',
      'PENERIMA': item.bank_account_name && item.bank_account_name !== '-' ? item.bank_account_name : item.name,
      'NOREK': item.bank_account_number,
      'SINGKATAN NAMA BANK': item.bank_name,
      'CABANG': '',
      'NOMINAL': item.gaji_bersih,
      'TANGGAL TRANSAKSI': '',
      'KETERANGAN': ''
    })),
    ...sectionBData.value.map(item => ({
      'SECTION': 'B',
      'PENERIMA': item.bank_account_name && item.bank_account_name !== '-' ? item.bank_account_name : item.name,
      'NOREK': item.bank_account_number,
      'SINGKATAN NAMA BANK': item.bank_name,
      'CABANG': '',
      'NOMINAL': item.gaji_bersih,
      'TANGGAL TRANSAKSI': '',
      'KETERANGAN': ''
    }))
  ]

  const totalRow = {
    'SECTION': '',
    'PENERIMA': '',
    'NOREK': '',
    'SINGKATAN NAMA BANK': '',
    'CABANG': '',
    'NOMINAL': totalA.value + totalB.value,
    'TANGGAL TRANSAKSI': '',
    'KETERANGAN': ''
  }

  dataToExport.push(totalRow)

  const worksheet = XLSX.utils.json_to_sheet(dataToExport)
  const workbook = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Laporan_Bank')

  const periodName = selectedPeriod.value?.name || 'Periode'
  const segName = activeSegment.value ? `_Segmen_${activeSegment.value}` : ''
  const fileName = `Kirim_Audit_${periodName}${segName}.xlsx`

  XLSX.writeFile(workbook, fileName)
}

onMounted(() => {
  fetchPeriods()
})
</script>
