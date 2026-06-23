<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import { usePermissionStore } from '../../../../Stores/permission'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import Pagination from '../../../../Components/Table/Pagination.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'

const router = useRouter()
const notification = useNotificationStore()
const permission = usePermissionStore()
const { get, destroy: apiDelete } = useApi()

const loading = ref(false)
const salaries = ref([])
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const searchQuery = ref('')
const filterChangeType = ref('')
const filterActive = ref('')
const currentPage = ref(1)

const showDeleteDialog = ref(false)
const selectedSalary = ref(null)

async function fetchSalaries() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', currentPage.value)
    if (searchQuery.value) params.set('search', searchQuery.value)
    if (filterChangeType.value) params.set('change_type', filterChangeType.value)
    if (filterActive.value !== '') params.set('is_active', filterActive.value)

    const res = await get(`/api/v1/employees/salaries?${params}`)
    salaries.value = res.data || []
    pagination.value = res.meta || {}
  } catch (e) {
    notification.addNotification('Gagal memuat data gaji', 'error')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  searchQuery.value = ''
  filterChangeType.value = ''
  filterActive.value = ''
  currentPage.value = 1
  fetchSalaries()
}

function handlePageChange(page) {
  currentPage.value = page
  fetchSalaries()
}

function viewSalary(id) {
  router.push(`/admin/employees/salaries/${id}`)
}

function editSalary(id) {
  router.push(`/admin/employees/salaries/${id}/edit`)
}

function confirmDelete(salary) {
  selectedSalary.value = salary
  showDeleteDialog.value = true
}

async function handleDelete() {
  if (!selectedSalary.value) return
  try {
    await apiDelete(`/api/v1/employees/salaries/${selectedSalary.value.id}`)
    notification.addNotification('Data gaji berhasil dihapus.', 'success')
    fetchSalaries()
  } catch (e) {
    notification.addNotification('Gagal menghapus data gaji.', 'error')
  } finally {
    showDeleteDialog.value = false
    selectedSalary.value = null
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value || 0)
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const getChangeTypeBadge = (type) => {
  const classes = {
    initial: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    increase: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    decrease: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    promotion: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    demotion: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    adjustment: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400',
  }
  return classes[type] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
}

let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchSalaries()
  }, 400)
})

watch([filterChangeType, filterActive], () => {
  currentPage.value = 1
  fetchSalaries()
})

