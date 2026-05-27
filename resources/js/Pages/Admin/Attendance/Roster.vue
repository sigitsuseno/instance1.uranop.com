<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Roster Karyawan</h1>
        <p class="text-sm text-(--text-muted) mt-1">Jadwal kerja karyawan bulan {{ monthNames[currentMonth] }} {{ currentYear }}</p>
      </div>
    </div>

    <div class="flex items-center justify-between mb-4">
      <div class="flex gap-2">
        <BaseButton
          :variant="viewMode === 'calendar' ? 'primary' : 'secondary'"
          size="sm"
          @click="viewMode = 'calendar'"
        >
          Kalender
        </BaseButton>
        <BaseButton
          :variant="viewMode === 'table' ? 'primary' : 'secondary'"
          size="sm"
          @click="viewMode = 'table'"
        >
          Tabel
        </BaseButton>
      </div>

      <div class="flex items-center gap-3">
        <div class="w-44">
          <SelectInput v-model="selectedDepartment" label="" :options="departmentOptions" placeholder="Semua Departemen" />
        </div>

        <div class="flex items-center gap-1">
          <BaseButton variant="ghost" size="sm" @click="prevMonth">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="15 18 9 12 15 6" />
            </svg>
          </BaseButton>
          <span class="text-sm font-semibold text-(--text-main) min-w-[160px] text-center">
            {{ monthNames[currentMonth] }} {{ currentYear }}
          </span>
          <BaseButton variant="ghost" size="sm" @click="nextMonth">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </BaseButton>
        </div>
      </div>
    </div>

    <div v-if="viewMode === 'calendar'" class="space-y-4">
      <BaseCard>
        <div class="flex items-center gap-6 mb-4 text-sm">
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-(--danger) inline-block" />
            <span class="text-(--text-muted)">Shift Pagi</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-(--primary) inline-block" />
            <span class="text-(--text-muted)">Shift Siang</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-(--text-soft) inline-block" />
            <span class="text-(--text-muted)">Libur</span>
          </div>
        </div>

        <div class="grid grid-cols-7 gap-px bg-(--border-soft) rounded-md overflow-hidden">
          <div
            v-for="day in dayHeaders"
            :key="day"
            class="bg-(--bg-elevated) px-3 py-2 text-xs font-semibold text-(--text-muted) uppercase text-center"
          >
            {{ day }}
          </div>
          <div
            v-for="(cell, idx) in calendarCells"
            :key="idx"
            class="bg-(--bg-card) p-2 min-h-[80px]"
          >
            <p class="text-xs font-medium text-(--text-muted) mb-1" :class="{ 'opacity-30': !cell.inMonth }">
              {{ cell.date }}
            </p>
            <div v-if="cell.shifts && cell.shifts.length" class="space-y-0.5">
              <span
                v-for="(shift, si) in cell.shifts"
                :key="si"
                class="inline-block w-2 h-2 rounded-full"
                :class="shiftColor(shift)"
              />
            </div>
          </div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #title>Daftar Karyawan</template>

        <div class="space-y-2">
          <div
            v-for="emp in rosterEmployees"
            :key="emp.id"
            class="flex items-center gap-3 p-2 rounded-md hover:bg-(--bg-elevated) transition-colors"
          >
            <div class="w-8 h-8 rounded-full bg-(--primary)/10 flex items-center justify-center text-xs font-bold text-(--primary)">
              {{ emp.initial }}
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-(--text-main)">{{ emp.name }}</p>
              <p class="text-xs text-(--text-muted)">{{ emp.nip }} - {{ emp.department }}</p>
            </div>
            <div class="text-xs text-(--text-muted)">
              {{ emp.shift }}
            </div>
          </div>
        </div>
      </BaseCard>
    </div>

    <BaseCard v-else>
      <div class="flex items-center gap-4 mb-4 text-xs">
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-sm bg-(--success) inline-block" />
          <span class="text-(--text-muted)">Hadir sesuai roster</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-sm bg-(--danger) inline-block" />
          <span class="text-(--text-muted)">Tidak sesuai</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-sm bg-(--bg-elevated) inline-block" />
          <span class="text-(--text-muted)">Libur</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-xs">
          <thead>
            <tr>
              <th class="sticky left-0 bg-(--bg-elevated) px-3 py-2 text-left font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) w-40">
                Nama
              </th>
              <th
                v-for="d in daysInMonth"
                :key="d"
                class="px-2 py-2 text-center font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) w-8"
              >
                {{ d }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="emp in rosterGridData" :key="emp.id" class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/50">
              <td class="sticky left-0 bg-(--bg-card) px-3 py-2 font-medium text-(--text-main) whitespace-nowrap">
                {{ emp.name }}
              </td>
              <td
                v-for="d in daysInMonth"
                :key="d"
                class="px-2 py-2 text-center"
                :class="cellBgClass(emp.schedule[d - 1])"
              />
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import SelectInput from '../../../Components/SelectInput.vue'

const viewMode = ref('calendar')
const selectedDepartment = ref('')
const currentYear = ref(2026)
const currentMonth = ref(5)

const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const dayHeaders = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']

const departmentOptions = [
  { value: 'IT', label: 'IT' },
  { value: 'HR', label: 'HR' },
  { value: 'Finance', label: 'Keuangan' },
  { value: 'Marketing', label: 'Pemasaran' },
  { value: 'Operations', label: 'Operasional' },
]

const daysInMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value, 0).getDate()
})

