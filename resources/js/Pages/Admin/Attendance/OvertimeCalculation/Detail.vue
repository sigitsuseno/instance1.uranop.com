<template>
  <div class="p-4 sm:p-6 lg:p-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div class="flex items-start gap-3">
        <router-link :to="`/admin/attendance/overtime-calculation?start_date=${startDate}&end_date=${endDate}`" class="mt-1 text-(--text-muted) hover:text-(--text-main) transition">
          <i class="bx bx-arrow-back text-2xl"></i>
        </router-link>
        <div>
          <h1 class="text-2xl font-bold text-(--text-main)">Detail Overtime: {{ employee?.name || 'Loading...' }}</h1>
          <p class="text-sm text-(--text-muted) mt-1">
            {{ employee?.employee_code || '-' }} &bull; {{ employee?.department?.name || '-' }} &bull; {{ employee?.position?.name || '-' }}
          </p>
          <p class="text-xs text-(--text-soft) mt-1">
            <i class="bx bx-calendar-alt mr-1"></i> Periode: {{ formatDateRange() }}
          </p>
        </div>
      </div>

      <!-- Navigation -->
      <div class="flex gap-2">
          <router-link 
              v-if="navigation.prev_id"
              :to="getNavigationUrl(navigation.prev_id)"
              class="px-4 py-2 bg-(--bg-card) border border-(--border-soft) rounded-lg text-(--text-main) hover:bg-(--bg-elevated) transition flex items-center gap-2 shadow-sm text-sm"
          >
              <i class="bx bx-chevron-left"></i> Sebelumnya
          </router-link>
          <div v-else class="px-4 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) flex items-center gap-2 shadow-sm text-sm opacity-50 cursor-not-allowed">
              <i class="bx bx-chevron-left"></i> Sebelumnya
          </div>
          
          <router-link 
              v-if="navigation.next_id"
              :to="getNavigationUrl(navigation.next_id)"
              class="px-4 py-2 bg-(--bg-card) border border-(--border-soft) rounded-lg text-(--text-main) hover:bg-(--bg-elevated) transition flex items-center gap-2 shadow-sm text-sm"
          >
              Selanjutnya <i class="bx bx-chevron-right"></i>
          </router-link>
          <div v-else class="px-4 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) flex items-center gap-2 shadow-sm text-sm opacity-50 cursor-not-allowed">
              Selanjutnya <i class="bx bx-chevron-right"></i>
          </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm max-h-[70vh]">
      <div v-if="isLoading" class="p-8 text-center text-(--text-muted)">
        <i class="bx bx-loader-alt bx-spin text-3xl mb-2"></i>
        <p>Memuat data...</p>
      </div>
      <table v-else class="w-full text-sm text-left relative">
        <thead class="bg-(--bg-elevated) border-b border-(--border-soft) sticky top-0 z-10 shadow-sm">
          <tr>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs">Tanggal</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs">In</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs">Out</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">LM</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Total L/M</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Lembur HB</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Total LHB</th>
            <th class="px-4 py-3 font-medium text-(--text-muted) uppercase tracking-wider text-xs text-center">Total Lembur</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-(--border-soft)">
          <tr 
            v-for="day in days" 
            :key="day.date"
            class="transition"
            :class="isHoliday(day) ? 'bg-orange-50/30 dark:bg-orange-900/10 hover:bg-orange-50/50 dark:hover:bg-orange-900/20' : 'hover:bg-(--bg-elevated)/50'"
          >
            <!-- Tanggal -->
            <td class="px-4 py-3">
              <div class="font-medium text-(--text-main)">{{ formatDateString(day.date) }}</div>
              <div class="text-[10px] text-(--text-muted)">{{ getDayName(day.date) }}</div>
              <div v-if="day.shift_id" class="text-[10px] bg-(--bg-elevated) border border-(--border-soft) px-1 py-0.5 rounded inline-block mt-0.5 text-(--text-soft)">
                Shift
              </div>
            </td>

            <!-- View Mode -->
            <td class="px-4 py-3 font-mono text-xs" :class="day.check_in ? 'text-(--text-main)' : 'text-(--text-soft)'">
              {{ extractTime(day.check_in) }}
            </td>
            <td class="px-4 py-3 font-mono text-xs" :class="day.check_out ? 'text-(--text-main)' : 'text-(--text-soft)'">
              {{ extractTime(day.check_out) }}
            </td>
            <td class="px-4 py-3 text-center text-orange-600 dark:text-orange-400 font-medium">
              <span v-if="day.lm > 0">{{ formatMinutes(day.lm) }}</span>
              <span v-else class="text-(--text-soft)">0</span>
            </td>
            <td class="px-4 py-3 text-center text-orange-600 dark:text-orange-400 font-medium">
              <span v-if="day.lm_count > 0">{{ formatMinutes(day.lm_count) }}</span>
              <span v-else class="text-(--text-soft)">0</span>
            </td>
            <td class="px-4 py-3 text-center text-(--text-main) font-medium">
              <span v-if="day.overtime > 0">{{ formatMinutes(day.overtime) }}</span>
              <span v-else class="text-(--text-soft)">0</span>
            </td>
            <td class="px-4 py-3 text-center text-(--text-main) font-medium">
              <span v-if="day.overtime_count > 0">{{ formatMinutes(day.overtime_count) }}</span>
              <span v-else class="text-(--text-soft)">0</span>
            </td>
            <td class="px-4 py-3 text-center font-bold text-(--primary)">
              {{ formatMinutes((day.overtime_count || 0) + (day.lm_count || 0)) }}
            </td>
          </tr>
          <tr v-if="days.length === 0">
             <td colspan="8" class="px-4 py-8 text-center text-(--text-muted)">Tidak ada data absensi pada periode ini.</td>
          </tr>
        </tbody>
        <tfoot v-if="days.length > 0" class="bg-(--bg-elevated) border-t border-(--border-soft) sticky bottom-0 z-10 font-medium">
          <tr>
            <td colspan="3" class="px-4 py-3 text-right text-(--text-muted)">TOTAL:</td>
            <td class="px-4 py-3 text-center text-orange-600 dark:text-orange-400">{{ formatMinutes(totalLM) }}</td>
            <td class="px-4 py-3 text-center text-orange-600 dark:text-orange-400">{{ formatMinutes(totalLmCount) }}</td>
            <td class="px-4 py-3 text-center text-(--text-main)">{{ formatMinutes(totalActual) }}</td>
            <td class="px-4 py-3 text-center text-(--text-main)">{{ formatMinutes(totalLhbCount) }}</td>
            <td class="px-4 py-3 text-center font-bold text-(--primary) text-base">{{ formatMinutes(totalCalculated) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '../../../../composables/useApi'

const { get } = useApi()
const route = useRoute()

const isLoading = ref(true)
const employee = ref(null)
const days = ref([])
const navigation = ref({ prev_id: null, next_id: null })

const employeeId = computed(() => route.params.id)
const startDate = ref(route.query.start_date || '')
const endDate = ref(route.query.end_date || '')

function getNavigationUrl(empId) {
    return `/admin/attendance/overtime-calculation/${empId}?start_date=${startDate.value}&end_date=${endDate.value}`
}

function extractTime(datetimeStr) {
    if (!datetimeStr) return '--:--';
    if (datetimeStr.includes(' ')) {
        return datetimeStr.split(' ')[1].substring(0, 5);
    }
    if (datetimeStr.includes('T')) {
        const d = new Date(datetimeStr);
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false });
    }
    return datetimeStr.substring(0, 5);
}

