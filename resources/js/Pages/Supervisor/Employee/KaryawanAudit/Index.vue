<script setup>
import { ref, onMounted, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import KanbanColumn from './Components/KanbanColumn.vue'

const notification = useNotificationStore()
const { get, post } = useApi()

const API = '/api/v1/supervisor/employee-data/karyawan-audit'

const COLUMNS = [
  { label: 'Karyawan Supervisor', value: true, icon: 'bx bx-user-check', color: 'var(--success)' },
  { label: 'Belum Masuk', value: false, icon: 'bx bx-user', color: 'var(--text-soft)' },
]

// Data dari server (acuan perbandingan) dan salinan yang boleh diubah di UI
const employees = ref([])
const localEmployees = ref([])
const stats = ref({ audit: 0, non_audit: 0 })
const departments = ref([])

const isLoading = ref(true)
const isSaving = ref(false)
const hasChanges = ref(false)

const selectedIds = ref([])
const draggedEmployeeId = ref(null)

const filters = ref({ search: '', department_id: '', status: 'aktif' })

// ========== API ==========

async function fetchData() {
  isLoading.value = true
  try {
    const params = new URLSearchParams()
    if (filters.value.search) params.set('search', filters.value.search)
    if (filters.value.department_id) params.set('department_id', filters.value.department_id)
    if (filters.value.status) params.set('status', filters.value.status)

    const res = await get(`${API}?${params.toString()}`)

    employees.value = res.data || []
    localEmployees.value = JSON.parse(JSON.stringify(employees.value))
    stats.value = res.stats || { audit: 0, non_audit: 0 }
    hasChanges.value = false
    selectedIds.value = []
  } catch (e) {
    notification.addNotification('Gagal memuat data karyawan.', 'error')
  } finally {
    isLoading.value = false
  }
}

async function fetchDepartments() {
  try {
    const res = await get('/api/v1/supervisor/master/departments?per_page=100')
    departments.value = res.data || []
  } catch {}
}

onMounted(() => {
  fetchDepartments()
  fetchData()
})

// Pencarian di-debounce, filter lain langsung memuat ulang
let searchTimer = null
watch(() => filters.value.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => fetchData(), 400)
})

watch([() => filters.value.department_id, () => filters.value.status], () => fetchData())

// ========== KANBAN ==========

const employeesForColumn = (value) =>
  localEmployees.value.filter(e => Boolean(e.is_audit) === value)

function onDragStart(id) {
  draggedEmployeeId.value = id
}

function onDrop(value) {
  if (draggedEmployeeId.value === null) return

  // Kalau kartu yang diseret sedang terpilih, ikutkan seluruh pilihan
  const ids = selectedIds.value.includes(draggedEmployeeId.value)
    ? [...selectedIds.value]
    : [draggedEmployeeId.value]

  localEmployees.value = localEmployees.value.map(emp =>
    ids.includes(emp.id) ? { ...emp, is_audit: value } : emp
  )

  hasChanges.value = true
  draggedEmployeeId.value = null
}

function toggleSelection(id) {
  const index = selectedIds.value.indexOf(id)
  if (index > -1) {
    selectedIds.value.splice(index, 1)
  } else {
    selectedIds.value.push(id)
  }
}

function selectAllInGroup(groupEmployees) {
  const ids = groupEmployees.map(e => e.id)
  const allSelected = ids.length > 0 && ids.every(id => selectedIds.value.includes(id))

  if (allSelected) {
    selectedIds.value = selectedIds.value.filter(id => !ids.includes(id))
  } else {
    selectedIds.value = [...new Set([...selectedIds.value, ...ids])]
  }
}

function cancelChanges() {
  localEmployees.value = JSON.parse(JSON.stringify(employees.value))
  hasChanges.value = false
  selectedIds.value = []
}

