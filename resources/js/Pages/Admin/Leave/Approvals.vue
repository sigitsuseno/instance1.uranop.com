<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Approval Cuti</h1>
        <p class="text-sm text-(--text-muted) mt-1">Persetujuan pengajuan cuti karyawan yang menunggu</p>
      </div>
    </div>

    <BaseCard>
      <div v-if="selectedItems.length > 0" class="flex items-center gap-3 mb-4 p-3 rounded-md bg-(--primary)/5 border border-(--primary)/20">
        <span class="text-sm font-medium text-(--text-main)">{{ selectedItems.length }} item terpilih</span>
        <div class="flex gap-2">
          <BaseButton variant="success" size="sm" @click="bulkApprove">
            <template #icon-left>
              <IconCalendarCheck class="w-4 h-4" />
            </template>
            Setujui Semua
          </BaseButton>
          <BaseButton variant="danger" size="sm" @click="bulkReject">
            <template #icon-left>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </template>
            Tolak Semua
          </BaseButton>
        </div>
      </div>

      <DataTable
        :headers="headers"
        :items="pendingLeaveRecords"
        :selectable="true"
        :selected="selectedItems"
        @update:selected="selectedItems = $event"
      >
        <template #item.employee_name="{ value }">{{ value }}</template>
        <template #item.nip="{ value }">{{ value }}</template>
        <template #item.department="{ value }">{{ value }}</template>
        <template #item.leave_type="{ value }">{{ value }}</template>
        <template #item.date_range="{ item }">{{ item.start_date }} - {{ item.end_date }}</template>
        <template #item.total_days="{ value }">{{ value }} hari</template>
        <template #item.status="{ value }">
          <Badge variant="warning">Pending</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <BaseButton variant="success" size="sm" @click="openApproveModal(item)">
              <IconCalendarCheck class="w-4 h-4" />
            </BaseButton>
            <BaseButton variant="danger" size="sm" @click="openRejectModal(item)">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </BaseButton>
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

    <BaseModal :show="showApproveModal" title="Setujui Cuti" size="md" @close="showApproveModal = false">
      <div class="space-y-3 text-sm">
        <p class="text-(--text-main)">
          Setujui pengajuan cuti untuk <strong>{{ approveTarget?.employee_name }}</strong>?
        </p>
        <div class="grid grid-cols-2 gap-2 text-(--text-muted)">
          <div>Tipe: <span class="text-(--text-main)">{{ approveTarget?.leave_type }}</span></div>
          <div>Tanggal: <span class="text-(--text-main)">{{ approveTarget?.start_date }} - {{ approveTarget?.end_date }}</span></div>
          <div>Total: <span class="text-(--text-main)">{{ approveTarget?.total_days }} hari</span></div>
        </div>
        <p class="text-(--text-muted)">Alasan: <span class="text-(--text-main)">{{ approveTarget?.reason }}</span></p>
        <TextInput v-model="approveNotes" label="Catatan (Opsional)" placeholder="Tambahkan catatan approval" />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showApproveModal = false">Batal</BaseButton>
        <BaseButton variant="success" @click="confirmSingleApprove">Setujui</BaseButton>
      </template>
    </BaseModal>

    <BaseModal :show="showRejectModal" title="Tolak Cuti" size="md" @close="showRejectModal = false">
      <div class="space-y-3 text-sm">
        <p class="text-(--text-main)">
          Tolak pengajuan cuti untuk <strong>{{ rejectTarget?.employee_name }}</strong>?
        </p>
        <TextInput v-model="rejectReason" label="Alasan Penolakan" placeholder="Berikan alasan penolakan" />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showRejectModal = false">Batal</BaseButton>
        <BaseButton variant="danger" @click="confirmSingleReject">Tolak</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="confirmBulk.show"
      :title="confirmBulk.title"
      :message="confirmBulk.message"
      :variant="confirmBulk.variant"
      @confirm="confirmBulkAction"
      @cancel="confirmBulk.show = false"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { IconCalendarCheck } from '../../../Components/Icons/index.js'

const selectedItems = ref([])
const showApproveModal = ref(false)
const showRejectModal = ref(false)
const approveTarget = ref(null)
const rejectTarget = ref(null)
const approveNotes = ref('')
const rejectReason = ref('')

const confirmBulk = reactive({
  show: false,
  title: '',
  message: '',
  variant: 'primary',
  action: null,
})

