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

        <!-- Period dropdown -->
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

        <!-- Date range inputs -->
        <template v-if="dateMode === 'range'">
          <input
            type="date"
            v-model="startDate"
            class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg p-2"
          />
          <span class="text-(--text-muted)">s/d</span>
          <input
            type="date"
            v-model="endDate"
            class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg p-2"
          />
          <BaseButton variant="primary" size="sm" @click="fetchData" :disabled="!startDate || !endDate">
            Tampilkan
          </BaseButton>
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
        <!-- Render per section -->
        <template v-for="(section, si) in sections" :key="section.key">
          <!-- Section Label -->
          <div class="px-4 py-2 bg-green-50/50 font-bold text-sm text-(--text-main) uppercase sticky left-0 border-b border-(--border-soft)">
            {{ section.label }}
          </div>

          <table class="min-w-full divide-y divide-(--border-soft) text-[10px] whitespace-nowrap mb-4">
            <thead class="bg-(--bg-elevated) sticky top-0 z-20">
              <!-- Header Row 1 -->
              <tr>
                <th rowspan="2" class="px-2 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-0 bg-(--bg-elevated) z-30">No</th>
                <th rowspan="2" class="px-2 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-[32px] bg-(--bg-elevated) z-30">ID</th>
                <th rowspan="2" class="px-3 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-[76px] bg-(--bg-elevated) z-30 w-44">Nama</th>
                <th rowspan="2" class="px-3 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Bagian / Jabatan</th>
                <th rowspan="2" class="px-2 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">L/P</th>
                <th rowspan="2" class="px-3 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tj. MK</th>
                <th rowspan="2" class="px-3 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tunjangan</th>
                <th rowspan="2" class="px-3 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Upah / Hari</th>
                <th rowspan="2" class="px-3 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Upah Lbr / Jam</th>
                <!-- Date group headers -->
                <th
                  v-for="dateStr in dates"
                  :key="'dh-' + section.key + '-' + dateStr"
                  :colspan="(section.key === 'jakarta' || section.key === 'spc_jakarta') ? 5 : 6"
                  class="px-2 py-2 text-center font-bold text-(--text-main) bg-blue-50/30 uppercase border-b border-(--border-soft) text-[9px]"
                >
                  {{ formatDateHeader(dateStr) }}
                </th>
                <th rowspan="2" class="px-3 py-2 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Total<br>Hari Kerja</th>
                <th rowspan="2" class="px-3 py-2 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Total<br>Overtime</th>
                <th rowspan="2" class="px-3 py-2 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Total<br>U.Makan</th>
                <th rowspan="2" class="px-3 py-2 text-right font-bold text-(--text-muted) uppercase">Total<br>Terima</th>
              </tr>

              <!-- Header Row 2 — per section type -->
              <tr>
                <template v-if="section.type === 'uang_makan'">
                  <!-- ALL IN: Uang Makan columns -->
                  <template v-for="dateStr in dates" :key="'sh-' + section.key + '-' + dateStr">
                    <th class="px-1 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">Kode</th>
                    <th class="px-1 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">H/A</th>
                    <th v-if="section.key !== 'jakarta'" class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">Upah/Hari</th>
                    <th class="px-1 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">L/M</th>
                    <th class="px-1 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">Lembur</th>
                    <th class="px-2 py-2 text-right font-bold text-(--text-muted) uppercase text-[9px]">Nominal</th>
                  </template>
                </template>
                <template v-else>
                  <!-- PRINTING / SPC: Lembur columns -->
                  <template v-for="dateStr in dates" :key="'sh-' + section.key + '-' + dateStr">
                    <th class="px-1 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">Kode</th>
                    <th class="px-1 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">H/A</th>
                    <th v-if="section.key !== 'spc_jakarta'" class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">Upah/Hari</th>
                    <th class="px-1 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">L/M</th>
                    <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">Lbr</th>
                    <th class="px-1 py-2 text-center font-bold text-(--text-muted) uppercase text-[9px]">Nominal</th>
                  </template>
                </template>
              </tr>
            </thead>

            <tbody class="divide-y divide-(--border-soft)">
              <!-- Employee Rows -->
              <tr
                v-for="(item, index) in section.data"
                :key="item.id"
                class="hover:bg-(--bg-elevated) transition-colors group"
              >
                <td class="px-2 py-3 text-center text-(--text-muted) border-r border-(--border-soft) sticky left-0 bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10">{{ index + 1 }}</td>
                <td class="px-2 py-3 text-center text-(--text-muted) border-r border-(--border-soft) sticky left-[32px] bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10 text-xs">{{ item.id }}</td>
                <td class="px-3 py-3 font-bold text-(--text-main) border-r border-(--border-soft) sticky left-[76px] bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10 truncate">{{ item.name }}</td>
                <td class="px-3 py-3 text-(--text-main) border-r border-(--border-soft)">{{ item.jabatan }}</td>
                <td class="px-2 py-3 text-center text-(--text-muted) border-r border-(--border-soft)">{{ item.gender }}</td>
                <td class="px-3 py-3 text-right font-medium border-r border-(--border-soft)">{{ item.tj_mk ? formatNumber(item.tj_mk) : '' }}</td>
                <td class="px-3 py-3 text-right font-medium border-r border-(--border-soft)">{{ item.tunjangan ? formatNumber(item.tunjangan) : '' }}</td>
                <td class="px-3 py-3 text-right font-medium border-r border-(--border-soft)">{{ item.upah_per_hari ? formatNumber(item.upah_per_hari) : '' }}</td>
                <td class="px-3 py-3 text-right font-medium border-r border-(--border-soft)">{{ item.upah_lembur_per_jam ? formatNumber(item.upah_lembur_per_jam) : '' }}</td>

                <!-- Daily cells — ALL IN (uang_makan) -->
                <template v-if="section.type === 'uang_makan'">
                  <template v-for="dateStr in dates" :key="'dc-' + item.id + '-' + dateStr">
                    <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', item.days[dateStr]?.kode ? 'text-blue-600 font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.kode || '-' }}
                    </td>
                    <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', item.days[dateStr]?.ha && item.days[dateStr]?.ha !== '-' ? 'font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.ha || '-' }}
                    </td>
                    <td v-if="section.key !== 'jakarta'" :class="['px-2 py-3 text-right border-r border-(--border-soft) text-xs', item.days[dateStr]?.upah_per_hari > 0 ? 'text-emerald-600 font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.upah_per_hari > 0 ? formatNumber(item.days[dateStr].upah_per_hari) : '-' }}
                    </td>
                    <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', item.days[dateStr]?.lm ? 'text-purple-600 font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.lm || '-' }}
                    </td>
                    <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', item.days[dateStr]?.lembur ? 'text-orange-600 font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.lembur || '-' }}
                    </td>
                    <td :class="['px-2 py-3 text-right text-xs', item.days[dateStr]?.nominal > 0 ? 'text-green-600 font-bold' : 'text-gray-300']">
                      {{ item.days[dateStr]?.nominal > 0 ? formatNumber(item.days[dateStr].nominal) : '-' }}
                    </td>
                  </template>
                </template>

                <!-- Daily cells — PRINTING / SPC (lembur) -->
                <template v-else>
                  <template v-for="dateStr in dates" :key="'dc-' + item.id + '-' + dateStr">
                    <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', item.days[dateStr]?.kode ? 'text-blue-600 font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.kode || '-' }}
                    </td>
                    <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', item.days[dateStr]?.ha && item.days[dateStr]?.ha !== '-' ? 'font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.ha || '-' }}
                    </td>
                    <td v-if="section.key !== 'spc_jakarta'" :class="['px-2 py-3 text-right border-r border-(--border-soft) text-xs', item.days[dateStr]?.upah_per_hari > 0 ? 'text-emerald-600 font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.upah_per_hari > 0 ? formatNumber(item.days[dateStr].upah_per_hari) : '-' }}
                    </td>
                    <td :class="['px-1 py-3 text-right border-r border-(--border-soft) text-xs', item.days[dateStr]?.lm > 0 ? 'text-purple-600 font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.lm > 0 ? formatNumber(item.days[dateStr].lm) : '-' }}
                    </td>
                    <td :class="['px-2 py-3 text-right border-r border-(--border-soft) text-xs', item.days[dateStr]?.lembur > 0 ? 'text-amber-600 font-medium' : 'text-gray-300']">
                      {{ item.days[dateStr]?.lembur > 0 ? formatNumber(item.days[dateStr].lembur) : '-' }}
                    </td>
                    <td :class="['px-1 py-3 text-right text-xs', item.days[dateStr]?.nominal > 0 ? 'text-green-600 font-bold' : 'text-gray-300']">
                      {{ item.days[dateStr]?.nominal > 0 ? formatNumber(item.days[dateStr].nominal) : '-' }}
                    </td>
                  </template>
                </template>

                <!-- Employee Totals -->
                <td class="px-3 py-3 text-right font-bold text-emerald-600 border-r border-(--border-soft)">{{ item.total_hari_kerja != null ? formatNumber(item.total_hari_kerja) : '-' }}</td>
                <td class="px-3 py-3 text-right font-bold text-green-600 border-r border-(--border-soft)">{{ item.total_overtime ? formatNumber(item.total_overtime) : '-' }}</td>
                <td class="px-3 py-3 text-right font-bold text-amber-600 border-r border-(--border-soft)">{{ item.total_uang_makan ? formatNumber(item.total_uang_makan) : '-' }}</td>
                <td class="px-3 py-3 text-right font-bold text-(--primary)">{{ item.total_terima ? formatNumber(item.total_terima) : '-' }}</td>
              </tr>

              <!-- Section Totals -->
              <tr v-if="section.totals && section.totals.count > 0" class="bg-green-100/50 font-bold">
                <td :colspan="9" class="px-3 py-2 text-right text-xs uppercase">
                  TOTAL {{ section.label }}
                </td>
                <template v-for="dateStr in dates" :key="'st-' + section.key + '-' + dateStr">
                  <td :colspan="(section.key === 'jakarta' || section.key === 'spc_jakarta') ? 5 : 6" class="px-2 py-2"></td>
                </template>
                <td class="px-3 py-2 text-right text-xs text-emerald-700 border-r">{{ formatNumber(section.totals.total_hari_kerja) }}</td>
                <td class="px-3 py-2 text-right text-xs text-green-700 border-r">{{ formatNumber(section.totals.total_overtime) }}</td>
                <td class="px-3 py-2 text-right text-xs text-amber-700 border-r">{{ formatNumber(section.totals.total_uang_makan) }}</td>
                <td class="px-3 py-2 text-right text-xs text-(--primary)">{{ formatNumber(section.totals.total_terima) }}</td>
              </tr>
            </tbody>
          </table>
        </template>

        <!-- Grand Totals -->
        <div v-if="grandTotals" class="px-4 py-3 bg-blue-50 font-bold border-t-2 border-(--primary) flex justify-end gap-4 text-sm">
          <span class="uppercase text-(--primary)">TOTAL KESELURUHAN</span>
          <span class="text-emerald-700">{{ formatNumber(grandTotals.total_hari_kerja) }}</span>
          <span class="text-green-700">{{ formatNumber(grandTotals.total_overtime) }}</span>
          <span class="text-amber-700">{{ formatNumber(grandTotals.total_uang_makan) }}</span>
          <span class="text-(--primary)">{{ formatNumber(grandTotals.total_terima) }}</span>
        </div>
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
const monthLabel = ref('')
const grandTotals = ref(null)
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
    const res = await get(`/api/v1/reports/lembur/combined-detail-pre?${params.toString()}`)
    sections.value = res.sections || []
    dates.value = res.dates || []
    monthLabel.value = res.month_label || ''
    grandTotals.value = res.grand_totals || null
  } catch (err) {
    notification.addNotification('Gagal mengambil data laporan', 'error')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  const token = localStorage.getItem('token')
  const params = buildParams()
  const url = `/api/v1/reports/lembur/combined-detail-pre/export?${params.toString()}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      const safeName = monthLabel.value.replace(/\s+/g, '_').replace(/[()]/g, '')
      link.setAttribute('download', `Rincian_Gaji_Overtime_Pre_${safeName}.xlsx`)
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