const calendarCells = computed(() => {
  const cells = []
  const firstDay = new Date(currentYear.value, currentMonth.value - 1, 1).getDay()
  const adjustedFirstDay = firstDay === 0 ? 6 : firstDay - 1
  const totalDays = daysInMonth.value

  const prevMonthDays = new Date(currentYear.value, currentMonth.value - 1, 0).getDate()

  for (let i = adjustedFirstDay - 1; i >= 0; i--) {
    cells.push({ date: prevMonthDays - i, inMonth: false, shifts: [] })
  }

  for (let d = 1; d <= totalDays; d++) {
    const dayOfWeek = (adjustedFirstDay + d - 1) % 7
    const shifts = []
    if (dayOfWeek !== 5 && dayOfWeek !== 6) {
      shifts.push(d % 3 === 0 ? 'siang' : 'pagi')
    }
    cells.push({ date: d, inMonth: true, shifts })
  }

  const remaining = 7 - (cells.length % 7)
  if (remaining < 7) {
    for (let i = 1; i <= remaining; i++) {
      cells.push({ date: i, inMonth: false, shifts: [] })
    }
  }

  return cells
})

const rosterEmployees = ref([
  { id: 1, name: 'Budi Santoso', nip: 'EMP001', department: 'IT', initial: 'BS', shift: 'Shift Pagi (07:00-16:00)' },
  { id: 2, name: 'Siti Nurhaliza', nip: 'EMP002', department: 'HR', initial: 'SN', shift: 'Shift Pagi (07:00-16:00)' },
  { id: 3, name: 'Ahmad Fauzi', nip: 'EMP003', department: 'Finance', initial: 'AF', shift: 'Shift Siang (13:00-22:00)' },
  { id: 4, name: 'Dewi Lestari', nip: 'EMP004', department: 'Marketing', initial: 'DL', shift: 'Shift Pagi (07:00-16:00)' },
  { id: 5, name: 'Rudi Hartono', nip: 'EMP005', department: 'IT', initial: 'RH', shift: 'Shift Siang (13:00-22:00)' },
  { id: 6, name: 'Anisa Rahman', nip: 'EMP006', department: 'Operations', initial: 'AR', shift: 'Shift Pagi (07:00-16:00)' },
  { id: 7, name: 'Hendra Gunawan', nip: 'EMP007', department: 'Finance', initial: 'HG', shift: 'Shift Pagi (07:00-16:00)' },
  { id: 8, name: 'Maya Indah', nip: 'EMP008', department: 'HR', initial: 'MI', shift: 'Shift Siang (13:00-22:00)' },
  { id: 9, name: 'Fajar Pratama', nip: 'EMP009', department: 'IT', initial: 'FP', shift: 'Shift Pagi (07:00-16:00)' },
  { id: 10, name: 'Rina Wijaya', nip: 'EMP010', department: 'Marketing', initial: 'RW', shift: 'Shift Siang (13:00-22:00)' },
])

const rosterGridData = computed(() => {
  return rosterEmployees.value.map((emp) => {
    const schedule = []
    for (let d = 1; d <= daysInMonth.value; d++) {
      const dayOfWeek = (new Date(currentYear.value, currentMonth.value - 1, d).getDay() + 6) % 7
      if (dayOfWeek >= 5) {
        schedule.push('libur')
      } else if (emp.shift.includes('Siang')) {
        schedule.push(d % 3 === 0 ? 'tidak_sesuai' : 'sesuai')
      } else {
        schedule.push(d % 3 === 0 ? 'tidak_sesuai' : 'sesuai')
      }
    }
    return { ...emp, schedule }
  })
})

function shiftColor(shift) {
  return shift === 'pagi' ? 'bg-(--danger)' : 'bg-(--primary)'
}

function cellBgClass(status) {
  if (status === 'sesuai') return 'bg-(--success)/30'
  if (status === 'tidak_sesuai') return 'bg-(--danger)/30'
  return 'bg-(--bg-elevated)'
}

function prevMonth() {
  if (currentMonth.value === 1) {
    currentMonth.value = 12
    currentYear.value--
  } else {
    currentMonth.value--
  }
}

function nextMonth() {
  if (currentMonth.value === 12) {
    currentMonth.value = 1
    currentYear.value++
  } else {
    currentMonth.value++
  }
}
</script>
