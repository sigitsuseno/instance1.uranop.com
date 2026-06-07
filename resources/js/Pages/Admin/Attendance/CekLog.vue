<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Cek Log Kehadiran</h1>
        <p class="text-sm text-(--text-muted) mt-1">Lihat detail log absensi per hari untuk setiap karyawan.</p>
      </div>
      <BaseButton variant="secondary" size="sm" @click="$router.push('/admin/attendance/import')">
        Kembali
      </BaseButton>
    </div>

    <BaseCard class="mb-6">
      <div class="flex items-center gap-4">
        <div class="w-48">
          <label class="block text-sm font-medium text-(--text-main) mb-1">Pilih Tanggal</label>
          <input 
            type="date" 
            v-model="selectedDate" 
            @change="fetchLogs"
            class="w-full rounded-md border border-(--border-soft) bg-(--bg-main) px-3 py-2 text-sm text-(--text-main) outline-none focus:border-(--primary) focus:ring-1 focus:ring-(--primary)"
          />
        </div>
        <div class="flex items-end pb-1 gap-4 text-sm text-(--text-muted) flex-1 pt-6">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-green-500"></span>
            <span>Di dalam rentang kerja</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-gray-300"></span>
            <span>Di luar rentang kerja</span>
          </div>
        </div>
      </div>
    </BaseCard>

    <BaseCard>
      <div v-if="loading" class="py-12 text-center">
        <div class="inline-block animate-spin w-8 h-8 border-4 border-(--primary) border-t-transparent rounded-full mb-4"></div>
        <p class="text-(--text-muted)">Memuat data log absensi...</p>
      </div>

      <div v-else-if="logs.length === 0" class="py-12 text-center text-(--text-muted)">
        Tidak ada data untuk tanggal {{ selectedDate }}
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-(--border-soft)">
              <th class="py-3 px-4 font-semibold text-sm text-(--text-main)">NIP</th>
              <th class="py-3 px-4 font-semibold text-sm text-(--text-main)">Nama</th>
              <th class="py-3 px-4 font-semibold text-sm text-(--text-main)">Pola Kerja</th>
              <th class="py-3 px-4 font-semibold text-sm text-(--text-main)">Log Scans</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log.employee_code" class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/50 transition-colors">
              <td class="py-3 px-4 text-sm text-(--text-main)">{{ log.employee_code }}</td>
              <td class="py-3 px-4 text-sm text-(--text-main) font-medium">{{ log.name }}</td>
              <td class="py-3 px-4 text-sm">
                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                  {{ log.work_pattern }}
                </span>
              </td>
              <td class="py-3 px-4">
                <div class="flex flex-wrap gap-2">
                  <span v-if="log.scans.length === 0" class="text-xs text-(--text-muted) italic">Tidak ada scan</span>
                  <span 
                    v-for="scan in log.scans" 
                    :key="scan.id"
                    :class="[
                      'inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium',
                      scan.in_range 
                        ? 'bg-green-100 text-green-800 border border-green-200' 
                        : 'bg-gray-100 text-gray-600 border border-gray-200'
                    ]"
                  >
                    {{ scan.time }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import { useApi } from '../../../composables/useApi'

const { get } = useApi()

const today = new Date().toISOString().split('T')[0]
const selectedDate = ref(today)
const loading = ref(false)
const logs = ref([])

async function fetchLogs() {
  if (!selectedDate.value) return
  
  loading.value = true
  try {
    const response = await get(`/api/v1/attendance/logs/cek?date=${selectedDate.value}`)
    logs.value = response.data || []
  } catch (error) {
    console.error('Failed to fetch logs:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchLogs()
})
</script>
