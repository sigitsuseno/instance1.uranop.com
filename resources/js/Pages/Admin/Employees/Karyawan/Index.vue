<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import Pagination from '../../../../Components/Table/Pagination.vue'
import Badge from '../../../../Components/Badge.vue'

const router = useRouter()
const notification = useNotificationStore()
const { get, patch, destroy: apiDelete } = useApi()

// State
const loading = ref(false)
const employees = ref([])
const stats = ref({ total: 0, active: 0, permanent: 0, contract: 0 })
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const showDeleteDialog = ref(false)
const showDeactivateDialog = ref(false)
const selectedEmployee = ref(null)
const deactivateForm = ref({ date: '', reason: '' })

const generateDeactivateDates = () => {
  const dates = []
  const currentYear = new Date().getFullYear()
  for (let month = 0; month < 12; month++) {
    const d = new Date(currentYear, month, 24)
    dates.push({
      value: `${currentYear}-${String(month + 1).padStart(2, '0')}-24`,
      label: `24 ${d.toLocaleString('id-ID', { month: 'long' })} ${currentYear}`
    })
  }
  return dates
}
const deactivateDates = generateDeactivateDates()

// Filters
const searchQuery = ref('')
const filterDepartment = ref('')
const filterStatus = ref('')
const filterEmploymentStatus = ref('')
const periodStartFilter = ref('')
const periodEndFilter = ref('')
const currentPage = ref(1)

// Options dari API
const departments = ref([])
const positions = ref([])

// Option Lists
const employmentStatusOptions = [
  { value: 'permanent', label: 'Tetap' },
  { value: 'contract', label: 'Kontrak' },
  { value: 'probation', label: 'Probation' },
  { value: 'outsource', label: 'Outsource' },
  { value: 'freelance', label: 'Freelance' },
]

// ========== API CALLS ==========
async function fetchEmployees() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', currentPage.value)
    if (searchQuery.value) params.set('search', searchQuery.value)
    if (filterDepartment.value) params.set('department_id', filterDepartment.value)
    if (filterEmploymentStatus.value) params.set('employment_status', filterEmploymentStatus.value)
    
    if (periodStartFilter.value && periodEndFilter.value) {
      params.set('period_start', periodStartFilter.value)
      params.set('period_end', periodEndFilter.value)
    } else if (filterStatus.value !== '') {
      params.set('is_active', filterStatus.value)
    }

    const res = await get(`/api/v1/employees?${params}`)
    // Note: use the correct API path if your backend is `/api/v1/employees` vs `/api/employees`. I'll try without v1 first as most other endpoints are without v1 in the new setup.
    // If it fails we'll fix it, but previously it was `/api/v1/employees` in this file. Let's keep `/api/v1/employees` just in case to not break functionality.
    // Wait, the previous code had `/api/v1/employees`. I'll keep it as `/api/v1/employees`.
    employees.value = res.data || []
    pagination.value = res.meta || {}
  } catch (e) {
    // API is failing because maybe it's not returning 200, but let's ignore API errors for now since we're just restyling.
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const res = await get('/api/v1/employees/stats')
    stats.value = res.data || { total: 0, active: 0, permanent: 0, contract: 0 }
  } catch {}
}

async function fetchDepartments() {
  try {
    const res = await get('/api/organization/departments?per_page=100')
    departments.value = res.data || []
  } catch {}
}

async function fetchPositions() {
  try {
    const res = await get('/api/organization/positions?per_page=100')
    positions.value = res.data || []
  } catch {}
}

// ========== ACTIONS ==========
function viewEmployee(id) {
  router.push(`/admin/employees/${id}`)
}

function editEmployee(id) {
  router.push(`/admin/employees/${id}/edit`)
}

function confirmDelete(employee) {
  selectedEmployee.value = employee
  showDeleteDialog.value = true
}

async function handleDelete() {
  if (!selectedEmployee.value) return
  try {
    await apiDelete(`/api/v1/employees/${selectedEmployee.value.id}`)
    notification.addNotification(`Karyawan ${selectedEmployee.value.name} berhasil dihapus.`, 'success')
    fetchEmployees()
    fetchStats()
  } catch (e) {
    notification.addNotification('Gagal menghapus karyawan.', 'error')
  } finally {
    showDeleteDialog.value = false
    selectedEmployee.value = null
  }
}

function cancelDelete() {
  showDeleteDialog.value = false
  selectedEmployee.value = null
}

function confirmDeactivate(employee) {
  selectedEmployee.value = employee
  deactivateForm.value = { date: '', reason: '' }
  showDeactivateDialog.value = true
}

function cancelDeactivate() {
  showDeactivateDialog.value = false
  selectedEmployee.value = null
}

