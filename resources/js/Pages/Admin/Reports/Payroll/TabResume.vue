<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Resume Laporan Payroll</h1>
        <p class="text-sm text-(--text-muted) mt-1">Laporan rekap penggajian karyawan per departemen</p>
      </div>

      <!-- Actions Toolbar -->
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="selectedPeriod" class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer">
          <option value="">Periode Terakhir</option>
          <option v-for="period in periods" :key="period.id" :value="period.id">{{ period.name }}</option>
        </select>

        <template v-if="isSplitPeriod">
          <select v-model="selectedSegment" class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer">
            <option value="">Semua Segment</option>
            <option value="1">Segment 1</option>
            <option value="2">Segment 2</option>
          </select>
        </template>

        <BaseButton variant="primary" size="sm" @click="fetchData" :disabled="loading">
          Terapkan
        </BaseButton>

        <BaseButton variant="secondary" size="sm" @click="exportExcel()" :disabled="loading || sections.length === 0">
          Export Excel
        </BaseButton>
      </div>
    </div>

    <!-- Table -->
    <BaseCard class="p-0 overflow-hidden">
      <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat data...</p>
      </div>

      <div v-else-if="sections.length === 0" class="p-12 text-center text-(--text-muted)">
        Tidak ada data resume untuk pilihan tersebut.
      </div>

      <div v-else class="overflow-x-auto max-h-[65vh]">
        <template v-for="section in sections" :key="section.label">
          <div class="px-4 py-3 bg-(--primary)/5 border-y border-(--border-soft) font-bold text-sm text-(--text-main) uppercase flex items-center justify-between">
            <span>{{ section.label }}</span>
            <span class="text-xs font-normal text-(--text-muted)">{{ section.data.length }} Departemen</span>
          </div>

          <table class="min-w-full divide-y divide-(--border-soft) text-xs whitespace-nowrap mb-4">
            <thead class="bg-(--bg-elevated) sticky top-0 z-20">
              <tr>
                <th rowspan="2" class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">No</th>
                <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Bagian</th>
                <th colspan="3" class="px-4 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-b border-(--border-soft)">Jml Karyawan</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Gaji</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Lembur</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Revisi</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tj. Masa Kerja</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tunjangan</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Premi Hadir</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">PBLT</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Total</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">BPJS Tenaga Kerja</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">BPJS Kesehatan</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">BPJS Pensiun</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Cashbon</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Revisi PPH</th>
                <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase">Total Terima</th>
              </tr>
              <tr>
                <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">L</th>
                <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">P</th>
                <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-soft)">
              <tr v-if="section.data.length === 0">
                <td colspan="19" class="px-4 py-8 text-center text-(--text-muted)">
                  Tidak ada data untuk bagian ini.
                </td>
              </tr>
              <tr v-for="(item, index) in section.data" :key="item.bagian" class="hover:bg-(--bg-elevated) transition-colors">
                <td class="px-3 py-2 text-center text-(--text-muted) border-r border-(--border-soft)">{{ index + 1 }}</td>
                <td class="px-4 py-2 font-bold text-(--text-main) border-r border-(--border-soft)">{{ item.bagian }}</td>
                <td class="px-2 py-2 text-center text-(--text-main) border-r border-(--border-soft)">{{ item.jml_karyawan_l }}</td>
                <td class="px-2 py-2 text-center text-(--text-main) border-r border-(--border-soft)">{{ item.jml_karyawan_p }}</td>
                <td class="px-2 py-2 text-center font-bold text-(--text-main) border-r border-(--border-soft)">{{ item.jml_karyawan_total }}</td>
                <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.gaji) }}</td>
                <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.lembur) }}</td>
                <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.revisi) }}</td>
                <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.tj_masa_kerja) }}</td>
                <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.tunjangan) }}</td>
                <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.premi_hadir) }}</td>
                <td class="px-4 py-2 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ formatNumber(item.pblt) }}</td>
                <td class="px-4 py-2 text-right font-bold text-blue-600 border-r border-(--border-soft)">{{ formatNumber(item.total) }}</td>
                <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.bpjs_tk) }}</td>
                <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.bpjs_ks) }}</td>
                <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.bpjs_pen) }}</td>
                <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.cashbon) }}</td>
                <td class="px-4 py-2 text-right font-medium text-red-600 border-r border-(--border-soft)">{{ formatNumber(item.revisi_pph) }}</td>
                <td class="px-4 py-2 text-right font-bold text-green-600">{{ formatNumber(item.total_terima) }}</td>
              </tr>
            </tbody>
            <!-- Section Total -->
            <tfoot v-if="section.data.length > 0">
              <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                <td colspan="2" class="px-4 py-3 text-right">TOTAL {{ section.label }}</td>
                <td class="px-2 py-3 text-center">{{ calculateTotal(section.data, 'jml_karyawan_l') }}</td>
                <td class="px-2 py-3 text-center">{{ calculateTotal(section.data, 'jml_karyawan_p') }}</td>
                <td class="px-2 py-3 text-center">{{ calculateTotal(section.data, 'jml_karyawan_total') }}</td>
                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(calculateTotal(section.data, 'gaji')) }}</td>
                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(calculateTotal(section.data, 'lembur')) }}</td>
                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(calculateTotal(section.data, 'revisi')) }}</td>
                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(calculateTotal(section.data, 'tj_masa_kerja')) }}</td>
                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(calculateTotal(section.data, 'tunjangan')) }}</td>
                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(calculateTotal(section.data, 'premi_hadir')) }}</td>
                <td class="px-4 py-3 text-right font-medium">{{ formatNumber(calculateTotal(section.data, 'pblt')) }}</td>
                <td class="px-4 py-3 text-right font-bold text-blue-600">{{ formatNumber(calculateTotal(section.data, 'total')) }}</td>
                <td class="px-4 py-3 text-right font-medium text-red-600">{{ formatNumber(calculateTotal(section.data, 'bpjs_tk')) }}</td>
                <td class="px-4 py-3 text-right font-medium text-red-600">{{ formatNumber(calculateTotal(section.data, 'bpjs_ks')) }}</td>
                <td class="px-4 py-3 text-right font-medium text-red-600">{{ formatNumber(calculateTotal(section.data, 'bpjs_pen')) }}</td>
                <td class="px-4 py-3 text-right font-medium text-red-600">{{ formatNumber(calculateTotal(section.data, 'cashbon')) }}</td>
                <td class="px-4 py-3 text-right font-medium text-red-600">{{ formatNumber(calculateTotal(section.data, 'revisi_pph')) }}</td>
                <td class="px-4 py-3 text-right font-bold text-green-600">{{ formatNumber(calculateTotal(section.data, 'total_terima')) }}</td>
              </tr>
            </tfoot>
          </table>
        </template>
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
const sections = ref([])
const loading = ref(false)

