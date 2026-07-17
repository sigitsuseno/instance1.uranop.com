<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Pengajuan Cuti</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola pengajuan cuti, approval, dan ekspor data.</p>
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

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center">
            <i class="bx bx-list-ol text-xl text-(--primary)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.total }}</p>
            <p class="text-xs text-(--text-muted)">Total Pengajuan</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--warning)/10 flex items-center justify-center">
            <i class="bx bx-time text-xl text-(--warning)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.pending }}</p>
            <p class="text-xs text-(--text-muted)">Pending</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--success)/10 flex items-center justify-center">
            <i class="bx bx-check-circle text-xl text-(--success)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.approved }}</p>
            <p class="text-xs text-(--text-muted)">Disetujui</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--danger)/10 flex items-center justify-center">
            <i class="bx bx-x-circle text-xl text-(--danger)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.rejected }}</p>
            <p class="text-xs text-(--text-muted)">Ditolak</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--bg-elevated) flex items-center justify-center">
            <i class="bx bx-x text-xl text-(--text-muted)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.cancelled }}</p>
            <p class="text-xs text-(--text-muted)">Dibatalkan</p>
          </div>
        </div>
      </BaseCard>
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
          v-if="isHrOrAdmin"
          variant="success"
          size="sm"
          @click="toggleApproveMode"
          :class="{ 'ring-2 ring-(--success)': approveMode }"
        >
          <template #icon-left><i class="bx bx-check-circle text-base"></i></template>
          Approve
        </BaseButton>

        <BaseButton
          v-if="isHrOrAdmin"
          variant="danger"
          size="sm"
          @click="toggleRejectMode"
          :class="{ 'ring-2 ring-(--danger)': rejectMode }"
        >
          <template #icon-left><i class="bx bx-x-circle text-base"></i></template>
          Tolak
        </BaseButton>

        <BaseButton 
          variant="primary" 
          size="sm" 
          @click="auth.isManajemen ? null : openCreateModal()"
          :disabled="auth.isManajemen"
          :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed' : ''"
        >
          <template #icon-left><i class="bx bx-plus text-base"></i></template>
          Ajukan Cuti
        </BaseButton>
      </div>
    </div>

    <!-- Bulk action bar -->
    <div v-if="selectedIds.length > 0 && (approveMode || rejectMode)" class="flex items-center gap-3 mb-4 p-3 rounded-md bg-(--primary)/5 border border-(--primary)/20">
      <span class="text-sm font-medium text-(--text-main)">{{ selectedIds.length }} item terpilih</span>
      <BaseButton v-if="approveMode" variant="success" size="sm" @click="executeBulkApprove" :loading="bulkProcessing">
        Setujui {{ selectedIds.length }} Pengajuan
      </BaseButton>
      <BaseButton v-if="rejectMode" variant="danger" size="sm" @click="executeBulkReject" :loading="bulkProcessing">
        Tolak {{ selectedIds.length }} Pengajuan
      </BaseButton>
      <BaseButton variant="ghost" size="sm" @click="cancelBulkMode">Batal</BaseButton>
    </div>

    <!-- Data Table -->
    <BaseCard>
      <DataTable
        :headers="headers"
        :items="requests"
        :selectable="approveMode || rejectMode"
        :selected="selectedIds"
        :loading="loadingRequests"
        @update:selected="selectedIds = $event"
        emptyText="Tidak ada data pengajuan cuti untuk periode ini."
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
        <template #item.tanggal_masuk="{ item }">
          <input
            type="date"
            :value="item.note?.tanggal_masuk ? item.note.tanggal_masuk.substring(0, 10) : (item.employee?.join_date ? item.employee.join_date.substring(0, 10) : '')"
            @change="updateTanggalMasuk(item, $event.target.value)"
            class="px-2 py-1 text-xs rounded border border-(--border-soft) bg-(--bg-card) text-(--text-main) focus:outline-none focus:ring-2 focus:ring-(--primary) focus:border-transparent w-32"
          />
        </template>
        <template #item.days_requested="{ value }">
          <span class="font-semibold">{{ value }} hari</span>
        </template>
        <template #item.status="{ value }">
          <Badge :variant="statusBadgeVariant(value)">{{ statusLabel(value) }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <BaseButton variant="ghost" size="sm" @click="viewDetail(item)">
              <template #icon-left><i class="bx bx-show text-lg"></i></template>
            </BaseButton>
            <BaseButton
              v-if="item.status === 'pending' && isHrOrAdmin"
              variant="ghost" size="sm" @click="singleApprove(item)" title="Setujui"
            >
              <template #icon-left><i class="bx bx-check text-lg text-(--success)"></i></template>
            </BaseButton>
            <BaseButton
              v-if="item.status === 'pending' && isHrOrAdmin"
              variant="ghost" size="sm" @click="singleReject(item)" title="Tolak"
            >
              <template #icon-left><i class="bx bx-x text-lg text-(--danger)"></i></template>
            </BaseButton>
            <BaseButton
              variant="ghost" size="sm" @click="printRequest(item)" title="Cetak"
            >
              <template #icon-left><i class="bx bx-printer text-lg text-(--primary)"></i></template>
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

    <!-- ============ CREATE MODAL — REDESIGNED ============ -->
    <BaseModal :show="showCreateModal" size="lg" @close="showCreateModal = false">
      <template #title>
        <div class="text-center w-full">
          <p class="text-xs font-bold tracking-wider text-(--text-main) uppercase">PT KEMILAU UNGARAN SUKSES</p>
          <p class="text-sm font-bold text-(--text-main) mt-0.5">FORMULIR PERMOHONAN CUTI</p>
        </div>
      </template>

      <div class="space-y-4">
        <!-- Row: Periode + NO DOKUMEN -->
        <div class="flex items-center gap-3 text-xs bg-(--bg-elevated) rounded-md px-3 py-2">
          <div class="flex items-center gap-2">
            <span class="text-(--text-muted) shrink-0">Periode:</span>
            <select
              v-model="formPeriodId"
              class="px-2 py-1 rounded border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-xs h-7 focus:outline-none focus:ring-2 focus:ring-(--primary)"
              @change="onPeriodChange"
            >
              <option v-if="periods.length === 0" value="" disabled>Tidak ada periode</option>
              <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <span class="ml-auto text-(--text-muted)">NO DOKUMEN: <em>(otomatis)</em></span>
        </div>

        <!-- Employee Selector (HR) atau display (non-HR) -->
        <div v-if="isHrOrAdmin">
          <SearchableSelect
            v-model="createForm.employee_id"
            label="Karyawan"
            :options="employeeOptions"
            placeholder="Cari nama atau NIP..."
            :required="true"
            :error="errors.employee_id"
            @update:model-value="onEmployeeSelected"
          />
        </div>
        <div v-else class="p-3 bg-(--bg-elevated) rounded-md text-sm text-(--text-main)">
          <span class="text-xs text-(--text-muted)">Mengajukan cuti untuk:</span>
          <p class="font-semibold mt-0.5">{{ auth.userName }}</p>
        </div>

        <!-- Auto-filled employee info -->
        <div v-if="selectedEmployee" class="grid grid-cols-2 gap-3 p-3 bg-(--bg-elevated)/50 rounded-md border border-(--border-soft) text-sm">
          <div>
            <span class="text-xs text-(--text-muted) block">Nama Lengkap</span>
            <span class="font-medium text-(--text-main)">{{ selectedEmployee.name || '-' }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Bagian / Jabatan</span>
            <span class="font-medium text-(--text-main)">{{ selectedEmployee.department_name || '-' }} / {{ selectedEmployee.position_name || '-' }}</span>
          </div>
        </div>

        <!-- Jenis Cuti — grid cards grouped by category -->
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-2">
            Mengajukan Permohonan Cuti <span class="text-(--danger)">*</span>
          </label>
          <div v-if="errors.leave_type_id" class="text-xs text-(--danger) mb-2">{{ errors.leave_type_id }}</div>
          <div class="space-y-3">
            <div v-for="group in leaveTypeGroups" :key="group.label">
              <p class="text-[10px] uppercase tracking-wider text-(--text-muted) mb-1.5 font-semibold">{{ group.label }}</p>
              <div class="grid grid-cols-2 gap-1.5">
                <button
                  v-for="lt in group.types"
                  :key="lt.id"
                  type="button"
                  @click="createForm.leave_type_id = lt.id; errors.leave_type_id = ''"
                  class="flex items-center gap-2 px-2.5 py-1.5 rounded-md border text-xs text-left transition-all"
                  :class="[
                    createForm.leave_type_id === lt.id
                      ? 'border-(--primary) bg-(--primary)/10 text-(--primary) font-semibold'
                      : 'border-(--border-soft) bg-(--bg-card) text-(--text-main) hover:border-(--text-soft)'
                  ]"
                >
                  <span class="w-3.5 h-3.5 rounded border flex-shrink-0 flex items-center justify-center"
                    :class="createForm.leave_type_id === lt.id ? 'bg-(--primary) border-(--primary)' : 'border-(--border-soft)'"
                  >
                    <i v-if="createForm.leave_type_id === lt.id" class="bx bx-check text-white text-[10px]"></i>
                  </span>
                  <span class="truncate leading-tight">{{ lt.name }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Date range + Duration -->
        <div class="grid grid-cols-3 gap-3">
          <TextInput
            v-model="createForm.start_date"
            label="Terhitung Mulai Tgl"
            type="date"
            :required="true"
            :error="errors.start_date"
            @change="calculateDays"
          />
          <TextInput
            v-model="createForm.end_date"
            label="s/d Tanggal"
            type="date"
            :required="true"
            :error="errors.end_date"
            @change="calculateDays"
          />
          <TextInput
            v-model="createForm.days_requested"
            label="Durasi (Hari)"
            type="number"
            min="1"
            :required="true"
            :error="errors.days_requested"
          />
        </div>

        <!-- Sisa Cuti Tahunan -->
        <div v-if="sisaCutiTahunan !== null" class="flex items-center gap-2 text-sm bg-(--bg-elevated) rounded-md px-3 py-2">
          <span class="text-(--text-muted)">Sisa Cuti Tahunan:</span>
          <span class="font-bold text-(--text-main)" :class="{ 'text-(--danger)': sisaCutiTahunan <= 0 }">{{ sisaCutiTahunan }} hari</span>
        </div>

        <!-- Tanggal Masuk untuk Print Form -->
        <TextInput
          v-model="createForm.tanggal_masuk"
          label="Tanggal Masuk (untuk cetak form)"
          type="date"
          helper="Default dari data karyawan, bisa diubah untuk keperluan cetak"
        />

        <!-- Alasan -->
        <TextInput
          v-model="createForm.reason"
          label="Alasan / Keterangan"
          placeholder="Tulis detail alasan pengajuan cuti..."
          :required="true"
          :error="errors.reason"
        />
      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <BaseButton variant="ghost" @click="showCreateModal = false">Batal</BaseButton>
          <div class="flex items-center gap-2">
            <BaseButton variant="primary" @click="submitRequest" :loading="submitting">
              <template #icon-left><i class="bx bx-send text-base"></i></template>
              Ajukan
            </BaseButton>
            <BaseButton
              v-if="isHrOrAdmin"
              variant="success"
              @click="approveAndPrint"
              :loading="approvingPrinting"
            >
              <template #icon-left><i class="bx bx-printer text-base"></i></template>
              Approve & Print
            </BaseButton>
          </div>
        </div>
      </template>
    </BaseModal>

    <!-- Detail Modal -->
    <BaseModal :show="showDetailModal" title="Detail Pengajuan Cuti" size="md" @close="showDetailModal = false">
      <div v-if="detailItem" class="space-y-4 text-sm">
        <div class="grid grid-cols-2 gap-4 border-b border-(--border-soft) pb-4">
          <div>
            <span class="text-xs text-(--text-muted) block">Nama Karyawan:</span>
            <span class="font-medium text-(--text-main)">{{ detailItem.employee?.name || '-' }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">NIP:</span>
            <span class="font-mono text-(--text-main)">{{ detailItem.employee?.nip || '-' }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Departemen:</span>
            <span class="text-(--text-main)">{{ detailItem.employee?.department?.name || '-' }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Jenis Cuti:</span>
            <span class="text-(--text-main)">{{ detailItem.leave_type?.name || '-' }}</span>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 border-b border-(--border-soft) pb-4">
          <div>
            <span class="text-xs text-(--text-muted) block">Tanggal Mulai:</span>
            <span class="font-medium text-(--text-main)">{{ formatDate(detailItem.start_date) }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Tanggal Selesai:</span>
            <span class="font-medium text-(--text-main)">{{ formatDate(detailItem.end_date) }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Total Durasi:</span>
            <span class="font-semibold text-(--text-main)">{{ detailItem.days_requested }} hari</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Status:</span>
            <Badge :variant="statusBadgeVariant(detailItem.status)">{{ statusLabel(detailItem.status) }}</Badge>
          </div>
        </div>
        <div class="border-b border-(--border-soft) pb-4" v-if="detailItem.reason">
          <span class="text-xs text-(--text-muted) block mb-1">Alasan Pengajuan:</span>
          <p class="text-(--text-main) bg-(--bg-elevated) p-3 rounded-md italic">"{{ detailItem.reason }}"</p>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showDetailModal = false">Tutup</BaseButton>
      </template>
    </BaseModal>

    <!-- Reject Reason Modal -->
    <BaseModal :show="showRejectModal" title="Alasan Penolakan" size="sm" @close="showRejectModal = false">
      <div class="space-y-4">
        <p class="text-sm text-(--text-muted)">
          {{ rejectTarget.isBulk ? `Menolak ${rejectTarget.count} pengajuan sekaligus?` : 'Tolak pengajuan ini?' }}
        </p>
        <TextInput
          v-model="rejectReason"
          label="Alasan Penolakan"
          placeholder="Tulis alasan penolakan..."
          :required="true"
        />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showRejectModal = false">Batal</BaseButton>
        <BaseButton variant="danger" @click="confirmReject" :loading="bulkProcessing">Tolak</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { useApi } from '../../../composables/useApi'
import { useNotification } from '../../../composables/useNotification'
import { useAuth } from '../../../composables/useAuth'

const api = useApi()
const notify = useNotification()
const auth = useAuth()

// --- State ---
const selectedPeriodId = ref('')
const periods = ref([])
const leaveTypes = ref([])
const employeeOptions = ref([])
const requests = ref([])
const loadingPeriods = ref(false)
const loadingRequests = ref(false)
const submitting = ref(false)
const approvingPrinting = ref(false)
const bulkProcessing = ref(false)

const filters = reactive({ status: '', search: '' })
const stats = reactive({ total: 0, pending: 0, approved: 0, rejected: 0, cancelled: 0 })
const pagination = reactive({ currentPage: 1, totalPages: 1, total: 0, perPage: 15 })

const showCreateModal = ref(false)
const showDetailModal = ref(false)
const showRejectModal = ref(false)
const detailItem = ref(null)
const rejectReason = ref('')
const formPeriodId = ref('')

const approveMode = ref(false)
const rejectMode = ref(false)
const selectedIds = ref([])

const createForm = reactive({
  employee_id: '',
  leave_type_id: '',
  start_date: '',
  end_date: '',
  days_requested: '',
  reason: '',
  tanggal_masuk: '',
})

const rejectTarget = reactive({ isBulk: false, id: null, count: 0 })
const errors = ref({})
const sisaCutiTahunan = ref(null)

let searchTimeout = null

// --- Computed ---
const isHrOrAdmin = computed(() => {
  const allowed = ['superadmin', 'hrmanager', 'hr_manager', 'hr']
  return allowed.includes(auth.userRole) || auth.isSuperadmin || auth.isHrmanager
})

const todayFormatted = computed(() => {
  return new Date().toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' })
})

const selectedEmployee = computed(() => {
  if (!createForm.employee_id) return null
  const emp = employeeOptions.value.find(e => e.value === createForm.employee_id)
  return emp || null
})

const leaveTypeGroups = computed(() => {
  const groups = [
    { label: 'Cuti', cat: 'leave', types: [] },
    { label: 'Khusus', cat: 'special', types: [] },
    { label: 'Izin', cat: 'permit', types: [] },
    { label: 'Sakit', cat: 'sick', types: [] },
  ]
  for (const lt of leaveTypes.value) {
    const group = groups.find(g => g.cat === lt.category) || groups[1]
    group.types.push(lt)
  }
  return groups.filter(g => g.types.length > 0)
})

const headers = [
  { key: 'employee_name', label: 'Karyawan' },
  { key: 'nip', label: 'NIP' },
  { key: 'department', label: 'Departemen' },
  { key: 'leave_type', label: 'Tipe' },
  { key: 'date_range', label: 'Tanggal Cuti' },
  { key: 'tanggal_masuk', label: 'Tgl Masuk' },
  { key: 'days_requested', label: 'Total Durasi' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false },
]

const statusFilterOptions = [
  { value: '', label: 'Semua Status' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Disetujui' },
  { value: 'rejected', label: 'Ditolak' },
  { value: 'cancelled', label: 'Dibatalkan' },
]

// --- API ---
async function fetchPeriods() {
  loadingPeriods.value = true
  try {
    const res = await api.get('/api/v1/leave/periods')
    periods.value = res.data || []
    const active = periods.value.find(p => p.status === 'active')
    if (active) selectedPeriodId.value = active.id
    else if (periods.value.length > 0) selectedPeriodId.value = periods.value[0].id
  } catch (err) {
    notify.error('Gagal memuat daftar periode.')
  } finally {
    loadingPeriods.value = false
  }
}

async function fetchLeaveTypes() {
  try {
    const res = await api.get('/api/v1/leave/types')
    leaveTypes.value = res.data || []
  } catch (err) {
    notify.error('Gagal memuat tipe cuti.')
  }
}

async function fetchEmployees() {
  if (!isHrOrAdmin.value) return
  try {
    const res = await api.get('/api/v1/employees/options')
    const list = res.data || []
    employeeOptions.value = list.map(e => ({
      value: e.id,
      label: `${e.name} (${e.nip})`,
      name: e.name,
      nip: e.nip,
      join_date: e.join_date,
      department_name: e.department_name || e.department?.name || '',
      position_name: e.position_name || e.position?.name || '',
    }))
  } catch (err) {
    notify.error('Gagal memuat opsi karyawan.')
  }
}

async function fetchRequests() {
  if (!selectedPeriodId.value) return
  loadingRequests.value = true
  try {
    const searchParam = filters.search ? `&search=${encodeURIComponent(filters.search)}` : ''
    const url = `/api/v1/leave/requests?leave_period_id=${selectedPeriodId.value}&status=${filters.status}${searchParam}&page=${pagination.currentPage}`
    const res = await api.get(url)
    const dataEnv = res.paginated || res
    requests.value = dataEnv.data || []
    pagination.currentPage = dataEnv.current_page || 1
    pagination.totalPages = dataEnv.last_page || 1
    pagination.total = dataEnv.total || 0
    pagination.perPage = dataEnv.per_page || 15
    if (res.stats) Object.assign(stats, res.stats)
  } catch (err) {
    notify.error('Gagal memuat riwayat pengajuan cuti.')
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

// --- Employee selected → fetch sisa cuti ---
function onEmployeeSelected(empId) {
  if (!empId) { sisaCutiTahunan.value = null; createForm.tanggal_masuk = ''; return }
  // Auto-fill tanggal_masuk dari data karyawan
  const emp = employeeOptions.value.find(e => e.value === empId)
  if (emp?.join_date) {
    createForm.tanggal_masuk = emp.join_date.substring(0, 10) // YYYY-MM-DD
  }
  fetchSisaCuti()
}

function onPeriodChange() {
  if (createForm.employee_id) fetchSisaCuti()
}

async function fetchSisaCuti() {
  if (!createForm.employee_id || !formPeriodId.value) return
  try {
    // Cari leave_type_id untuk "Cuti Tahunan" (code: CT)
    const ct = leaveTypes.value.find(t => t.code === 'CT')
    if (!ct) { sisaCutiTahunan.value = null; return }

    const res = await api.get(`/api/v1/leave/employee-balance?employee_id=${createForm.employee_id}&leave_type_id=${ct.id}&leave_period_id=${formPeriodId.value}`)
    sisaCutiTahunan.value = res.data?.balance ?? 0
  } catch {
    sisaCutiTahunan.value = null
  }
}

// --- Create ---
function openCreateModal() {
  errors.value = {}
  createForm.employee_id = isHrOrAdmin.value ? '' : (auth.user?.employee_id || '')
  createForm.leave_type_id = ''
  createForm.start_date = ''
  createForm.end_date = ''
  createForm.days_requested = ''
  createForm.reason = ''
  createForm.tanggal_masuk = ''
  sisaCutiTahunan.value = null
  formPeriodId.value = selectedPeriodId.value || (periods.value.length > 0 ? periods.value[0].id : '')
  showCreateModal.value = true

  if (!isHrOrAdmin.value && auth.user?.employee_id) {
    createForm.employee_id = auth.user.employee_id
    fetchSisaCuti()
  }
}

function calculateDays() {
  if (createForm.start_date && createForm.end_date) {
    const start = new Date(createForm.start_date)
    const end = new Date(createForm.end_date)
    if (end >= start) {
      createForm.days_requested = Math.ceil(Math.abs(end - start) / (1000 * 60 * 60 * 24)) + 1
    } else {
      createForm.days_requested = ''
    }
  }
}

async function submitRequest() {
  errors.value = {}
  if (isHrOrAdmin.value && !createForm.employee_id) errors.value.employee_id = 'Karyawan wajib dipilih.'
  if (!createForm.leave_type_id) errors.value.leave_type_id = 'Tipe cuti wajib dipilih.'
  if (!createForm.start_date) errors.value.start_date = 'Tanggal mulai wajib dipilih.'
  if (!createForm.end_date) errors.value.end_date = 'Tanggal selesai wajib dipilih.'
  if (!createForm.days_requested || createForm.days_requested < 1) errors.value.days_requested = 'Jumlah hari minimal 1 hari.'
  if (!createForm.reason) errors.value.reason = 'Alasan pengajuan wajib diisi.'
  if (Object.keys(errors.value).length > 0) return

  submitting.value = true
  try {
    await api.post('/api/v1/leave/requests', {
      employee_id: createForm.employee_id,
      leave_type_id: createForm.leave_type_id,
      start_date: createForm.start_date,
      end_date: createForm.end_date,
      days_requested: parseInt(createForm.days_requested),
      reason: createForm.reason,
      note: createForm.tanggal_masuk ? { tanggal_masuk: createForm.tanggal_masuk } : null,
    })
    notify.success('Pengajuan cuti berhasil disubmit.')
    showCreateModal.value = false
    fetchRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal menyimpan pengajuan.')
  } finally {
    submitting.value = false
  }
}

// --- Approve & Print ---
async function approveAndPrint() {
  errors.value = {}
  if (isHrOrAdmin.value && !createForm.employee_id) errors.value.employee_id = 'Karyawan wajib dipilih.'
  if (!createForm.leave_type_id) errors.value.leave_type_id = 'Tipe cuti wajib dipilih.'
  if (!createForm.start_date) errors.value.start_date = 'Tanggal mulai wajib dipilih.'
  if (!createForm.end_date) errors.value.end_date = 'Tanggal selesai wajib dipilih.'
  if (!createForm.days_requested || createForm.days_requested < 1) errors.value.days_requested = 'Jumlah hari minimal 1 hari.'
  if (!createForm.reason) errors.value.reason = 'Alasan pengajuan wajib diisi.'
  if (Object.keys(errors.value).length > 0) return

  approvingPrinting.value = true
  try {
    // 1. Save + approve + decrement
    const res = await api.post('/api/v1/leave/requests/approve-print', {
      employee_id: createForm.employee_id,
      leave_type_id: createForm.leave_type_id,
      start_date: createForm.start_date,
      end_date: createForm.end_date,
      days_requested: parseInt(createForm.days_requested),
      reason: createForm.reason,
      note: createForm.tanggal_masuk ? { tanggal_masuk: createForm.tanggal_masuk } : null,
    })

    notify.success(res.message || 'Pengajuan disetujui & siap cetak.')

    const requestId = res.data?.id
    showCreateModal.value = false
    fetchRequests()

    // 2. Print — buka HTML form di window baru
    if (requestId) {
      const token = localStorage.getItem('token')
      const printUrl = `/api/v1/leave/requests/${requestId}/print`
      const response = await fetch(printUrl, {
        headers: { 'Authorization': `Bearer ${token}` }
      })
      const html = await response.text()
      const win = window.open('', '_blank', 'width=900,height=700')
      win.document.write(html)
      win.document.close()
    }
  } catch (err) {
    notify.error(err.message || 'Gagal approve & print.')
  } finally {
    approvingPrinting.value = false
  }
}

// --- Bulk mode ---
function toggleApproveMode() {
  approveMode.value = !approveMode.value
  rejectMode.value = false
  selectedIds.value = []
}

function toggleRejectMode() {
  rejectMode.value = !rejectMode.value
  approveMode.value = false
  selectedIds.value = []
}

function cancelBulkMode() {
  approveMode.value = false
  rejectMode.value = false
  selectedIds.value = []
}

async function executeBulkApprove() {
  if (!selectedIds.value.length) return
  if (!confirm(`Setujui ${selectedIds.value.length} pengajuan cuti?`)) return
  bulkProcessing.value = true
  try {
    const res = await api.post('/api/v1/leave/requests/bulk-approve', { ids: selectedIds.value })
    notify.success(res.message || 'Bulk approve berhasil.')
    cancelBulkMode()
    fetchRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal bulk approve.')
  } finally {
    bulkProcessing.value = false
  }
}

async function executeBulkReject() {
  if (!selectedIds.value.length) return
  rejectTarget.isBulk = true
  rejectTarget.count = selectedIds.value.length
  rejectReason.value = ''
  showRejectModal.value = true
}

async function confirmReject() {
  if (!rejectReason.value.trim()) {
    notify.error('Alasan penolakan wajib diisi.')
    return
  }
  showRejectModal.value = false
  bulkProcessing.value = true
  try {
    const res = await api.post('/api/v1/leave/requests/bulk-reject', {
      ids: rejectTarget.isBulk ? selectedIds.value : [rejectTarget.id],
      rejection_reason: rejectReason.value,
    })
    notify.success(res.message || 'Pengajuan ditolak.')
    cancelBulkMode()
    fetchRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal menolak pengajuan.')
  } finally {
    bulkProcessing.value = false
  }
}

// --- Single actions ---
async function singleApprove(item) {
  if (!confirm('Setujui pengajuan cuti ini?')) return
  try {
    await api.post(`/api/v1/leave/requests/${item.id}/approve`, {})
    notify.success('Pengajuan disetujui.')
    fetchRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal menyetujui.')
  }
}

async function singleReject(item) {
  rejectTarget.isBulk = false
  rejectTarget.id = item.id
  rejectTarget.count = 1
  rejectReason.value = ''
  showRejectModal.value = true
}

// --- Detail ---
function viewDetail(item) {
  detailItem.value = item
  showDetailModal.value = true
}

// --- Inline edit tanggal masuk ---
async function updateTanggalMasuk(item, value) {
  try {
    await api.patch(`/api/v1/leave/requests/${item.id}/tanggal-masuk`, {
      tanggal_masuk: value || null,
    })
    // Update local cache biar gak perlu refetch
    if (!item.note) item.note = {}
    item.note.tanggal_masuk = value || null
    notify.success('Tanggal masuk diupdate.')
  } catch (err) {
    notify.error('Gagal update tanggal masuk.')
  }
}

// --- Print ---
async function printRequest(item) {
  try {
    const token = localStorage.getItem('token')
    const printUrl = `/api/v1/leave/requests/${item.id}/print`
    const response = await fetch(printUrl, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    const html = await response.text()
    const win = window.open('', '_blank', 'width=900,height=700')
    win.document.write(html)
    win.document.close()
  } catch (err) {
    notify.error('Gagal mencetak form.')
  }
}

// --- Export ---
function exportExcel() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams()
  if (selectedPeriodId.value) params.append('leave_period_id', selectedPeriodId.value)
  if (filters.status) params.append('status', filters.status)
  const url = `/api/v1/leave/export/requests?${params.toString()}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', `Pengajuan_Cuti.xlsx`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(() => notify.error('Gagal export Excel.'))
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
  await fetchLeaveTypes()
  await fetchEmployees()
  fetchRequests()
})
</script>
