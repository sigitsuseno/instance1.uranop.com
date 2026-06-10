<template>
  <div>
    <!-- Filter Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
      <div class="flex items-center gap-3">
        <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal:</span>
        <input
          type="date"
          v-model="currentDate"
          @change="fetchData"
          class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg focus:ring-(--primary) focus:border-(--primary) p-2"
        />
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
        Tidak ada data lembur untuk tanggal yang dipilih.
      </div>

      <div v-else class="overflow-x-auto max-h-[65vh]">
        <table class="min-w-full divide-y divide-(--border-soft) text-xs whitespace-nowrap">
          <thead class="bg-(--bg-elevated) sticky top-0 z-20">
            <tr>
              <th rowspan="2" class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-0 bg-(--bg-elevated) z-30">No</th>
              <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-[40px] bg-(--bg-elevated) z-30 w-48">Nama</th>
              <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Bagian / Jabatan</th>
              <th rowspan="2" class="px-2 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">L/P</th>
              <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tj. MK</th>
              <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Tunjangan</th>
              <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Upah Lembur<br>Per Jam</th>
              <th colspan="6" class="px-4 py-2 text-center font-bold text-(--text-main) bg-(--bg-soft) uppercase border-b border-(--border-soft)">
                {{ formattedDate }}
              </th>
            </tr>
            <tr>
              <th class="px-3 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Kode</th>
              <th class="px-3 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">H/A</th>
              <th class="px-3 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Upah/Hari</th>
              <th class="px-3 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">L/M</th>
              <th class="px-3 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Lembur</th>
              <th class="px-4 py-2 text-right font-bold text-(--text-muted) uppercase">Nominal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-for="(item, index) in data" :key="item.id" class="hover:bg-(--bg-elevated) transition-colors group">
              <td class="px-3 py-3 text-center text-(--text-muted) border-r border-(--border-soft) sticky left-0 bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10">{{ index + 1 }}</td>
              <td class="px-4 py-3 font-bold text-(--text-main) border-r border-(--border-soft) sticky left-[40px] bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10 truncate">{{ item.name }}</td>
              <td class="px-4 py-3 text-(--text-main) border-r border-(--border-soft)">{{ item.jabatan }}</td>
              <td class="px-2 py-3 text-center text-(--text-muted) border-r border-(--border-soft)">{{ item.gender === 'male' ? 'L' : (item.gender === 'female' ? 'P' : item.gender) }}</td>
              <td class="px-4 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.tj_mk ? formatNumber(item.tj_mk) : '' }}</td>
              <td class="px-4 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.tunjangan ? formatNumber(item.tunjangan) : '' }}</td>
              <td class="px-4 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.upah_lembur_per_jam ? formatNumber(item.upah_lembur_per_jam) : '' }}</td>
              
              <!-- Daily Details -->
              <td class="px-3 py-3 text-center font-medium text-blue-600 border-r border-(--border-soft) bg-blue-50/10">{{ item.kode || '-' }}</td>
              <td class="px-3 py-3 text-center font-medium text-(--text-main) border-r border-(--border-soft) bg-blue-50/10">{{ item.ha === '-' ? '-' : item.ha }}</td>
              <td class="px-3 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft) bg-blue-50/10">{{ item.upah_per_hari ? formatNumber(item.upah_per_hari) : '-' }}</td>
              <td class="px-3 py-3 text-center font-medium text-(--text-main) border-r border-(--border-soft) bg-blue-50/10">{{ item.lembur_minggu || '' }}</td>
              <td class="px-3 py-3 text-center font-medium text-orange-600 border-r border-(--border-soft) bg-blue-50/10">{{ item.lembur || '' }}</td>
              <td class="px-4 py-3 text-right font-bold text-green-600 bg-blue-50/10">{{ item.nominal ? formatNumber(item.nominal) : '' }}</td>
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

const currentDate = ref(new Date().toISOString().split('T')[0])
const data = ref([])
const loading = ref(false)

const formattedDate = computed(() => {
  const d = new Date(currentDate.value)
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  }).format(d).toUpperCase()
})

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num || 0)
}

async function fetchData() {
  if (!props.groups.length) return
  loading.value = true
  try {
    const params = new URLSearchParams({ date: currentDate.value })
    props.groups.forEach(g => params.append('groups[]', g))
    const res = await get(`/api/v1/reports/lembur/harian?${params.toString()}`)
    data.value = res.data || []
  } catch (err) {
    notification.addNotification('Gagal mengambil data laporan harian', 'error')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
    const token = localStorage.getItem('token');
    const params = new URLSearchParams({ date: currentDate.value });
    props.groups.forEach(g => params.append('groups[]', g));
    const url = `/api/v1/reports/lembur/harian/export?${params.toString()}`;
    fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
        .then(r => r.blob())
        .then(blob => {
            const downloadUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.setAttribute('download', `Laporan_Lembur_Harian_${currentDate.value}.xlsx`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(downloadUrl);
        })
        .catch(err => notification.addNotification('Gagal export Excel', 'error'));
}

function openPrint() {
    const token = localStorage.getItem('token');
    const params = new URLSearchParams({ date: currentDate.value });
    props.groups.forEach(g => params.append('groups[]', g));
    const url = `/api/v1/reports/lembur/harian/print?${params.toString()}`;
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
