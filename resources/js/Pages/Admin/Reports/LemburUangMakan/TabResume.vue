<template>
  <div>
    <!-- Filter Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
      <div class="flex items-center gap-3">
        <!-- Mode toggle -->
        <div class="flex rounded-lg border border-(--border-soft) overflow-hidden">
          <button
            :class="['px-3 py-2 text-sm font-medium transition-colors', dateMode === 'period' ? 'bg-(--primary) text-white' : 'bg-(--bg-elevated) text-(--text-muted) hover:text-(--text-main)']"
            @click="dateMode = 'period'"
          >Periode</button>
          <button
            :class="['px-3 py-2 text-sm font-medium transition-colors', dateMode === 'range' ? 'bg-(--primary) text-white' : 'bg-(--bg-elevated) text-(--text-muted) hover:text-(--text-main)']"
            @click="dateMode = 'range'"
          >Rentang Tanggal</button>
        </div>

        <select
          v-if="dateMode === 'period'"
          v-model="selectedPeriodId"
          @change="fetchData"
          class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg focus:ring-(--primary) focus:border-(--primary) p-2 min-w-[300px]"
        >
          <option :value="null" disabled>-- Pilih Periode --</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">
            {{ p.name }} ({{ formatDateRange(p.start_date, p.end_date) }})
          </option>
        </select>

        <template v-if="dateMode === 'range'">
          <input type="date" v-model="startDate" class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg p-2" />
          <span class="text-(--text-muted)">s/d</span>
          <input type="date" v-model="endDate" class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg p-2" />
          <BaseButton variant="primary" size="sm" @click="fetchData" :disabled="!startDate || !endDate">Tampilkan</BaseButton>
        </template>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="sm" @click="exportExcel" :disabled="loading || !hasData">
          Export Excel
        </BaseButton>
      </div>
    </div>

    <!-- Table -->
    <BaseCard>
      <div v-if="!hasFilter" class="p-12 text-center text-(--text-muted)">
        Silakan pilih periode atau rentang tanggal terlebih dahulu.
      </div>

      <div v-else-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat data...</p>
      </div>

      <div v-else-if="!hasData" class="p-12 text-center text-(--text-muted)">
        Tidak ada data untuk periode yang dipilih.
      </div>

      <div v-else class="overflow-auto max-h-[65vh]">
        <template v-for="section in sections" :key="section.key">
          <div class="px-4 py-2 bg-green-50/50 font-bold text-sm text-(--text-main) uppercase sticky left-0 border-b border-(--border-soft)">
            {{ section.label }}
          </div>

          <table class="min-w-full divide-y divide-(--border-soft) text-[11px] whitespace-nowrap mb-4">
            <thead class="bg-(--bg-elevated) sticky top-0 z-20">
              <tr>
                <th rowspan="2" class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-0 bg-(--bg-elevated) z-30">No</th>
                <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-[40px] bg-(--bg-elevated) z-30 w-40">Bagian</th>
                <th colspan="2" class="px-3 py-2 text-center font-bold text-(--text-main) bg-(--bg-soft) uppercase border-b border-(--border-soft)">
                  Jml Karyawan
                </th>
                <th
                  v-for="dateStr in dates"
                  :key="'dh-' + section.key + '-' + dateStr"
                  colspan="3"
                  class="px-2 py-2 text-center font-bold text-(--text-main) bg-blue-50/30 uppercase border-b border-(--border-soft)"
                >
                  {{ formatDateHeader(dateStr) }}
                </th>
                <th rowspan="2" class="px-4 py-2 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Total<br>Hari Kerja</th>
                <th rowspan="2" class="px-4 py-2 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Total<br>Overtime</th>
                <th rowspan="2" class="px-4 py-2 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Total<br>U.Makan</th>
                <th rowspan="2" class="px-4 py-2 text-right font-bold text-(--text-muted) uppercase">Total<br>Terima</th>
              </tr>
              <tr>
                <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase text-[10px]">L</th>
                <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase text-[10px]">P</th>
                <template v-for="dateStr in dates" :key="'sh-' + section.key + '-' + dateStr">
                  <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase text-[10px]">Hari Kerja</th>
                  <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase text-[10px]">Overtime</th>
                  <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase text-[10px]">U.Makan</th>
                </template>
              </tr>
            </thead>

            <tbody class="divide-y divide-(--border-soft)">
              <tr
                v-for="(item, index) in section.data"
                :key="'dept-' + section.key + '-' + index"
                class="hover:bg-(--bg-elevated) transition-colors group"
              >
                <td class="px-3 py-3 text-center text-(--text-muted) border-r border-(--border-soft) sticky left-0 bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10">{{ index + 1 }}</td>
                <td class="px-4 py-3 font-bold text-(--text-main) border-r border-(--border-soft) sticky left-[40px] bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10 truncate">{{ item.bagian }}</td>
                <td class="px-2 py-3 text-center font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.l || '-' }}</td>
                <td class="px-2 py-3 text-center font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.p || '-' }}</td>

                <template v-for="dateStr in dates" :key="'dc-' + section.key + '-' + index + '-' + dateStr">
                  <td class="px-2 py-3 text-right text-xs border-r border-(--border-soft)" :class="item.days[dateStr]?.hari_kerja > 0 ? 'text-emerald-600 font-medium' : 'text-gray-300'">
                    {{ item.days[dateStr]?.hari_kerja > 0 ? formatNumber(item.days[dateStr].hari_kerja) : '-' }}
                  </td>
                  <td class="px-2 py-3 text-right text-xs border-r border-(--border-soft)" :class="item.days[dateStr]?.overtime > 0 ? 'text-orange-600 font-medium' : 'text-gray-300'">
                    {{ item.days[dateStr]?.overtime > 0 ? formatNumber(item.days[dateStr].overtime) : '-' }}
                  </td>
                  <td class="px-2 py-3 text-right text-xs" :class="item.days[dateStr]?.uang_makan > 0 ? 'text-amber-600 font-medium' : 'text-gray-300'">
                    {{ item.days[dateStr]?.uang_makan > 0 ? formatNumber(item.days[dateStr].uang_makan) : '-' }}
                  </td>
                </template>

                <td class="px-4 py-3 text-right font-bold text-emerald-600 border-r border-(--border-soft)">{{ item.total_hari_kerja ? formatNumber(item.total_hari_kerja) : '-' }}</td>
                <td class="px-4 py-3 text-right font-bold text-orange-600 border-r border-(--border-soft)">{{ item.total_overtime ? formatNumber(item.total_overtime) : '-' }}</td>
                <td class="px-4 py-3 text-right font-bold text-amber-600 border-r border-(--border-soft)">{{ item.total_uang_makan ? formatNumber(item.total_uang_makan) : '-' }}</td>
                <td class="px-4 py-3 text-right font-bold text-(--primary)">{{ item.total_terima ? formatNumber(item.total_terima) : '-' }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const props = defineProps({
  groups: { type: Array, default: () => [] }
})

const { get } = useApi()
const notification = useNotificationStore()

const dateMode = ref('period')
const selectedPeriodId = ref(null)
const startDate = ref('')
const endDate = ref('')
const periods = ref([])
const sections = ref([])
const dates = ref([])
const periodLabel = ref('')
const loading = ref(false)

const hasFilter = computed(() => {
  if (dateMode.value === 'period') return !!selectedPeriodId.value
  return !!(startDate.value && endDate.value)
})

const hasData = computed(() => {
  return sections.value.some(s => s.data && s.data.length > 0)
})

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
      selectedPeriodId.value = periods.value[0].id
    }
  } catch (err) {
    console.error('Gagal fetch periods:', err)
  }
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