function formatDateRange() {
  if (!startDate.value || !endDate.value) return ''
  const start = new Date(startDate.value)
  const end = new Date(endDate.value)
  const formatOptions = { day: 'numeric', month: 'short', year: 'numeric' }
  return `${start.toLocaleDateString('id-ID', formatOptions)} - ${end.toLocaleDateString('id-ID', formatOptions)}`
}

function formatDateString(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

function getDayName(dateStr) {
  if (!dateStr) return '';
  const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const d = new Date(dateStr);
  return days[d.getDay()];
}

function isHoliday(day) {
  if (day.status === 'libur' || day.status === 'off') return true;
  if (!day.date) return false;
  const d = new Date(day.date);
  return d.getDay() === 0; // Sunday
}

function formatMinutes(minutes) {
  if (!minutes || minutes === 0) return '0'
  return Number((minutes / 60).toFixed(2)).toString()
}

const totalActual = computed(() => {
  return days.value.reduce((sum, day) => {
    return sum + (day.overtime || 0);
  }, 0);
});

const totalLM = computed(() => {
  return days.value.reduce((sum, day) => {
    return sum + (day.lm || 0);
  }, 0);
});

const totalLmCount = computed(() => {
  return days.value.reduce((sum, day) => {
    return sum + (day.lm_count || 0);
  }, 0);
});

const totalLhbCount = computed(() => {
  return days.value.reduce((sum, day) => {
    return sum + (day.overtime_count || 0);
  }, 0);
});

const totalCalculated = computed(() => {
  return days.value.reduce((sum, day) => {
    return sum + (day.overtime_count || 0) + (day.lm_count || 0);
  }, 0);
});

async function fetchData() {
  isLoading.value = true
  try {
    // Fetch employee details
    const empRes = await get(`/api/v1/employees/${employeeId.value}`)
    if (empRes.data) {
      employee.value = empRes.data
    }

    // Fetch attendance prepares for this employee
    const attRes = await get(`/api/v1/attendance/prepare/list?employee_id=${employeeId.value}&start_date=${startDate.value}&end_date=${endDate.value}&per_page=100`)
    if (attRes.data && Array.isArray(attRes.data.data)) {
      days.value = attRes.data.data
    } else if (Array.isArray(attRes.data)) {
      days.value = attRes.data
    } else {
      days.value = []
    }
    
    // Sort by date just in case
    days.value.sort((a, b) => new Date(a.date) - new Date(b.date));

    // Fetch navigation data
    try {
      const navRes = await get(`/api/v1/attendance/prepare/overtime-navigation?employee_id=${employeeId.value}`)
      if (navRes) {
         navigation.value = { prev_id: navRes.prev_id, next_id: navRes.next_id }
      }
    } catch (e) {
      console.warn("Could not fetch navigation", e)
    }

  } catch (error) {
    console.error('Failed to fetch detail:', error)
  } finally {
    isLoading.value = false
  }
}

watch(employeeId, () => {
    fetchData()
})

onMounted(() => {
  fetchData()
})
</script>
