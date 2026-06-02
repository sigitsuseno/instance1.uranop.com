<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Approval Cuti</h1>
        <p class="text-sm text-(--text-muted) mt-1">Persetujuan pengajuan cuti karyawan yang menunggu approval.</p>
      </div>
    </div>

    <BaseCard>
      <!-- Bulk Actions Bar -->
      <div v-if="selectedItems.length > 0" class="flex items-center gap-3 mb-4 p-3 rounded-md bg-(--primary)/5 border border-(--primary)/20">
        <span class="text-sm font-medium text-(--text-main)">{{ selectedItems.length }} item terpilih</span>
        <div class="flex gap-2">
          <BaseButton variant="success" size="sm" @click="bulkApprove" :loading="processingBulk">
            <template #icon-left>
              <i class="bx bx-check-circle text-base"></i>
            </template>
            Setujui Semua
          </BaseButton>
          <BaseButton variant="danger" size="sm" @click="bulkReject" :loading="processingBulk">
            <template #icon-left>
              <i class="bx bx-x-circle text-base"></i>
            </template>
            Tolak Semua
          </BaseButton>
        </div>
      </div>

      <!-- Data Table -->
      <DataTable
        :headers="headers"
        :items="pendingRequests"
        :selectable="true"
        :selected="selectedItems"
        :loading="loading"
        @update:selected="selectedItems = $event"
        emptyText="Tidak ada pengajuan cuti yang menunggu persetujuan."
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
        <template #item.status>
          <Badge variant="warning">Pending</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <BaseButton variant="success" size="sm" @click="openApproveModal(item)" title="Setujui">
              <template #icon-left>
                <i class="bx bx-check text-base"></i>
              </template>
            </BaseButton>
            <BaseButton variant="danger" size="sm" @click="openRejectModal(item)" title="Tolak">
              <template #icon-left>
                <i class="bx bx-x text-base"></i>
              </template>
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

    <!-- MODAL: APPROVE -->
    <BaseModal :show="showApproveModal" title="Setujui Cuti" size="md" @close="showApproveModal = false">
      <div v-if="approveTarget" class="space-y-3 text-sm">
        <p class="text-(--text-main)">
          Apakah Anda yakin ingin menyetujui pengajuan cuti untuk <strong>{{ approveTarget.employee?.name }}</strong>?
        </p>
        <div class="p-3 bg-(--bg-elevated) rounded-md grid grid-cols-2 gap-2 text-xs text-(--text-muted)">
          <div>Tipe: <span class="text-(--text-main) font-semibold">{{ approveTarget.leave_type?.name }}</span></div>
          <div>Total Hari: <span class="text-(--text-main) font-semibold">{{ approveTarget.days_requested }} hari</span></div>
          <div class="col-span-2">Tanggal: <span class="text-(--text-main) font-semibold">{{ formatDate(approveTarget.start_date) }} - {{ formatDate(approveTarget.end_date) }}</span></div>
        </div>
        <p class="text-(--text-muted) italic" v-if="approveTarget.reason">
          Alasan: <span class="text-(--text-main)">"{{ approveTarget.reason }}"</span>
        </p>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showApproveModal = false">Batal</BaseButton>
        <BaseButton variant="success" @click="confirmSingleApprove" :loading="processingAction">Setujui</BaseButton>
      </template>
    </BaseModal>

    <!-- MODAL: REJECT -->
    <BaseModal :show="showRejectModal" title="Tolak Cuti" size="md" @close="showRejectModal = false">
      <div v-if="rejectTarget" class="space-y-4 text-sm">
        <p class="text-(--text-main)">
          Berikan alasan penolakan pengajuan cuti untuk <strong>{{ rejectTarget.employee?.name }}</strong>:
        </p>
        <TextInput
          v-model="rejectReason"
          label="Alasan Penolakan"
          placeholder="Tulis alasan penolakan pengajuan cuti"
          :required="true"
          :error="rejectReasonError"
        />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showRejectModal = false">Batal</BaseButton>
        <BaseButton variant="danger" @click="confirmSingleReject" :loading="processingAction">Tolak</BaseButton>
      </template>
    </BaseModal>

    <!-- CONFIRM DIALOG: BULK ACTION -->
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
import { ref, reactive, onMounted } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { useApi } from '../../../composables/useApi'
import { useNotification } from '../../../composables/useNotification'

const api = useApi()
const notify = useNotification()

const loading = ref(false)
const processingAction = ref(false)
const processingBulk = ref(false)

const selectedItems = ref([])
const showApproveModal = ref(false)
const showRejectModal = ref(false)

const approveTarget = ref(null)
const rejectTarget = ref(null)
const rejectReason = ref('')
const rejectReasonError = ref('')

const pendingRequests = ref([])

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
  total: 0,
  perPage: 15,
})

