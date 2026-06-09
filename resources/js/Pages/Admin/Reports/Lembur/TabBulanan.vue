<template>
  <div>
    <!-- Filter Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
      <div class="flex items-center gap-3">
        <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider">Tahun:</span>
        <select
          v-model="currentYear"
          @change="fetchData"
          class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg focus:ring-(--primary) focus:border-(--primary) p-2"
        >
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
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
      <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat data...</p>
      </div>

      <div v-else-if="data.length === 0" class="p-12 text-center text-(--text-muted)">
        Tidak ada data lembur untuk tahun yang dipilih.
      </div>

      <div v-else class="overflow-x-auto max-h-[65vh]">
        <table class="min-w-full divide-y divide-(--border-soft) text-xs">
          <thead class="bg-(--bg-elevated) sticky top-0 z-20">
            <tr>
              <th class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase sticky left-0 bg-(--bg-elevated) z-30">No</th>
              <th class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase sticky left-[40px] bg-(--bg-elevated) z-30 w-48">Nama</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase">Gaji Pokok</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase">Premi</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase">Tj. MK</th>
              <th class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase">Tunjangan</th>
              <th
                v-for="month in months"
                :key="month"
                class="px-4 py-3 text-center font-bold text-(--text-muted) uppercase border-l border-(--border-soft) min-w-[140px]"
              >
                {{ month }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr
              v-for="(item, index) in data"
              :key="item.id"
              class="hover:bg-(--bg-elevated) transition-colors group"
            >
              <td class="px-3 py-4 text-(--text-muted) sticky left-0 bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10">{{ index + 1 }}</td>
              <td class="px-4 py-4 font-bold text-(--text-main) sticky left-[40px] bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10 truncate">{{ item.name }}</td>
              <td class="px-4 py-4 text-right font-medium text-(--text-main)">{{ formatNumber(item.gaji_pokok) }}</td>
              <td class="px-4 py-4 text-right font-medium text-(--text-main)">{{ formatNumber(item.premi) }}</td>
              <td class="px-4 py-4 text-right font-medium text-(--text-main)">{{ formatNumber(item.tj_mk) }}</td>
              <td class="px-4 py-4 text-right font-medium text-(--text-main)">{{ formatNumber(item.tunjangan) }}</td>

              <td v-for="m in 12" :key="m" class="px-4 py-3 border-l border-(--border-soft)">
                <div v-if="item.months && item.months[m]" class="space-y-1 text-[10px]">
                  <div class="flex justify-between gap-2 border-b border-dashed border-(--border-soft) pb-1">
                    <span class="text-(--text-muted)">Upah/jam:</span>
                    <span class="font-bold text-(--text-main)">{{ formatNumber(item.months[m].hourlyRate) }}</span>
                  </div>
                  <div class="flex justify-between gap-2 border-b border-dashed border-(--border-soft) pb-1">
                    <span class="text-(--text-muted)">Lembur:</span>
                    <span class="font-bold text-blue-600">{{ item.months[m].lm || 0 }} / {{ item.months[m].calculated }}</span>
                  </div>
                  <div class="flex justify-between gap-2">
                    <span class="text-(--text-muted)">Uang Lbr:</span>
                    <span class="font-bold text-green-600">{{ formatNumber(item.months[m].overtimePay) }}</span>
                  </div>
                </div>
                <div v-else class="text-[10px] text-(--text-muted) text-center">-</div>
              </td>
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

const currentYear = ref(new Date().getFullYear())
const data = ref([])
const loading = ref(false)

const months = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
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

async function fetchData() {
  if (!props.groups.length) return
  loading.value = true
  try {
    const params = new URLSearchParams({ year: currentYear.value })
    props.groups.forEach(g => params.append('groups[]', g))
    const res = await get(`/api/v1/reports/lembur/bulanan?${params.toString()}`)
    data.value = res.data || []
  } catch (err) {
    notification.addNotification('Gagal mengambil data laporan bulanan', 'error')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
    const token = localStorage.getItem('token');
    const params = new URLSearchParams({ year: currentYear.value });
    props.groups.forEach(g => params.append('groups[]', g));
    const url = `/api/v1/reports/lembur/bulanan/export?${params.toString()}`;
    fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
        .then(r => r.blob())
        .then(blob => {
            const downloadUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.setAttribute('download', `Laporan_Lembur_Bulanan_${currentYear.value}.xlsx`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(downloadUrl);
        })
        .catch(err => notification.addNotification('Gagal export Excel', 'error'));
}

function openPrint() {
    const token = localStorage.getItem('token');
    const params = new URLSearchParams({ year: currentYear.value });
    props.groups.forEach(g => params.append('groups[]', g));
    const url = `/api/v1/reports/lembur/bulanan/print?${params.toString()}`;
    fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
        .then(r => r.text())
        .then(html => {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(html);
            printWindow.document.close();
        })
        .catch(err => notification.addNotification('Gagal membuka print', 'error'));
}

watch(() => props.groups, fetchData, { immediate: true })
</script>
