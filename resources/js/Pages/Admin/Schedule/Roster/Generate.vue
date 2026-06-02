<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <BaseButton variant="ghost" size="sm" @click="goBack" class="!h-10 !px-3">
          <template #icon-left>
            <IconArrowLeft class="w-4 h-4" />
          </template>
          Kembali
        </BaseButton>
        <div>
          <h1 class="text-xl font-semibold text-(--text-main)">Generate Jadwal Shift</h1>
          <p class="text-xs text-(--text-muted) mt-1">Buat roster otomatis berdasarkan pola kerja dan siklus shift</p>
        </div>
      </div>
    </div>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Left Column: Form Wizards -->
      <div class="lg:col-span-2 space-y-6">
        
        <!-- Step 1: Periode -->
        <BaseCard>
          <div class="flex items-center gap-2 mb-4">
            <span class="w-6 h-6 rounded-md bg-(--primary) text-white font-bold text-xs flex items-center justify-center">1</span>
            <span class="text-sm font-bold text-(--text-main)">Pilih Periode Roster</span>
          </div>
          <div class="max-w-xs">
            <TextInput v-model="form.monthStr" label="Pilih Bulan Target" type="month" />
            <p class="text-[10px] text-(--text-muted) mt-1">
              Roster mencakup cut-off tanggal 25 s/d tanggal 24 bulan berikutnya.
            </p>
          </div>
        </BaseCard>

        <!-- Step 2: Pilih Pola Kerja -->
        <BaseCard>
          <div class="flex items-center gap-2 mb-4">
            <span class="w-6 h-6 rounded-md bg-(--primary) text-white font-bold text-xs flex items-center justify-center">2</span>
            <span class="text-sm font-bold text-(--text-main)">Pilih Pola Kerja</span>
          </div>

          <div class="space-y-3">
            <div
              v-for="p in store.workPatterns"
              :key="p.id"
              @click="selectPattern(p)"
              class="border-2 rounded-md p-4 cursor-pointer hover:border-(--primary)/40 transition-colors"
              :class="form.work_pattern_id === p.id ? 'border-(--primary) bg-(--primary)/5' : 'border-(--border-soft)'"
            >
              <div class="flex items-start gap-3">
                <input type="radio" :value="p.id" v-model="form.work_pattern_id" class="mt-1 h-4 w-4 text-(--primary) border-(--border-strong)" />
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-sm text-(--text-main)">{{ p.name }}</span>
                    <Badge variant="primary">{{ p.code }}</Badge>
                    <Badge variant="neutral">{{ p.employee_type }}</Badge>
                  </div>
                  <p class="text-xs text-(--text-muted) mt-1" v-if="p.description">{{ p.description }}</p>
                  <div class="flex items-center gap-4 mt-2 text-[10px] text-(--text-soft)">
                    <span>📅 {{ p.work_day }} Hari/Minggu</span>
                    <span>✂️ Cut-off: tgl {{ p.cut_off_date }}</span>
                    <span>🔄 {{ Object.keys(p.detailGroups || {}).length }} Grup Siklus</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </BaseCard>

        <!-- Step 3: Pilih Grup Siklus -->
        <BaseCard v-if="selectedPattern">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-6 h-6 rounded-md bg-(--primary) text-white font-bold text-xs flex items-center justify-center">3</span>
            <span class="text-sm font-bold text-(--text-main)">Pilih Siklus Shift</span>
          </div>

          <div class="space-y-3" v-if="Object.keys(selectedPattern.detailGroups || {}).length > 0">
            <div
              v-for="(details, groupName) in selectedPattern.detailGroups"
              :key="groupName"
              @click="form.detail_group_name = groupName"
              class="border-2 rounded-md p-4 cursor-pointer hover:border-(--primary)/40 transition-colors"
              :class="form.detail_group_name === groupName ? 'border-(--primary) bg-(--primary)/5' : 'border-(--border-soft)'"
            >
              <div class="flex items-start gap-3">
                <input type="radio" :value="groupName" v-model="form.detail_group_name" class="mt-1 h-4 w-4 text-(--primary) border-(--border-strong)" />
                <div class="flex-1">
                  <div class="font-bold text-sm text-(--text-main)">{{ groupName }}</div>
                  <div class="text-xs text-(--text-muted) mt-1">Siklus: {{ details.length }} Hari</div>
                  
                  <!-- Cycle visual details -->
                  <div class="flex flex-wrap gap-1 mt-2">
                    <div
                      v-for="d in details"
                      :key="d.day_number"
                      class="px-1.5 py-1 border rounded text-[9px] font-bold"
                      :class="getDayBadgeClass(d.day_type)"
                    >
                      H-{{ d.day_number }}: {{ d.shift_id ? getShiftCode(d.shift_id) : 'L' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-6 text-xs text-(--text-muted) italic">
            Pola ini belum memiliki detail siklus shift. Konfigurasikan terlebih dahulu di menu Pola Kerja.
          </div>
        </BaseCard>

        <!-- Step 4: Pilih Karyawan -->
        <BaseCard v-if="form.detail_group_name">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-6 h-6 rounded-md bg-(--primary) text-white font-bold text-xs flex items-center justify-center">4</span>
            <span class="text-sm font-bold text-(--text-main)">Pilih Karyawan Target</span>
          </div>

          <!-- Filters -->
          <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <div class="flex-1">
              <input
                type="text"
                v-model="empSearchQuery"
                placeholder="Cari nama atau NIK..."
                class="w-full h-10 px-3 rounded-md bg-(--bg-card) border border-(--border-strong) text-xs text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
              />
            </div>
            <div>
              <select
                v-model="empDeptFilter"
                class="h-10 px-3 rounded-md bg-(--bg-card) border border-(--border-strong) text-xs text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
              >
                <option :value="null">Semua Departemen</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
          </div>

          <!-- Select all checkbox -->
          <div class="p-3 bg-(--bg-elevated)/40 border border-(--border-soft) rounded-md flex items-center gap-2 mb-3">
            <input
              type="checkbox"
              id="select-all"
              @change="toggleSelectAll"
              :checked="isAllSelected"
              class="rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow)"
            />
            <label for="select-all" class="text-xs font-bold text-(--text-main) cursor-pointer select-none">
              Pilih Semua Karyawan ({{ filteredEmployees.length }} tampil)
            </label>
          </div>

          <!-- Employees checklist list -->
          <div class="space-y-1 max-h-[300px] overflow-y-auto pr-1">
            <label
              v-for="emp in filteredEmployees"
              :key="emp.id"
              class="flex items-center gap-3 p-2.5 rounded-md hover:bg-(--bg-elevated)/50 cursor-pointer border border-transparent transition-all"
              :class="form.employee_ids.includes(emp.id) ? 'border-(--primary)/20 bg-(--primary)/5' : ''"
            >
              <input
                type="checkbox"
                :value="emp.id"
                v-model="form.employee_ids"
                class="rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow)"
              />
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded bg-(--bg-elevated) flex items-center justify-center font-bold text-sm text-(--text-main)">
                  {{ emp.name.charAt(0) }}
                </div>
                <div>
                  <div class="text-xs font-bold text-(--text-main)">{{ emp.name }}</div>
                  <div class="text-[10px] text-(--text-muted)">NIK: {{ emp.nik }} &middot; {{ emp.department }}</div>
                </div>
              </div>
            </label>

            <div v-if="filteredEmployees.length === 0" class="text-center py-6 text-xs text-(--text-muted) italic">
              Tidak ada karyawan ditemukan.
            </div>
          </div>
        </BaseCard>

      </div>

      <!-- Right Column: Sidebar Preview Summary -->
      <div class="lg:col-span-1">
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-5 sticky top-4 space-y-4 shadow-sm">
          <h2 class="text-base font-bold text-(--text-main) border-b border-(--border-soft) pb-2">
            Ringkasan Konfigurasi
          </h2>

          <div class="space-y-3 text-xs">
            <div>
              <span class="text-(--text-muted) block">Bulan Target:</span>
              <span class="font-bold text-(--text-main) text-sm" v-if="form.monthStr">{{ formatMonthLabel(form.monthStr) }}</span>
              <span class="text-red-500 font-bold" v-else>Belum dipilih</span>
            </div>

            <div>
              <span class="text-(--text-muted) block">Pola Kerja:</span>
              <span class="font-bold text-(--text-main)" v-if="selectedPattern">{{ selectedPattern.name }} ({{ selectedPattern.code }})</span>
              <span class="text-red-500 font-bold" v-else>Belum dipilih</span>
            </div>

            <div>
              <span class="text-(--text-muted) block">Siklus Shift:</span>
              <span class="font-bold text-(--text-main)" v-if="form.detail_group_name">{{ form.detail_group_name }}</span>
              <span class="text-red-500 font-bold" v-else>Belum dipilih</span>
            </div>

            <div>
              <span class="text-(--text-muted) block">Jumlah Karyawan Target:</span>
              <span class="font-bold text-(--primary) text-base">{{ form.employee_ids.length }} Karyawan</span>
            </div>
          </div>

          <BaseButton variant="primary" class="w-full" :disabled="!canSubmit || isGenerating" @click="submitGenerate">
            <span v-if="isGenerating">Memproses...</span>
            <span v-else>Generate Roster Sekarang</span>
          </BaseButton>

          <BaseButton variant="ghost" class="w-full" @click="goBack">
            Batal
          </BaseButton>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useScheduleStore } from '../../../../Stores/schedule'
import { useApi } from '../../../../composables/useApi'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import TextInput from '../../../../Components/TextInput.vue'
import Badge from '../../../../Components/Badge.vue'
import { IconArrowLeft } from '../../../../Components/Icons/index.js'

const router = useRouter()
const store = useScheduleStore()
const { get } = useApi()

const form = reactive({
  monthStr: '2026-06',
  work_pattern_id: null,
  detail_group_name: null,
  employee_ids: [],
})

const isGenerating = ref(false)

const empSearchQuery = ref('')
const empDeptFilter = ref(null)
const employees = ref([])
const departments = ref([])

const monthLabels = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]

