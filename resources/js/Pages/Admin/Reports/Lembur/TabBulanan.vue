<template>
  <div>
    <!-- Filter Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
      <div class="flex items-center gap-3">
        <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider">Bulan:</span>
        <select
          v-model="currentMonth"
          @change="fetchData"
          class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg focus:ring-(--primary) focus:border-(--primary) p-2"
        >
          <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
        </select>
        <select
          v-model="currentYear"
          @change="fetchData"
          class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg focus:ring-(--primary) focus:border-(--primary) p-2"
        >
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="sm" @click="exportExcel" :disabled="loading || data.length === 0">
          Export Excel
        </BaseButton>
        <BaseButton variant="secondary" size="sm" @click="openPrint" :disabled="loading || data.length === 0">
          Print
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
        Tidak ada data lembur untuk bulan yang dipilih.
      </div>

      <div v-else class="overflow-auto max-h-[65vh]">
        <table class="min-w-full divide-y divide-(--border-soft) text-[11px] whitespace-nowrap">
          <!-- Header: Row 1 -->
          <thead class="bg-(--bg-elevated) sticky top-0 z-20">
            <tr>
              <th rowspan="2" class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-0 bg-(--bg-elevated) z-30">No</th>
              <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-[40px] bg-(--bg-elevated) z-30 w-48">Nama</th>
              <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Bagian / Jabatan</th>
              <th rowspan="2" class="px-2 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">L/P</th>
              <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tj. MK</th>
              <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tunjangan</th>
              <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Upah/Hari</th>
              <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Upah Lembur<br>Per Jam</th>
              <!-- Date group headers -->
              <th
                v-for="dateStr in dates"
                :key="'dh-' + dateStr"
                colspan="5"
                class="px-2 py-2 text-center font-bold text-(--text-main) bg-blue-50/30 uppercase border-b border-(--border-soft)"
              >
                {{ formatDateHeader(dateStr) }}
              </th>
            </tr>
            <!-- Header: Row 2 -->
            <tr>
              <template v-for="dateStr in dates" :key="'sh-' + dateStr">
                <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px]">Kode</th>
                <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px]">H/A</th>
                <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px]">L/M</th>
                <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px]">Lembur</th>
                <th class="px-3 py-2 text-right font-bold text-(--text-muted) uppercase text-[10px]">Nominal</th>
              </template>
            </tr>
          </thead>

          <tbody class="divide-y divide-(--border-soft)">
            <tr
              v-for="(item, index) in data"
              :key="item.id"
              class="hover:bg-(--bg-elevated) transition-colors group"
            >
              <td class="px-3 py-3 text-center text-(--text-muted) border-r border-(--border-soft) sticky left-0 bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10">{{ index + 1 }}</td>
              <td class="px-4 py-3 font-bold text-(--text-main) border-r border-(--border-soft) sticky left-[40px] bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10 truncate">{{ item.name }}</td>
              <td class="px-4 py-3 text-(--text-main) border-r border-(--border-soft)">{{ item.jabatan }}</td>
              <td class="px-2 py-3 text-center text-(--text-muted) border-r border-(--border-soft)">{{ item.gender === 'male' ? 'L' : (item.gender === 'female' ? 'P' : item.gender) }}</td>
              <td class="px-4 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.tj_mk ? formatNumber(item.tj_mk) : '' }}</td>
              <td class="px-4 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.tunjangan ? formatNumber(item.tunjangan) : '' }}</td>
              <td class="px-4 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.upah_per_hari ? formatNumber(item.upah_per_hari) : '' }}</td>
              <td class="px-4 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.upah_lembur_per_jam ? formatNumber(item.upah_lembur_per_jam) : '' }}</td>

              <!-- Daily cells -->
              <template v-for="dateStr in dates" :key="'dc-' + item.id + '-' + dateStr">
                <td :class="['px-2 py-3 text-center border-r border-(--border-soft) text-xs', (item.days[dateStr]?.kode) ? 'text-blue-600 font-medium' : 'text-gray-300']">
                  {{ item.days[dateStr]?.kode || '-' }}
                </td>
                <td :class="['px-2 py-3 text-center border-r border-(--border-soft) text-xs', item.days[dateStr]?.ha && item.days[dateStr]?.ha !== '-' ? 'font-medium' : 'text-gray-300']">
                  {{ item.days[dateStr]?.ha || '-' }}
                </td>
                <td :class="['px-2 py-3 text-center border-r border-(--border-soft) text-xs', item.days[dateStr]?.lm > 0 ? 'text-purple-600 font-medium' : 'text-gray-300']">
                  {{ item.days[dateStr]?.lm > 0 ? item.days[dateStr].lm : '-' }}
                </td>
                <td :class="['px-2 py-3 text-center border-r border-(--border-soft) text-xs', item.days[dateStr]?.lembur > 0 ? 'text-orange-600 font-medium' : 'text-gray-300']">
                  {{ item.days[dateStr]?.lembur > 0 ? item.days[dateStr].lembur : '-' }}
                </td>
                <td :class="['px-3 py-3 text-right text-xs', item.days[dateStr]?.nominal > 0 ? 'text-green-600 font-bold' : 'text-gray-300']">
                  {{ item.days[dateStr]?.nominal > 0 ? formatNumber(item.days[dateStr].nominal) : '-' }}
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const props = defineProps({
  groups: { type: Array, default: () => [] }
})

const { get } = useApi()
const notification = useNotificationStore()

const now = new Date()
const currentMonth = ref(now.getMonth() + 1)
const currentYear = ref(now.getFullYear())
const data = ref([])
const dates = ref([])
const monthLabel = ref('')
const loading = ref(false)

const monthOptions = [
  { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
  { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
  { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
  { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
  { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
  { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
]

const years = computed(() => {
  const current = new Date().getFullYear()
  const list = []
  for (let y = 2024; y <= current; y++) {
    list.push(y)
  }
  return list
})

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num || 0)
}

function formatDateHeader(dateStr) {
  const d = new Date(dateStr + 'T00:00:00')
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
  }).format(d).toUpperCase()
}

async function fetchData() {
  if (!props.groups.length) return
  loading.value = true
  try {
    const params = new URLSearchParams({
      month: currentMonth.value,
      year: currentYear.value
    })
    props.groups.forEach(g => params.append('groups[]', g))
    const res = await get(`/api/v1/reports/lembur/bulanan?${params.toString()}`)
    data.value = res.data?.data || []
    dates.value = res.data?.dates || []
    monthLabel.value = res.data?.month_label || ''
  } catch (err) {
    notification.addNotification('Gagal mengambil data laporan bulanan', 'error')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams({
    month: currentMonth.value,
    year: currentYear.value
  })
  props.groups.forEach(g => params.append('groups[]', g))
  const url = `/api/v1/reports/lembur/bulanan/export?${params.toString()}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', `Laporan_Lembur_Bulanan_${monthLabel.value.replace(' ', '_')}.xlsx`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(err => notification.addNotification('Gagal export Excel', 'error'))
}

function openPrint() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams({
    month: currentMonth.value,
    year: currentYear.value
  })
  props.groups.forEach(g => params.append('groups[]', g))
  const url = `/api/v1/reports/lembur/bulanan/print?${params.toString()}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.text())
    .then(html => {
      const printWindow = window.open('', '_blank')
      printWindow.document.write(html)
      printWindow.document.close()
    })
    .catch(err => notification.addNotification('Gagal membuka print', 'error'))
}

watch(() => props.groups, fetchData, { immediate: true })
</script>
