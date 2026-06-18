<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Pembatalan Cuti</h1>
        <p class="text-sm text-(--text-muted) mt-1">Batalkan pengajuan cuti yang sudah disetujui atau masih pending. Kuota akan dikembalikan otomatis.</p>
      </div>
      <div class="w-72 flex items-center gap-2">
        <label class="text-sm font-medium text-(--text-main) shrink-0">Periode:</label>
        <select
          v-model="selectedPeriodId"
          class="w-full px-3 py-2 pr-10 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300 h-10 appearance-none text-sm"
          @change="fetchRequests"
        >
          <option v-if="loadingPeriods" value="" disabled>Memuat periode...</option>
          <option v-else-if="periods.length === 0" value="" disabled>Tidak ada periode</option>
          <option v-for="period in periods" :key="period.id" :value="period.id">
            {{ period.name }} ({{ period.status }})
          </option>
        </select>
      </div>
    </div>

    <!-- Filter & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
      <div class="flex flex-wrap gap-2 flex-1">
        <button
          v-for="opt in statusFilterOptions"
          :key="opt.value"
          @click="setStatusFilter(opt.value)"
          class="px-3 py-1.5 text-xs font-medium rounded-full transition-all border h-8 focus:outline-none"
          :class="[
            filters.status === opt.value
              ? 'bg-(--primary) text-white border-transparent'
              : 'bg-(--bg-card) text-(--text-muted) border-(--border-soft) hover:text-(--text-main)'
          ]"
        >
          {{ opt.label }}
        </button>
        <div class="ml-auto w-full md:w-64 relative">
          <input
            type="text"
            v-model="filters.search"
            @input="handleSearch"
            placeholder="Cari NIP atau Nama..."
            class="w-full pl-9 pr-3 py-1.5 bg-(--bg-card) border border-(--border-soft) rounded-md text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary) focus:border-transparent transition-all h-8"
          />
          <i class="bx bx-search absolute left-2.5 top-1/2 -translate-y-1/2 text-(--text-muted)"></i>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="sm" @click="exportExcel" title="Export Excel">
          <template #icon-left><i class="bx bx-spreadsheet text-base"></i></template>
        </BaseButton>
        <BaseButton variant="secondary" size="sm" @click="exportPdf" title="Export PDF">
          <template #icon-left><i class="bx bxs-file-pdf text-base"></i></template>
        </BaseButton>
        <BaseButton
          variant="danger"
          size="sm"
          @click="toggleCancelMode"
          :class="{ 'ring-2 ring-(--danger)': cancelMode }"
        >
          <template #icon-left><i class="bx bx-x-circle text-base"></i></template>
          Batalkan
        </BaseButton>
      </div>
    </div>

    <!-- Bulk cancel bar -->
    <div v-if="selectedIds.length > 0 && cancelMode" class="flex items-center gap-3 mb-4 p-3 rounded-md bg-(--danger)/5 border border-(--danger)/20">
      <span class="text-sm font-medium text-(--text-main)">{{ selectedIds.length }} item terpilih</span>
      <BaseButton variant="danger" size="sm" @click="executeBulkCancel" :loading="bulkProcessing">
        Batalkan {{ selectedIds.length }} Pengajuan
      </BaseButton>
      <BaseButton variant="ghost" size="sm" @click="cancelBulkMode">Batal</BaseButton>
    </div>

    <!-- Data Table -->
    <BaseCard>
      <DataTable
        :headers="headers"
        :items="requests"
        :selectable="cancelMode"
        :selected="selectedIds"
        :loading="loadingRequests"
        @update:selected="selectedIds = $event"
        emptyText="Tidak ada pengajuan yang bisa dibatalkan."
      >
        <template #item.employee_name="{ item }">
          <span class="font-medium text-(--text-main)">{{ item.employee?.name || '-' }}</span>
        </template>
        <template #item.nip="{ item }">
          <span class="font-mono text-xs text-(--text-muted)">{{ item.employee?.nip || '-' }}</span>
        </template>
        <template #item.department="{ item }">
          <span class="text-xs">{{ item.employee?.department?.name || '-' }}</span>
        </template>
        <template #item.leave_type="{ item }">
          <Badge variant="neutral">{{ item.leave_type?.name || '-' }}</Badge>
        </template>
        <template #item.date_range="{ item }">
          <span>{{ formatDate(item.start_date) }} - {{ formatDate(item.end_date) }}</span>
        </template>
        <template #item.days_requested="{ value }">
          <span class="font-semibold">{{ value }} hari</span>
        </template>
        <template #item.status="{ value }">
          <Badge :variant="statusBadgeVariant(value)">{{ statusLabel(value) }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <BaseButton variant="ghost" size="sm" @click="singleCancel(item)" title="Batalkan">
              <template #icon-left><i class="bx bx-x-circle text-lg text-(--danger)"></i></template>
            </BaseButton>
          </div>
        </template>
      </DataTable>

      <div class="mt-4">
        <Pagination
          :current-page="pagination.currentPage"
          :total-pages="pagination.totalPages"
          :total="pagination.total"
          :per-page="pagination.perPage"
          @page-change="handlePageChange"
        />
      </div>
    </BaseCard>

    <!-- Confirm Single Cancel -->
    <ConfirmDialog
      :show="confirmCancel.show"
      title="Batalkan Pengajuan Cuti"
      :message="`Batalkan pengajuan cuti untuk ${confirmCancel.employeeName} (${confirmCancel.days} hari)? Kuota akan dikembalikan.`"
      variant="danger"
      @confirm="executeSingleCancel"
      @cancel="confirmCancel.show = false"
    />
    
    <!-- Confirm Bulk Cancel -->
    <ConfirmDialog
      :show="confirmBulkCancel.show"
      title="Batalkan Pengajuan Cuti"
      :message="confirmBulkCancel.message"
      variant="danger"
      @confirm="confirmBulkCancelAction"
      @cancel="confirmBulkCancel.show = false"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import Badge from '../../../Components/Badge.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { useApi } from '../../../composables/useApi'
import { useNotification } from '../../../composables/useNotification'

const api = useApi()
const notify = useNotification()

// --- State ---
const selectedPeriodId = ref('')
const periods = ref([])
const requests = ref([])
const loadingPeriods = ref(false)
const loadingRequests = ref(false)
const bulkProcessing = ref(false)

const filters = reactive({ status: '', search: '' })
const pagination = reactive({ currentPage: 1, totalPages: 1, total: 0, perPage: 15 })

const cancelMode = ref(false)
const selectedIds = ref([])

const confirmCancel = reactive({ show: false, id: null, employeeName: '', days: 0 })
const confirmBulkCancel = reactive({ show: false, message: '' })

let searchTimeout = null

// --- Computed ---
const headers = [
  { key: 'employee_name', label: 'Karyawan' },
  { key: 'nip', label: 'NIP' },
  { key: 'department', label: 'Departemen' },
  { key: 'leave_type', label: 'Tipe' },
  { key: 'date_range', label: 'Tanggal Cuti' },
  { key: 'days_requested', label: 'Total Durasi' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false },
]

const statusFilterOptions = [
  { value: '', label: 'Semua (Pending & Disetujui)' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Disetujui' },
]

// --- API ---
async function fetchPeriods() {
  loadingPeriods.value = true
  try {
    const res = await api.get('/api/v1/leave/periods')
    periods.value = res.data || []
    const active = periods.value.find((p) => p.status === 'active')
    if (active) selectedPeriodId.value = active.id
    else if (periods.value.length > 0) selectedPeriodId.value = periods.value[0].id
  } catch (err) {
    notify.error('Gagal memuat daftar periode.')
  } finally {
    loadingPeriods.value = false
  }
}

async function fetchRequests() {
  if (!selectedPeriodId.value) return
  loadingRequests.value = true
  try {
    const searchParam = filters.search ? `&search=${encodeURIComponent(filters.search)}` : ''
    // Only fetch cancellable statuses: pending + approved
    const statusParam = filters.status || ''
    let url = `/api/v1/leave/requests?leave_period_id=${selectedPeriodId.value}${searchParam}&page=${pagination.currentPage}`
    // Filter by status if selected, otherwise show both pending and approved by default
    if (statusParam) {
      url += `&status=${statusParam}`
    } else {
      // Fetch all, we'll filter on frontend for pending/approved only
      url += `&status=`
    }
    const res = await api.get(url)
    const dataEnv = res.paginated || res
    const allRequests = dataEnv.data || []

    // Filter to only show cancellable items (pending or approved)
    if (!statusParam) {
      requests.value = allRequests.filter((r) => ['pending', 'approved'].includes(r.status))
    } else {
      requests.value = allRequests
    }

    pagination.currentPage = dataEnv.current_page || 1
    pagination.totalPages = dataEnv.last_page || 1
    pagination.total = dataEnv.total || 0
    pagination.perPage = dataEnv.per_page || 15
  } catch (err) {
    notify.error('Gagal memuat daftar pengajuan.')
  } finally {
    loadingRequests.value = false
  }
}

// --- Filters ---
function setStatusFilter(status) {
  filters.status = status
  pagination.currentPage = 1
  fetchRequests()
}

function handleSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { pagination.currentPage = 1; fetchRequests() }, 500)
}

