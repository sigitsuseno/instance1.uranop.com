<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Kalender Kerja</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola hari kerja, hari libur, dan tanggal khusus</p>
      </div>
      <div class="flex items-center gap-3">
        <BaseButton variant="secondary" size="sm" @click="selectedYear = selectedYear - 1">
          &laquo;
        </BaseButton>
        <span class="text-lg font-semibold text-(--text-main) min-w-[80px] text-center">{{ selectedYear }}</span>
        <BaseButton variant="secondary" size="sm" @click="selectedYear = selectedYear + 1">
          &raquo;
        </BaseButton>
        <BaseButton variant="primary" @click="showHolidayModal = true">
          <template #icon-left>
            <IconPlus class="w-4 h-4" />
          </template>
          Tambah Hari Libur
        </BaseButton>
      </div>
    </div>

    <BaseCard class="mb-6">
      <template #title>Kalender {{ selectedYear }}</template>
      <template #subtitle>Klik sel untuk mengubah status hari</template>

      <div class="flex items-center gap-4 mb-4">
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-sm bg-(--success)/30 border border-(--success)"></span>
          <span class="text-xs text-(--text-muted)">Hari Kerja</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-sm bg-(--danger)/30 border border-(--danger)"></span>
          <span class="text-xs text-(--text-muted)">Hari Libur</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-sm bg-(--warning)/30 border border-(--warning)"></span>
          <span class="text-xs text-(--text-muted)">Tanggal Khusus</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr>
              <th class="px-1 py-2 text-center text-xs font-semibold text-(--text-muted) uppercase">Bulan</th>
              <th
                v-for="day in 31"
                :key="day"
                class="px-1 py-2 text-center text-xs font-semibold text-(--text-muted)"
              >
                {{ day }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="month in calendarData" :key="month.name">
              <td class="px-1 py-1 text-xs font-medium text-(--text-main) whitespace-nowrap">{{ month.name }}</td>
              <td
                v-for="day in 31"
                :key="day"
                class="p-0.5"
              >
                <div
                  v-if="day <= month.days"
                  :class="cellClasses(month, day)"
                  :title="getCellTooltip(month, day)"
                  @click="handleCellClick(month, day)"
                >
                  {{ day }}
                </div>
                <div v-else class="w-7 h-7"></div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

    <BaseCard>
      <template #title>Daftar Hari Libur {{ selectedYear }}</template>
      <template #actions>
        <TextInput v-model="holidaySearch" placeholder="Cari hari libur..." type="text" class="w-56">
          <template #icon>
            <IconSearch class="w-4 h-4" />
          </template>
        </TextInput>
      </template>

      <DataTable :headers="holidayHeaders" :items="filteredHolidays">
        <template #item.type="{ value }">
          <Badge :variant="value === 'Nasional' ? 'danger' : value === 'Cuti Bersama' ? 'warning' : 'info'">{{ value }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <button
            class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors"
            title="Hapus"
            @click="confirmDeleteHoliday = item"
          >
            <IconTrash class="w-4 h-4" />
          </button>
        </template>
      </DataTable>
    </BaseCard>

    <BaseModal :show="showHolidayModal" title="Tambah Hari Libur" @close="showHolidayModal = false">
      <div class="space-y-4">
        <TextInput v-model="holidayForm.date" label="Tanggal" type="date" />
        <TextInput v-model="holidayForm.name" label="Nama Hari Libur" placeholder="Contoh: Idul Fitri 1447 H" />
        <SelectInput
          v-model="holidayForm.type"
          label="Tipe"
          :options="[
            { value: 'Nasional', label: 'Nasional' },
            { value: 'Cuti Bersama', label: 'Cuti Bersama' },
            { value: 'Perusahaan', label: 'Perusahaan' },
          ]"
        />
        <TextInput v-model="holidayForm.description" label="Deskripsi" placeholder="Deskripsi (opsional)" />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showHolidayModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="addHoliday">Simpan</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="!!confirmDeleteHoliday"
      title="Hapus Hari Libur"
      :message="`Apakah Anda yakin ingin menghapus '${confirmDeleteHoliday?.name}'?`"
      confirm-text="Hapus"
      variant="danger"
      @confirm="deleteHoliday"
      @cancel="confirmDeleteHoliday = null"
    />
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import Badge from '../../../../Components/Badge.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import { IconPlus, IconSearch, IconTrash } from '../../../../Components/Icons/index.js'

const selectedYear = ref(2026)
const holidaySearch = ref('')
const showHolidayModal = ref(false)
const confirmDeleteHoliday = ref(null)

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const monthDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]

