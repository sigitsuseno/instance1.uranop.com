<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import Pagination from '../../../../Components/Table/Pagination.vue'
import Badge from '../../../../Components/Badge.vue'
import ContractForm from './Form.vue'

const router = useRouter()
const notification = useNotificationStore()
const { get, post, patch, destroy: apiDelete } = useApi()

// State
const loading = ref(false)
const employees = ref([])
const stats = ref({ active: 0, expiring_soon: 0, expired: 0 })
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const selectedEmployee = ref(null)

// Filters
const searchQuery = ref('')
const contractTypeFilter = ref('')
const contractStatusFilter = ref('')
const periodStartFilter = ref('')
const periodEndFilter = ref('')
const currentPage = ref(1)

// Sorting
const sortBy = ref('contract_end_date')
const sortDir = ref('asc')

// Options
const contractTypeOptions = [
  { value: 'pkwt', label: 'PKWT' },
  { value: 'pkwtt', label: 'PKWTT' },
  { value: 'outsourcing', label: 'Outsourcing' },
  { value: 'freelance', label: 'Freelance' },
]

const contractStatusOptions = [
  { value: 'active', label: 'Aktif' },
  { value: 'expired', label: 'Expired' },
  { value: 'terminated', label: 'Terminated' },
]

// Modal Form
const showContractModal = ref(false)
const selectedContract = ref(null)
const modalEmployee = ref(null)

// History Modal
const showHistoryModal = ref(false)
const contractHistory = ref([])
const loadingHistory = ref(false)

// ========== API CALLS ==========
async function fetchEmployees() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', currentPage.value)
    // Only get active contract employees for the contract list
    if (periodStartFilter.value && periodEndFilter.value) {
      params.set('period_start', periodStartFilter.value)
      params.set('period_end', periodEndFilter.value)
    } else {
      params.set('is_active', '1')
    }
    params.set('employment_status', 'contract')
    
    if (searchQuery.value) params.set('search', searchQuery.value)
    if (contractTypeFilter.value) params.set('contract_type', contractTypeFilter.value)
    
    if (contractStatusFilter.value) {
      params.set('contract_status', contractStatusFilter.value)
    } else {
      // By default, exclude contracts that expired > 30 days ago
      params.set('exclude_expired_contracts', '1')
    }
    
    if (sortBy.value) params.set('sort_by', sortBy.value)
    if (sortDir.value) params.set('sort_dir', sortDir.value)

    const res = await get(`/api/v1/supervisor/employee-data/karyawan?${params}`)
    employees.value = res.data || []
    pagination.value = res.meta || {}
  } catch (e) {
    notification.addNotification('Gagal memuat data karyawan.', 'error')
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const res = await get('/api/v1/supervisor/employee-data/karyawan/contracts/stats')
    stats.value = res.data || { active: 0, expiring_soon: 0, expired: 0 }
  } catch (e) {
    console.error(e)
  }
}

async function fetchContractHistory(employee) {
  selectedEmployee.value = employee
  showHistoryModal.value = true
  loadingHistory.value = true
  try {
    const res = await get(`/api/v1/supervisor/employee-data/karyawan/${employee.id}/contracts`)
    contractHistory.value = res.data || []
  } catch (e) {
    notification.addNotification('Gagal memuat histori kontrak.', 'error')
  } finally {
    loadingHistory.value = false
  }
}

// ========== ACTIONS ==========
function handlePageChange(page) {
  currentPage.value = page
  fetchEmployees()
}

function handleSort(column) {
  if (sortBy.value === column) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column
    sortDir.value = 'asc'
  }
  fetchEmployees()
}

function resetFilters() {
  searchQuery.value = ''
  contractTypeFilter.value = ''
  contractStatusFilter.value = ''
  periodStartFilter.value = ''
  periodEndFilter.value = ''
  currentPage.value = 1
  fetchEmployees()
}

