<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Lembur</h1>
        <p class="text-sm text-(--text-muted) mt-1">Pengajuan dan persetujuan lembur karyawan</p>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="sm" @click="showRuleModal = true">
          <template #icon-left>
            <IconCog class="w-4 h-4" />
          </template>
          Aturan Lembur
        </BaseButton>
        <BaseButton variant="primary" size="sm" @click="showCreateModal = true">
          <template #icon-left>
            <IconPlus class="w-4 h-4" />
          </template>
          Ajukan Lembur
        </BaseButton>
      </div>
    </div>

    <div class="flex gap-2 mb-4">
      <BaseButton
        v-for="tab in tabs"
        :key="tab.key"
        :variant="activeTab === tab.key ? 'primary' : 'ghost'"
        size="sm"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span
          class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full"
          :class="activeTab === tab.key ? 'bg-white/20' : 'bg-(--bg-elevated) text-(--text-muted)'"
        >
          {{ tabCount(tab.key) }}
        </span>
      </BaseButton>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="filteredItems" :loading="loading">
        <template #item.date="{ value }">{{ value }}</template>
        <template #item.nip="{ value }">{{ value }}</template>
        <template #item.employee_name="{ value }">{{ value }}</template>
        <template #item.department="{ value }">{{ value }}</template>
        <template #item.start_time="{ value }">{{ value }}</template>
        <template #item.end_time="{ value }">{{ value }}</template>
        <template #item.total_hours="{ value }">{{ value }} jam</template>
        <template #item.reason="{ value }">{{ value }}</template>
        <template #item.status="{ value }">
          <Badge :variant="statusBadgeVariant(value)">{{ statusLabel(value) }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <BaseButton
              v-if="item.status === 'pending'"
              variant="success"
              size="sm"
              @click="handleApprove(item)"
            >
              <IconCalendarCheck class="w-4 h-4" />
            </BaseButton>
            <BaseButton
              v-if="item.status === 'pending'"
              variant="danger"
              size="sm"
              @click="handleReject(item)"
            >
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

    <BaseModal :show="showCreateModal" title="Ajukan Lembur" size="md" @close="showCreateModal = false">
      <div class="space-y-4">
        <SelectInput v-model="createForm.employee" label="Karyawan" :options="employeeOptions" placeholder="Pilih karyawan" />
        <TextInput v-model="createForm.date" label="Tanggal" type="date" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="createForm.startTime" label="Jam Mulai" type="time" />
          <TextInput v-model="createForm.endTime" label="Jam Selesai" type="time" />
        </div>
        <TextInput v-model="createForm.reason" label="Alasan Lembur" placeholder="Jelaskan alasan lembur" />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showCreateModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="submitOvertime">Ajukan</BaseButton>
      </template>
    </BaseModal>

    <BaseModal :show="showRuleModal" title="Aturan Lembur" size="md" @close="showRuleModal = false">
      <div class="space-y-4 text-sm">
        <div class="p-3 rounded-md bg-(--bg-elevated)">
          <p class="font-medium text-(--text-main)">Batas Maksimal Lembur</p>
          <p class="text-(--text-muted)">Maksimal 4 jam per hari dan 18 jam per minggu</p>
        </div>
        <div class="p-3 rounded-md bg-(--bg-elevated)">
          <p class="font-medium text-(--text-main)">Jam Berlaku Lembur</p>
          <p class="text-(--text-muted)">Setelah jam kerja normal (di atas 17:00 atau sebelum 07:00)</p>
        </div>
        <div class="p-3 rounded-md bg-(--bg-elevated)">
          <p class="font-medium text-(--text-main)">Perhitungan Upah Lembur</p>
          <p class="text-(--text-muted)">Jam pertama: 1,5x upah per jam<br />Jam berikutnya: 2x upah per jam</p>
        </div>
        <div class="p-3 rounded-md bg-(--bg-elevated)">
          <p class="font-medium text-(--text-main)">Persyaratan</p>
          <p class="text-(--text-muted)">Harus mendapat persetujuan atasan minimal 1 hari sebelumnya</p>
        </div>
      </div>
    </BaseModal>

    <ConfirmDialog
      :show="confirmApprove.show"
      title="Setujui Lembur"
      :message="'Setujui pengajuan lembur untuk ' + confirmApprove.employeeName + '?'"
      variant="success"
      @confirm="confirmApproveAction"
      @cancel="confirmApprove.show = false"
    />

    <ConfirmDialog
      :show="confirmReject.show"
      title="Tolak Lembur"
      :message="'Tolak pengajuan lembur untuk ' + confirmReject.employeeName + '?'"
      variant="danger"
      @confirm="confirmRejectAction"
      @cancel="confirmReject.show = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import Badge from '../../../../Components/Badge.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import Pagination from '../../../../Components/Table/Pagination.vue'
import { IconPlus, IconCalendarCheck, IconCog } from '../../../../Components/Icons/index.js'

const loading = ref(false)
const activeTab = ref('pending')
const showCreateModal = ref(false)
const showRuleModal = ref(false)

const tabs = [
  { key: 'pending', label: 'Menunggu Approval' },
  { key: 'approved', label: 'Disetujui' },
  { key: 'rejected', label: 'Ditolak' },
]

const confirmApprove = reactive({ show: false, id: null, employeeName: '' })
const confirmReject = reactive({ show: false, id: null, employeeName: '' })

const createForm = reactive({
  employee: '',
  date: '',
  startTime: '',
  endTime: '',
  reason: '',
})

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
  { key: 'date', label: 'Tanggal' },
  { key: 'nip', label: 'NIP' },
  { key: 'employee_name', label: 'Nama' },
  { key: 'department', label: 'Departemen' },
  { key: 'start_time', label: 'Mulai' },
  { key: 'end_time', label: 'Selesai' },
  { key: 'total_hours', label: 'Total Jam' },
  { key: 'reason', label: 'Alasan' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi' },
]

const overtimeRecords = ref([
  { id: 1, employee_name: 'Budi Santoso', nip: 'EMP001', department: 'IT', date: '2026-05-27', start_time: '17:00', end_time: '19:00', total_hours: 2, reason: 'Project deadline', status: 'pending' },
  { id: 2, employee_name: 'Siti Nurhaliza', nip: 'EMP002', department: 'HR', date: '2026-05-27', start_time: '17:00', end_time: '20:00', total_hours: 3, reason: 'Laporan bulanan', status: 'pending' },
  { id: 3, employee_name: 'Ahmad Fauzi', nip: 'EMP003', department: 'Finance', date: '2026-05-26', start_time: '18:00', end_time: '21:00', total_hours: 3, reason: 'Closing bulanan', status: 'pending' },
  { id: 4, employee_name: 'Dewi Lestari', nip: 'EMP004', department: 'Marketing', date: '2026-05-26', start_time: '17:00', end_time: '19:00', total_hours: 2, reason: 'Persiapan event', status: 'pending' },
  { id: 5, employee_name: 'Rudi Hartono', nip: 'EMP005', department: 'IT', date: '2026-05-25', start_time: '17:00', end_time: '22:00', total_hours: 5, reason: 'Server maintenance', status: 'pending' },
  { id: 6, employee_name: 'Anisa Rahman', nip: 'EMP006', department: 'Operations', date: '2026-05-25', start_time: '17:00', end_time: '20:00', total_hours: 3, reason: 'Audit internal', status: 'approved' },
  { id: 7, employee_name: 'Hendra Gunawan', nip: 'EMP007', department: 'Finance', date: '2026-05-24', start_time: '17:00', end_time: '19:00', total_hours: 2, reason: 'Rekonsiliasi', status: 'approved' },
  { id: 8, employee_name: 'Maya Indah', nip: 'EMP008', department: 'HR', date: '2026-05-24', start_time: '17:00', end_time: '18:00', total_hours: 1, reason: 'Dokumentasi', status: 'approved' },
  { id: 9, employee_name: 'Fajar Pratama', nip: 'EMP009', department: 'IT', date: '2026-05-23', start_time: '17:00', end_time: '21:00', total_hours: 4, reason: 'Bug fixing urgent', status: 'approved' },
  { id: 10, employee_name: 'Rina Wijaya', nip: 'EMP010', department: 'Marketing', date: '2026-05-23', start_time: '17:00', end_time: '18:00', total_hours: 1, reason: 'Meeting klien', status: 'approved' },
  { id: 11, employee_name: 'Doni Kusuma', nip: 'EMP011', department: 'Operations', date: '2026-05-22', start_time: '17:00', end_time: '20:00', total_hours: 3, reason: 'Stock opname', status: 'approved' },
  { id: 12, employee_name: 'Putri Anggraini', nip: 'EMP012', department: 'IT', date: '2026-05-27', start_time: '17:00', end_time: '19:00', total_hours: 2, reason: 'Tidak ada alasan', status: 'rejected' },
  { id: 13, employee_name: 'Bayu Aditya', nip: 'EMP013', department: 'Finance', date: '2026-05-26', start_time: '17:00', end_time: '18:00', total_hours: 1, reason: 'Tidak diperlukan', status: 'rejected' },
  { id: 14, employee_name: 'Citra Dewi', nip: 'EMP014', department: 'HR', date: '2026-05-25', start_time: '17:00', end_time: '20:00', total_hours: 3, reason: 'Di luar jam', status: 'rejected' },
  { id: 15, employee_name: 'Eko Prasetyo', nip: 'EMP015', department: 'Marketing', date: '2026-05-24', start_time: '17:00', end_time: '19:00', total_hours: 2, reason: 'Cukup jam normal', status: 'rejected' },
])

const filteredItems = computed(() => {
  return overtimeRecords.value.filter((item) => item.status === activeTab.value)
})

function tabCount(key) {
  return overtimeRecords.value.filter((item) => item.status === key).length
}

function statusBadgeVariant(status) {
  const map = { pending: 'warning', approved: 'success', rejected: 'danger' }
  return map[status] || 'neutral'
}

function statusLabel(status) {
  const map = { pending: 'Pending', approved: 'Disetujui', rejected: 'Ditolak' }
  return map[status] || status
}

function handleApprove(item) {
  confirmApprove.show = true
  confirmApprove.id = item.id
  confirmApprove.employeeName = item.employee_name
}

function confirmApproveAction() {
  const item = overtimeRecords.value.find((i) => i.id === confirmApprove.id)
  if (item) item.status = 'approved'
  confirmApprove.show = false
}

function handleReject(item) {
  confirmReject.show = true
  confirmReject.id = item.id
  confirmReject.employeeName = item.employee_name
}

function confirmRejectAction() {
  const item = overtimeRecords.value.find((i) => i.id === confirmReject.id)
  if (item) item.status = 'rejected'
  confirmReject.show = false
}

function submitOvertime() {
  overtimeRecords.value.unshift({
    id: Date.now(),
    employee_name: 'Karyawan Baru',
    nip: '', // will be filled from employee selection
    department: 'IT',
    date: createForm.date || '2026-05-27',
    start_time: createForm.startTime || '17:00',
    end_time: createForm.endTime || '19:00',
    total_hours: 2,
    reason: createForm.reason || '-',
    status: 'pending',
  })
  showCreateModal.value = false
  createForm.employee = ''
  createForm.date = ''
  createForm.startTime = ''
  createForm.endTime = ''
  createForm.reason = ''
}

function handlePageChange(page) {
  pagination.currentPage = page
}
</script>
