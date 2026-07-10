<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import KanbanColumn from './Components/KanbanColumn.vue'

const { get, post } = useApi()
const notification = useNotificationStore()

// ── State ──
const employees = ref([])
const groups = ref([])
const groupedById = ref({})
const groupedEmployeeIds = ref([])
const period = ref({ start: '', end: '', year: new Date().getFullYear(), month: new Date().getMonth() + 1 })

const activeYear = ref(period.value.year)
const activeMonth = ref(period.value.month)
const isLoading = ref(true)
const draggedEmployeeId = ref(null)
const selectedIds = ref([])
const hasChanges = ref(false)

// Local working copy
const rosterPool = ref([])
const groupPools = ref({})

// ── Months ──
const months = [
  { id: 1, name: 'Jan' }, { id: 2, name: 'Feb' }, { id: 3, name: 'Mar' },
  { id: 4, name: 'Apr' }, { id: 5, name: 'Mei' }, { id: 6, name: 'Jun' },
  { id: 7, name: 'Jul' }, { id: 8, name: 'Agu' }, { id: 9, name: 'Sep' },
  { id: 10, name: 'Okt' }, { id: 11, name: 'Nov' }, { id: 12, name: 'Des' },
]

// ── Fetch Data ──
const fetchData = async () => {
  isLoading.value = true
  try {
    const data = await get(`/api/v1/supervisor/employee-data/karyawan-group?year=${activeYear.value}&month=${activeMonth.value}`)

    employees.value = data.employees
    groups.value = data.groups
    groupedEmployeeIds.value = data.grouped_employee_ids
    period.value = data.period

    // Build local pools
    // Roster pool: employees NOT in any group
    rosterPool.value = employees.value
      .filter(e => !groupedEmployeeIds.value.includes(e.id))
      .map(e => ({ ...e }))

    // Group pools: employees per group_code
    groupPools.value = {}
    if (data.grouped_by_code) {
      Object.entries(data.grouped_by_code).forEach(([code, members]) => {
        groupPools.value[code] = members.map(m => ({
          id: m.employee_id,
          name: m.employee?.name || '',
          employee_code: m.employee?.employee_code || '',
          nip: m.employee?.nip || '',
          photo_url: m.employee?.photo || null,
          _group_id: m.id,              // ID record di supervisor_employee_groups
          _group_name: m.group_name,
          _group_code: m.group_code,
        }))
      })
    }

    hasChanges.value = false
    selectedIds.value = []
  } catch (e) {
    console.error(e)
    notification.addNotification('Gagal memuat data.', 'error')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => fetchData())
watch([activeMonth, activeYear], () => fetchData())

// ── Column definitions (computed) ──
const kanbanColumns = computed(() => {
  const cols = []

  // Kolom 1: Karyawan Roster (available)
  cols.push({
    label: 'Karyawan Roster',
    value: '__roster__',
    icon: 'bx-user-plus',
    color: 'var(--text-soft)',
    employees: rosterPool.value,
  })

  // Kolom dinamis: per group_code
  groups.value.forEach(g => {
    const members = groupPools.value[g.group_code] || []
    cols.push({
      label: g.group_name,
      value: g.group_code,
      icon: 'bx-folder',
      color: 'var(--primary)',
      employees: members,
    })
  })

  return cols
})

// ── Drag & Drop ──
const onDragStart = (id) => {
  draggedEmployeeId.value = id
}

const onDrop = (targetValue) => {
  if (!draggedEmployeeId.value) return

  const sourceEmployeeIds = selectedIds.value.includes(draggedEmployeeId.value)
    ? [...selectedIds.value]
    : [draggedEmployeeId.value]

  // Cari employee dari rosterPool atau groupPools
  sourceEmployeeIds.forEach(empId => {
    let emp = null
    let sourceGroupCode = null

    // Cek di roster pool
    emp = rosterPool.value.find(e => e.id === empId)
    if (!emp) {
      // Cek di group pools
      for (const [code, members] of Object.entries(groupPools.value)) {
        const found = members.find(m => m.id === empId)
        if (found) {
          emp = found
          sourceGroupCode = code
          break
        }
      }
    }

    if (!emp) return

    // Remove from source
    if (sourceGroupCode) {
      groupPools.value[sourceGroupCode] = groupPools.value[sourceGroupCode].filter(m => m.id !== empId)
    } else {
      rosterPool.value = rosterPool.value.filter(e => e.id !== empId)
    }

    // Add to target
    if (targetValue === '__roster__') {
      // Pindah ke roster → remove dari group (akan dihapus saat save)
      rosterPool.value.push({
        id: emp.id,
        name: emp.name,
        employee_code: emp.employee_code,
        nip: emp.nip,
        _to_delete: emp._group_id || null,
      })
    } else {
      // Pindah ke group lain
      const targetGroup = groups.value.find(g => g.group_code === targetValue)
      if (!targetGroup) return

      if (!groupPools.value[targetValue]) {
        groupPools.value[targetValue] = []
      }
      groupPools.value[targetValue].push({
        id: emp.id,
        name: emp.name,
        employee_code: emp.employee_code,
        nip: emp.nip,
        _group_id: emp._group_id || null,
        _group_name: targetGroup.group_name,
        _group_code: targetGroup.group_code,
      })
    }

    hasChanges.value = true
  })

  draggedEmployeeId.value = null
}

// ── Save Changes ──
const saveChanges = async () => {
  const assignments = []
  const removals = []

  // Collect removals (karyawan di roster pool yang punya _to_delete)
  rosterPool.value.forEach(e => {
    if (e._to_delete) removals.push(e._to_delete)
  })

  // Collect assignments per group
  Object.entries(groupPools.value).forEach(([code, members]) => {
    const group = groups.value.find(g => g.group_code === code)
    members.forEach(m => {
      assignments.push({
        employee_id: m.id,
        group_name: group?.group_name || m._group_name || code,
        group_code: code,
      })
    })
  })

  try {
    await post('/api/v1/supervisor/employee-data/karyawan-group/bulk-update', {
      period_start: period.value.start,
      period_end: period.value.end,
      assignments,
      removals,
    })
    notification.addNotification('Perubahan berhasil disimpan!', 'success')
    fetchData()
  } catch (e) {
    notification.addNotification('Gagal menyimpan perubahan.', 'error')
  }
}

const cancelChanges = () => {
  fetchData()
}

// ── Selection ──
const toggleSelection = (id) => {
  const idx = selectedIds.value.indexOf(id)
  if (idx > -1) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(id)
}

const selectAllInGroup = (groupEmployees) => {
  const ids = groupEmployees.map(e => e.id)
  const allSelected = ids.every(id => selectedIds.value.includes(id))
  if (allSelected) {
    selectedIds.value = selectedIds.value.filter(id => !ids.includes(id))
  } else {
    selectedIds.value = [...new Set([...selectedIds.value, ...ids])]
  }
}
</script>

<template>
  <div class="min-h-screen bg-(--bg-main) p-6">
    <!-- Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-(--text-main) tracking-tight">Karyawan Group</h1>
        <p class="text-(--text-muted) mt-1">Kelola group karyawan untuk dashboard supervisor berdasarkan roster periode.</p>
      </div>

      <div class="flex items-center gap-3">
        <transition name="slide-fade">
          <div v-if="hasChanges" class="flex items-center gap-2">
            <button
              @click="cancelChanges"
              class="px-4 py-2.5 rounded-md font-bold text-(--text-soft) hover:text-(--text-main) transition-all"
            >
              Batal
            </button>
            <button
              @click="saveChanges"
              class="bg-(--success) text-white px-6 py-2.5 rounded-md font-bold shadow-lg shadow-(--success)/20 flex items-center gap-2 hover:opacity-90 transition-all animate-pulse"
            >
              <i class="bx bx-save text-xl"></i>
              Simpan Perubahan
            </button>
          </div>
        </transition>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center items-center py-20">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-(--primary)"></div>
    </div>

    <div v-else>
      <!-- Periode Selector -->
      <div class="flex flex-wrap items-center gap-4 mb-8 bg-(--bg-card) p-1.5 rounded-md border border-(--border-soft) shadow-sm w-fit">
        <div class="flex items-center gap-1 border-r border-(--border-soft) pr-2 mr-2">
          <select
            v-model="activeYear"
            class="bg-transparent border-none text-sm font-bold text-(--text-main) focus:ring-0 cursor-pointer outline-none"
          >
            <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="flex items-center gap-1">
          <button
            v-for="month in months"
            :key="month.id"
            @click="activeMonth = month.id"
            :class="[
              'px-3 py-1.5 rounded-md text-xs font-bold transition-all whitespace-nowrap',
              activeMonth === month.id
                ? 'bg-(--primary) text-white shadow-md'
                : 'text-(--text-soft) hover:bg-(--bg-elevated)',
            ]"
          >
            {{ month.name }}
          </button>
        </div>
      </div>

      <p class="text-xs text-(--text-muted) mb-4">
        Periode: <strong>{{ period.start }}</strong> s/d <strong>{{ period.end }}</strong>
      </p>

      <!-- Kanban Board -->
      <div
        :class="[
          'grid gap-6 transition-all duration-500',
          kanbanColumns.length === 1
            ? 'grid-cols-1'
            : kanbanColumns.length === 2
              ? 'grid-cols-1 lg:grid-cols-2'
              : 'grid-cols-1 lg:grid-cols-2 xl:grid-cols-3',
        ]"
      >
        <KanbanColumn
          v-for="col in kanbanColumns"
          :key="col.value"
          :category="col"
          :employees="col.employees"
          :selectedIds="selectedIds"
          :draggedEmployeeId="draggedEmployeeId"
          @drop="onDrop"
          @dragstart="onDragStart"
          @toggleSelection="toggleSelection"
          @selectAllInGroup="selectAllInGroup"
        />
      </div>

      <!-- Empty state if no roster employees at all -->
      <div v-if="employees.length === 0 && !isLoading" class="py-20 text-center">
        <i class="bx bx-calendar-x text-6xl text-(--text-muted) mb-4"></i>
        <h3 class="text-lg font-semibold text-(--text-main)">Tidak ada karyawan dengan roster</h3>
        <p class="text-(--text-muted) mt-2">Pilih periode lain atau pastikan roster sudah digenerate.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(20px);
  opacity: 0;
}
</style>
