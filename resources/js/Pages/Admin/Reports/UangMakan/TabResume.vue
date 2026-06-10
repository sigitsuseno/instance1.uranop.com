<template>
  <div>
    <!-- Filter Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
      <div class="flex items-center gap-3">
        <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider">Periode:</span>
        <select
          v-model="selectedPeriodId"
          @change="fetchData"
          class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg focus:ring-(--primary) focus:border-(--primary) p-2 min-w-[300px]"
        >
          <option :value="null" disabled>-- Pilih Periode --</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">
            {{ p.name }} ({{ formatDateRange(p.start_date, p.end_date) }})
          </option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="sm" @click="exportExcel" :disabled="loading">
          Export Excel
        </BaseButton>
        <BaseButton variant="secondary" size="sm" @click="openPrint" :disabled="loading">
          Print
        </BaseButton>
      </div>
    </div>

    <!-- Table -->
    <BaseCard>
      <div class="mb-4">
        <h2 class="text-lg font-semibold text-(--text-main)">Resume Uang Makan — ALL IN</h2>
      </div>

      <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat data...</p>
      </div>

      <div v-else-if="!data.length" class="p-12 text-center text-(--text-muted)">
        Tidak ada data karyawan ALL IN untuk periode yang dipilih.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-800 border-b border-(--border-soft) text-(--text-muted)">
            <tr>
              <th class="px-3 py-3 border-r border-(--border-soft) text-center font-semibold">No.</th>
              <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">BAGIAN</th>
              <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">UANG MAKAN</th>
              <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">LEMBUR SABTU</th>
              <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">LEMBUR MINGGU</th>
              <th class="px-4 py-3 text-center font-semibold">TOTAL</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft) text-(--text-main)">
            <tr v-if="!data.length">
              <td colspan="6" class="px-4 py-8 text-center text-(--text-muted)">
                Tidak ada data
              </td>
            </tr>
            <tr v-for="(item, index) in data" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
              <td class="px-3 py-2 border-r border-(--border-soft) text-center">{{ index + 1 }}</td>
              <td class="px-4 py-2 border-r border-(--border-soft) font-medium">{{ item.bagian }}</td>
              <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(item.uang_makan) }}</td>
              <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(item.lembur_sabtu) }}</td>
              <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(item.lembur_minggu) }}</td>
              <td class="px-4 py-2 text-right font-bold text-indigo-600">{{ formatRupiah(item.total) }}</td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50 dark:bg-gray-800 border-t border-(--border-soft)">
            <tr class="font-bold">
              <td colspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center uppercase">TOTAL</td>
              <td class="px-4 py-3 border-r border-(--border-soft) text-right">{{ formatRupiah(totalUangMakan) }}</td>
              <td class="px-4 py-3 border-r border-(--border-soft) text-right">{{ formatRupiah(totalLemburSabtu) }}</td>
              <td class="px-4 py-3 border-r border-(--border-soft) text-right">{{ formatRupiah(totalLemburMinggu) }}</td>
              <td class="px-4 py-3 text-right text-indigo-700 dark:text-indigo-400">{{ formatRupiah(grandTotal) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const props = defineProps({
  groups: { type: Array, default: () => [] }
})

const { get } = useApi()
const notification = useNotificationStore()

const selectedPeriodId = ref(null)
const periods = ref([])
const data = ref([])
const loading = ref(false)

const formatRupiah = (value) => {
  if (!value || value === 0) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'decimal', minimumFractionDigits: 0 }).format(value);
}

const totalUangMakan = computed(() => data.value.reduce((sum, item) => sum + (item.uang_makan || 0), 0))
const totalLemburSabtu = computed(() => data.value.reduce((sum, item) => sum + (item.lembur_sabtu || 0), 0))
const totalLemburMinggu = computed(() => data.value.reduce((sum, item) => sum + (item.lembur_minggu || 0), 0))
const grandTotal = computed(() => data.value.reduce((sum, item) => sum + (item.total || 0), 0))

function formatDateRange(start, end) {
  if (!start || !end) return ''
  const fmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  return fmt.format(new Date(start)) + ' - ' + fmt.format(new Date(end))
}

onMounted(async () => {
  try {
    const res = await get('/api/v1/payroll/periods')
    periods.value = (res.data || []).map(p => ({
      id: p.id,
      name: p.name,
      start_date: p.start_date,
      end_date: p.end_date,
    }))
    if (periods.value.length > 0) {
      const today = new Date().toISOString().split('T')[0]
      const valid = periods.value.find(p => p.start_date <= today)
      selectedPeriodId.value = valid ? valid.id : periods.value[0].id
    }
  } catch (err) {
    console.error('Gagal fetch periods:', err)
  }
})

// ─── TabResume ───
async function fetchData() {
  if (!props.groups.length || !selectedPeriodId.value) return
  loading.value = true
  try {
    const params = new URLSearchParams({ period_id: selectedPeriodId.value })
    props.groups.forEach(g => params.append('groups[]', g))
    const res = await get(`/api/v1/reports/uang-makan/resume?${params.toString()}`)
    data.value = res.data || []
  } catch (err) {
    notification.addNotification('Gagal mengambil data resume', 'error')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams({ period_id: selectedPeriodId.value })
  props.groups.forEach(g => params.append('groups[]', g))
  const url = `/api/v1/reports/uang-makan/resume/export?${params.toString()}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', `Resume_Uang_Makan_${periods.value.find(p => p.id === selectedPeriodId.value)?.name || ''}.xlsx`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(err => notification.addNotification('Gagal export Excel', 'error'))
}

function openPrint() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams({ period_id: selectedPeriodId.value })
  props.groups.forEach(g => params.append('groups[]', g))
  const url = `/api/v1/reports/uang-makan/resume/print?${params.toString()}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.text())
    .then(html => {
      const printWindow = window.open('', '_blank')
      printWindow.document.write(html)
      printWindow.document.close()
    })
    .catch(err => notification.addNotification('Gagal membuka print', 'error'))
}

watch(() => props.groups, fetchData)
watch(selectedPeriodId, fetchData)
</script>
