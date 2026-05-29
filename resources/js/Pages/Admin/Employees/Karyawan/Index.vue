<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import Badge from '../../../../Components/Badge.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import Pagination from '../../../../Components/Table/Pagination.vue'
import {
  IconPlus,
  IconPencil,
  IconTrash,
  IconEye,
  IconDownload,
  IconUpload,
  IconUsers,
  IconRefresh,
} from '../../../../Components/Icons/index.js'

const router = useRouter()
const notification = useNotificationStore()
const { get, destroy: apiDelete, post } = useApi()

// State
const loading = ref(false)
const employees = ref([])
const stats = ref({ total: 0, active: 0, permanent: 0, contract: 0 })
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const showDeleteDialog = ref(false)
const selectedEmployee = ref(null)



// Filters
const searchQuery = ref('')
const filterDepartment = ref('')
const filterStatus = ref('')
const filterEmploymentStatus = ref('')
const currentPage = ref(1)

// Departments dari API
const departments = ref([])

const departmentOptions = computed(() =>
  departments.value.map(d => ({ value: d.id, label: d.name }))
)

const statusOptions = [
  { value: '1', label: 'Aktif' },
  { value: '0', label: 'Nonaktif' },
]

const employmentStatusOptions = [
  { value: 'permanent', label: 'Tetap' },
  { value: 'contract', label: 'Kontrak' },
  { value: 'probation', label: 'Probation' },
  { value: 'outsource', label: 'Outsource' },
  { value: 'freelance', label: 'Freelance' },
]

const tableHeaders = [
  { key: 'employee_code', label: 'Kode' },
  { key: 'name', label: 'Nama Karyawan' },
  { key: 'department', label: 'Departemen' },
  { key: 'position', label: 'Jabatan' },
  { key: 'employment_status', label: 'Status' },
  { key: 'is_active', label: 'Aktif', width: '90px' },
  { key: 'join_date', label: 'Bergabung', width: '110px' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '120px' },
]

// ========== API CALLS ==========

async function fetchEmployees() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', currentPage.value)
    if (searchQuery.value)         params.set('search', searchQuery.value)
    if (filterDepartment.value)    params.set('department_id', filterDepartment.value)
    if (filterEmploymentStatus.value) params.set('employment_status', filterEmploymentStatus.value)
    if (filterStatus.value !== '')  params.set('is_active', filterStatus.value)

    const res = await get(`/api/v1/employees?${params}`)
    employees.value = res.data
    pagination.value = res.meta || {}
  } catch (e) {
    notification.error(e.message || 'Gagal memuat data karyawan.')
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const res = await get('/api/v1/employees/stats')
    stats.value = res.data
  } catch {}
}

async function fetchDepartments() {
  try {
    const res = await get('/api/organization/departments/options')
    departments.value = res.data || []
  } catch {}
}

// ========== ACTIONS ==========

function viewEmployee(id) {
  router.push(`/employees/${id}`)
}

function editEmployee(id) {
  router.push(`/employees/${id}/edit`)
}

function confirmDelete(employee) {
  selectedEmployee.value = employee
  showDeleteDialog.value = true
}

async function handleDelete() {
  if (!selectedEmployee.value) return
  try {
    await apiDelete(`/api/v1/employees/${selectedEmployee.value.id}`)
    notification.success(`Karyawan ${selectedEmployee.value.name} berhasil dihapus.`)
    fetchEmployees()
    fetchStats()
  } catch (e) {
    notification.error(e.message || 'Gagal menghapus karyawan.')
  } finally {
    showDeleteDialog.value = false
    selectedEmployee.value = null
  }
}



function cancelDelete() {
  showDeleteDialog.value = false
  selectedEmployee.value = null
}

function handlePageChange(page) {
  currentPage.value = page
}

function clearFilters() {
  searchQuery.value = ''
  filterDepartment.value = ''
  filterStatus.value = ''
  filterEmploymentStatus.value = ''
  currentPage.value = 1
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}

// ========== WATCHERS ==========

let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchEmployees()
  }, 400)
})

watch([filterDepartment, filterStatus, filterEmploymentStatus, currentPage], () => {
  fetchEmployees()
})

