<template>
  <div v-if="calendar">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <BaseButton variant="ghost" size="sm" @click="goBack" class="!h-10 !px-3">
          <template #icon-left>
            <IconArrowLeft class="w-4 h-4" />
          </template>
          Kembali
        </BaseButton>
        <div>
          <h1 class="text-xl font-semibold text-(--text-main)">{{ calendar.name }}</h1>
          <p class="text-xs text-(--text-muted) mt-1">Tahun Kalender: {{ calendar.year }}</p>
        </div>
      </div>
      <BaseButton variant="primary" @click="openHolidayModal(null)">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Hari Libur
      </BaseButton>
    </div>

    <!-- Calendar Layout Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Panel: Month View (2/3 width) -->
      <div class="lg:col-span-2 space-y-6">
        <BaseCard>
          <template #title>
            <div class="flex items-center justify-between w-full gap-6">
              <div class="flex items-center gap-2">
                <BaseButton variant="secondary" size="sm" @click="prevMonth" class="!h-8 !px-2">&larr;</BaseButton>
                <span class="text-base font-semibold text-(--text-main) min-w-[120px] text-center">{{
                  monthNames[currentMonth - 1] }} {{ calendar.year }}</span>
                <BaseButton variant="secondary" size="sm" @click="nextMonth" class="!h-8 !px-2">&rarr;</BaseButton>
              </div>
              <div class="flex gap-4 text-xs text-(--text-muted)">
                <span class="flex items-center gap-1">
                  <span class="w-3 h-3 rounded bg-red-500/20 border border-red-500 inline-block"></span>
                  Nasional
                </span>
                <span class="flex items-center gap-1">
                  <span class="w-3 h-3 rounded bg-yellow-500/20 border border-yellow-500 inline-block"></span>
                  Cuti Bersama
                </span>
                <span class="flex items-center gap-1">
                  <span class="w-3 h-3 rounded bg-blue-500/20 border border-blue-500 inline-block"></span>
                  Perusahaan
                </span>
              </div>
            </div>
          </template>

          <!-- Calendar Grid -->
          <div class="mt-4">
            <div class="grid grid-cols-7 gap-2 mb-2 text-center text-xs font-bold text-(--text-muted) uppercase">
              <div v-for="d in ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']" :key="d"
                :class="{ 'text-red-500': d === 'Min' }">
                {{ d }}
              </div>
            </div>

            <div class="grid grid-cols-7 gap-2">
              <!-- Empty padding before the 1st day -->
              <div v-for="p in firstDayOffset" :key="'pad-' + p"
                class="aspect-square rounded-md border border-dashed border-(--border-soft) bg-(--bg-elevated)/10">
              </div>

              <!-- Month days -->
              <div v-for="day in daysInMonth" :key="day" @click="handleDayClick(day)"
                class="aspect-square rounded-md border p-2 flex flex-col justify-between cursor-pointer transition-shadow hover:shadow-sm"
                :class="getDayClasses(day)">
                <span class="text-xs font-bold" :class="getDayNumberClass(day)">{{ day }}</span>

                <!-- Holiday Label -->
                <div v-if="getDayHoliday(day)"
                  class="text-[9px] font-semibold truncate leading-none mt-1 p-0.5 rounded text-center w-full"
                  :class="getHolidayLabelClass(getDayHoliday(day).type)">
                  {{ getDayHoliday(day).name }}
                </div>
              </div>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- Right Panel: Holidays List (1/3 width) -->
      <div class="lg:col-span-1 space-y-6">
        <BaseCard class="h-full">
          <template #title>Hari Libur Terdaftar</template>
          <template #subtitle>{{ calendar.holidays?.length || 0 }} Hari Libur</template>

          <div class="mt-4 space-y-3 overflow-y-auto max-h-[500px] pr-1">
            <div v-for="h in sortedHolidays" :key="h.id"
              class="p-3 bg-(--bg-elevated)/30 border border-(--border-soft) rounded-md flex items-start justify-between group/item hover:border-(--primary)/30 transition-colors">
              <div class="space-y-1">
                <div class="text-xs font-mono font-bold text-(--text-main)">{{ formatDate(h.date) }}</div>
                <div class="text-sm font-bold text-(--text-main)">{{ h.name }}</div>
                <div class="text-[10px] text-(--text-muted)" v-if="h.description">{{ h.description }}</div>
                <Badge :variant="h.type === 'Nasional' ? 'danger' : h.type === 'Cuti Bersama' ? 'warning' : 'primary'"
                  class="mt-1">
                  {{ h.type }}
                </Badge>
              </div>
              <div class="flex gap-1 shrink-0 opacity-0 group-hover/item:opacity-100 transition-opacity">
                <button @click="openHolidayModal(h)"
                  class="p-1 text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 rounded transition-colors">
                  <IconPencil class="w-3.5 h-3.5" />
                </button>
                <button @click="deleteHoliday(h)"
                  class="p-1 text-(--text-muted) hover:text-red-500 hover:bg-red-50 rounded transition-colors">
                  <IconTrash class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <div v-if="!calendar.holidays || calendar.holidays.length === 0"
              class="text-center py-10 text-xs text-(--text-muted) italic">
              Belum ada hari libur terdaftar di kalender ini.
            </div>
          </div>
        </BaseCard>
      </div>
    </div>

    <!-- Holiday Form Modal -->
    <BaseModal :show="showModal" :title="editingHoliday ? 'Edit Hari Libur' : 'Tambah Hari Libur'" size="md"
      @close="showModal = false">
      <div class="space-y-4">
        <TextInput v-model="form.date" label="Tanggal Libur" type="date" />
        <TextInput v-model="form.name" label="Nama Hari Libur" placeholder="Contoh: Tahun Baru Imlek" />
        <SelectInput v-model="form.type" label="Tipe Hari Libur" :options="[
          { value: 'Nasional', label: 'Libur Nasional (Nasional)' },
          { value: 'Cuti Bersama', label: 'Cuti Bersama' },
          { value: 'Perusahaan', label: 'Libur Perusahaan (Perusahaan)' },
        ]" />
        <TextInput v-model="form.description" label="Deskripsi/Keterangan" placeholder="Keterangan singkat" />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="saveHoliday">Simpan</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useScheduleStore } from '../../../../Stores/schedule'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import Badge from '../../../../Components/Badge.vue'