async function saveChanges() {
  const changes = localEmployees.value
    .filter(emp => {
      const original = employees.value.find(e => e.id === emp.id)
      return original && Boolean(original.is_audit) !== Boolean(emp.is_audit)
    })
    .map(emp => ({ employee_id: emp.id, is_audit: Boolean(emp.is_audit) }))

  if (changes.length === 0) {
    hasChanges.value = false
    return
  }

  isSaving.value = true
  try {
    const res = await post(`${API}/bulk-update`, { changes })
    notification.addNotification(res?.message || 'Perubahan berhasil disimpan.', 'success')
    await fetchData()
  } catch (e) {
    notification.addNotification(e?.message || 'Gagal menyimpan perubahan.', 'error')
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-(--bg-main) p-6">
    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold text-(--text-main) tracking-tight">Karyawan Audit</h1>
        <p class="text-(--text-muted) mt-1">Pilah karyawan mana saja yang masuk ke lingkup karyawan supervisor.</p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 px-4 py-2 rounded-md bg-(--bg-card) border border-(--border-soft)">
          <i class="bx bx-user-check text-(--success) text-lg"></i>
          <span class="text-xs font-bold text-(--text-main)">{{ stats.audit }}</span>
          <span class="text-[10px] text-(--text-soft) font-medium">masuk</span>
        </div>
        <div class="flex items-center gap-2 px-4 py-2 rounded-md bg-(--bg-card) border border-(--border-soft)">
          <i class="bx bx-user text-(--text-soft) text-lg"></i>
          <span class="text-xs font-bold text-(--text-main)">{{ stats.non_audit }}</span>
          <span class="text-[10px] text-(--text-soft) font-medium">belum masuk</span>
        </div>

        <template v-if="hasChanges">
          <button
            @click="cancelChanges"
            :disabled="isSaving"
            class="px-4 py-2.5 rounded-md font-bold text-(--text-soft) hover:text-(--text-main) transition-all disabled:opacity-50"
          >
            Batal
          </button>
          <button
            @click="saveChanges"
            :disabled="isSaving"
            class="bg-(--success) text-white px-6 py-2.5 rounded-md font-bold shadow-lg shadow-(--success)/20 flex items-center gap-2 hover:opacity-90 transition-all disabled:opacity-50"
          >
            <i :class="['bx text-xl', isSaving ? 'bx-loader-alt animate-spin' : 'bx-save']"></i>
            {{ isSaving ? 'Menyimpan...' : 'Simpan Perubahan' }}
          </button>
        </template>
      </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-col md:flex-row gap-3">
      <div class="relative flex-1">
        <i class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft)"></i>
        <input
          v-model="filters.search"
          type="text"
          placeholder="Cari nama, NIP, atau kode karyawan..."
          class="w-full pl-10 pr-4 py-2.5 rounded-md bg-(--bg-card) border border-(--border-soft) text-sm text-(--text-main) focus:outline-none focus:border-(--primary)"
        />
      </div>

      <select
        v-model="filters.department_id"
        class="px-4 py-2.5 rounded-md bg-(--bg-card) border border-(--border-soft) text-sm text-(--text-main) focus:outline-none focus:border-(--primary)"
      >
        <option value="">Semua Departemen</option>
        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
      </select>

      <select
        v-model="filters.status"
        class="px-4 py-2.5 rounded-md bg-(--bg-card) border border-(--border-soft) text-sm text-(--text-main) focus:outline-none focus:border-(--primary)"
      >
        <option value="aktif">Aktif</option>
        <option value="nonaktif">Nonaktif</option>
        <option value="semua">Semua Status</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="py-20 text-center">
      <i class="bx bx-loader-alt animate-spin text-4xl text-(--primary) inline-block"></i>
      <p class="text-sm text-(--text-soft) mt-3">Memuat data karyawan...</p>
    </div>

    <!-- Kanban -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <KanbanColumn
        v-for="column in COLUMNS"
        :key="String(column.value)"
        :category="column"
        :employees="employeesForColumn(column.value)"
        :selectedIds="selectedIds"
        :draggedEmployeeId="draggedEmployeeId"
        @drop="onDrop"
        @dragstart="onDragStart"
        @toggleSelection="toggleSelection"
        @selectAllInGroup="selectAllInGroup"
      />
    </div>
  </div>
</template>