onMounted(() => {
  fetchSalaries()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-md bg-(--primary)/10 flex items-center justify-center text-(--primary) shadow-sm">
          <i class="bx bx-money text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-(--text-main)">Gaji Karyawan</h1>
          <p class="text-sm text-(--text-muted) mt-1">Kelola riwayat dan perubahan komponen gaji karyawan</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <BaseButton v-if="permission.can('import employees')" variant="secondary" @click="$router.push('/admin/employees/salaries/import')">
          <template #icon-left><i class="bx bx-import text-lg"></i></template>
          Import
        </BaseButton>
        <BaseButton v-if="permission.can('create employees')" variant="primary" @click="$router.push('/admin/employees/salaries/create')">
          <template #icon-left><i class="bx bx-plus text-lg"></i></template>
          Tambah Gaji
        </BaseButton>
      </div>
    </div>

    <!-- Filters -->
    <BaseCard padding="p-3" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
      <div class="flex flex-wrap gap-3">
        <div class="flex-1 min-w-[200px] relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
            <i class="bx bx-search text-lg"></i>
          </div>
          <input type="text" v-model="searchQuery" placeholder="Cari nama, NIK..." class="w-full pl-10 pr-4 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) placeholder:text-(--text-soft) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm">
        </div>
        <div class="w-48 relative">
          <select v-model="filterChangeType" class="w-full pl-3 pr-8 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm appearance-none">
            <option value="">Semua Tipe Perubahan</option>
            <option value="initial">Gaji Awal</option>
            <option value="increase">Kenaikan Gaji</option>
            <option value="decrease">Penurunan Gaji</option>
            <option value="promotion">Kenaikan Jabatan</option>
            <option value="demotion">Penurunan Jabatan</option>
            <option value="adjustment">Penyesuaian</option>
          </select>
          <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-(--text-muted)"><i class="bx bx-chevron-down text-lg"></i></div>
        </div>
        <div class="w-36 relative">
          <select v-model="filterActive" class="w-full pl-3 pr-8 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm appearance-none">
            <option value="">Status (Semua)</option>
            <option value="1">Aktif</option>
            <option value="0">Tidak Aktif</option>
          </select>
          <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-(--text-muted)"><i class="bx bx-chevron-down text-lg"></i></div>
        </div>
        <BaseButton variant="ghost" @click="resetFilters" class="px-3" title="Reset Filter">
          <i class="bx bx-filter-alt text-lg text-(--text-muted)"></i>
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Table -->
    <BaseCard padding="p-0" class="border-(--border-soft) shadow-sm bg-(--bg-card) overflow-hidden">
      <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat riwayat gaji...</p>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-(--bg-elevated) border-b border-(--border-soft)">
              <th class="px-4 py-3 text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Karyawan</th>
              <th class="px-4 py-3 text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Gaji Pokok</th>
              <th class="px-4 py-3 text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tunjangan</th>
              <th class="px-4 py-3 text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tipe</th>
              <th class="px-4 py-3 text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Efektif</th>
              <th class="px-4 py-3 text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-if="salaries.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-(--text-muted)">Tidak ada data gaji ditemukan</td>
            </tr>
            <tr v-for="salary in salaries" :key="salary.id" class="hover:bg-(--bg-elevated)/50 transition-colors">
              <td class="px-4 py-3">
                <div class="font-medium text-(--text-main)">{{ salary.employee?.name || '-' }}</div>
                <div class="text-xs text-(--text-muted)">{{ salary.employee?.employee_code || '-' }}</div>
              </td>
              <td class="px-4 py-3 text-sm font-medium text-(--text-main)">{{ formatCurrency(salary.base_salary) }}</td>
              <td class="px-4 py-3 text-sm text-(--text-muted)">{{ formatCurrency((salary.total_salary || 0) - (salary.base_salary || 0)) }}</td>
              <td class="px-4 py-3">
                <span :class="['px-2 py-0.5 text-[11px] font-medium rounded-md uppercase tracking-wider', getChangeTypeBadge(salary.change_type)]">{{ salary.change_type_label }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatDate(salary.effective_date) }}</td>
              <td class="px-4 py-3">
                <span v-if="salary.is_active" class="px-2 py-0.5 text-[11px] font-medium rounded-md bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">AKTIF</span>
                <span v-else class="px-2 py-0.5 text-[11px] font-medium rounded-md bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400">TIDAK AKTIF</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="viewSalary(salary.id)" class="w-8 h-8 rounded text-(--text-muted) hover:text-(--primary) hover:bg-(--bg-elevated) transition-all"><i class="bx bx-show"></i></button>
                  <button v-if="permission.can('edit employees')" @click="editSalary(salary.id)" class="w-8 h-8 rounded text-(--text-muted) hover:text-(--primary) hover:bg-(--bg-elevated) transition-all"><i class="bx bx-edit"></i></button>
                  <button v-if="permission.can('delete employees')" @click="confirmDelete(salary)" class="w-8 h-8 rounded text-(--text-muted) hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all"><i class="bx bx-trash"></i></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="salaries.length > 0" class="p-4 border-t border-(--border-soft)">
        <Pagination
          :current-page="pagination.current_page ?? 1"
          :total-pages="pagination.last_page ?? 1"
          :total="pagination.total ?? 0"
          :per-page="pagination.per_page ?? 15"
          @page-change="handlePageChange"
        />
      </div>
    </BaseCard>

    <ConfirmDialog
      :show="showDeleteDialog"
      title="Hapus Data Gaji"
      message="Apakah Anda yakin ingin menghapus data gaji ini? Tindakan ini tidak dapat dibatalkan."
      confirm-text="Hapus"
      cancel-text="Batal"
      variant="danger"
      @confirm="handleDelete"
      @cancel="showDeleteDialog = false"
    />
  </div>
</template>