import { IconArrowLeft, IconPlus, IconTrash, IconPencil } from '../../../../Components/Icons/index.js'

const route = useRoute()
const router = useRouter()
const store = useScheduleStore()

const calendarId = parseInt(route.params.id)
const calendar = computed(() => store.calendars.find(c => c.id === calendarId))

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]

const currentMonth = ref(new Date().getMonth() + 1)

// Monthly days calculations
const daysInMonth = computed(() => {
  if (!calendar.value) return 0
  return new Date(calendar.value.year, currentMonth.value, 0).getDate()
})

const firstDayOffset = computed(() => {
  if (!calendar.value) return 0
  // First day of month (0 = Sun, 1 = Mon ... 6 = Sat)
  const dow = new Date(calendar.value.year, currentMonth.value - 1, 1).getDay()
  // Align Monday as the first column (Mon = 0, Tue = 1 ... Sun = 6)
  return dow === 0 ? 6 : dow - 1
})

const sortedHolidays = computed(() => {
  if (!calendar.value?.holidays) return []
  return [...calendar.value.holidays].sort((a, b) => new Date(a.date) - new Date(b.date))
})

const showModal = ref(false)
const editingHoliday = ref(null)

const form = reactive({
  date: '',
  name: '',
  type: 'Nasional',
  description: '',
})

function goBack() {
  router.push({ name: 'schedule.calendars' })
}

function prevMonth() {
  if (currentMonth.value > 1) {
    currentMonth.value--
  } else {
    currentMonth.value = 12
  }
}