function openCreateModal(employee = null) {
  selectedContract.value = null
  modalEmployee.value = employee || selectedEmployee.value
  showContractModal.value = true
}

function openEditModal(contract, employee = null) {
  selectedContract.value = contract
  modalEmployee.value = employee || selectedEmployee.value
  showContractModal.value = true
}

function handleFormSuccess() {
  showContractModal.value = false
  fetchEmployees()
  fetchStats()
  if (showHistoryModal.value && selectedEmployee.value) {
    fetchContractHistory(selectedEmployee.value)
  }
}

async function markCompensationPaid(contractId, employeeId) {
  try {
    await patch(`/api/v1/supervisor/employee-data/karyawan/${employeeId}/contracts/${contractId}/mark-paid`)
    notification.addNotification('Kompensasi berhasil ditandai sudah dibayar.', 'success')
    fetchEmployees()
    if (showHistoryModal.value) {
      fetchContractHistory(selectedEmployee.value)
    }
  } catch (e) {
    notification.addNotification('Gagal menandai kompensasi.', 'error')
  }
}

// ========== HELPERS ==========
const getInitials = (name) => {
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).slice(0, 1).join('').toUpperCase()
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

const getTypeLabel = (type) => {
  const t = contractTypeOptions.find(o => o.value === type)
  return t ? t.label : type
}

const getDaysRemaining = (endDate) => {
  if (!endDate) return null
  const end = new Date(endDate)
  const now = new Date()
  end.setHours(0, 0, 0, 0)
  now.setHours(0, 0, 0, 0)
  return Math.ceil((end - now) / (1000 * 60 * 60 * 24))
}

const getStatusBadgeVariant = (contract) => {
  if (!contract || !contract.end_date) return 'secondary'
  const days = getDaysRemaining(contract.end_date)
  if (days > 14) return 'success' // active
  if (days >= 0) return 'warning' // expiring soon
  return 'danger' // expired
}

