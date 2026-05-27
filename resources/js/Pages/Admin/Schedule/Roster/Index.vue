<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-(--text-main)">Generate Roster</h1>
      <p class="text-sm text-(--text-muted) mt-1">Buat jadwal roster karyawan per bulan</p>
    </div>

    <BaseCard class="mb-6">
      <template #title>Parameter Generate Roster</template>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <SelectInput
          v-model="rosterForm.month"
          label="Bulan"
          :options="monthOptions"
        />
        <SelectInput
          v-model="rosterForm.year"
          label="Tahun"
          :options="yearOptions"
        />
        <SelectInput
          v-model="rosterForm.work_pattern_id"
          label="Pola Kerja"
          :options="workPatternOptions"
        />
        <SelectInput
          v-model="rosterForm.shift_id"
          label="Pola Shift"
          :options="shiftOptions"
        />
      </div>
      <template #footer>
        <BaseButton variant="primary" @click="generateRoster">
          <template #icon-left>
            <IconCog class="w-4 h-4" />
          </template>
          Generate Roster
        </BaseButton>
      </template>
    </BaseCard>

    <div v-if="previewData.length > 0">
      <BaseCard class="mb-6">
        <template #title>Preview Roster - {{ selectedMonthName }} {{ rosterForm.year }}</template>
        <template #subtitle>{{ previewData.length }} Karyawan &middot; {{ rosterForm.month }} hari</template>
        <template #actions>
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: '#3b82f6' }"></span>
              <span class="text-xs text-(--text-muted)">Shift Pagi</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: '#f59e0b' }"></span>
              <span class="text-xs text-(--text-muted)">Shift Siang</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: '#8b5cf6' }"></span>
              <span class="text-xs text-(--text-muted)">Shift Malam</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded-sm bg-(--danger)/30 border border-(--danger)"></span>
              <span class="text-xs text-(--text-muted)">Libur</span>
            </div>
          </div>
        </template>

        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-3 py-2 text-left text-xs font-semibold text-(--text-muted) uppercase whitespace-nowrap sticky left-0 bg-(--bg-elevated) z-10">Nama</th>
                <th
                  v-for="day in previewDays"
                  :key="day.day"
                  :class="[
                    'px-1 py-2 text-center text-xs font-semibold min-w-[32px]',
                    day.isWeekend ? 'text-(--danger)' : 'text-(--text-muted)',
                  ]"
                >
                  <div>{{ day.label }}</div>
                  <div>{{ day.day }}</div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="employee in previewData" :key="employee.id" class="border-b border-(--border-soft)">
                <td class="px-3 py-2 whitespace-nowrap sticky left-0 bg-(--bg-card) z-10">
                  <div class="font-medium text-(--text-main)">{{ employee.name }}</div>
                  <div class="text-xs text-(--text-muted)">{{ employee.department }}</div>
                </td>
                <td
                  v-for="day in previewDays"
                  :key="day.day"
                  class="px-1 py-2 text-center"
                >
                  <div
                    v-if="employee.schedule[day.idx]"
                    class="w-7 h-7 rounded-full mx-auto flex items-center justify-center text-[10px] font-semibold text-white"
                    :style="{ backgroundColor: employee.schedule[day.idx] }"
                    :title="getShiftName(employee.schedule[day.idx])"
                  >
                    {{ getShiftCode(employee.schedule[day.idx]) }}
                  </div>
                  <div v-else class="w-7 h-7 mx-auto"></div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>

      <div class="flex justify-end">
        <BaseButton variant="primary" size="lg" @click="saveRoster">
          <template #icon-left>
            <IconGift class="w-4 h-4" />
          </template>
          Simpan Roster
        </BaseButton>
      </div>
    </div>

    <div v-if="!previewData.length" class="text-center py-16">
      <IconClock class="w-16 h-16 mx-auto text-(--text-soft) mb-4" />
      <p class="text-(--text-muted)">Pilih parameter di atas dan klik "Generate Roster" untuk melihat jadwal</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import { IconCog, IconGift, IconClock } from '../../../../Components/Icons/index.js'

const monthLabels = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