function nextMonth() {
  if (currentMonth.value < 12) {
    currentMonth.value++
  } else {
    currentMonth.value = 1
  }
}

// Check if a day is weekend
function isWeekendDay(day) {
  if (!calendar.value) return false
  const date = new Date(calendar.value.year, currentMonth.value - 1, day)
  const weekends = [0] // Standar weekend adalah hari Minggu (0)
  return weekends.includes(date.getDay())
}

// Get holiday on day
function getDayHoliday(day) {
  if (!calendar.value?.holidays) return null
  const dateStr = `${calendar.value.year}-${String(currentMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  return calendar.value.holidays.find(h => String(h.date).substring(0, 10) === dateStr) || null
}

function getDayClasses(day) {
  const holiday = getDayHoliday(day)
  const isWeekend = isWeekendDay(day)

  let classes = 'border-(--border-soft) bg-(--bg-card) '

  if (holiday) {
    if (holiday.type === 'Nasional') classes += 'border-red-400 bg-red-500/10 dark:bg-red-500/5 '
    else if (holiday.type === 'Cuti Bersama') classes += 'border-yellow-400 bg-yellow-500/10 dark:bg-yellow-500/5 '
    else classes += 'border-blue-400 bg-blue-500/10 dark:bg-blue-500/5 '
  } else if (isWeekend) {
    classes += 'border-(--border-soft) bg-red-500/5 text-red-500 '
  }

  return classes
}

function getDayNumberClass(day) {
  const isWeekend = isWeekendDay(day)
  const holiday = getDayHoliday(day)
  if (holiday || isWeekend) return 'text-red-500'
  return 'text-(--text-main)'
}

function getHolidayLabelClass(type) {
  if (type === 'Nasional') return 'bg-red-500/20 text-red-600 dark:bg-red-950/40 dark:text-red-400'
  if (type === 'Cuti Bersama') return 'bg-yellow-500/20 text-yellow-600 dark:bg-yellow-950/40 dark:text-yellow-400'
  return 'bg-blue-500/20 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400'
}

function formatDate(dateStr) {
  const date = new Date(String(dateStr).substring(0, 10))
  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

function handleDayClick(day) {
  const holiday = getDayHoliday(day)
  if (holiday) {
    openHolidayModal(holiday)
  } else {
    const padMonth = String(currentMonth.value).padStart(2, '0')
    const padDay = String(day).padStart(2, '0')
    openHolidayModal({
      date: `${calendar.value.year}-${padMonth}-${padDay}`,
      name: '',
      type: 'Nasional',
      description: '',
    })
  }
}

function openHolidayModal(holiday) {
  editingHoliday.value = holiday
  if (holiday) {
    form.date = String(holiday.date).substring(0, 10)
    form.name = holiday.name
    form.type = holiday.type || 'Nasional'
    form.description = holiday.description || ''
  } else {
    const padMonth = String(currentMonth.value).padStart(2, '0')
    form.date = `${calendar.value.year}-${padMonth}-01`
    form.name = ''
    form.type = 'Nasional'
    form.description = ''
  }
  showModal.value = true
}

async function saveHoliday() {
  if (!form.date || !form.name) return
  
  try {
    const payload = {
      date: form.date,
      name: form.name,
      type: form.type,
      description: form.description
    }
    
    await store.saveHoliday(calendar.value.id, editingHoliday.value?.id, payload)
    
    showModal.value = false
    editingHoliday.value = null
  } catch (error) {
    alert('Gagal menyimpan hari libur.')
  }
}

async function deleteHoliday(holiday) {
  if (confirm(`Yakin ingin menghapus hari libur "${holiday.name}"?`)) {
    try {
      await store.deleteHoliday(calendar.value.id, holiday.id)
    } catch (error) {
      alert('Gagal menghapus hari libur.')
    }
  }
}

onMounted(() => {
  if (!calendar.value) {
    goBack()
  }
})
</script>