const isSplitPeriod = computed(() => {
  if (!selectedPeriod.value) return false
  const p = periods.value.find(x => x.id === selectedPeriod.value)
  return p ? p.is_split : false
})

function formatNumber(num) {
  if (!num) return '-'
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(num)
}

function calculateTotal(data, key) {
  return data.reduce((sum, item) => sum + (parseFloat(item[key]) || 0), 0)
}

async function fetchPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    periods.value = res.data || []
  } catch (err) {
    notification.error('Gagal mengambil data periode')
  }
}

async function fetchData() {
  loading.value = true
  sections.value = []
  try {
    const params = new URLSearchParams()
    if (selectedPeriod.value) params.append('period_id', selectedPeriod.value)
    if (isSplitPeriod.value && selectedSegment.value) params.append('segment', selectedSegment.value)

    const paramsAllIn = new URLSearchParams(params)
    paramsAllIn.append('tab', 'all-in')
    
    const paramsPrint = new URLSearchParams(params)
    paramsPrint.append('tab', 'print')

    const [resAllIn, resPrint] = await Promise.all([
      get(`/api/v1/laporan/payroll/resume?${paramsAllIn.toString()}`),
      get(`/api/v1/laporan/payroll/resume?${paramsPrint.toString()}`)
    ])

    if ((resAllIn.data && resAllIn.data.length > 0) || (resPrint.data && resPrint.data.length > 0)) {
        sections.value = [
          { label: 'A. Karyawan All In', data: resAllIn.data || [] },
          { label: 'B. Karyawan Bulanan Print', data: resPrint.data || [] }
        ]
    }
  } catch (err) {
    notification.error('Gagal mengambil data resume payroll')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams()
  if (selectedPeriod.value) params.append('period_id', selectedPeriod.value)
  if (isSplitPeriod.value && selectedSegment.value) params.append('segment', selectedSegment.value)

  const url = `/api/v1/laporan/payroll/resume/export?${params.toString()}`

  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', 'Laporan_Resume.xlsx')
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(err => notification.error('Gagal export Excel'))
}

onMounted(async () => {
  await fetchPeriods()
  fetchData()
})
</script>