function formatDateRange(start, end) {
  if (!start || !end) return ''
  const fmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  return fmt.format(new Date(start)) + ' - ' + fmt.format(new Date(end))
}

function buildParams() {
  const params = new URLSearchParams()
  if (dateMode.value === 'range') {
    params.set('start_date', startDate.value)
    params.set('end_date', endDate.value)
  } else {
    params.set('period_id', selectedPeriodId.value)
  }
  props.groups.forEach(g => params.append('groups[]', g))
  return params
}

async function fetchData() {
  if (!props.groups.length) return
  if (!hasFilter.value) return
  loading.value = true
  try {
    const params = buildParams()
    const res = await get(`/api/v1/reports/lembur/combined-resume?${params.toString()}`)
    sections.value = res.sections || []
    dates.value = res.dates || []
    periodLabel.value = res.period_label || ''
  } catch (err) {
    notification.addNotification('Gagal mengambil data resume', 'error')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  const token = localStorage.getItem('token')
  const params = buildParams()
  const url = `/api/v1/reports/lembur/combined-resume/export?${params.toString()}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      const safeName = periodLabel.value.replace(/\s+/g, '_').replace(/[()]/g, '')
      link.setAttribute('download', `Resume_Gaji_Overtime_${safeName}.xlsx`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(err => notification.addNotification('Gagal export Excel', 'error'))
}

watch(() => props.groups, fetchData)
watch(selectedPeriodId, () => { if (dateMode.value === 'period') fetchData() })
watch(dateMode, () => { selectedPeriodId.value = null; startDate.value = ''; endDate.value = '' })
</script>
