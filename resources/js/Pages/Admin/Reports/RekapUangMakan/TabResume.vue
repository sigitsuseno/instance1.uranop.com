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
      Tidak ada data resume untuk periode yang dipilih.
    </div>

    <div v-else class="overflow-auto max-h-[65vh]">
      <table class="min-w-full divide-y divide-(--border-soft) text-[11px] whitespace-nowrap">
        <thead class="bg-(--bg-elevated) sticky top-0 z-20">
          <tr>
            <th class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-0 bg-(--bg-elevated) z-30">No</th>
            <th class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-[40px] bg-(--bg-elevated) z-30 w-52">BAGIAN</th>
            <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft) bg-green-50/40">UANG MAKAN</th>
            <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft) bg-blue-50/30">LEMBUR<br>SABTU</th>
            <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft) bg-red-50/30">LEMBUR<br>MINGGU</th>
            <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">INSENTIF</th>
            <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">PBLT</th>
            <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">REVISI</th>
            <th class="px-4 py-3 text-right font-bold text-(--text-main) uppercase border-r border-(--border-soft) bg-(--primary)/10">TOTAL</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-(--border-soft)">
          <tr
            v-for="(item, index) in data"
            :key="'resume-' + index"
            class="hover:bg-(--bg-elevated) transition-colors group"
          >
            <td class="px-3 py-3 text-center text-(--text-muted) border-r border-(--border-soft) sticky left-0 bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10">{{ index + 1 }}</td>
            <td class="px-4 py-3 font-bold text-(--text-main) border-r border-(--border-soft) sticky left-[40px] bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10">{{ item.bagian }}</td>
            <td class="px-4 py-3 text-right font-medium border-r border-(--border-soft)" :class="item.uang_makan > 0 ? 'text-green-700' : 'text-(--text-muted)'">{{ item.uang_makan > 0 ? formatNumber(item.uang_makan) : '-' }}</td>
            <td class="px-4 py-3 text-right font-medium border-r border-(--border-soft)" :class="item.lembur_sabtu > 0 ? 'text-blue-700' : 'text-(--text-muted)'">{{ item.lembur_sabtu > 0 ? formatNumber(item.lembur_sabtu) : '-' }}</td>
            <td class="px-4 py-3 text-right font-medium border-r border-(--border-soft)" :class="item.lembur_minggu > 0 ? 'text-red-700' : 'text-(--text-muted)'">{{ item.lembur_minggu > 0 ? formatNumber(item.lembur_minggu) : '-' }}</td>
            <td class="px-4 py-3 text-right border-r border-(--border-soft) text-(--text-muted)">{{ item.insentif > 0 ? formatNumber(item.insentif) : '-' }}</td>
            <td class="px-4 py-3 text-right border-r border-(--border-soft) text-(--text-muted)">{{ item.pblt > 0 ? formatNumber(item.pblt) : '-' }}</td>
            <td class="px-4 py-3 text-right border-r border-(--border-soft) text-(--text-muted)">{{ item.revisi > 0 ? formatNumber(item.revisi) : '-' }}</td>
            <td class="px-4 py-3 text-right font-bold border-r border-(--border-soft)" :class="item.total > 0 ? 'text-(--primary)' : 'text-(--text-muted)'">{{ item.total > 0 ? formatNumber(item.total) : '-' }}</td>
          </tr>

          <!-- Grand Total Row -->
          <tr class="bg-(--bg-elevated) font-bold border-t-2 border-(--primary)/30">
            <td colspan="2" class="px-4 py-3 text-right text-(--text-main) border-r border-(--border-soft)">TOTAL</td>
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

const totalNominals = computed(() => {
  return data.value.reduce((acc, item) => {
    acc.uang_makan    += item.uang_makan    || 0
    acc.lembur_sabtu  += item.lembur_sabtu  || 0
    acc.lembur_minggu += item.lembur_minggu || 0
    acc.insentif      += item.insentif      || 0
    acc.pblt          += item.pblt          || 0
    acc.revisi        += item.revisi        || 0
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
    const res = await get(`/api/v1/reports/uang-makan/rekap-resume?${params.toString()}`)
    data.value = res.data || []
    monthLabel.value = res.month_label || ''
    emit('hasData', data.value.length > 0)
  } catch (err) {
    notification.addNotification('Gagal mengambil data resume uang makan', 'error')
  } finally {
    loading.value = false
    emit('loading', false)
  }
}

function exportExcel() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams({ period_id: props.periodId })
  props.groups.forEach(g => params.append('groups[]', g))
  const url = `/api/v1/reports/uang-makan/rekap-resume/export?${params.toString()}`
  const safeName = (monthLabel.value || 'Resume_Uang_Makan').replace(/\s+/g, '_').replace(/[()]/g, '')
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', `Resume_Uang_Makan_${safeName}.xlsx`)
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
  const url = `/api/v1/reports/uang-makan/rekap-resume/print?${params.toString()}`
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
