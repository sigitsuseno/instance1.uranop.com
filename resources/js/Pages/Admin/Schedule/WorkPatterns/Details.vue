<template>
  <div>
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
          <h1 class="text-xl font-semibold text-(--text-main)">Konfigurasi Siklus: {{ pattern?.name }}</h1>
          <p class="text-xs text-(--text-muted) mt-1">
            Kode: <span class="font-mono">{{ pattern?.code }}</span> &middot; Tipe: {{ pattern?.employee_type }} &middot; Cut-off: Tanggal {{ pattern?.cut_off_date }}
          </p>
        </div>
      </div>
    </div>

    <!-- Existing Groups -->
    <div class="space-y-6">
      <div v-if="Object.keys(existingGroups).length > 0" class="space-y-4">
        <h2 class="text-base font-bold text-(--text-main) flex items-center gap-2">
          <span>Detail Siklus yang Terdaftar</span>
        </h2>

        <div v-for="(details, groupName) in existingGroups" :key="groupName" class="bg-(--bg-card) border border-(--border-soft) rounded-md overflow-hidden">
          <div class="px-4 py-3 bg-(--bg-elevated)/30 border-b border-(--border-soft) flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-(--text-main)">{{ groupName }}</span>
              <Badge variant="primary">{{ details.length }} Hari Siklus</Badge>
            </div>
            <BaseButton variant="ghost" size="sm" @click="deleteGroup(groupName)" class="text-red-500 hover:text-red-700 !h-8 !px-2">
              <template #icon-left>
                <IconTrash class="w-3.5 h-3.5" />
              </template>
              Hapus
            </BaseButton>
          </div>
          
          <div class="p-4">
            <div class="grid grid-cols-7 gap-2">
              <div v-for="dayName in ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']" :key="dayName" class="text-center text-xs font-bold text-(--text-muted) py-1">
                {{ dayName }}
              </div>
              
              <!-- Empty padding for offset alignment -->
              <div v-for="n in getGroupOffset(details)" :key="'offset-' + n" class="p-2 rounded-md border border-dashed border-(--border-soft) bg-(--bg-elevated)/20"></div>

              <!-- Day Cards -->
              <div v-for="detail in details" :key="detail.day_number" class="p-2 rounded-md border text-center text-xs" :class="getDayCardStaticClass(detail.day_type)">
                <div class="font-bold text-(--text-main)">H-{{ detail.day_number }}</div>
                <div class="text-[9px] text-(--text-muted) mt-0.5">{{ detail.day_name || '' }}</div>
                <div class="mt-1 font-semibold text-[10px]">
                  {{ getDayTypeLabel(detail.day_type) }}
                </div>
                <div v-if="detail.shift_id" class="text-[9px] text-(--primary) mt-0.5 truncate font-bold">
                  {{ getShiftCode(detail.shift_id) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Add New Group Form Toggle -->
      <div v-if="!showAddForm" class="flex justify-center">
        <BaseButton variant="primary" @click="showAddForm = true">
          <template #icon-left>
            <IconPlus class="w-4 h-4" />
          </template>
          Tambah Siklus Baru
        </BaseButton>
      </div>

      <!-- Add New Group Form -->
      <div v-else class="space-y-6">
        <BaseCard>
          <template #title>Buat Detail Siklus Baru</template>
          
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-4">
            <div>
              <TextInput v-model="form.name" label="Nama Siklus" placeholder="Contoh: Siklus A" />
            </div>
            <div>
              <SelectInput
                v-model="selectedPeriod"
                label="Periode Mulai (Align Kalender)"
                :options="periods"
              />
            </div>
            <div>
              <TextInput v-model.number="form.cycle_day" label="Jumlah Hari Siklus" type="number" min="1" max="31" />
            </div>
            <div>
              <BaseButton variant="primary" @click="generateDays" :disabled="!selectedPeriod || !form.name" class="w-full">
                Generate Hari Siklus
              </BaseButton>
            </div>
          </div>
          <div v-if="filteredShifts.length === 0" class="mt-4 p-3 bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-md flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            Pola kerja ini belum memiliki Shift Kerja yang terdaftar (atau tidak ada shift aktif). Anda tidak dapat menyimpan jadwal hari kerja jika tidak ada pilihan shift. Silakan buat/edit shift dan kaitkan ke Pola Kerja ini di menu Shift Kerja.
          </div>
        </BaseCard>

        <!-- Configurator Grid -->
        <div v-if="form.details.length > 0" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-bold text-(--text-main)">Atur Jadwal Hari Siklus</h3>
                <p class="text-xs text-(--text-muted) mt-0.5">Klik satu atau beberapa hari, lalu ubah shift di panel kanan</p>
              </div>
              <div class="flex items-center gap-3 text-xs">
                <button @click="selectAll" class="text-(--primary) hover:underline">Pilih Semua</button>
                <span class="text-(--border-soft)">|</span>
                <button @click="clearSelection" class="text-(--text-muted) hover:underline">Clear</button>
                <span v-if="selectedDays.length > 0" class="font-semibold text-(--primary)">({{ selectedDays.length }} Terpilih)</span>
              </div>
            </div>

            <!-- Calendar-like 7-Col Grid -->
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4">
              <div class="grid grid-cols-7 gap-2 mb-2">
                <div v-for="dayName in ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']" :key="dayName" class="text-center text-xs font-bold text-(--text-muted) py-1">
                  {{ dayName }}
                </div>
              </div>
              
              <div v-for="(row, rIdx) in getWeekRows" :key="rIdx" class="grid grid-cols-7 gap-2 mb-2">
                <template v-for="(day, cIdx) in row" :key="cIdx">
                  <div v-if="day" @click="toggleDaySelection(day.originalIndex)" class="relative p-3 rounded-md border-2 cursor-pointer transition-colors text-center" :class="getDayCardClasses(day, day.originalIndex)">
                    <!-- Selection indicator -->
                    <div v-if="selectedDays.includes(day.originalIndex)" class="absolute top-1 right-1 w-4 h-4 rounded bg-(--primary) text-white flex items-center justify-center text-[8px]">
                      ✓
                    </div>
                    <div class="text-[10px] text-(--text-muted)">H-{{ day.day_number }}</div>
                    <div class="text-xs font-bold mt-1 text-(--text-main)">{{ day.day_name }}</div>
                    <div class="text-[10px] font-semibold mt-1" :class="getDayTypeBadgeClass(day.day_type)">
                      {{ getDayTypeLabel(day.day_type) }}
                    </div>
                    <div v-if="day.shift_id" class="text-[9px] text-(--primary) mt-1 truncate font-bold">
                      {{ getShiftCode(day.shift_id) }}
                    </div>
                  </div>
                  <div v-else class="p-3 rounded-md border border-dashed border-(--border-soft) bg-(--bg-elevated)/10"></div>
                </template>
              </div>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap gap-4 text-xs text-(--text-muted)">
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-500/20 border border-emerald-500 inline-block"></span> Kerja</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-orange-500/20 border border-orange-500 inline-block"></span> ½ Hari</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-rose-500/20 border border-rose-500 inline-block"></span> Libur</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500/20 border border-amber-500 inline-block"></span> Minggu</span>
            </div>
          </div>

          <!-- Configuration Panel -->
          <div class="lg:col-span-1">
            <div v-if="selectedDays.length > 0" class="bg-(--bg-card) border border-(--primary)/30 rounded-md p-4 space-y-4 sticky top-4 shadow-sm">
              <div class="border-b border-(--border-soft) pb-2">
                <h3 class="text-sm font-bold text-(--text-main)">Ubah Hari Terpilih</h3>
                <p class="text-xs text-(--text-muted)">{{ selectedDays.length }} hari terseleksi</p>
              </div>

              <!-- Day Type Option -->
              <div class="space-y-2">
                <label class="block text-xs font-semibold text-(--text-main)">Tipe Hari</label>
                <div class="grid grid-cols-2 gap-2">
                  <button
                    v-for="t in ['work_day', 'half_day', 'day_off', 'is_sun']"
                    :key="t"
                    @click="bulkConfig.day_type = t"
                    class="py-2 px-3 text-xs font-medium rounded-md border text-center transition-colors"
                    :class="bulkConfig.day_type === t ? 'border-(--primary) bg-(--primary)/5 text-(--primary)' : 'border-(--border-soft) text-(--text-muted) hover:border-(--border-strong)'"
                  >
                    {{ getDayTypeLabel(t) }}
                  </button>
                </div>
              </div>

              <!-- Shift Selection -->
              <div class="space-y-2" v-if="bulkConfig.day_type === 'work_day' || bulkConfig.day_type === 'half_day'">
                <label class="block text-xs font-semibold text-(--text-main)">Pilih Shift Kerja</label>
                <select v-model="bulkConfig.shift_id" class="w-full h-10 px-3 text-xs rounded-md bg-(--bg-card) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)">
                  <option :value="null">-- Pilih Shift --</option>
                  <option v-for="s in filteredShifts" :key="s.id" :value="s.id">
                    {{ s.code }} - {{ s.name }} ({{ s.work_hour_start }} - {{ s.work_hour_end }})
                  </option>
                </select>
              </div>

              <!-- Apply button -->
              <BaseButton variant="primary" class="w-full" :disabled="(bulkConfig.day_type === 'work_day' || bulkConfig.day_type === 'half_day') && !bulkConfig.shift_id" @click="applyBulkConfig">
                Terapkan ke {{ selectedDays.length }} Hari
              </BaseButton>

              <BaseButton variant="ghost" class="w-full" @click="clearSelection">
                Batal
              </BaseButton>
            </div>

            <!-- Panel Placeholder -->
            <div v-else class="bg-(--bg-elevated)/40 border border-(--border-soft) rounded-md p-6 text-center text-sm text-(--text-muted) sticky top-4">
              Pilih satu atau beberapa hari siklus di kiri untuk melakukan konfigurasi shift.
            </div>
          </div>
        </div>

        <!-- Form Summary & Save -->
        <div v-if="form.details.length > 0" class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex items-center justify-between">
          <div class="text-xs text-(--text-muted)">
            <span class="font-bold text-(--text-main)">Siklus "{{ form.name }}"</span>: 
            {{ form.details.length }} hari, 
            {{ form.details.filter(d => d.day_type === 'work_day').length }} hari kerja,
            {{ form.details.filter(d => d.day_type === 'day_off').length }} hari libur.
          </div>
          <div class="flex gap-2">
            <BaseButton variant="ghost" @click="cancelAdd" :disabled="isSaving">Batal</BaseButton>
            <BaseButton variant="primary" :disabled="!allDaysConfigured || isSaving" @click="saveNewGroup">
              <span v-if="isSaving">Menyimpan...</span>
              <span v-else>Simpan Siklus</span>
            </BaseButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useScheduleStore } from '../../../../Stores/schedule'
import { useApi } from '../../../../composables/useApi'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import Badge from '../../../../Components/Badge.vue'
import { IconArrowLeft, IconPlus, IconTrash } from '../../../../Components/Icons/index.js'

const route = useRoute()
const router = useRouter()
const store = useScheduleStore()
const { get } = useApi()

const patternId = parseInt(route.params.id)
const pattern = computed(() => store.workPatterns.find(p => p.id === patternId))

const showAddForm = ref(false)
const selectedPeriod = ref('')
const selectedDays = ref([])
const isSaving = ref(false)

const form = reactive({
  name: '',
  cycle_day: 7,
  details: [],
})

const bulkConfig = reactive({
  day_type: 'work_day',
  shift_id: null,
})

const periods = ref([])

const filteredShifts = computed(() => {
  if (!pattern.value) return []
  return store.shifts.filter(s => 
    s.is_active && 
    !s.is_dayoff && 
    (s.work_pattern_id == pattern.value.id || s.work_pattern_id === null)
  )
})

const existingGroups = computed(() => {
  return pattern.value?.detailGroups || {}
})

const allDaysConfigured = computed(() => {
  if (form.details.length === 0) return false
  return form.details.every(day => 
    day.day_type === 'day_off' || 
    day.day_type === 'is_sun' || 
    day.shift_id !== null
  )
})

function goBack() {
  router.push({ name: 'schedule.work-patterns' })
}

function getShiftCode(shiftId) {
  const s = store.shifts.find(x => x.id === shiftId)
  return s ? s.code : ''
}

function getDayTypeLabel(type) {
  const map = {
    work_day: 'Kerja',
    half_day: '½ Hari',
    day_off: 'Libur',
    is_sun: 'Minggu',
  }
  return map[type] || type
}

function getDayCardStaticClass(type) {
  if (type === 'day_off') return 'border-rose-200 bg-rose-50/50 dark:border-rose-950 dark:bg-rose-950/20'
  if (type === 'is_sun') return 'border-amber-200 bg-amber-50/50 dark:border-amber-950 dark:bg-amber-950/20'
  if (type === 'half_day') return 'border-orange-200 bg-orange-50/50 dark:border-orange-950 dark:bg-orange-950/20'
  return 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-950 dark:bg-emerald-950/20'
}

function getDayTypeBadgeClass(type) {
  if (type === 'work_day') return 'text-emerald-600'
  if (type === 'half_day') return 'text-orange-600'
  if (type === 'day_off') return 'text-rose-600'
  return 'text-amber-600'
}

// Offset to align starting day with Mon-Sun column headers
function getGroupOffset(details) {
  if (!details || details.length === 0 || !details[0].day_name) return 0
  const names = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']
  const idx = names.indexOf(details[0].day_name)
  return idx > -1 ? idx : 0
}

function generateDays() {
  const list = []
  const startingDate = new Date(selectedPeriod.value)
  const names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
  
  for (let i = 1; i <= form.cycle_day; i++) {
    const curDate = new Date(startingDate)
    curDate.setDate(startingDate.getDate() + i - 1)
    const dow = curDate.getDay()
    
    // Create the day record. All work days get null shift by default (requires manual config)
    list.push({
      day_number: i,
      day_name: names[dow],
      day_type: dow === 0 ? 'is_sun' : 'work_day',
      shift_id: null,
    })
  }
  
  form.details = list
  selectedDays.value = []
}

const getWeekRows = computed(() => {
  if (form.details.length === 0) return []
  const rows = []
  let currentRow = []
  
  const names = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']
  const offset = names.indexOf(form.details[0].day_name)
  
  for (let i = 0; i < offset; i++) {
    currentRow.push(null)
  }
  
  form.details.forEach((day, index) => {
    currentRow.push({ ...day, originalIndex: index })
    if (currentRow.length === 7) {
      rows.push(currentRow)
      currentRow = []
    }
  })
  
  if (currentRow.length > 0) {
    while (currentRow.length < 7) {
      currentRow.push(null)
    }
    rows.push(currentRow)
  }
  
  return rows
})

function toggleDaySelection(index) {
  const pos = selectedDays.value.indexOf(index)
  if (pos > -1) {
    selectedDays.value.splice(pos, 1)
  } else {
    selectedDays.value.push(index)
  }
}

function selectAll() {
  selectedDays.value = form.details.map((_, i) => i)
}

function clearSelection() {
  selectedDays.value = []
}

function applyBulkConfig() {
  selectedDays.value.forEach(idx => {
    form.details[idx].day_type = bulkConfig.day_type
    if (bulkConfig.day_type === 'day_off' || bulkConfig.day_type === 'is_sun') {
      form.details[idx].shift_id = null
    } else {
      form.details[idx].shift_id = bulkConfig.shift_id
    }
  })
  selectedDays.value = []
}

function cancelAdd() {
  showAddForm.value = false
  form.name = ''
  form.details = []
  selectedDays.value = []
}

async function saveNewGroup() {
  isSaving.value = true
  try {
    const payload = {
      name: form.name,
      details: form.details.map(d => ({
        day_number: d.day_number,
        day_name: d.day_name,
        day_type: d.day_type,
        shift_id: d.shift_id
      }))
    }
    await store.saveWorkPatternDetails(pattern.value.id, payload)
    cancelAdd()
  } catch (error) {
    console.error(error)
    alert('Gagal menyimpan detail siklus.')
  } finally {
    isSaving.value = false
  }
}

async function deleteGroup(name) {
  if (confirm(`Yakin ingin menghapus siklus "${name}"?`)) {
    try {
      await store.deleteWorkPatternDetailsGroup(pattern.value.id, name)
      // Delete from local state just in case
      delete pattern.value.detailGroups[name]
      alert(`Siklus "${name}" berhasil dihapus.`)
    } catch (error) {
      console.error(error)
      alert('Gagal menghapus siklus.')
    }
  }
}

function getDayCardClasses(day, index) {
  const isSelected = selectedDays.value.includes(index)
  let base = 'border-2 rounded-md hover:border-(--primary)/50 '
  
  if (isSelected) {
    base += 'border-(--primary) bg-(--primary)/10 shadow-sm '
  } else {
    if (day.day_type === 'day_off') base += 'border-rose-200 bg-rose-50/20 dark:border-rose-950 dark:bg-rose-950/10 '
    else if (day.day_type === 'is_sun') base += 'border-amber-200 bg-amber-50/20 dark:border-amber-950 dark:bg-amber-950/10 '
    else if (day.day_type === 'half_day') base += 'border-orange-200 bg-orange-50/20 dark:border-orange-950 dark:bg-orange-950/10 '
    else base += 'border-emerald-200 bg-emerald-50/20 dark:border-emerald-950 dark:bg-emerald-950/10 '
  }
  
  return base
}

onMounted(async () => {
  store.fetchShifts()
  if (store.workPatterns.length === 0) {
    await store.fetchWorkPatterns()
  }
  
  if (!pattern.value) {
    goBack()
    return
  }
  
  try {
    const res = await get('/api/v1/payroll/periods')
    if (res.data) {
      periods.value = res.data.map(p => {
        const start = p.start_date.split('T')[0]
        const end = p.end_date.split('T')[0]
        return {
          value: start,
          label: `${p.name} (${start} s/d ${end})`
        }
      })
      if (periods.value.length > 0) {
        selectedPeriod.value = periods.value[0].value
      }
    }
  } catch (error) {
    console.error('Failed to fetch periods', error)
  }
})
</script>