const holidays = ref([
  { id: 1, date: '2026-01-01', name: 'Tahun Baru Masehi', type: 'Nasional', description: 'Tahun Baru 2026' },
  { id: 2, date: '2026-01-29', name: 'Tahun Baru Imlek 2577', type: 'Nasional', description: 'Tahun Baru Imlek' },
  { id: 3, date: '2026-02-18', name: 'Isra Miraj Nabi Muhammad SAW', type: 'Nasional', description: 'Isra Miraj 1447 H' },
  { id: 4, date: '2026-03-20', name: 'Idul Fitri 1447 H (Hari Pertama)', type: 'Nasional', description: 'Hari Raya Idul Fitri' },
  { id: 5, date: '2026-03-21', name: 'Idul Fitri 1447 H (Hari Kedua)', type: 'Nasional', description: 'Hari Raya Idul Fitri' },
  { id: 6, date: '2026-03-22', name: 'Cuti Bersama Idul Fitri', type: 'Cuti Bersama', description: 'Cuti bersama Idul Fitri' },
  { id: 7, date: '2026-05-01', name: 'Hari Buruh Internasional', type: 'Nasional', description: 'May Day' },
  { id: 8, date: '2026-05-26', name: 'Waisak 2570', type: 'Nasional', description: 'Hari Raya Waisak' },
  { id: 9, date: '2026-05-27', name: 'Kenaikan Yesus Kristus', type: 'Nasional', description: 'Kenaikan Isa Almasih' },
  { id: 10, date: '2026-08-17', name: 'HUT Kemerdekaan RI Ke-81', type: 'Nasional', description: 'Hari Kemerdekaan Republik Indonesia' },
  { id: 11, date: '2026-12-25', name: 'Hari Raya Natal', type: 'Nasional', description: 'Natal 2026' },
  { id: 12, date: '2026-09-15', name: 'Ulang Tahun Perusahaan', type: 'Perusahaan', description: 'HUT Perusahaan ke-15' },
])

const calendarData = computed(() => {
  return monthNames.map((name, i) => ({
    name,
    monthIdx: i,
    days: monthDays[i],
  }))
})

const holidayDateMap = computed(() => {
  const map = {}
  for (const h of holidays.value) {
    const [y, m, d] = h.date.split('-')
    if (parseInt(y) === selectedYear.value) {
      map[`${parseInt(m)}-${parseInt(d)}`] = h
    }
  }
  return map
})

function cellClasses(month, day) {
  const key = `${month.monthIdx + 1}-${day}`
  const holiday = holidayDateMap.value[key]
  const base = 'w-7 h-7 flex items-center justify-center text-xs rounded-sm cursor-pointer transition-colors'

  if (holiday) {
    if (holiday.type === 'Nasional') return `${base} bg-(--danger)/20 text-(--danger) font-medium`
    if (holiday.type === 'Cuti Bersama') return `${base} bg-(--warning)/20 text-(--warning) font-medium`
    return `${base} bg-(--primary)/20 text-(--primary) font-medium`
  }

  const isWeekend = getDayOfWeek(selectedYear.value, month.monthIdx, day)
  if (isWeekend === 0 || isWeekend === 6) {
    return `${base} bg-(--danger)/10 text-(--text-soft) hover:bg-(--bg-elevated)`
  }

  return `${base} text-(--text-main) bg-(--success)/10 hover:bg-(--success)/20`
}

function getDayOfWeek(year, month, day) {
  const d = new Date(year, month, day)
  return d.getDay()
}

function getCellTooltip(month, day) {
  const key = `${month.monthIdx + 1}-${day}`
  const holiday = holidayDateMap.value[key]
  return holiday ? holiday.name : `${day} ${month.name} ${selectedYear.value}`
}

function handleCellClick(month, day) {
  const key = `${month.monthIdx + 1}-${day}`
  const holiday = holidayDateMap.value[key]
  if (holiday) {
    confirmDeleteHoliday.value = holiday
    return
  }
  showHolidayModal.value = true
  const mm = String(month.monthIdx + 1).padStart(2, '0')
  const dd = String(day).padStart(2, '0')
  holidayForm.date = `${selectedYear.value}-${mm}-${dd}`
}

const holidayHeaders = [
  { key: 'date', label: 'Tanggal' },
  { key: 'name', label: 'Nama' },
  { key: 'type', label: 'Tipe' },
  { key: 'description', label: 'Deskripsi' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '80px' },
]

const filteredHolidays = computed(() => {
  if (!holidaySearch.value) return holidays.value
  const q = holidaySearch.value.toLowerCase()
  return holidays.value.filter((h) => h.name.toLowerCase().includes(q) || h.type.toLowerCase().includes(q))
})

const holidayForm = reactive({ date: '', name: '', type: 'Nasional', description: '' })

function addHoliday() {
  holidays.value.push({
    id: holidays.value.length + 1,
    date: holidayForm.date,
    name: holidayForm.name,
    type: holidayForm.type,
    description: holidayForm.description,
  })
  showHolidayModal.value = false
  holidayForm.date = ''
  holidayForm.name = ''
  holidayForm.type = 'Nasional'
  holidayForm.description = ''
}

function deleteHoliday() {
  if (confirmDeleteHoliday.value) {
    holidays.value = holidays.value.filter((h) => h.id !== confirmDeleteHoliday.value.id)
    confirmDeleteHoliday.value = null
  }
}
</script>
