<script setup>
import { ref, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import KanbanColumn from './Components/KanbanColumn.vue'
import ImportModal from './Components/ImportModal.vue'

const { get, post } = useApi()
const notification = useNotificationStore()

// ── State ──
const rosterPool = ref([])
const groupPool = ref([])
const period = ref({ start: '', end: '', name: '', year: new Date().getFullYear(), month: new Date().getMonth() + 1 })

const activeYear = ref(period.value.year)
const activeMonth = ref(period.value.month)
const isLoading = ref(true)
const draggedEmployeeId = ref(null)
const selectedIds = ref([])
const hasChanges = ref(false)
const showImportModal = ref(false)

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

    period.value = data.period || { start: '', end: '', name: '' }
    rosterPool.value = data.roster_pool || []
    groupPool.value = data.group_pool || []

    hasChanges.value = false
    selectedIds.value = []
  } catch (e) {
    console.error(e)
    notification.addNotification('Gagal memuat data.', 'error')
  } finally {
    isLoading.value = false
  }
}

watch([activeMonth, activeYear], () => fetchData(), { immediate: true })

// ── Drag & Drop ──
const onDragStart = (id) => {
  draggedEmployeeId.value = id
}

const onDrop = (target) => {
  if (!draggedEmployeeId.value) return

  const sourceEmployeeIds = selectedIds.value.includes(draggedEmployeeId.value)
    ? [...selectedIds.value]
    : [draggedEmployeeId.value]

  sourceEmployeeIds.forEach(empId => {
    if (target === 'roster') {
      // Drag dari group → roster (remove from group)
      const found = groupPool.value.find(m => m.employee_id === empId)
      if (!found) return

      rosterPool.value.push({
        id: found.employee_id,
        name: found.name,
        employee_code: found.employee_code,
        nip: found.nip,
        _to_delete: found._group_id || null,
      })
      groupPool.value = groupPool.value.filter(m => m.employee_id !== empId)
    } else {
      // Drag dari roster → group (assign)
      const found = rosterPool.value.find(e => e.id === empId)
      if (!found) return

      groupPool.value.push({
        employee_id: found.id,
        name: found.name,
        employee_code: found.employee_code,
        nip: found.nip,
        photo_url: found.photo || found.photo_url || null,
        _group_id: null,
      })
      rosterPool.value = rosterPool.value.filter(e => e.id !== empId)
    }

    hasChanges.value = true
  })

  draggedEmployeeId.value = null
}

// ── Save ──
const saveChanges = async () => {
  const assignments = groupPool.value
    .filter(m => !m._group_id) // yang baru (belum punya record)
    .map(m => ({ employee_id: m.employee_id }))

  const removals = rosterPool.value
    .filter(e => e._to_delete)
    .map(e => e._to_delete)

  try {
    await post('/api/v1/supervisor/employee-data/karyawan-group/bulk-update', {
      period_start: period.value.start,
      period_end: period.value.end,
      group_name: period.value.name,
      group_code: period.value.name,
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

const selectAllInGroup = (employees) => {
  const ids = employees.map(e => e.employee_id || e.id)
  const allSelected = ids.every(id => selectedIds.value.includes(id))
  if (allSelected) {
    selectedIds.value = selectedIds.value.filter(id => !ids.includes(id))
  } else {
    selectedIds.value = [...new Set([...selectedIds.value, ...ids])]
  }
}

// ── Column definitions ──
const rosterColumn = {
  label: 'Karyawan Roster',
  value: 'roster',
  icon: 'bx-user-plus',
  color: 'var(--text-soft)',
  employees: rosterPool,
}

const groupColumn = {
  label: period.value.name || 'Periode',
  value: 'group',
  icon: 'bx-folder',
  color: 'var(--primary)',
  employees: groupPool,
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
        <button
          @click="showImportModal = true"
          class="bg-(--primary) text-white px-4 py-2.5 rounded-md font-bold shadow-lg shadow-(--primary)/20 flex items-center gap-2 hover:opacity-90 transition-all"
          title="Import dari Excel"
        >
          <i class="bx bx-import text-xl"></i>
          <span class="hidden md:inline">Import Excel</span>
        </button>

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

      <!-- Empty state -->
      <div v-if="!period.name" class="py-20 text-center">
        <i class="bx bx-calendar-x text-6xl text-(--text-muted) mb-4"></i>
        <h3 class="text-lg font-semibold text-(--text-main)">Pay period tidak ditemukan</h3>
        <p class="text-(--text-muted) mt-2">Pilih periode lain atau pastikan pay period sudah digenerate.</p>
      </div>

      <!-- Empty state: no roster -->
      <div v-else-if="rosterPool.length === 0 && groupPool.length === 0" class="py-20 text-center">
        <i class="bx bx-calendar-x text-6xl text-(--text-muted) mb-4"></i>
        <h3 class="text-lg font-semibold text-(--text-main)">Tidak ada karyawan dengan roster</h3>
        <p class="text-(--text-muted) mt-2">Pilih periode lain atau pastikan roster sudah digenerate.</p>
      </div>

      <!-- Kanban Board: 2 columns -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <KanbanColumn
          :category="rosterColumn"
          :employees="rosterPool"
          :selectedIds="selectedIds"
          :draggedEmployeeId="draggedEmployeeId"
          @drop="onDrop"
          @dragstart="onDragStart"
          @toggleSelection="toggleSelection"
          @selectAllInGroup="selectAllInGroup"
        />

        <KanbanColumn
          :category="groupColumn"
          :employees="groupPool"
          :selectedIds="selectedIds"
          :draggedEmployeeId="draggedEmployeeId"
          @drop="onDrop"
          @dragstart="onDragStart"
          @toggleSelection="toggleSelection"
          @selectAllInGroup="selectAllInGroup"
        />
      </div>
    </div>

    <!-- Import Modal -->
    <ImportModal
      :show="showImportModal"
      :activeYear="activeYear"
      :activeMonth="activeMonth"
      @close="showImportModal = false"
      @success="fetchData"
    />
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
