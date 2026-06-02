<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="sm:flex sm:justify-between sm:items-end border-b border-(--border-soft) pb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Roster Shift Kerja</h1>
        <p class="text-xs text-(--text-muted) mt-1 flex items-center gap-2">
          <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          Periode Roster: <span class="font-bold text-(--text-main)">25 {{ monthLabels[prevMonthIndex] }} {{ prevMonthYear }}</span> s/d <span class="font-bold text-(--text-main)">24 {{ monthLabels[selectedMonth - 1] }} {{ selectedYear }}</span>
        </p>
      </div>

      <!-- Header Actions & Filters -->
      <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto mt-4 sm:mt-0">
        <!-- Month Select -->
        <div>
          <input
            type="month"
            v-model="monthFilter"
            @change="handleMonthChange"
            class="h-10 px-3 rounded-md bg-(--bg-card) border border-(--border-strong) text-sm text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
          />
        </div>

        <!-- Department Filter -->
        <div>
          <select
            v-model="departmentFilter"
            class="h-10 px-3 rounded-md bg-(--bg-card) border border-(--border-strong) text-sm text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
          >
            <option :value="null">Semua Departemen</option>
            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Action Bar -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-3 flex gap-3 items-center flex-wrap justify-between">
      <div class="flex items-center gap-2 border-r border-(--border-soft) pr-3">
        <router-link
          :to="{ name: 'schedule.roster.import' }"
          class="h-10 px-4 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 font-semibold rounded-md flex items-center text-xs transition-colors"
        >
          Import Excel
        </router-link>

        <router-link
          :to="{ name: 'schedule.roster.generate' }"
          class="h-10 px-4 bg-(--primary) hover:bg-(--primary-hover) text-white font-semibold rounded-md flex items-center text-xs transition-colors"
        >
          Generate Roster
        </router-link>
      </div>

      <!-- Search Input -->
      <div class="flex-1 max-w-xs relative">
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Cari nama atau NIK..."
          class="w-full h-10 pl-3 pr-4 rounded-md bg-(--bg-card) border border-(--border-strong) text-xs text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
        />
      </div>

      <!-- Color Legend Reference -->
      <div class="flex items-center gap-3 overflow-x-auto py-1 no-scrollbar text-xs">
        <div class="flex items-center gap-1 bg-(--bg-elevated) px-2 py-1 rounded">
          <span class="w-4 h-4 rounded bg-blue-500 text-white flex items-center justify-center font-bold text-[8px]">PG</span>
          <span class="text-(--text-muted)">Pagi</span>
        </div>
        <div class="flex items-center gap-1 bg-(--bg-elevated) px-2 py-1 rounded">
          <span class="w-4 h-4 rounded bg-yellow-500 text-white flex items-center justify-center font-bold text-[8px]">SG</span>
          <span class="text-(--text-muted)">Siang</span>
        </div>
        <div class="flex items-center gap-1 bg-(--bg-elevated) px-2 py-1 rounded">
          <span class="w-4 h-4 rounded bg-purple-500 text-white flex items-center justify-center font-bold text-[8px]">ML</span>
          <span class="text-(--text-muted)">Malam</span>
        </div>
        <div class="flex items-center gap-1 bg-(--bg-elevated) px-2 py-1 rounded">
          <span class="w-4 h-4 rounded bg-rose-500 text-white flex items-center justify-center font-bold text-[8px]">L</span>
          <span class="text-(--text-muted)">Libur</span>
        </div>
      </div>
    </div>

    <!-- Main Schedule Table Card -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden relative">
      <div class="overflow-x-auto scrollbar-thin max-h-[500px]">
        <table class="w-full border-collapse text-left text-xs table-fixed" :style="{ minWidth: (250 + (daysInMonthRange.length * 40)) + 'px' }">
          <thead class="bg-(--bg-elevated) border-b border-(--border-soft) sticky top-0 z-20">
            <tr>
              <th class="w-48 px-4 py-3 bg-(--bg-elevated) sticky left-0 z-30 shadow-[1px_0_0_0_rgba(0,0,0,0.05)] dark:shadow-[1px_0_0_0_rgba(255,255,255,0.05)]">
                Karyawan & Organisasi
              </th>
              
              <!-- Daily headers -->
              <th
                v-for="(day, idx) in daysInMonthRange"
                :key="idx"
                class="w-[40px] px-0 py-2 text-center border-r border-(--border-soft)/50"
              >
                <div class="flex flex-col items-center">
                  <span class="text-[9px] text-(--text-muted)" :class="{ 'text-red-500': day.dow === 0 }">
                    {{ dayNameAbbr[day.dow] }}
                  </span>
                  <span class="font-bold text-xs" :class="{ 'text-red-500': day.dow === 0 }">
                    {{ day.day }}
                  </span>
                </div>
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-(--border-soft)">
            <tr v-if="filteredRoster.length === 0">
              <td :colspan="daysInMonthRange.length + 1" class="py-16 text-center text-(--text-muted)">
                Belum ada data roster untuk periode ini
              </td>
            </tr>

            <tr v-for="emp in filteredRoster" :key="emp.id" class="hover:bg-(--bg-elevated)/20 transition-colors group">
              <!-- Employee Sticky Column -->
              <td class="px-4 py-3 bg-(--bg-card) group-hover:bg-(--bg-elevated)/10 sticky left-0 z-10 shadow-[1px_0_0_0_rgba(0,0,0,0.05)] dark:shadow-[1px_0_0_0_rgba(255,255,255,0.05)] truncate">
                <div class="font-bold text-(--text-main)">{{ emp.name }}</div>
                <div class="text-[10px] text-(--text-muted) flex items-center gap-1 mt-0.5">
                  <span class="font-mono bg-(--bg-elevated) px-1 rounded">{{ emp.nik }}</span>
                  <span class="truncate">{{ emp.department }}</span>
                </div>
              </td>

              <!-- Daily Shift Cells -->
              <td
                v-for="(day, idx) in daysInMonthRange"
                :key="idx"
                @click="openOverrideModal(emp, day, idx)"
                class="px-0 py-1 text-center border-r border-(--border-soft)/30 cursor-pointer hover:bg-(--primary-glow)/10 transition-colors"
              >
                <div class="flex justify-center items-center h-8">
                  <span
                    v-if="emp.schedule[idx]"
                    class="w-[28px] h-[28px] rounded flex items-center justify-center font-bold text-[9px] shadow-sm"
                    :style="getShiftStyle(emp.schedule[idx])"
                  >
                    {{ emp.schedule[idx].code }}
                  </span>
                  <span v-else class="text-gray-300">-</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer Stats -->
      <div class="px-4 py-3 bg-(--bg-elevated)/20 border-t border-(--border-soft) flex items-center justify-between text-xs text-(--text-muted)">
        <div>
          Menampilkan <span class="font-bold text-(--text-main)">{{ filteredRoster.length }}</span> karyawan roster.
        </div>
      </div>
    </div>

    <!-- Override Shift Cell Modal -->
    <BaseModal :show="showModal" title="Update Jadwal Kerja" size="sm" @close="showModal = false">
      <div class="space-y-4" v-if="editingCell">
        <div class="bg-(--bg-elevated)/50 p-3 rounded-md border border-(--border-soft)">
          <div class="font-bold text-sm text-(--text-main)">{{ editingCell.employeeName }}</div>
          <div class="text-xs text-(--text-muted) mt-0.5">NIK: {{ editingCell.employeeNik }}</div>
          <div class="text-xs font-semibold text-(--primary) mt-1">
            {{ formatDateLabel(editingCell.date) }}
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-(--text-main) mb-2">Pilih Shift Pengganti</label>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="s in store.shifts"
              :key="s.id"
              @click="applyOverride(s)"
              class="flex flex-col items-center justify-center p-3 rounded-md border border-(--border-soft) hover:border-(--primary) hover:bg-(--primary-glow)/5 transition-all text-center"
            >
              <span class="w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold text-white shadow-sm" :style="{ backgroundColor: s.color }">
                {{ s.code }}
              </span>
              <span class="text-[10px] font-bold text-(--text-main) mt-1.5">{{ s.name }}</span>
            </button>
            
            <button
              @click="applyOverride({ code: 'L', name: 'Libur', is_off: true })"
              class="flex flex-col items-center justify-center p-3 rounded-md border-2 border-rose-100 bg-rose-50/40 hover:bg-rose-50 hover:border-rose-300 transition-all col-span-2 text-center"
            >
              <span class="w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold text-white bg-rose-500 shadow-sm">
                L
              </span>
              <span class="text-[10px] font-bold text-rose-700 mt-1.5">LIBUR (OFF DAY)</span>
            </button>
          </div>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showModal = false" class="w-full">Batal</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useScheduleStore } from '../../../../Stores/schedule'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'

