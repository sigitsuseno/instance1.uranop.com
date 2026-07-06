<template>
  <BaseCard>
    <div v-if="!periodId" class="p-12 text-center text-(--text-muted)">
      Silakan pilih periode terlebih dahulu.
    </div>

    <div v-else-if="loading" class="p-12 flex flex-col items-center justify-center">
      <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
      <p class="text-(--text-muted)">Memuat data...</p>
    </div>

    <div v-else-if="data.length === 0" class="p-12 text-center text-(--text-muted)">
      Tidak ada data rekab uang makan untuk periode yang dipilih.
    </div>

    <div v-else class="overflow-auto max-h-[65vh]">
      <table class="min-w-full divide-y divide-(--border-soft) text-[11px] whitespace-nowrap">
        <thead class="bg-(--bg-elevated) sticky top-0 z-20">
          <tr>
            <th rowspan="2" class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-0 bg-(--bg-elevated) z-30">No</th>
            <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-[40px] bg-(--bg-elevated) z-30 w-52">Nama</th>
            <th rowspan="2" class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Group</th>
            <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Jabatan</th>
            <th colspan="4" class="px-2 py-2 text-center font-bold text-(--text-main) bg-amber-50/50 border-b border-(--border-soft) uppercase">Lembur Sabtu</th>
            <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft) bg-green-50/30">Uang Makan</th>
            <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft) bg-blue-50/20">Lembur<br>Sabtu</th>
            <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft) bg-red-50/20">Lembur<br>Minggu</th>
            <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Insentif</th>
            <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">PBLT</th>
            <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Revisi</th>
            <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-main) uppercase border-r border-(--border-soft) bg-(--primary)/10">TOTAL</th>
          </tr>
          <tr>
            <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px] bg-amber-50/50">2</th>
            <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px] bg-amber-50/50">FULL</th>
            <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px] bg-amber-50/50">1/2 HK</th>
            <th class="px-2 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px] bg-amber-50/50">L</th>
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
            <td class="px-3 py-3 text-center font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.group_name || '-' }}</td>
            <td class="px-4 py-3 text-(--text-main) border-r border-(--border-soft)">{{ item.jabatan || '-' }}</td>
            <td class="px-2 py-3 text-center font-mono font-medium border-r border-(--border-soft)" :class="item.counts['2'] > 0 ? 'text-amber-700' : 'text-(--text-muted)'">{{ item.counts['2'] || '-' }}</td>
            <td class="px-2 py-3 text-center font-mono font-medium border-r border-(--border-soft)" :class="item.counts.FULL > 0 ? 'text-amber-700' : 'text-(--text-muted)'">{{ item.counts.FULL || '-' }}</td>
            <td class="px-2 py-3 text-center font-mono font-medium border-r border-(--border-soft)" :class="item.counts.HALF > 0 ? 'text-purple-700' : 'text-(--text-muted)'">{{ item.counts.HALF || '-' }}</td>
            <td class="px-2 py-3 text-center font-mono font-medium border-r border-(--border-soft)" :class="item.counts.L > 0 ? 'text-purple-700' : 'text-(--text-muted)'">{{ item.counts.L || '-' }}</td>
            <td class="px-4 py-3 text-right font-medium border-r border-(--border-soft)" :class="item.nominals.uang_makan > 0 ? 'text-green-700' : 'text-(--text-muted)'">{{ item.nominals.uang_makan > 0 ? formatNumber(item.nominals.uang_makan) : '-' }}</td>
            <td class="px-4 py-3 text-right font-medium border-r border-(--border-soft)" :class="item.nominals.lembur_sabtu > 0 ? 'text-blue-700' : 'text-(--text-muted)'">{{ item.nominals.lembur_sabtu > 0 ? formatNumber(item.nominals.lembur_sabtu) : '-' }}</td>
            <td class="px-4 py-3 text-right font-medium border-r border-(--border-soft)" :class="item.nominals.lembur_minggu > 0 ? 'text-red-700' : 'text-(--text-muted)'">{{ item.nominals.lembur_minggu > 0 ? formatNumber(item.nominals.lembur_minggu) : '-' }}</td>
            <td class="px-4 py-3 text-right border-r border-(--border-soft) text-(--text-muted)">{{ item.nominals.insentif > 0 ? formatNumber(item.nominals.insentif) : '-' }}</td>
            <td class="px-4 py-3 text-right border-r border-(--border-soft) text-(--text-muted)">{{ item.nominals.pblt > 0 ? formatNumber(item.nominals.pblt) : '-' }}</td>
            <td class="px-4 py-3 text-right border-r border-(--border-soft) text-(--text-muted)">{{ item.nominals.revisi > 0 ? formatNumber(item.nominals.revisi) : '-' }}</td>
            <td class="px-4 py-3 text-right font-bold border-r border-(--border-soft)" :class="item.total > 0 ? 'text-(--primary)' : 'text-(--text-muted)'">{{ item.total > 0 ? formatNumber(item.total) : '-' }}</td>
          </tr>

          <!-- Grand Total Row -->
          <tr class="bg-(--bg-elevated) font-bold border-t-2 border-(--primary)/30">
            <td colspan="4" class="px-4 py-3 text-right text-(--text-main) border-r border-(--border-soft)">TOTAL</td>
            <td class="px-2 py-3 text-center font-mono text-(--text-main) border-r border-(--border-soft)">{{ totalCounts['2'] }}</td>
            <td class="px-2 py-3 text-center font-mono text-(--text-main) border-r border-(--border-soft)">{{ totalCounts.FULL }}</td>
            <td class="px-2 py-3 text-center font-mono text-(--text-main) border-r border-(--border-soft)">{{ totalCounts.HALF }}</td>
            <td class="px-2 py-3 text-center font-mono text-(--text-main) border-r border-(--border-soft)">{{ totalCounts.L }}</td>
            <td class="px-4 py-3 text-right text-green-700 border-r border-(--border-soft)">{{ formatNumber(totalNominals.uang_makan) }}</td>
            <td class="px-4 py-3 text-right text-blue-700 border-r border-(--border-soft)">{{ formatNumber(totalNominals.lembur_sabtu) }}</td>
            <td class="px-4 py-3 text-right text-red-700 border-r border-(--border-soft)">{{ formatNumber(totalNominals.lembur_minggu) }}</td>
            <td class="px-4 py-3 text-right border-r border-(--border-soft)">{{ formatNumber(totalNominals.insentif) }}</td>
            <td class="px-4 py-3 text-right border-r border-(--border-soft)">{{ formatNumber(totalNominals.pblt) }}</td>
            <td class="px-4 py-3 text-right border-r border-(--border-soft)">{{ formatNumber(totalNominals.revisi) }}</td>
            <td class="px-4 py-3 text-right text-(--primary) border-r border-(--border-soft) text-base">{{ formatNumber(grandTotal) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </BaseCard>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'

const props = defineProps({
  periodId: { type: [Number, String], default: null },
  groups: { type: Array, default: () => [] }
})

const emit = defineEmits(['hasData', 'loading'])

const { get } = useApi()
const notification = useNotificationStore()

const data = ref([])
const monthLabel = ref('')
const loading = ref(false)

const totalCounts = computed(() => {
  return data.value.reduce((acc, item) => {
    acc['2']  += item.counts['2']  || 0
    acc.FULL += item.counts.FULL || 0
    acc.HALF += item.counts.HALF || 0
    acc.L    += item.counts.L    || 0
    return acc
  }, { '2': 0, FULL: 0, HALF: 0, L: 0 })
})

const totalNominals = computed(() => {
  return data.value.reduce((acc, item) => {
    acc.uang_makan    += item.nominals.uang_makan    || 0
    acc.lembur_sabtu  += item.nominals.lembur_sabtu  || 0
    acc.lembur_minggu += item.nominals.lembur_minggu || 0
    acc.insentif      += item.nominals.insentif      || 0
    acc.pblt          += item.nominals.pblt          || 0
    acc.revisi        += item.nominals.revisi        || 0
    return acc
  }, { uang_makan: 0, lembur_sabtu: 0, lembur_minggu: 0, insentif: 0, pblt: 0, revisi: 0 })
})

const grandTotal = computed(() => {
  const t = totalNominals.value
  return t.uang_makan + t.lembur_sabtu + t.lembur_minggu + t.insentif + t.pblt + t.revisi
})

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num || 0)
}