const pagination = reactive({
  currentPage: 1,
  totalPages: 1,
  total: 10,
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

const pendingLeaveRecords = ref([
  { id: 1, employee_name: 'Budi Santoso', nip: 'EMP001', department: 'IT', leave_type: 'Cuti Tahunan', start_date: '2026-06-01', end_date: '2026-06-05', total_days: 5, reason: 'Liburan keluarga', status: 'pending' },
  { id: 2, employee_name: 'Siti Nurhaliza', nip: 'EMP002', department: 'HR', leave_type: 'Cuti Tahunan', start_date: '2026-06-10', end_date: '2026-06-14', total_days: 5, reason: 'Acara keluarga', status: 'pending' },
  { id: 3, employee_name: 'Ahmad Fauzi', nip: 'EMP003', department: 'Finance', leave_type: 'Cuti Sakit', start_date: '2026-05-27', end_date: '2026-05-28', total_days: 2, reason: 'Demam', status: 'pending' },
  { id: 4, employee_name: 'Dewi Lestari', nip: 'EMP004', department: 'Marketing', leave_type: 'Cuti Tahunan', start_date: '2026-07-01', end_date: '2026-07-03', total_days: 3, reason: 'Pernikahan saudara', status: 'pending' },
  { id: 5, employee_name: 'Rudi Hartono', nip: 'EMP005', department: 'IT', leave_type: 'Cuti Tahunan', start_date: '2026-08-01', end_date: '2026-08-05', total_days: 5, reason: 'Renovasi rumah', status: 'pending' },
  { id: 6, employee_name: 'Maya Indah', nip: 'EMP008', department: 'HR', leave_type: 'Cuti Tahunan', start_date: '2026-07-10', end_date: '2026-07-12', total_days: 3, reason: 'Keperluan pribadi', status: 'pending' },
  { id: 7, employee_name: 'Fajar Pratama', nip: 'EMP009', department: 'IT', leave_type: 'Cuti Sakit', start_date: '2026-05-29', end_date: '2026-05-30', total_days: 2, reason: 'Cek kesehatan', status: 'pending' },
  { id: 8, employee_name: 'Hendra Gunawan', nip: 'EMP007', department: 'Finance', leave_type: 'Cuti Tahunan', start_date: '2026-06-15', end_date: '2026-06-16', total_days: 2, reason: 'Urusan keluarga', status: 'pending' },
  { id: 9, employee_name: 'Putri Anggraini', nip: 'EMP012', department: 'IT', leave_type: 'Cuti Tahunan', start_date: '2026-07-01', end_date: '2026-07-05', total_days: 5, reason: 'Pulang kampung', status: 'pending' },
  { id: 10, employee_name: 'Bayu Aditya', nip: 'EMP013', department: 'Finance', leave_type: 'Cuti Melahirkan', start_date: '2026-09-01', end_date: '2026-11-30', total_days: 90, reason: 'Persalinan', status: 'pending' },
])

function openApproveModal(item) {
  approveTarget.value = item
  approveNotes.value = ''
  showApproveModal.value = true
}

function confirmSingleApprove() {
  const item = pendingLeaveRecords.value.find((i) => i.id === approveTarget.value.id)
  if (item) item.status = 'approved'
  showApproveModal.value = false
}

function openRejectModal(item) {
  rejectTarget.value = item
  rejectReason.value = ''
  showRejectModal.value = true
}

function confirmSingleReject() {
  const item = pendingLeaveRecords.value.find((i) => i.id === rejectTarget.value.id)
  if (item) item.status = 'rejected'
  showRejectModal.value = false
}

function bulkApprove() {
  confirmBulk.title = 'Setujui Cuti'
  confirmBulk.message = `Setujui ${selectedItems.value.length} pengajuan cuti yang dipilih?`
  confirmBulk.variant = 'success'
  confirmBulk.action = 'approve'
  confirmBulk.show = true
}

function bulkReject() {
  confirmBulk.title = 'Tolak Cuti'
  confirmBulk.message = `Tolak ${selectedItems.value.length} pengajuan cuti yang dipilih?`
  confirmBulk.variant = 'danger'
  confirmBulk.action = 'reject'
  confirmBulk.show = true
}

function confirmBulkAction() {
  const ids = selectedItems.value.map((i) => i.id)
  const newStatus = confirmBulk.action === 'approve' ? 'approved' : 'rejected'
  pendingLeaveRecords.value.forEach((item) => {
    if (ids.includes(item.id)) item.status = newStatus
  })
  selectedItems.value = []
  confirmBulk.show = false
}

function handlePageChange(page) {
  pagination.currentPage = page
}
</script>
