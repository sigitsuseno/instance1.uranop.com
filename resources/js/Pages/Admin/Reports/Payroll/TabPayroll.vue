<template>
  <div>
    <!-- Filter Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
      <div class="flex items-center gap-3">
        <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider">Periode:</span>
        <select v-model="selectedPeriod" class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg p-2 focus:ring-(--primary) focus:border-(--primary)">
          <option value="">Periode Terakhir</option>
          <option v-for="period in periods" :key="period.id" :value="period.id">{{ period.name }} ({{ period.date_range }})</option>
        </select>

        <template v-if="isSplitPeriod">
          <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider ml-4">Segment:</span>
          <select v-model="selectedSegment" class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg p-2 focus:ring-(--primary) focus:border-(--primary)">
            <option value="">Semua (Gabungan)</option>
            <option value="1">1 (Pertama)</option>
            <option value="2">2 (Kedua)</option>
          </select>
        </template>

        <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider ml-4">Kelompok:</span>
        <select v-model="groupTab" class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg p-2 focus:ring-(--primary) focus:border-(--primary)">
          <option value="all-in">A. Karyawan All In</option>
          <option value="print">B. Karyawan Bulanan Print</option>
        </select>

        <BaseButton variant="primary" size="sm" @click="fetchData" :disabled="loading">
          Terapkan
        </BaseButton>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="sm" @click="exportExcel" :disabled="loading || data.length === 0">
          Export Excel
        </BaseButton>
      </div>
    </div>

    <!-- Table -->
    <BaseCard>
      <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat data...</p>
      </div>

      <div v-else-if="data.length === 0" class="p-12 text-center text-(--text-muted)">
        Tidak ada data payroll untuk pilihan tersebut.
      </div>

      <div v-else class="overflow-x-auto max-h-[65vh]">
        <table class="min-w-full divide-y divide-(--border-soft) text-xs whitespace-nowrap">
          <thead class="bg-(--bg-elevated) sticky top-0 z-20">
            <tr>
              <th class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">No</th>
              <th class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">ID No</th>
              <th class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Nama</th>
              <th class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Bagian / Jabatan</th>
              <th class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">L/P</th>
              <th class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Thn Masuk</th>
              <th class="px-4 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Masa Kerja</th>
              <th class="px-4 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Status</th>
              <th class="px-4 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Jml Anak</th>
              <th class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Account No</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Premi</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Gaji Pokok</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tj. Masa Kerja</th>
              <th class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">HK</th>
              <th class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">L/M</th>
              <th class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Lbr Jam</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Gaji</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Lembur</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Revisi</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tunjangan</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Premi Hadir</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">PBLT</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Total</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">BPJS Tenaga Kerja</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">BPJS Kesehatan</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">BPJS Pensiun</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Cashbon</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">PPH</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase">Total Terima</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-for="(item, index) in data" :key="item.id" class="hover:bg-(--bg-elevated) transition-colors">
              <td class="px-3 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ index + 1 }}</td>
              <td class="px-4 py-2 text-(--text-main) border-r border-(--border-soft)">{{ item.no_id }}</td>
              <td class="px-4 py-2 font-bold text-(--text-main) border-r border-(--border-soft)">{{ item.name }}</td>
              <td class="px-4 py-2 text-(--text-main) border-r border-(--border-soft)">{{ item.bagian }}</td>
              <td class="px-4 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ item.gender }}</td>
              <td class="px-4 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ item.join_date }}</td>
              <td class="px-4 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ item.masa_kerja }}</td>
              <td class="px-4 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ item.status }}</td>
              <td class="px-4 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ item.jml_anak }}</td>
              <td class="px-4 py-2 text-left text-(--text-muted) border-r border-(--border-soft)">{{ item.account_no }}</td>
              <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.premi) }}</td>
              <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.gaji_pokok) }}</td>
              <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.tj_masa_kerja) }}</td>
              <td class="px-3 py-2 text-center text-(--text-main) border-r border-(--border-soft)">{{ item.hk }}</td>
              <td class="px-3 py-2 text-center text-(--text-main) border-r border-(--border-soft)">{{ item.lm }}</td>
              <td class="px-3 py-2 text-center text-(--text-main) border-r border-(--border-soft)">{{ item.lbr_jam }}</td>
              <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.gaji) }}</td>
              <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.lembur) }}</td>
              <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.revisi) }}</td>
              <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.tunjangan) }}</td>
              <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.premi_hadir) }}</td>
              <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.pblt) }}</td>
              <td class="px-4 py-2 text-right font-bold text-blue-600 border-r border-(--border-soft)">{{ formatNumber(item.total) }}</td>
              <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.bpjs_tk) }}</td>
              <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.bpjs_ks) }}</td>
              <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.bpjs_pen) }}</td>
              <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.cashbon) }}</td>
              <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.pph) }}</td>
              <td class="px-4 py-2 text-right font-bold text-green-600">{{ formatNumber(item.total_terima) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const { get } = useApi()
const notification = useNotificationStore()

const periods = ref([])
const selectedPeriod = ref('')
const selectedSegment = ref('')
const groupTab = ref('all-in')
const data = ref([])
const loading = ref(false)

const isSplitPeriod = computed(() => {
  if (!selectedPeriod.value) return false
  const p = periods.value.find(x => x.id === selectedPeriod.value)
  return p ? p.is_split : false
})

function formatNumber(num) {
  if (!num) return '-'
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
}

async function fetchPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    periods.value = res.data || []
  } catch (err) {
    notification.addNotification('Gagal mengambil data periode', 'error')
  }
}

async function fetchData() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (selectedPeriod.value) params.append('period_id', selectedPeriod.value)
    if (isSplitPeriod.value && selectedSegment.value) params.append('segment', selectedSegment.value)
    params.append('tab', groupTab.value)

    const res = await get(`/api/v1/laporan/payroll/detail?${params.toString()}`)
    data.value = res.data || []
  } catch (err) {
    notification.addNotification('Gagal mengambil data laporan payroll', 'error')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams()
  if (selectedPeriod.value) params.append('period_id', selectedPeriod.value)
  if (isSplitPeriod.value && selectedSegment.value) params.append('segment', selectedSegment.value)
  params.append('tab', groupTab.value)

  const url = `/api/v1/laporan/payroll/detail/export?${params.toString()}`
  
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', `Laporan_Payroll_${groupTab.value}.xlsx`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(err => notification.addNotification('Gagal export Excel', 'error'))
}

onMounted(async () => {
  await fetchPeriods()
  fetchData()
})
</script>