// ========== INIT ==========
onMounted(async () => {
  await Promise.all([fetchEmployees(), fetchStats(), fetchDepartments()])
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-(--text-main)">Data Karyawan</h1>
      <div class="flex items-center gap-3">
        <BaseButton variant="secondary" @click="$router.push('/admin/employees/import')">
          <template #icon-left>
            <IconUpload class="w-4 h-4" />
          </template>
          Import Excel
        </BaseButton>
        <BaseButton variant="secondary">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Export Excel
        </BaseButton>
        <BaseButton variant="primary" @click="$router.push('/admin/employees/create')">
          <template #icon-left>
            <IconPlus class="w-4 h-4" />
          </template>
          Tambah Karyawan
        </BaseButton>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-4 gap-4">
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-md bg-(--primary)/10 text-(--primary)">
            <IconUsers class="w-5 h-5" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Total Karyawan</p>
            <p class="text-xl font-bold text-(--text-main)">{{ stats.total }}</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-md bg-(--success)/10 text-(--success)">
            <IconUsers class="w-5 h-5" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Aktif</p>
            <p class="text-xl font-bold text-(--text-main)">{{ stats.active }}</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-md bg-(--primary)/10 text-(--primary)">
            <IconUsers class="w-5 h-5" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Tetap</p>
            <p class="text-xl font-bold text-(--text-main)">{{ stats.permanent }}</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-md bg-(--warning)/10 text-(--warning)">
            <IconUsers class="w-5 h-5" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Kontrak</p>
            <p class="text-xl font-bold text-(--text-main)">{{ stats.contract }}</p>
          </div>
        </div>
      </BaseCard>
    </div>

    <!-- Table -->
    <BaseCard>
      <!-- Filters -->
      <div class="flex items-center gap-4 mb-4 flex-wrap">
        <div class="relative w-64">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari kode, nama, NIK, email..."
            class="w-full pl-10 pr-3 py-2 text-sm rounded-md border bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
          />
        </div>
        <SelectInput
          v-model="filterDepartment"
          :options="departmentOptions"
          placeholder="Semua Departemen"
          class="w-48"
        />
        <SelectInput
          v-model="filterEmploymentStatus"
          :options="employmentStatusOptions"
          placeholder="Status Karyawan"
          class="w-44"
        />
        <SelectInput
          v-model="filterStatus"
          :options="statusOptions"
          placeholder="Semua Status"
          class="w-40"
        />
        <BaseButton variant="ghost" @click="clearFilters">
          Reset Filter
        </BaseButton>
        <BaseButton variant="ghost" @click="fetchEmployees" :disabled="loading">
          <template #icon-left>
            <IconRefresh class="w-4 h-4" :class="{ 'animate-spin': loading }" />
          </template>
        </BaseButton>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-16 text-(--text-muted)">
        <IconRefresh class="w-5 h-5 animate-spin mr-2" />
        <span class="text-sm">Memuat data...</span>
      </div>

      <!-- Table -->
      <DataTable
        v-else
        :headers="tableHeaders"
        :items="employees"
      >
        <template #item.department="{ item }">
          {{ item.department?.name ?? '-' }}
        </template>
        <template #item.position="{ item }">
          {{ item.position?.name ?? '-' }}
        </template>
        <template #item.employment_status="{ value }">
          <Badge
            :variant="value === 'permanent' ? 'primary' : value === 'contract' ? 'warning' : value === 'probation' ? 'info' : 'secondary'"
          >
            {{ value === 'permanent' ? 'Tetap' : value === 'contract' ? 'Kontrak' : value === 'probation' ? 'Probation' : value }}
          </Badge>
        </template>
        <template #item.is_active="{ value }">
          <Badge :variant="value ? 'success' : 'danger'">
            {{ value ? 'Aktif' : 'Nonaktif' }}
          </Badge>
        </template>
        <template #item.join_date="{ value }">
          <span class="text-sm text-(--text-muted)">{{ formatDate(value) }}</span>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
              title="Lihat Detail"
              @click="viewEmployee(item.id)"
            >
              <IconEye class="w-4 h-4" />
            </button>
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--warning) hover:bg-(--warning)/10 transition-colors"
              title="Edit"
              @click="editEmployee(item.id)"
            >
              <IconPencil class="w-4 h-4" />
            </button>
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors"
              title="Hapus"
              @click="confirmDelete(item)"
            >
              <IconTrash class="w-4 h-4" />
            </button>
          </div>
        </template>
        <!-- Empty state -->
        <template #empty>
          <div class="text-center py-12 text-(--text-muted)">
            <IconUsers class="w-10 h-10 mx-auto mb-3 opacity-30" />
            <p class="text-sm">Tidak ada karyawan ditemukan.</p>
            <BaseButton class="mt-4" @click="router.push('/employees/create')">
              <template #icon-left><IconPlus class="w-4 h-4" /></template>
              Tambah Karyawan
            </BaseButton>
          </div>
        </template>
      </DataTable>

      <!-- Pagination -->
      <Pagination
        :current-page="pagination.current_page ?? 1"
        :total-pages="pagination.last_page ?? 1"
        :total="pagination.total ?? 0"
        :per-page="pagination.per_page ?? 15"
        @page-change="handlePageChange"
      />
    </BaseCard>

    <!-- Delete Confirm Dialog -->
    <ConfirmDialog
      :show="showDeleteDialog"
      title="Hapus Karyawan"
      :message="'Apakah Anda yakin ingin menghapus karyawan ' + selectedEmployee?.name + ' (' + selectedEmployee?.employee_code + ')? Tindakan ini tidak dapat dibatalkan.'"
      confirm-text="Ya, Hapus"
      cancel-text="Batal"
      variant="danger"
      @confirm="handleDelete"
      @cancel="cancelDelete"
    />


  </div>
</template>