function handlePageChange(page) {
  pagination.currentPage = page
  fetchRequests()
}

// --- Bulk mode ---
function toggleCancelMode() {
  cancelMode.value = !cancelMode.value
  selectedIds.value = []
}

function cancelBulkMode() {
  cancelMode.value = false
  selectedIds.value = []
}

async function executeBulkCancel() {
  if (!selectedIds.value.length) return
  confirmBulkCancel.message = `Batalkan ${selectedIds.value.length} pengajuan cuti? Kuota akan dikembalikan otomatis.`
  confirmBulkCancel.show = true
}

async function confirmBulkCancelAction() {
  confirmBulkCancel.show = false
  bulkProcessing.value = true
  try {
    const res = await api.post('/api/v1/leave/requests/bulk-cancel', { ids: selectedIds.value })
    notify.success(res.message || 'Pembatalan berhasil.')
    cancelBulkMode()
    fetchRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal membatalkan pengajuan.')
  } finally {
    bulkProcessing.value = false
  }
}

// --- Single cancel ---
function singleCancel(item) {
  confirmCancel.id = item.id
  confirmCancel.employeeName = item.employee?.name || '-'
  confirmCancel.days = item.days_requested
  confirmCancel.show = true
}

async function executeSingleCancel() {
  confirmCancel.show = false
  try {
    await api.post(`/api/v1/leave/requests/${confirmCancel.id}/cancel`, {})
    notify.success('Pengajuan cuti berhasil dibatalkan dan kuota dikembalikan.')
    fetchRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal membatalkan pengajuan.')
  }
}

// --- Export ---
function exportExcel() {
  notify.info('Export Excel akan diimplementasikan.')
}

function exportPdf() {
  notify.info('Export PDF akan diimplementasikan.')
}

// --- Helpers ---
function statusBadgeVariant(status) {
  const map = { pending: 'warning', approved: 'success', rejected: 'danger', cancelled: 'neutral' }
  return map[status] || 'neutral'
}

function statusLabel(status) {
  const map = { pending: 'Pending', approved: 'Disetujui', rejected: 'Ditolak', cancelled: 'Dibatalkan' }
  return map[status] || status
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

// --- Init ---
onMounted(async () => {
  await fetchPeriods()
  fetchRequests()
})
</script>
