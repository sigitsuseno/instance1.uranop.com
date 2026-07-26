<template>
  <div class="space-y-8">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Laporan Kirim ALL</h1>
        <p class="text-sm text-(--text-muted) mt-1">Gabungan daftar transfer bank All In dan Print</p>
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
          :disabled="dataAllIn.length === 0 && dataPrint.length === 0"
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
      <!-- Section A: KARYAWAN ALLIN -->
      <BaseCard padding="p-0" class="overflow-hidden border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
        <div class="px-4 py-3 border-b border-(--border-soft) flex items-center justify-between bg-(--bg-main)/30">
          <div>
            <h3 class="text-sm font-semibold text-(--text-main) uppercase">A. KARYAWAN ALLIN</h3>
            <p class="text-xs text-(--text-muted) mt-0.5">
              Total {{ dataAllIn.length }} Data Transfer
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
              <tr v-if="dataAllIn.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-(--text-muted)">
                  Tidak ada data.
                </td>
              </tr>
              <tr v-for="(item, idx) in dataAllIn" :key="'allin-' + idx" class="hover:bg-(--bg-elevated) transition-colors">
                <td class="px-4 py-2 text-(--text-main) font-medium">{{ item.bank_account_name && item.bank_account_name !== '-' ? item.bank_account_name : item.name }}</td>
                <td class="px-4 py-2 text-(--text-main) font-mono">{{ item.bank_account_number }}</td>
                <td class="px-4 py-2 text-(--text-main)">{{ item.bank_name }}</td>
                <td class="px-4 py-2 text-(--text-muted)">{{ item.bank_cabang || '-' }}</td>
                <td class="px-4 py-2 text-right font-bold text-blue-600">{{ formatNumber(item.gaji_bersih) }}</td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
              </tr>
            </tbody>
            <tfoot v-if="dataAllIn.length > 0">
              <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                <td colspan="4" class="px-4 py-3 text-right">TOTAL A</td>
                <td class="px-4 py-3 text-right font-bold text-blue-600">{{ formatNumber(totalAllIn) }}</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </BaseCard>

      <!-- Section B: KARYAWAN BULANAN PRINT -->
      <BaseCard padding="p-0" class="overflow-hidden border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
        <div class="px-4 py-3 border-b border-(--border-soft) flex items-center justify-between bg-(--bg-main)/30">
          <div>
            <h3 class="text-sm font-semibold text-(--text-main) uppercase">B. KARYAWAN BULANAN PRINT</h3>
            <p class="text-xs text-(--text-muted) mt-0.5">
              Total {{ dataPrint.length }} Data Transfer
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
              <tr v-if="dataPrint.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-(--text-muted)">
                  Tidak ada data.
                </td>
              </tr>
              <tr v-for="(item, idx) in dataPrint" :key="'print-' + idx" class="hover:bg-(--bg-elevated) transition-colors">
                <td class="px-4 py-2 text-(--text-main) font-medium">{{ item.bank_account_name && item.bank_account_name !== '-' ? item.bank_account_name : item.name }}</td>
                <td class="px-4 py-2 text-(--text-main) font-mono">{{ item.bank_account_number }}</td>
                <td class="px-4 py-2 text-(--text-main)">{{ item.bank_name }}</td>
                <td class="px-4 py-2 text-(--text-muted)">{{ item.bank_cabang || '-' }}</td>
                <td class="px-4 py-2 text-right font-bold text-blue-600">{{ formatNumber(item.gaji_bersih) }}</td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
                <td class="px-4 py-2 text-(--text-muted)"></td>
              </tr>
            </tbody>
            <tfoot v-if="dataPrint.length > 0">
              <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                <td colspan="4" class="px-4 py-3 text-right">TOTAL B</td>
                <td class="px-4 py-3 text-right font-bold text-blue-600">{{ formatNumber(totalPrint) }}</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </BaseCard>

      <!-- Grand Total -->
      <BaseCard v-if="dataAllIn.length > 0 || dataPrint.length > 0" padding="p-4" class="border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
        <div class="flex items-center justify-end gap-6 text-sm">
          <div class="text-right">
            <span class="text-(--text-muted)">Subtotal A (All In)</span>
            <div class="font-bold text-blue-600">{{ formatNumber(totalAllIn) }}</div>
          </div>
          <div class="text-right">
            <span class="text-(--text-muted)">Subtotal B (Print)</span>
            <div class="font-bold text-blue-600">{{ formatNumber(totalPrint) }}</div>
          </div>
          <div class="w-px h-10 bg-(--border-soft)"></div>
          <div class="text-right">
            <span class="text-(--text-muted) font-semibold">Grand Total</span>
            <div class="font-bold text-lg text-blue-700">{{ formatNumber(totalAllIn + totalPrint) }}</div>
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
          <p class="text-sm text-(--text-muted) mt-1">Pilih periode dari dropdown di atas untuk melihat daftar transfer bank gabungan</p>
        </div>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'
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
const payrollConfig = ref({ sections: { A: ['GRP-ALLIN', 'GRP-SPR'], B: ['GRP-GD', 'GRP-SS', 'GRP-PS1'] } })

