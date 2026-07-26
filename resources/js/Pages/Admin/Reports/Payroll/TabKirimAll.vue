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

        <!-- Tanggal Transaksi -->
        <div v-if="selectedPeriod" class="flex items-center gap-2">
          <label class="text-xs font-medium text-(--text-muted) whitespace-nowrap">Tgl. Transaksi</label>
          <input
            type="date"
            :value="tanggalPenggajian"
            @change="updateTanggalPenggajian"
            class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer"
          />
        </div>

        <!-- Bulk Fill Cabang -->
        <BaseButton
          variant="secondary"
          size="sm"
          :disabled="!selectedPeriod || (dataAllIn.length === 0 && dataPrint.length === 0)"
          @click="bulkFillCabang"
          title="Isi semua cabang dengan SALATIGA"
        >
          <template #icon-left>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <line x1="19" y1="8" x2="19" y2="14"></line>
              <line x1="22" y1="11" x2="16" y2="11"></line>
            </svg>
          </template>
          Isi Semua Cabang
        </BaseButton>

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
                <td class="px-4 py-2">
                  <input
                    type="text"
                    v-model="item.bank_cabang"
                    @blur="saveCabang(item)"
                    class="w-full px-2 py-1 text-sm border border-transparent hover:border-(--border-soft) focus:border-(--primary) rounded bg-transparent focus:bg-(--bg-card) outline-none transition-colors"
                    placeholder="-"
                  />
                </td>
                <td class="px-4 py-2 text-right font-bold text-blue-600">{{ formatNumber(item.gaji_bersih) }}</td>
                <td class="px-4 py-2 text-sm text-(--text-muted)">
                  {{ tanggalPenggajian ? formatDate(tanggalPenggajian) : '-' }}
                </td>
                <td class="px-4 py-2">
                  <input
                    type="text"
                    v-model="item.notes"
                    @blur="saveNotes(item)"
                    class="w-full px-2 py-1 text-sm border border-transparent hover:border-(--border-soft) focus:border-(--primary) rounded bg-transparent focus:bg-(--bg-card) outline-none transition-colors"
                    placeholder="-"
                  />
                </td>
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
                <td class="px-4 py-2">
                  <input
                    type="text"
                    v-model="item.bank_cabang"
                    @blur="saveCabang(item)"
                    class="w-full px-2 py-1 text-sm border border-transparent hover:border-(--border-soft) focus:border-(--primary) rounded bg-transparent focus:bg-(--bg-card) outline-none transition-colors"
                    placeholder="-"
                  />
                </td>
                <td class="px-4 py-2 text-right font-bold text-blue-600">{{ formatNumber(item.gaji_bersih) }}</td>
                <td class="px-4 py-2 text-sm text-(--text-muted)">
                  {{ tanggalPenggajian ? formatDate(tanggalPenggajian) : '-' }}
                </td>
                <td class="px-4 py-2">
                  <input
                    type="text"
                    v-model="item.notes"
                    @blur="saveNotes(item)"
                    class="w-full px-2 py-1 text-sm border border-transparent hover:border-(--border-soft) focus:border-(--primary) rounded bg-transparent focus:bg-(--bg-card) outline-none transition-colors"
                    placeholder="-"
                  />
                </td>
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
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

const { get, put } = useApi()
const notification = useNotificationStore()

const periods = ref([])
const selectedPeriodId = ref('')
const records = ref([])
const activeSegment = ref(null)
const payrollConfig = ref({ sections: { A: ['GRP-ALLIN', 'GRP-SPR'], B: ['GRP-GD', 'GRP-SS', 'GRP-PS1'] } })
const tanggalPenggajian = ref('')

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

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' })
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
    tanggalPenggajian.value = ''
    return
  }
  try {
    let url = `/api/v1/payroll/gaji-karyawan?period_id=${selectedPeriodId.value}`
    if (activeSegment.value) {
      url += `&segment=${activeSegment.value}`
    }
    const res = await get(url)
    records.value = res.data || []
    tanggalPenggajian.value = res.period?.tanggal_penggajian || ''
  } catch (error) {
    console.error('Error fetching records', error)
    records.value = []
    tanggalPenggajian.value = ''
  }
}

async function onPeriodChange() {
  const period = periods.value.find(p => p.id === selectedPeriodId.value)
  activeSegment.value = period?.is_split ? 'A' : null
  await fetchRecords()
}

async function saveCabang(item) {
  try {
    await put(`/api/v1/payroll/gaji-karyawan/${item.id}/transfer-info`, { bank_cabang: item.bank_cabang })
  } catch (e) {
    notification.error('Gagal menyimpan cabang')
    console.error(e)
  }
}

async function saveNotes(item) {
  try {
    await put(`/api/v1/payroll/gaji-karyawan/${item.id}/transfer-info`, { notes: item.notes })
  } catch (e) {
    notification.error('Gagal menyimpan keterangan')
    console.error(e)
  }
}

async function updateTanggalPenggajian(e) {
  const value = e.target.value
  if (!selectedPeriodId.value) return
  try {
    await put(`/api/v1/payroll/periods/${selectedPeriodId.value}`, { tanggal_penggajian: value || null })
    tanggalPenggajian.value = value
    notification.success('Tanggal transaksi berhasil diupdate')
  } catch (e) {
    notification.error('Gagal mengupdate tanggal transaksi')
    console.error(e)
  }
}

async function bulkFillCabang() {
  if (!selectedPeriodId.value) return
  if (!confirm('Isi semua cabang dengan SALATIGA?')) return

  try {
    const payload = {
      period_id: selectedPeriodId.value,
      bank_cabang: 'SALATIGA',
    }
    if (activeSegment.value) {
      payload.segment = activeSegment.value
    }

    const res = await put('/api/v1/payroll/gaji-karyawan/bulk-update-cabang', payload)
    notification.success(res.message || 'Cabang berhasil diupdate')

    // Update local data
    records.value.forEach(r => { r.bank_cabang = 'SALATIGA' })
  } catch (e) {
    notification.error('Gagal update cabang')
    console.error(e)
  }
}

async function exportExcel() {
  if (dataAllIn.value.length === 0 && dataPrint.value.length === 0) {
    notification.error('Tidak ada data untuk di-export')
    return
  }

  const params = new URLSearchParams({ period_id: selectedPeriodId.value })
  if (activeSegment.value) params.append('segment', activeSegment.value)

  const periodName = selectedPeriod.value?.name || 'Periode'
  const segName = activeSegment.value ? `_Segmen_${activeSegment.value}` : ''
  const fileName = `Kirim_ALL_${periodName}${segName}.xlsx`

  try {
    const response = await fetch(`/api/v1/payroll/gaji-karyawan/export-kirim-all?${params.toString()}`, {
      credentials: 'include',
      headers: { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
    })
    if (!response.ok) {
      notification.error('Gagal mengunduh file Excel')
      return
    }
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = fileName
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch (e) {
    notification.error('Gagal mengunduh file Excel')
    console.error(e)
  }
}

onMounted(() => {
  fetchPeriods()
  fetchPayrollConfig()
})
</script>