const getStatusLabel = (contract) => {
  if (!contract || !contract.end_date) return 'Tidak Diketahui'
  const days = getDaysRemaining(contract.end_date)
  if (days > 14) return 'Aktif'
  if (days >= 0) return `Sisa ${days} Hari`
  return `Expired (${Math.abs(days)} Hari)`
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

watch([contractTypeFilter, contractStatusFilter, periodStartFilter, periodEndFilter], () => {
  currentPage.value = 1
  fetchEmployees()
})

// ========== INIT ==========
onMounted(() => {
  fetchEmployees()
  fetchStats()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-md bg-(--primary)/10 flex items-center justify-center text-(--primary) shadow-sm">
          <i class="bx bx-file text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-(--text-main)">Kontrak Kerja</h1>
          <p class="text-sm text-(--text-muted) mt-1">Kelola masa berlaku dan kompensasi kontrak karyawan</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <BaseButton variant="secondary" @click="$router.push('/supervisor/employee-data/kontrak-kerja/import')">
          <template #icon-left>
            <i class="bx bx-upload text-lg"></i>
          </template>
          Import Kontrak
        </BaseButton>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 w-24 h-24 bg-emerald-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between relative z-10">
          <div>
            <p class="text-xs font-bold text-(--text-muted) uppercase tracking-wide">Kontrak Aktif</p>
            <p class="text-3xl font-black text-emerald-600 mt-1">{{ stats.active }}</p>
          </div>
          <div class="w-10 h-10 bg-emerald-500/10 text-emerald-600 rounded-md flex items-center justify-center">
            <i class="bx bx-check-shield text-2xl"></i>
          </div>
        </div>
      </BaseCard>

      <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 w-24 h-24 bg-amber-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between relative z-10">
          <div>
            <p class="text-xs font-bold text-amber-600 uppercase tracking-wide">Segera Berakhir</p>
            <p class="text-3xl font-black text-amber-600 mt-1">{{ stats.expiring_soon }}</p>
          </div>
          <div class="w-10 h-10 bg-amber-500/10 text-amber-600 rounded-md flex items-center justify-center">
            <i class="bx bx-time-five text-2xl"></i>
          </div>
        </div>
        <p class="text-[10px] text-amber-600/70 mt-2 italic font-medium relative z-10">Habis dalam 14 hari kedepan</p>
      </BaseCard>

      <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 w-24 h-24 bg-red-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between relative z-10">
          <div>
            <p class="text-xs font-bold text-red-600 uppercase tracking-wide">Sudah Berakhir</p>
            <p class="text-3xl font-black text-red-600 mt-1">{{ stats.expired }}</p>
          </div>
          <div class="w-10 h-10 bg-red-500/10 text-red-600 rounded-md flex items-center justify-center">
            <i class="bx bx-error-circle text-2xl"></i>
          </div>
        </div>
      </BaseCard>
    </div>

    <!-- Search & Filters -->
    <BaseCard padding="p-2" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
      <div class="flex flex-wrap gap-2">
        <div class="flex-1 min-w-[200px] relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
            <i class="bx bx-search text-lg"></i>
          </div>
          <input 
            type="text" 
            v-model="searchQuery"
            placeholder="Cari nama atau NIK karyawan..."
            class="w-full pl-10 pr-4 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) placeholder:text-(--text-soft) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm"
          >
        </div>

        <div class="w-48 relative">
          <select 
            v-model="contractTypeFilter"
            class="w-full pl-3 pr-8 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm appearance-none"
          >
            <option value="">Semua Tipe Kontrak</option>
            <option v-for="opt in contractTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-(--text-muted)">
            <i class="bx bx-chevron-down text-lg"></i>
          </div>
        </div>

        <div class="w-40 relative">
          <select 
            v-model="contractStatusFilter"
            class="w-full pl-3 pr-8 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm appearance-none"
          >
            <option value="">Semua Status</option>
            <option v-for="opt in contractStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
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

    <!-- Content Table -->
    <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
      <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
      <p class="text-(--text-muted)">Memuat data kontrak...</p>
    </div>

    <BaseCard v-else-if="employees.length > 0" padding="p-0" class="border-(--border-soft) shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-(--bg-elevated)/50 border-b border-(--border-soft)">
              <th 
                class="px-6 py-3 text-xs font-bold text-(--text-muted) uppercase tracking-wider w-[35%] cursor-pointer select-none hover:text-(--text-main) transition-colors"
                @click="handleSort('name')"
              >
                <div class="flex items-center gap-2">
                  Karyawan
                  <i v-if="sortBy === 'name'" :class="['bx', sortDir === 'asc' ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt']" class="text-sm"></i>
                  <i v-else class="bx bx-sort text-sm opacity-30"></i>
                </div>
              </th>
              <th class="px-6 py-3 text-xs font-bold text-(--text-muted) uppercase tracking-wider w-[20%]">Kontrak Terakhir</th>
              <th 
                class="px-6 py-3 text-xs font-bold text-(--text-muted) uppercase tracking-wider w-[20%] cursor-pointer select-none hover:text-(--text-main) transition-colors"
                @click="handleSort('contract_end_date')"
              >
                <div class="flex items-center gap-2">
                  Periode Kontrak
                  <i v-if="sortBy === 'contract_end_date'" :class="['bx', sortDir === 'asc' ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt']" class="text-sm"></i>
                  <i v-else class="bx bx-sort text-sm opacity-30"></i>
                </div>
              </th>
              <th 
                class="px-6 py-3 text-xs font-bold text-(--text-muted) uppercase tracking-wider w-[15%] cursor-pointer select-none hover:text-(--text-main) transition-colors"
                @click="handleSort('contract_end_date')"
              >
                <div class="flex items-center gap-2">
                  Status
                  <i v-if="sortBy === 'contract_end_date'" :class="['bx', sortDir === 'asc' ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt']" class="text-sm"></i>
                  <i v-else class="bx bx-sort text-sm opacity-30"></i>
                </div>
              </th>
              <th class="px-6 py-3 text-xs font-bold text-(--text-muted) uppercase tracking-wider text-right w-[10%]">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-for="emp in employees" :key="emp.id" class="hover:bg-(--bg-elevated)/30 transition-colors">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center border border-(--primary)/20 shrink-0">
                    <img v-if="emp.photo_url" :src="emp.photo_url" class="w-full h-full rounded-md object-cover" />
                    <span v-else class="text-(--primary) font-bold text-sm">{{ getInitials(emp.name) }}</span>
                  </div>
                  <div>
                    <p class="text-sm font-bold text-(--text-main)">{{ emp.name }}</p>
                    <p class="text-xs text-(--text-muted) mt-0.5">{{ emp.employee_code }} • {{ emp.department?.name || '-' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <template v-if="emp.latest_contract">
                  <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) uppercase mb-1">
                    {{ emp.latest_contract.contract_number }}
                  </span>
                  <div class="text-xs font-medium text-(--text-soft)">
                    {{ getTypeLabel(emp.latest_contract.contract_type) }}
                  </div>
                </template>
                <span v-else class="text-xs text-red-500 font-medium italic">Belum ada kontrak</span>
              </td>
              <td class="px-6 py-4">
                <div v-if="emp.latest_contract" class="text-xs text-(--text-main) space-y-1">
                  <div class="flex items-center gap-1">
                    <span class="text-(--text-muted) w-10">Mulai:</span>
                    <span class="font-medium">{{ formatDate(emp.latest_contract.start_date) }}</span>
                  </div>
                  <div class="flex items-center gap-1" v-if="emp.latest_contract.end_date">
                    <span class="text-(--text-muted) w-10">Akhir:</span>
                    <span class="font-medium">{{ formatDate(emp.latest_contract.end_date) }}</span>
                  </div>
                </div>
                <span v-else>-</span>
              </td>
              <td class="px-6 py-4">
                <Badge v-if="emp.latest_contract" :variant="getStatusBadgeVariant(emp.latest_contract)">
                  <template #icon-left>
                    <i v-if="getStatusBadgeVariant(emp.latest_contract) === 'success'" class="bx bx-check-circle"></i>
                    <i v-else-if="getStatusBadgeVariant(emp.latest_contract) === 'warning'" class="bx bx-time"></i>
                    <i v-else class="bx bx-error-circle"></i>
                  </template>
                  {{ getStatusLabel(emp.latest_contract) }}
                </Badge>
                <span v-else>-</span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="fetchContractHistory(emp)" class="w-8 h-8 flex items-center justify-center rounded-md text-(--text-soft) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors" title="Riwayat Kontrak">
                    <i class="bx bx-history text-lg"></i>
                  </button>
                  <button @click="openCreateModal(emp)" class="w-8 h-8 flex items-center justify-center rounded-md text-(--text-soft) hover:text-emerald-600 hover:bg-emerald-500/10 transition-colors" title="Tambah Kontrak Baru">
                    <i class="bx bx-plus-circle text-lg"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="p-4 border-t border-(--border-soft)">
        <Pagination
          :current-page="pagination.current_page ?? 1"
          :total-pages="pagination.last_page ?? 1"
          :total="pagination.total ?? 0"
          :per-page="pagination.per_page ?? 15"
          @page-change="handlePageChange"
        />
      </div>
    </BaseCard>

    <!-- Empty State -->
    <div v-else class="bg-(--bg-card) rounded-md border border-(--border-soft) p-16 text-center shadow-sm">
      <div class="w-20 h-20 bg-(--primary)/5 rounded-full flex items-center justify-center mx-auto mb-4 text-(--primary)/40">
        <i class="bx bx-file-blank text-4xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-(--text-main)">Tidak ada data kontrak</h3>
      <p class="text-(--text-muted) mt-2 max-w-sm mx-auto">Tidak dapat menemukan data dengan filter yang Anda berikan. Coba ubah pencarian atau tambahkan kontrak baru.</p>
    </div>

    <!-- History Modal -->
    <BaseModal :show="showHistoryModal" @close="showHistoryModal = false" :title="`Riwayat Kontrak - ${selectedEmployee?.name}`" size="lg">
      <div v-if="loadingHistory" class="p-8 flex justify-center">
        <div class="w-8 h-8 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin"></div>
      </div>
      <div v-else-if="contractHistory.length === 0" class="p-8 text-center text-(--text-muted)">
        Belum ada riwayat kontrak untuk karyawan ini.
      </div>
      <div v-else class="space-y-4 max-h-[60vh] overflow-y-auto">
        <div v-for="contract in contractHistory" :key="contract.id" class="p-4 rounded-md border border-(--border-soft) bg-(--bg-elevated) relative group">
          <div class="absolute top-4 right-4 flex gap-2">
            <!-- Edit Button -->
            <button @click="openEditModal(contract, selectedEmployee)" class="text-(--text-soft) hover:text-(--primary) transition-colors">
              <i class="bx bx-edit text-lg"></i>
            </button>
            <span v-if="contract.is_latest" class="text-[9px] font-bold bg-blue-500/10 text-blue-600 px-1.5 py-0.5 rounded border border-blue-500/20 uppercase">LATEST</span>
          </div>

          <div class="flex flex-col md:flex-row md:items-center gap-4 md:gap-8">
            <div>
              <p class="text-xs text-(--text-muted) uppercase font-bold mb-1">No. Kontrak</p>
              <p class="text-sm font-semibold text-(--text-main)">{{ contract.contract_number }}</p>
            </div>
            <div>
              <p class="text-xs text-(--text-muted) uppercase font-bold mb-1">Tipe</p>
              <p class="text-sm text-(--text-main)">{{ getTypeLabel(contract.contract_type) }}</p>
            </div>
            <div>
              <p class="text-xs text-(--text-muted) uppercase font-bold mb-1">Periode</p>
              <p class="text-sm text-(--text-main)">
                {{ formatDate(contract.start_date) }} - {{ formatDate(contract.end_date) || 'Seterusnya' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-(--text-muted) uppercase font-bold mb-1">Kompensasi</p>
              <div v-if="contract.contract_type === 'pkwt'" class="flex items-center gap-2">
                <Badge :variant="contract.compensation_paid_at ? 'success' : 'warning'" class="text-[10px]">
                  {{ contract.compensation_paid_at ? 'Sudah Dibayar' : 'Belum Dibayar' }}
                </Badge>
                <button v-if="!contract.compensation_paid_at && getStatusBadgeVariant(contract) === 'danger'" 
                        @click="markCompensationPaid(contract.id, selectedEmployee.id)"
                        class="text-xs text-blue-500 hover:underline">
                  Tandai Selesai
                </button>
              </div>
              <span v-else class="text-xs text-(--text-soft)">-</span>
            </div>
          </div>
        </div>
      </div>
      
      <template #footer>
        <BaseButton variant="secondary" @click="showHistoryModal = false">Tutup</BaseButton>
        <BaseButton variant="primary" @click="openCreateModal()">Tambah Kontrak Baru</BaseButton>
      </template>
    </BaseModal>

    <!-- Modal Form (Create/Edit) -->
    <BaseModal :show="showContractModal" @close="showContractModal = false" :title="selectedContract ? 'Edit Kontrak' : 'Tambah Kontrak'" size="lg">
      <ContractForm
        :contract="selectedContract"
        :employee="modalEmployee"
        @success="handleFormSuccess"
        @cancel="showContractModal = false"
      />
    </BaseModal>
  </div>
</template>