const headers = [
  { key: 'employee_name', label: 'Karyawan' },
  { key: 'nip', label: 'NIP' },
  { key: 'department', label: 'Departemen' },
  { key: 'leave_type', label: 'Tipe Cuti' },
  { key: 'date_range', label: 'Tanggal' },
  { key: 'days_requested', label: 'Durasi' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false },
]

async function fetchPendingRequests() {
  loading.value = true
  try {
    // Get requests with status pending
    const res = await api.get(`/api/v1/leave/requests?status=pending&page=${pagination.currentPage}`)
    
    // API returns stats envelope
    const dataEnvelope = res.paginated || res
    pendingRequests.value = dataEnvelope.data || []
    
    pagination.currentPage = dataEnvelope.current_page || 1
    pagination.totalPages = dataEnvelope.last_page || 1
    pagination.total = dataEnvelope.total || 0
    pagination.perPage = dataEnvelope.per_page || 15
  } catch (err) {
    notify.error('Gagal memuat pengajuan cuti pending.')
  } finally {
    loading.value = false
  }
}

function openApproveModal(item) {
  approveTarget.value = item
  showApproveModal.value = true
}

async function confirmSingleApprove() {
  if (!approveTarget.value) return
  processingAction.value = true
  try {
    await api.post(`/api/v1/leave/requests/${approveTarget.value.id}/approve`, {})
    notify.success('Pengajuan cuti berhasil disetujui.')
    showApproveModal.value = false
    selectedItems.value = selectedItems.value.filter((i) => i.id !== approveTarget.value.id)
    fetchPendingRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal menyetujui pengajuan.')
  } finally {
    processingAction.value = false
  }
}

function openRejectModal(item) {
  rejectTarget.value = item
  rejectReason.value = ''
  rejectReasonError.value = ''
  showRejectModal.value = true
}

async function confirmSingleReject() {
  if (!rejectTarget.value) return
  if (!rejectReason.value.trim()) {
    rejectReasonError.value = 'Alasan penolakan wajib diisi.'
    return
  }
  
  processingAction.value = true
  try {
    await api.post(`/api/v1/leave/requests/${rejectTarget.value.id}/reject`, {
      rejection_reason: rejectReason.value,
    })
    notify.success('Pengajuan cuti berhasil ditolak.')
    showRejectModal.value = false
    selectedItems.value = selectedItems.value.filter((i) => i.id !== rejectTarget.value.id)
    fetchPendingRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal menolak pengajuan.')
  } finally {
    processingAction.value = false
  }
}

function bulkApprove() {
  confirmBulk.title = 'Setujui Semua Terpilih'
  confirmBulk.message = `Apakah Anda yakin ingin menyetujui ${selectedItems.value.length} pengajuan cuti terpilih secara massal?`
  confirmBulk.variant = 'success'
  confirmBulk.action = 'approve'
  confirmBulk.show = true
}

function bulkReject() {
  confirmBulk.title = 'Tolak Semua Terpilih'
  confirmBulk.message = `Apakah Anda yakin ingin menolak ${selectedItems.value.length} pengajuan cuti terpilih secara massal? Anda harus memberikan alasan penolakan.`
  confirmBulk.variant = 'danger'
  confirmBulk.action = 'reject'
  confirmBulk.show = true
}

async function confirmBulkAction() {
  confirmBulk.show = false
  processingBulk.value = true
  
  let successCount = 0
  let failCount = 0
  
  try {
    if (confirmBulk.action === 'approve') {
      for (const item of selectedItems.value) {
        try {
          await api.post(`/api/v1/leave/requests/${item.id}/approve`, {})
          successCount++
        } catch (err) {
          failCount++
        }
      }
      notify.success(`${successCount} pengajuan berhasil disetujui.${failCount > 0 ? ` ${failCount} gagal.` : ''}`)
    } else {
      // Bulk Reject requires reason - show prompt
      const reason = prompt('Masukkan alasan penolakan massal untuk pengajuan yang dipilih:')
      if (reason === null) {
        // User cancelled the prompt
        processingBulk.value = false
        return
      }
      if (!reason.trim()) {
        notify.error('Alasan penolakan tidak boleh kosong.')
        processingBulk.value = false
        return
      }
      
      for (const item of selectedItems.value) {
        try {
          await api.post(`/api/v1/leave/requests/${item.id}/reject`, {
            rejection_reason: reason,
          })
          successCount++
        } catch (err) {
          failCount++
        }
      }
      notify.success(`${successCount} pengajuan berhasil ditolak.${failCount > 0 ? ` ${failCount} gagal.` : ''}`)
    }
    
    selectedItems.value = []
    fetchPendingRequests()
  } catch (err) {
    notify.error('Terjadi kesalahan saat memproses aksi massal.')
  } finally {
    processingBulk.value = false
  }
}

function handlePageChange(page) {
  pagination.currentPage = page
  fetchPendingRequests()
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(() => {
  fetchPendingRequests()
})
</script>