const monthOptions = monthLabels.map((l, i) => ({ value: i + 1, label: l }))
const yearOptions = [
  { value: 2025, label: '2025' },
  { value: 2026, label: '2026' },
  { value: 2027, label: '2027' },
]
const workPatternOptions = [
  { value: 1, label: 'Pola 5 Hari Kerja' },
  { value: 2, label: 'Pola 6 Hari Kerja' },
  { value: 3, label: 'Pola Shift' },
]
const shiftOptions = [
  { value: 1, label: 'Shift Pagi (07:00-15:00)' },
  { value: 2, label: 'Shift Siang (15:00-23:00)' },
  { value: 3, label: 'Shift Malam (23:00-07:00)' },
  { value: 4, label: 'Non Shift (08:00-17:00)' },
]

const shiftColors = ['', '#3b82f6', '#f59e0b', '#8b5cf6', '#10b981']
const shiftCodes = ['', 'PG', 'SG', 'ML', 'NS']
const shiftNames = ['', 'Shift Pagi', 'Shift Siang', 'Shift Malam', 'Non Shift']

const rosterForm = ref({
  month: 6,
  year: 2026,
  work_pattern_id: 1,
  shift_id: 1,
})

const previewData = ref([])

const selectedMonthName = computed(() => {
  return monthLabels[rosterForm.value.month - 1] || ''
})

const daysInMonth = computed(() => {
  return new Date(rosterForm.value.year, rosterForm.value.month, 0).getDate()
})

const previewDays = computed(() => {
  const days = []
  for (let d = 1; d <= daysInMonth.value; d++) {
    const date = new Date(rosterForm.value.year, rosterForm.value.month - 1, d)
    const dow = date.getDay()
    days.push({
      day: d,
      idx: d - 1,
      label: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][dow],
      isWeekend: dow === 0 || dow === 6,
    })
  }
  return days
})

const dummyEmployees = [
  { id: 1, name: 'Ahmad Fauzi', department: 'Teknologi Informasi', position: 'Senior Developer' },
  { id: 2, name: 'Siti Nurhaliza', department: 'Keuangan', position: 'Finance Manager' },
  { id: 3, name: 'Budi Santoso', department: 'SDM', position: 'HR Supervisor' },
  { id: 4, name: 'Dewi Lestari', department: 'Pemasaran', position: 'Marketing Lead' },
  { id: 5, name: 'Rudi Hartono', department: 'Teknologi Informasi', position: 'Backend Developer' },
  { id: 6, name: 'Rina Marlina', department: 'Keuangan', position: 'Accountant' },
  { id: 7, name: 'Hendra Gunawan', department: 'Operasional', position: 'Ops Manager' },
  { id: 8, name: 'Fitriani', department: 'SDM', position: 'Recruitment Specialist' },
  { id: 9, name: 'Agus Wijaya', department: 'Teknologi Informasi', position: 'Frontend Developer' },
  { id: 10, name: 'Lina Kusuma', department: 'Pemasaran', position: 'Content Writer' },
]

function getShiftName(color) {
  const idx = shiftColors.indexOf(color)
  return idx >= 0 ? shiftNames[idx] : ''
}

function getShiftCode(color) {
  const idx = shiftColors.indexOf(color)
  return idx >= 0 ? shiftCodes[idx] : ''
}

function generateRoster() {
  previewData.value = dummyEmployees.map((emp) => {
    const schedule = []
    const totalDays = daysInMonth.value
    const pattern = rosterForm.value.work_pattern_id
    const shift = rosterForm.value.shift_id

    for (let d = 0; d < totalDays; d++) {
      const date = new Date(rosterForm.value.year, rosterForm.value.month - 1, d + 1)
      const dow = date.getDay()

      if (pattern === 1 && (dow === 0 || dow === 6)) {
        schedule.push(null)
        continue
      }
      if (pattern === 2 && dow === 0) {
        schedule.push(null)
        continue
      }

      if (shift === 1) schedule.push('#3b82f6')
      else if (shift === 2) schedule.push('#f59e0b')
      else if (shift === 3) schedule.push('#8b5cf6')
      else schedule.push('#10b981')
    }

    return { ...emp, schedule }
  })
}

function saveRoster() {
  alert(`Roster untuk bulan ${selectedMonthName.value} ${rosterForm.value.year} berhasil disimpan!`)
}
</script>