const selectedPeriod = computed(() => {
  return periods.value.find(p => p.id === selectedPeriodId.value)
})

const dataAllIn = computed(() => {
  const groups = payrollConfig.value?.sections?.A || ['GRP-ALLIN', 'GRP-SPR']
  return records.value.filter(r => (r.groups || []).some(g => groups.includes(g)))
})

const dataPrint = computed(() => {
  const groups = payrollConfig.value?.sections?.B || ['GRP-GD', 'GRP-SS', 'GRP-PS1']
  return records.value.filter(r => (r.groups || []).some(g => groups.includes(g)))
})

const totalAllIn = computed(() => {
  return dataAllIn.value.reduce((acc, curr) => acc + (parseFloat(curr.gaji_bersih) || 0), 0)
})

const totalPrint = computed(() => {
  return dataPrint.value.reduce((acc, curr) => acc + (parseFloat(curr.gaji_bersih) || 0), 0)
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

async function fetchPayrollConfig() {
  try {
    const res = await get('/api/v1/payroll/configs/gaji_karyawan')
    payrollConfig.value = res.config || { sections: { A: ['GRP-ALLIN', 'GRP-SPR'], B: ['GRP-GD', 'GRP-SS', 'GRP-PS1'] } }
  } catch (error) {
    console.error('Error fetching payroll config', error)
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

async function onPeriodChange() {
  const period = periods.value.find(p => p.id === selectedPeriodId.value)
  activeSegment.value = period?.is_split ? 'A' : null
  await fetchRecords()
}

function addSheetWithStyle(workbook, data, total, title, sheetName) {
  const ws = workbook.addWorksheet(sheetName)

  // Column widths
  ws.getColumn(1).width = 30  // Penerima
  ws.getColumn(2).width = 20  // Norek
  ws.getColumn(3).width = 20  // Bank
  ws.getColumn(4).width = 15  // Cabang
  ws.getColumn(5).width = 18  // Nominal
  ws.getColumn(6).width = 18  // Tanggal
  ws.getColumn(7).width = 25  // Keterangan

  // Colors
  const primaryColor = '1F4E79'
  const accentColor = '2E75B6'
  const borderColor = 'B0B0B0'

  const borderStyle = {
    top: { style: 'thin', color: { argb: borderColor } },
    left: { style: 'thin', color: { argb: borderColor } },
    bottom: { style: 'thin', color: { argb: borderColor } },
    right: { style: 'thin', color: { argb: borderColor } },
  }

  const headerFill = {
    type: 'pattern',
    pattern: 'solid',
    fgColor: { argb: primaryColor },
  }

  const headerFont = {
    name: 'Calibri',
    size: 11,
    bold: true,
    color: { argb: 'FFFFFF' },
  }

  const titleFont = {
    name: 'Calibri',
    size: 14,
    bold: true,
    color: { argb: primaryColor },
  }

  const totalFill = {
    type: 'pattern',
    pattern: 'solid',
    fgColor: { argb: 'D6E4F0' },
  }

  const totalFont = {
    name: 'Calibri',
    size: 11,
    bold: true,
    color: { argb: primaryColor },
  }

  // Row 1: Title
  ws.mergeCells(1, 1, 1, 7)
  const titleCell = ws.getCell(1, 1)
  titleCell.value = title
  titleCell.font = titleFont
  titleCell.alignment = { vertical: 'middle', horizontal: 'left' }
  ws.getRow(1).height = 30

  // Row 2: Header
  const headers = ['PENERIMA', 'NOREK', 'SINGKATAN NAMA BANK', 'CABANG', 'NOMINAL', 'TANGGAL TRANSAKSI', 'KETERANGAN']
  const headerRow = ws.getRow(2)
  headerRow.height = 22
  headers.forEach((h, i) => {
    const cell = headerRow.getCell(i + 1)
    cell.value = h
    cell.font = headerFont
    cell.fill = headerFill
    cell.alignment = { vertical: 'middle', horizontal: 'center' }
    cell.border = borderStyle
  })

  // Data rows
  data.forEach((item, idx) => {
    const row = ws.getRow(idx + 3)
    row.height = 20

    const values = [
      item.bank_account_name && item.bank_account_name !== '-' ? item.bank_account_name : item.name,
      item.bank_account_number,
      item.bank_name,
      item.bank_cabang || '',
      item.gaji_bersih,
      '',
      '',
    ]

    values.forEach((v, i) => {
      const cell = row.getCell(i + 1)
      cell.value = v
      cell.border = borderStyle
      cell.font = { name: 'Calibri', size: 10, color: { argb: '333333' } }

      if (i === 4) {
        // Nominal: right align with number format
        cell.alignment = { vertical: 'middle', horizontal: 'right' }
        cell.numFmt = '#,##0.00'
      } else if (i === 1) {
        // Norek: monospace
        cell.alignment = { vertical: 'middle', horizontal: 'left' }
        cell.font = { name: 'Consolas', size: 10, color: { argb: '333333' } }
      } else {
        cell.alignment = { vertical: 'middle', horizontal: 'left' }
      }

      // Zebra striping
      if (idx % 2 === 1) {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'F2F7FB' } }
      }
    })
  })

  // Total row
  const totalRowNum = data.length + 3
  const totalRow = ws.getRow(totalRowNum)
  totalRow.height = 24

  // Merge label cells
  ws.mergeCells(totalRowNum, 1, totalRowNum, 4)
  const labelCell = totalRow.getCell(1)
  labelCell.value = 'TOTAL'
  labelCell.font = totalFont
  labelCell.fill = totalFill
  labelCell.alignment = { vertical: 'middle', horizontal: 'right' }
  labelCell.border = borderStyle

  // Fill merged area border
  for (let c = 2; c <= 4; c++) {
    totalRow.getCell(c).border = borderStyle
    totalRow.getCell(c).fill = totalFill
  }

  const totalValueCell = totalRow.getCell(5)
  totalValueCell.value = total
  totalValueCell.font = { ...totalFont, size: 11 }
  totalValueCell.fill = totalFill
  totalValueCell.alignment = { vertical: 'middle', horizontal: 'right' }
  totalValueCell.numFmt = '#,##0.00'
  totalValueCell.border = borderStyle

  for (let c = 6; c <= 7; c++) {
    totalRow.getCell(c).border = borderStyle
    totalRow.getCell(c).fill = totalFill
  }
}

async function exportExcel() {
  if (dataAllIn.value.length === 0 && dataPrint.value.length === 0) {
    notification.error('Tidak ada data untuk di-export')
    return
  }

  const workbook = new ExcelJS.Workbook()
  workbook.creator = 'Payroll System'
  workbook.created = new Date()

  if (dataAllIn.value.length > 0) {
    addSheetWithStyle(workbook, dataAllIn.value, totalAllIn.value, 'A - KARYAWAN ALLIN', 'All In')
  }

  if (dataPrint.value.length > 0) {
    addSheetWithStyle(workbook, dataPrint.value, totalPrint.value, 'B - KARYAWAN BULANAN PRINT', 'Print')
  }

  const periodName = selectedPeriod.value?.name || 'Periode'
  const segName = activeSegment.value ? `_Segmen_${activeSegment.value}` : ''
  const fileName = `Kirim_ALL_${periodName}${segName}.xlsx`

  const buffer = await workbook.xlsx.writeBuffer()
  saveAs(new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), fileName)
}

onMounted(() => {
  fetchPeriods()
  fetchPayrollConfig()
})
</script>