onMounted(async () => {
  if (store.workPatterns.length === 0) {
    store.fetchWorkPatterns()
  }
  store.fetchShifts()
  
  // Fetch actual employees
  try {
    const res = await get('/api/v1/employees?limit=100')
    if (res.data && res.data.data) {
      employees.value = res.data.data.map(emp => ({
        id: emp.id,
        name: emp.first_name + ' ' + (emp.last_name || ''),
        nik: emp.employee_id || emp.nik || '-',
        department: emp.department ? emp.department.name : 'Unknown',
        department_id: emp.department_id
      }))
    } else {
      employees.value = res.data || []
    }
  } catch (error) {
    console.error('Failed to fetch employees', error)
  }

  try {
    const resDept = await get('/api/organization/departments/options')
    departments.value = resDept.data || []
  } catch (error) {
    console.error('Failed to fetch departments', error)
  }
})

const selectedPattern = computed(() => {
  return store.workPatterns.find(p => p.id === form.work_pattern_id) || null
})

const filteredEmployees = computed(() => {
  return employees.value.filter(emp => {
    const matchesSearch = String(emp.name || '').toLowerCase().includes(empSearchQuery.value.toLowerCase()) || 
                          String(emp.nik || '').toLowerCase().includes(empSearchQuery.value.toLowerCase())
    const matchesDept = !empDeptFilter.value || emp.department_id === empDeptFilter.value
    return matchesSearch && matchesDept
  })
})