const store = useScheduleStore()

const monthFilter = ref('2026-06')
const selectedYear = ref(2026)
const selectedMonth = ref(6)

onMounted(() => {
  store.fetchShifts()
  store.fetchRosterForPeriod(selectedYear.value, selectedMonth.value)
})

watch([selectedYear, selectedMonth], ([y, m]) => {
  store.fetchRosterForPeriod(y, m)
})

const searchQuery = ref('')
const departmentFilter = ref(null)

const showModal = ref(false)
const editingCell = ref(null)

const monthLabels = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]
const dayNameAbbr = ['Mg', 'Sn', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

const departments = [
  { id: 1, name: 'Teknologi Informasi' },
  { id: 2, name: 'Keuangan' },
  { id: 3, name: 'SDM' },
  { id: 4, name: 'Pemasaran' },
  { id: 5, name: 'Operasional' },
]

const prevMonthIndex = computed(() => {
  return selectedMonth.value === 1 ? 11 : selectedMonth.value - 2
})

const prevMonthYear = computed(() => {
  return selectedMonth.value === 1 ? selectedYear.value - 1 : selectedYear.value
})

const daysInMonthRange = computed(() => {
  return store.getDaysInMonthRange(selectedYear.value, selectedMonth.value)
})

const rosterList = computed(() => {
  return store.getRosterForPeriod(selectedYear.value, selectedMonth.value)
})

const filteredRoster = computed(() => {
  return rosterList.value.filter(emp => {
    const matchesSearch = emp.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                          emp.nik.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesDept = !departmentFilter.value || emp.department_id === departmentFilter.value
    return matchesSearch && matchesDept
  })
})

function handleMonthChange() {
  if (!monthFilter.value) return
  const [y, m] = monthFilter.value.split('-')
  selectedYear.value = parseInt(y)
  selectedMonth.value = parseInt(m)
}

function getShiftStyle(scheduleDay) {
  if (scheduleDay.is_off) {
    return { backgroundColor: '#ef4444', color: '#ffffff' } // red
  }
  // Lookup shift in store to get color
  const shift = store.shifts.find(s => s.id === scheduleDay.shift_id)
  return {
    backgroundColor: shift ? shift.color : '#64748b',
    color: '#ffffff'
  }
}

function openOverrideModal(employee, dayInfo, dayIndex) {
  editingCell.value = {
    employeeId: employee.id,
    employeeName: employee.name,
    employeeNik: employee.nik,
    date: dayInfo.date,
    dayIndex,
    currentShift: employee.schedule[dayIndex]
  }
  showModal.value = true
}

function applyOverride(newShift) {
  if (!editingCell.value) return
  
  const roster = store.getRosterForPeriod(selectedYear.value, selectedMonth.value)
  const empIdx = roster.findIndex(e => e.id === editingCell.value.employeeId)
  
  if (empIdx !== -1) {
    roster[empIdx].schedule[editingCell.value.dayIndex] = {
      code: newShift.code,
      name: newShift.name,
      is_off: !!newShift.is_off,
      shift_id: newShift.id || null
    }
  }
  
  showModal.value = false
  editingCell.value = null
}

function formatDateLabel(date) {
  return date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}
</script>