async function fetchData() {
  if (!props.groups.length || !props.periodId) return
  loading.value = true
  emit('loading', true)
  try {
    const params = new URLSearchParams({ period_id: props.periodId })
    props.groups.forEach(g => params.append('groups[]', g))
    const res = await get(`/api/v1/reports/uang-makan/rekab?${params.toString()}`)
    data.value = res.data || []
    monthLabel.value = res.month_label || ''
    emit('hasData', data.value.length > 0)
  } catch (err) {
    notification.addNotification('Gagal mengambil data rekab uang makan', 'error')
  } finally {
    loading.value = false
    emit('loading', false)
  }
}

function exportExcel() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams({ period_id: props.periodId })
  props.groups.forEach(g => params.append('groups[]', g))
  const url = `/api/v1/reports/uang-makan/rekab/export?${params.toString()}`
  const safeName = (monthLabel.value || 'Rekab_Uang_Makan').replace(/\s+/g, '_').replace(/[()]/g, '')
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', `Rekab_Uang_Makan_${safeName}.xlsx`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(() => notification.addNotification('Gagal export Excel', 'error'))
}

function openPrint() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams({ period_id: props.periodId })
  props.groups.forEach(g => params.append('groups[]', g))
  const url = `/api/v1/reports/uang-makan/rekab/print?${params.toString()}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.text())
    .then(html => {
      const printWindow = window.open('', '_blank')
      printWindow.document.write(html)
      printWindow.document.close()
    })
    .catch(() => notification.addNotification('Gagal membuka print', 'error'))
}

watch(() => [props.periodId, props.groups], fetchData, { deep: true })

defineExpose({ exportExcel, openPrint })
</script>
