<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Daftar Cuti</h1>
        <p class="text-sm text-(--text-muted) mt-1">Pengajuan dan persetujuan cuti karyawan</p>
      </div>
      <BaseButton variant="primary" size="sm" @click="showCreateModal = true">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Ajukan Cuti
      </BaseButton>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center">
            <IconCalendarCheck class="w-5 h-5 text-(--primary)" />
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">45</p>
            <p class="text-xs text-(--text-muted)">Total Cuti</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--success)/10 flex items-center justify-center">
            <IconCalendarCheck class="w-5 h-5 text-(--success)" />
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">32</p>
            <p class="text-xs text-(--text-muted)">Disetujui</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--warning)/10 flex items-center justify-center">
            <IconClock class="w-5 h-5 text-(--warning)" />
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">8</p>
            <p class="text-xs text-(--text-muted)">Pending</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--danger)/10 flex items-center justify-center">
            <IconCalendarCheck class="w-5 h-5 text-(--danger)" />
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">5</p>
            <p class="text-xs text-(--text-muted)">Ditolak</p>
          </div>
        </div>
      </BaseCard>
    </div>

    <div class="flex items-center gap-3 mb-4 flex-wrap">
      <div class="w-36">
        <SelectInput v-model="filters.status" label="Status" :options="statusFilterOptions" placeholder="Semua" />
      </div>
      <div class="w-44">
        <SelectInput v-model="filters.department" label="Departemen" :options="departmentOptions" placeholder="Semua" />
      </div>
      <div class="w-44">
        <SelectInput v-model="filters.leaveType" label="Tipe Cuti" :options="leaveTypeOptions" placeholder="Semua" />
      </div>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="leaveRecords" :loading="loading">
        <template #item.employee_name="{ value }">{{ value }}</template>
        <template #item.nip="{ value }">{{ value }}</template>
        <template #item.department="{ value }">{{ value }}</template>
        <template #item.leave_type="{ value }">{{ value }}</template>
        <template #item.date_range="{ item }">{{ item.start_date }} - {{ item.end_date }}</template>
        <template #item.total_days="{ value }">{{ value }} hari</template>
        <template #item.status="{ value }">
          <Badge :variant="statusBadgeVariant(value)">{{ statusLabel(value) }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <BaseButton variant="ghost" size="sm" @click="viewDetail(item)">
              <IconEye class="w-4 h-4" />
            </BaseButton>
            <template v-if="item.status === 'pending'">
              <BaseButton variant="success" size="sm" @click="handleApprove(item)">
                <IconCalendarCheck class="w-4 h-4" />
              </BaseButton>
              <BaseButton variant="danger" size="sm" @click="handleReject(item)">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18" />
                  <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
              </BaseButton>
            </template>
          </div>
        </template>
      </DataTable>

      <Pagination
        :current-page="pagination.currentPage"
        :total-pages="pagination.totalPages"
        :total="pagination.total"
        :per-page="pagination.perPage"
        @page-change="handlePageChange"
      />
    </BaseCard>

    <BaseModal :show="showCreateModal" title="Ajukan Cuti" size="md" @close="showCreateModal = false">
      <div class="space-y-4">
        <SelectInput v-model="createForm.employee" label="Karyawan" :options="employeeOptions" placeholder="Pilih karyawan" />
        <SelectInput v-model="createForm.leaveType" label="Tipe Cuti" :options="leaveTypeFormOptions" placeholder="Pilih tipe cuti" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="createForm.startDate" label="Tanggal Mulai" type="date" />
          <TextInput v-model="createForm.endDate" label="Tanggal Selesai" type="date" />
        </div>
        <TextInput v-model="createForm.reason" label="Alasan Cuti" placeholder="Tulis alasan pengajuan cuti" />
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Lampiran (Opsional)</label>
          <div class="border-2 border-dashed border-(--border-soft) rounded-md p-4 text-center cursor-pointer hover:border-(--primary)/50 transition-colors">
            <p class="text-xs text-(--text-muted)">Klik untuk upload file pendukung</p>
          </div>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showCreateModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="submitLeave">Ajukan</BaseButton>
      </template>
    </BaseModal>

    <BaseModal :show="showDetailModal" title="Detail Cuti" size="md" @close="showDetailModal = false">
      <div v-if="detailItem" class="space-y-3 text-sm">
        <div class="grid grid-cols-2 gap-3">
          <div><span class="text-(--text-muted)">Nama:</span> <span class="text-(--text-main) font-medium">{{ detailItem.employee_name }}</span></div>
          <div><span class="text-(--text-muted)">NIP:</span> <span class="text-(--text-main) font-medium">{{ detailItem.nip }}</span></div>
          <div><span class="text-(--text-muted)">Departemen:</span> <span class="text-(--text-main) font-medium">{{ detailItem.department }}</span></div>
          <div><span class="text-(--text-muted)">Tipe Cuti:</span> <span class="text-(--text-main) font-medium">{{ detailItem.leave_type }}</span></div>
          <div><span class="text-(--text-muted)">Tanggal:</span> <span class="text-(--text-main) font-medium">{{ detailItem.start_date }} - {{ detailItem.end_date }}</span></div>
          <div><span class="text-(--text-muted)">Total Hari:</span> <span class="text-(--text-main) font-medium">{{ detailItem.total_days }} hari</span></div>
          <div><span class="text-(--text-muted)">Status:</span> <Badge :variant="statusBadgeVariant(detailItem.status)">{{ statusLabel(detailItem.status) }}</Badge></div>
        </div>
        <div>
          <span class="text-(--text-muted)">Alasan:</span>
          <p class="text-(--text-main) mt-1">{{ detailItem.reason }}</p>
        </div>
      </div>
    </BaseModal>

    <ConfirmDialog
      :show="confirmApprove.show"
      title="Setujui Cuti"
      :message="'Setujui pengajuan cuti untuk ' + confirmApprove.employeeName + '?'"
      variant="success"
      @confirm="confirmApproveAction"
      @cancel="confirmApprove.show = false"
    />

    <ConfirmDialog
      :show="confirmReject.show"
      title="Tolak Cuti"
      :message="'Tolak pengajuan cuti untuk ' + confirmReject.employeeName + '?'"
      variant="danger"
      @confirm="confirmRejectAction"
      @cancel="confirmReject.show = false"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { IconPlus, IconCalendarCheck, IconClock, IconEye } from '../../../Components/Icons/index.js'

const loading = ref(false)
const showCreateModal = ref(false)
const showDetailModal = ref(false)
const detailItem = ref(null)

const filters = reactive({
  status: '',
  department: '',
  leaveType: '',
})

const createForm = reactive({
  employee: '',
  leaveType: '',
  startDate: '',
  endDate: '',
  reason: '',
})

const confirmApprove = reactive({ show: false, id: null, employeeName: '' })
const confirmReject = reactive({ show: false, id: null, employeeName: '' })

const statusFilterOptions = [
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Disetujui' },
  { value: 'rejected', label: 'Ditolak' },
]

const departmentOptions = [
  { value: 'IT', label: 'IT' },
  { value: 'HR', label: 'HR' },
  { value: 'Finance', label: 'Keuangan' },
  { value: 'Marketing', label: 'Pemasaran' },
  { value: 'Operations', label: 'Operasional' },
]

const leaveTypeOptions = [
  { value: 'Cuti Tahunan', label: 'Cuti Tahunan' },
  { value: 'Cuti Sakit', label: 'Cuti Sakit' },
  { value: 'Cuti Melahirkan', label: 'Cuti Melahirkan' },
  { value: 'Cuti Besar', label: 'Cuti Besar' },
]

const leaveTypeFormOptions = [
  { value: 'annual', label: 'Cuti Tahunan' },
  { value: 'sick', label: 'Cuti Sakit' },
  { value: 'maternity', label: 'Cuti Melahirkan' },
  { value: 'special', label: 'Cuti Besar' },
]

const employeeOptions = [
  { value: '1', label: 'Budi Santoso' },
  { value: '2', label: 'Siti Nurhaliza' },
  { value: '3', label: 'Ahmad Fauzi' },
]

const pagination = reactive({
  currentPage: 1,
  totalPages: 2,
  total: 15,
  perPage: 10,
})

const headers = [
  { key: 'employee_name', label: 'Karyawan' },
  { key: 'nip', label: 'NIP' },
  { key: 'department', label: 'Departemen' },
  { key: 'leave_type', label: 'Tipe Cuti' },
  { key: 'date_range', label: 'Tanggal' },
  { key: 'total_days', label: 'Total Hari' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi' },
]

const leaveRecords = ref([
  { id: 1, employee_name: 'Budi Santoso', nip: 'EMP001', department: 'IT', leave_type: 'Cuti Tahunan', start_date: '2026-06-01', end_date: '2026-06-05', total_days: 5, reason: 'Liburan keluarga', status: 'pending' },
  { id: 2, employee_name: 'Siti Nurhaliza', nip: 'EMP002', department: 'HR', leave_type: 'Cuti Tahunan', start_date: '2026-06-10', end_date: '2026-06-14', total_days: 5, reason: 'Acara keluarga', status: 'pending' },
  { id: 3, employee_name: 'Ahmad Fauzi', nip: 'EMP003', department: 'Finance', leave_type: 'Cuti Sakit', start_date: '2026-05-27', end_date: '2026-05-28', total_days: 2, reason: 'Demam', status: 'pending' },
  { id: 4, employee_name: 'Dewi Lestari', nip: 'EMP004', department: 'Marketing', leave_type: 'Cuti Tahunan', start_date: '2026-07-01', end_date: '2026-07-03', total_days: 3, reason: 'Pernikahan saudara', status: 'pending' },
  { id: 5, employee_name: 'Rudi Hartono', nip: 'EMP005', department: 'IT', leave_type: 'Cuti Melahirkan', start_date: '2026-08-01', end_date: '2026-11-30', total_days: 90, reason: 'Persalinan', status: 'approved' },
  { id: 6, employee_name: 'Anisa Rahman', nip: 'EMP006', department: 'Operations', leave_type: 'Cuti Tahunan', start_date: '2026-06-15', end_date: '2026-06-17', total_days: 3, reason: 'Perlu istirahat', status: 'approved' },
  { id: 7, employee_name: 'Hendra Gunawan', nip: 'EMP007', department: 'Finance', leave_type: 'Cuti Tahunan', start_date: '2026-06-20', end_date: '2026-06-24', total_days: 5, reason: 'Umroh', status: 'approved' },
  { id: 8, employee_name: 'Maya Indah', nip: 'EMP008', department: 'HR', leave_type: 'Cuti Tahunan', start_date: '2026-07-05', end_date: '2026-07-07', total_days: 3, reason: 'Keperluan pribadi', status: 'approved' },
  { id: 9, employee_name: 'Fajar Pratama', nip: 'EMP009', department: 'IT', leave_type: 'Cuti Sakit', start_date: '2026-05-20', end_date: '2026-05-21', total_days: 2, reason: 'Sakit gigi', status: 'approved' },
  { id: 10, employee_name: 'Rina Wijaya', nip: 'EMP010', department: 'Marketing', leave_type: 'Cuti Besar', start_date: '2026-09-01', end_date: '2026-09-30', total_days: 30, reason: 'Ibadah haji', status: 'approved' },
  { id: 11, employee_name: 'Doni Kusuma', nip: 'EMP011', department: 'Operations', leave_type: 'Cuti Tahunan', start_date: '2026-06-01', end_date: '2026-06-03', total_days: 3, reason: 'Tidak ada alasan jelas', status: 'rejected' },
  { id: 12, employee_name: 'Putri Anggraini', nip: 'EMP012', department: 'IT', leave_type: 'Cuti Tahunan', start_date: '2026-06-08', end_date: '2026-06-09', total_days: 2, reason: 'Bentrok deadline', status: 'rejected' },
  { id: 13, employee_name: 'Bayu Aditya', nip: 'EMP013', department: 'Finance', leave_type: 'Cuti Tahunan', start_date: '2026-06-25', end_date: '2026-06-26', total_days: 2, reason: 'Kuota habis', status: 'rejected' },
  { id: 14, employee_name: 'Citra Dewi', nip: 'EMP014', department: 'HR', leave_type: 'Cuti Sakit', start_date: '2026-05-15', end_date: '2026-05-16', total_days: 2, reason: 'Tanpa surat dokter', status: 'rejected' },
  { id: 15, employee_name: 'Eko Prasetyo', nip: 'EMP015', department: 'Marketing', leave_type: 'Cuti Tahunan', start_date: '2026-06-12', end_date: '2026-06-13', total_days: 2, reason: 'Tidak memenuhi syarat', status: 'rejected' },
])

function statusBadgeVariant(status) {
  const map = { pending: 'warning', approved: 'success', rejected: 'danger' }
  return map[status] || 'neutral'
}

function statusLabel(status) {
  const map = { pending: 'Pending', approved: 'Disetujui', rejected: 'Ditolak' }
  return map[status] || status
}

function viewDetail(item) {
  detailItem.value = item
  showDetailModal.value = true
}

function handleApprove(item) {
  confirmApprove.show = true
  confirmApprove.id = item.id
  confirmApprove.employeeName = item.employee_name
}

function confirmApproveAction() {
  const item = leaveRecords.value.find((i) => i.id === confirmApprove.id)
  if (item) item.status = 'approved'
  confirmApprove.show = false
}

function handleReject(item) {
  confirmReject.show = true
  confirmReject.id = item.id
  confirmReject.employeeName = item.employee_name
}

function confirmRejectAction() {
  const item = leaveRecords.value.find((i) => i.id === confirmReject.id)
  if (item) item.status = 'rejected'
  confirmReject.show = false
}

function submitLeave() {
  leaveRecords.value.unshift({
    id: Date.now(),
    employee_name: 'Karyawan Baru',
    nip: 'EMPXXX',
    department: 'IT',
    leave_type: 'Cuti Tahunan',
    start_date: createForm.startDate || '2026-06-01',
    end_date: createForm.endDate || '2026-06-05',
    total_days: 5,
    reason: createForm.reason || 'Alasan pengajuan',
    status: 'pending',
  })
  showCreateModal.value = false
  createForm.employee = ''
  createForm.leaveType = ''
  createForm.startDate = ''
  createForm.endDate = ''
  createForm.reason = ''
}

function handlePageChange(page) {
  pagination.currentPage = page
}
</script>