const isAllSelected = computed(() => {
  return filteredEmployees.value.length > 0 && 
    filteredEmployees.value.every(emp => form.employee_ids.includes(emp.id))
})

const canSubmit = computed(() => {
  return form.monthStr && 
         form.work_pattern_id && 
         form.detail_group_name && 
         form.employee_ids.length > 0
})

function goBack() {
  router.push({ name: 'schedule.roster' })
}

function selectPattern(p) {
  form.work_pattern_id = p.id
  form.detail_group_name = null // reset group selection
}

function getShiftCode(shiftId) {
  const s = store.shifts.find(x => x.id === shiftId)
  return s ? s.code : 'L'
}

function getDayBadgeClass(type) {
  if (type === 'day_off') return 'bg-rose-500/10 text-rose-600 border-rose-200 dark:border-rose-900/30'
  if (type === 'is_sun') return 'bg-amber-500/10 text-amber-600 border-amber-200 dark:border-amber-900/30'
  return 'bg-emerald-500/10 text-emerald-600 border-emerald-200 dark:border-emerald-900/30'
}

function formatMonthLabel(monthStr) {
  const [y, m] = monthStr.split('-')
  return `${monthLabels[parseInt(m) - 1]} ${y}`
}

function toggleSelectAll() {
  if (isAllSelected.value) {
    const visibleIds = filteredEmployees.value.map(e => e.id)
    form.employee_ids = form.employee_ids.filter(id => !visibleIds.includes(id))
  } else {
    const newSelection = new Set([...form.employee_ids, ...filteredEmployees.value.map(e => e.id)])
    form.employee_ids = Array.from(newSelection)
  }
}

async function submitGenerate() {
  if (!canSubmit.value) return
  
  const [yStr, mStr] = form.monthStr.split('-')
  const year = parseInt(yStr)
  const month = parseInt(mStr)
  
  isGenerating.value = true
  try {
    const payload = {
      year,
      month,
      work_pattern_id: form.work_pattern_id,
      detail_group_name: form.detail_group_name,
      employee_ids: form.employee_ids
    }
    
    await store.generateRosterBulk(payload)
    alert(`Roster berhasil di-generate untuk ${form.employee_ids.length} karyawan!`)
    router.push({ name: 'schedule.roster' })
  } catch (error) {
    alert('Gagal membuat roster. Silakan coba lagi.')
  } finally {
    isGenerating.value = false
  }
}
</script>