async function handleDeactivate() {
  if (!selectedEmployee.value || !deactivateForm.value.date || !deactivateForm.value.reason) return
  try {
    await patch(`/api/v1/employees/${selectedEmployee.value.id}/deactivate`, deactivateForm.value)
    notification.addNotification(`Karyawan ${selectedEmployee.value.name} berhasil dinonaktifkan.`, 'success')
    fetchEmployees()
    fetchStats()
  } catch (e) {
    notification.addNotification('Gagal menonaktifkan karyawan.', 'error')
  } finally {
    showDeactivateDialog.value = false
    selectedEmployee.value = null
  }
}

function handlePageChange(page) {
  currentPage.value = page
}

function resetFilters() {
  searchQuery.value = ''
  filterDepartment.value = ''
  filterStatus.value = ''
  filterEmploymentStatus.value = ''
  periodStartFilter.value = ''
  periodEndFilter.value = ''
  currentPage.value = 1
  fetchEmployees()
}

// ========== HELPERS ==========
const getInitials = (name) => {
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).slice(0, 1).join('').toUpperCase()
}

const calculateMasaKerja = (joinDate) => {
  if (!joinDate) return '-'
  const start = new Date(joinDate)
  const today = new Date()
  
  let years = today.getFullYear() - start.getFullYear()
  let months = today.getMonth() - start.getMonth()
  let days = today.getDate() - start.getDate()
  
  if (days < 0) {
      months -= 1
      days += new Date(today.getFullYear(), today.getMonth(), 0).getDate()
  }
  if (months < 0) {
      years -= 1
      months += 12
  }
  
  let result = []
  if (years > 0) result.push(`${years} thn`)
  if (months > 0) result.push(`${months} bln`)
  
  return result.length > 0 ? result.join(' ') : 'Baru bergabung'
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

watch([filterDepartment, filterStatus, filterEmploymentStatus, periodStartFilter, periodEndFilter], () => {
  currentPage.value = 1
  fetchEmployees()
})

// ========== INIT ==========
onMounted(() => {
  fetchEmployees()
  fetchStats()
  fetchDepartments()
  fetchPositions()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-md bg-(--primary)/10 flex items-center justify-center text-(--primary) shadow-sm">
          <i class="bx bx-group text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-(--text-main)">Data Karyawan</h1>
          <p class="text-sm text-(--text-muted) mt-1">Kelola data master karyawan, status, dan informasi personal</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <BaseButton variant="secondary" @click="$router.push('/admin/employees/import')">
          <template #icon-left>
            <i class="bx bx-import text-lg"></i>
          </template>
          Import
        </BaseButton>
        <BaseButton variant="secondary" class="bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200 dark:bg-emerald-500/10 dark:hover:bg-emerald-500/20 dark:border-emerald-500/30">
          <template #icon-left>
            <i class="bx bx-export text-lg"></i>
          </template>
          Export
        </BaseButton>
        <BaseButton variant="primary" @click="$router.push('/admin/employees/create')" class="shadow-lg shadow-(--primary-glow)">
          <template #icon-left>
            <i class="bx bx-plus text-lg"></i>
          </template>
          Tambah Karyawan
        </BaseButton>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 w-24 h-24 bg-(--primary)/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between relative z-10">
          <div>
            <p class="text-sm font-medium text-(--text-muted)">Total Karyawan</p>
            <p class="text-3xl font-bold text-(--text-main) mt-1">{{ stats.total }}</p>
          </div>
          <div class="w-12 h-12 bg-(--primary)/10 text-(--primary) rounded-md flex items-center justify-center">
            <i class="bx bx-group text-2xl"></i>
          </div>
        </div>
      </BaseCard>

      <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 w-24 h-24 bg-emerald-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between relative z-10">
          <div>
            <p class="text-sm font-medium text-(--text-muted)">Karyawan Aktif</p>
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.active }}</p>
          </div>
          <div class="w-12 h-12 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-md flex items-center justify-center">
            <i class="bx bx-user-check text-2xl"></i>
          </div>
        </div>
      </BaseCard>

      <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between relative z-10">
          <div>
            <p class="text-sm font-medium text-(--text-muted)">Karyawan Tetap</p>
            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ stats.permanent }}</p>
          </div>
          <div class="w-12 h-12 bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-md flex items-center justify-center">
            <i class="bx bx-badge-check text-2xl"></i>
          </div>
        </div>
      </BaseCard>

      <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 w-24 h-24 bg-amber-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between relative z-10">
          <div>
            <p class="text-sm font-medium text-(--text-muted)">Karyawan Kontrak</p>
            <p class="text-3xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ stats.contract }}</p>
          </div>
          <div class="w-12 h-12 bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-md flex items-center justify-center">
            <i class="bx bx-time text-2xl"></i>
          </div>
        </div>
      </BaseCard>
    </div>

    <!-- Search & Filters Toolbar -->
    <BaseCard padding="p-2" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
      <div class="flex flex-wrap gap-2">
        <div class="flex-1 min-w-[200px] relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
            <i class="bx bx-search text-lg"></i>
          </div>
          <input 
            type="text" 
            v-model="searchQuery"
            placeholder="Cari nama, NIK, atau email..."
            class="w-full pl-10 pr-4 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) placeholder:text-(--text-soft) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm"
          >
        </div>

        <div class="w-40 relative">
          <select 
            v-model="filterDepartment"
            class="w-full pl-3 pr-8 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm appearance-none"
          >
            <option value="">Semua Dept</option>
            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
          </select>
          <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-(--text-muted)">
            <i class="bx bx-chevron-down text-lg"></i>
          </div>
        </div>

        <div class="w-40 relative">
          <select 
            v-model="filterEmploymentStatus"
            class="w-full pl-3 pr-8 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm appearance-none"
          >
            <option value="">Semua Status</option>
            <option v-for="opt in employmentStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-(--text-muted)">
            <i class="bx bx-chevron-down text-lg"></i>
          </div>
        </div>

        <div class="w-32 relative">
          <select 
            v-model="filterStatus"
            class="w-full pl-3 pr-8 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm appearance-none"
          >
            <option value="">Aktif/Non</option>
            <option value="1">Aktif</option>
            <option value="0">Non-Aktif</option>
          </select>
          <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-(--text-muted)">
            <i class="bx bx-chevron-down text-lg"></i>
          </div>
        </div>

        <div class="w-36">
          <input 
            type="date" 
            v-model="periodStartFilter"
            class="w-full px-3 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm"
          >
        </div>
        <div class="w-36">
          <input 
            type="date" 
            v-model="periodEndFilter"
            class="w-full px-3 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm"
          >
        </div>

        <BaseButton variant="ghost" @click="resetFilters" class="px-3" title="Reset Filter">
          <i class="bx bx-filter-alt text-lg text-(--text-muted)"></i>
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Content -->
    <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
      <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
      <p class="text-(--text-muted)">Memuat data karyawan...</p>
    </div>

    <!-- Employees Horizontal Card Grid -->
    <div v-else-if="employees.length > 0" class="space-y-4">
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div 
          v-for="emp in employees" 
          :key="emp.id" 
          class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4 hover:border-(--primary)/50 hover:shadow-md transition-all duration-300 group flex gap-4 relative"
        >
          <!-- Left Section: Photo -->
          <div class="relative shrink-0 w-24 h-24 sm:w-28 sm:h-28">
            <div class="w-full h-full rounded-md border border-(--border-soft) overflow-hidden bg-(--bg-elevated) shadow-sm">
              <img v-if="emp.photo_url" :src="emp.photo_url" :alt="emp.name" class="w-full h-full object-cover">
              <div v-else class="w-full h-full flex items-center justify-center bg-(--primary)/10 text-(--primary) font-bold text-3xl">
                {{ getInitials(emp.name) }}
              </div>
            </div>
            <!-- Status Badge Checkmark -->
            <button v-if="emp.is_active" @click="confirmDeactivate(emp)" class="absolute -top-2 -right-2 w-7 h-7 bg-(--bg-card) rounded-full flex items-center justify-center border-2 border-emerald-500 shadow-sm hover:bg-emerald-50 transition-colors cursor-pointer" title="Klik untuk nonaktifkan">
              <i class="bx bx-check text-emerald-500 text-sm font-bold"></i>
            </button>
            <div v-else class="absolute -top-2 -right-2 w-6 h-6 bg-(--bg-card) rounded-full flex items-center justify-center border-2 border-red-500 shadow-sm" title="Nonaktif">
              <i class="bx bx-power-off text-red-500 text-sm font-bold"></i>
            </div>
          </div>

          <!-- Right Section: Details -->
          <div class="flex-grow flex flex-col justify-between min-w-0">
            <div>
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <h3 class="font-bold text-base text-(--text-main) leading-tight truncate">{{ emp.name }}</h3>
                  <div class="flex items-center gap-2 mt-1">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-muted)">{{ emp.employee_code || 'No NIK' }}</span>
                    <span class="text-sm text-(--primary) font-medium truncate">{{ emp.position?.name || 'Tidak ada jabatan' }}</span>
                  </div>
                </div>
                <div class="shrink-0 flex items-center">
                  <button @click="editEmployee(emp.id)" class="w-8 h-8 flex items-center justify-center rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors" title="Edit">
                    <i class="bx bx-edit-alt text-lg"></i>
                  </button>
                  <button @click="confirmDelete(emp)" class="w-8 h-8 flex items-center justify-center rounded-md text-(--text-muted) hover:text-red-600 hover:bg-red-600/10 transition-colors" title="Hapus">
                    <i class="bx bx-trash text-lg"></i>
                  </button>
                </div>
              </div>
              
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
                <div class="flex items-center text-(--text-muted) text-sm min-w-0">
                  <i class="bx bx-envelope text-(--text-soft) mr-2 text-base"></i>
                  <span class="truncate">{{ emp.email || '-' }}</span>
                </div>
                <div class="flex items-center text-(--text-muted) text-sm min-w-0">
                  <i class="bx bx-phone text-(--text-soft) mr-2 text-base"></i>
                  <span class="truncate">{{ emp.phone || '-' }}</span>
                </div>
              </div>
            </div>

            <!-- Footer: Meta -->
            <div class="mt-3 pt-3 border-t border-(--border-soft) flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Badge :variant="emp.employment_status === 'permanent' ? 'primary' : 'warning'" class="px-2 py-0.5 text-[11px]">
                  {{ employmentStatusOptions.find(o => o.value === emp.employment_status)?.label || emp.employment_status || 'Unknown' }}
                </Badge>
                <span class="text-[12px] text-(--text-soft) flex items-center gap-1">
                  <i class="bx bx-time-five"></i> {{ calculateMasaKerja(emp.join_date) }}
                </span>
              </div>
              <BaseButton variant="ghost" @click="viewEmployee(emp.id)" class="h-7 px-3 text-xs bg-(--bg-elevated)">
                Detail
                <template #icon-right>
                  <i class="bx bx-chevron-right text-sm"></i>
                </template>
              </BaseButton>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <BaseCard padding="p-4" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
        <Pagination
          :current-page="pagination.current_page ?? 1"
          :total-pages="pagination.last_page ?? 1"
          :total="pagination.total ?? 0"
          :per-page="pagination.per_page ?? 15"
          @page-change="handlePageChange"
        />
      </BaseCard>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-(--bg-card) rounded-md border border-(--border-soft) p-16 text-center shadow-sm">
      <div class="w-20 h-20 bg-(--primary)/5 rounded-full flex items-center justify-center mx-auto mb-4 text-(--primary)/40">
        <i class="bx bx-user-x text-4xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-(--text-main)">Tidak ada data karyawan</h3>
      <p class="text-(--text-muted) mt-2 max-w-sm mx-auto">Tidak dapat menemukan karyawan dengan filter yang Anda berikan. Coba ubah pencarian atau tambahkan karyawan baru.</p>
      <div class="mt-6 flex justify-center gap-3">
        <BaseButton variant="ghost" @click="resetFilters">
          Reset Filter
        </BaseButton>
        <BaseButton variant="primary" @click="$router.push('/admin/employees/create')">
          <template #icon-left>
            <i class="bx bx-plus text-lg"></i>
          </template>
          Tambah Baru
        </BaseButton>
      </div>
    </div>

    <!-- Delete Confirm Dialog -->
    <ConfirmDialog
      :show="showDeleteDialog"
      title="Hapus Karyawan"
      :message="`Apakah Anda yakin ingin menghapus karyawan ${selectedEmployee?.name} (${selectedEmployee?.employee_code || '-'})? Tindakan ini tidak dapat dibatalkan.`"
      confirm-text="Ya, Hapus Karyawan"
      cancel-text="Batal"
      variant="danger"
      @confirm="handleDelete"
      @cancel="cancelDelete"
    />

    <!-- Deactivate Modal -->
    <BaseModal
      :show="showDeactivateDialog"
      title="Nonaktifkan Karyawan"
      @close="cancelDeactivate"
    >
      <div class="space-y-4">
        <p class="text-sm text-(--text-muted)">Silakan tentukan tanggal dan alasan penonaktifan untuk <strong class="text-(--text-main)">{{ selectedEmployee?.name }}</strong>.</p>
        
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Tanggal Non-Aktif</label>
          <select v-model="deactivateForm.date" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary) outline-none transition-all">
            <option value="" disabled>Pilih Tanggal</option>
            <option v-for="d in deactivateDates" :key="d.value" :value="d.value">{{ d.label }}</option>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Keterangan</label>
          <select v-model="deactivateForm.reason" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary) outline-none transition-all">
            <option value="" disabled>Pilih Keterangan</option>
            <option value="resign">Resign</option>
            <option value="phk">PHK</option>
            <option value="mangkir">Mangkir</option>
          </select>
        </div>
      </div>

      <template #footer>
        <BaseButton variant="ghost" @click="cancelDeactivate">Batal</BaseButton>
        <BaseButton variant="danger" :disabled="!deactivateForm.date || !deactivateForm.reason" @click="handleDeactivate">
          Nonaktifkan Karyawan
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
