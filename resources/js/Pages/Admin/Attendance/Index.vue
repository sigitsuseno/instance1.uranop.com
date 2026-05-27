<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Data Absensi</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola dan pantau data kehadiran karyawan</p>
      </div>
      <BaseButton variant="secondary" size="sm">
        <template #icon-left>
          <IconDownload class="w-4 h-4" />
        </template>
        Export
      </BaseButton>
    </div>

    <div class="flex items-center gap-3 mb-6 flex-wrap">
      <div class="w-40">
        <TextInput v-model="filters.startDate" label="Tanggal Mulai" type="date" />
      </div>
      <div class="w-40">
        <TextInput v-model="filters.endDate" label="Tanggal Akhir" type="date" />
      </div>
      <div class="w-44">
        <SelectInput v-model="filters.department" label="Departemen" :options="departmentOptions" placeholder="Semua" />
      </div>
      <div class="w-36">
        <SelectInput v-model="filters.status" label="Status" :options="statusOptions" placeholder="Semua" />
      </div>
      <div class="flex items-end gap-2">
        <BaseButton variant="ghost" size="sm" class="mb-0.5">
          <template #icon-left>
            <IconFilter class="w-4 h-4" />
          </template>
          Filter
        </BaseButton>
        <BaseButton variant="secondary" size="sm" class="mb-0.5" @click="goToImport">
          <template #icon-left>
            <IconUpload class="w-4 h-4" />
          </template>
          Import Log
        </BaseButton>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--success)/10 flex items-center justify-center">
            <IconCalendarCheck class="w-5 h-5 text-(--success)" />
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">142</p>
            <p class="text-xs text-(--text-muted)">Hadir</p>
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
            <p class="text-xs text-(--text-muted)">Terlambat</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center">
            <IconCalendarCheck class="w-5 h-5 text-(--primary)" />
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">4</p>
            <p class="text-xs text-(--text-muted)">Izin</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--danger)/10 flex items-center justify-center">
            <IconCalendarCheck class="w-5 h-5 text-(--danger)" />
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">2</p>
            <p class="text-xs text-(--text-muted)">Alfa</p>
          </div>
        </div>
      </BaseCard>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="attendanceRecords" :loading="loading">
        <template #item.date="{ value }">{{ value }}</template>
        <template #item.nip="{ value }">{{ value }}</template>
        <template #item.employee_name="{ value }">{{ value }}</template>
        <template #item.department="{ value }">{{ value }}</template>
        <template #item.check_in="{ value }">{{ value }}</template>
        <template #item.check_out="{ value }">{{ value }}</template>
        <template #item.status="{ value }">
          <Badge :variant="statusBadgeVariant(value)">{{ statusLabel(value) }}</Badge>
        </template>
        <template #item.late_minutes="{ value }">
          <span :class="value > 0 ? 'text-(--danger) font-medium' : 'text-(--text-muted)'">
            {{ value > 0 ? value + ' mnt' : '-' }}
          </span>
        </template>
        <template #item.overtime_minutes="{ value }">
          <span :class="value > 0 ? 'text-(--primary) font-medium' : 'text-(--text-muted)'">
            {{ value > 0 ? value + ' mnt' : '-' }}
          </span>
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
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { IconDownload, IconUpload, IconFilter, IconCalendarCheck, IconClock } from '../../../Components/Icons/index.js'

const loading = ref(false)

const filters = reactive({
  startDate: '2026-05-01',
  endDate: '2026-05-31',
  department: '',
  status: '',
})

const departmentOptions = [
  { value: 'IT', label: 'IT' },
  { value: 'HR', label: 'HR' },
  { value: 'Finance', label: 'Keuangan' },
  { value: 'Marketing', label: 'Pemasaran' },
  { value: 'Operations', label: 'Operasional' },
]

const statusOptions = [
  { value: 'Hadir', label: 'Hadir' },
  { value: 'Terlambat', label: 'Terlambat' },
  { value: 'Izin', label: 'Izin' },
  { value: 'Sakit', label: 'Sakit' },
  { value: 'Alfa', label: 'Alfa' },
]

const pagination = reactive({
  currentPage: 1,
  totalPages: 2,
  total: 20,
  perPage: 10,
})

const headers = [
  { key: 'date', label: 'Tanggal' },
  { key: 'nip', label: 'NIP' },
  { key: 'employee_name', label: 'Nama' },
  { key: 'department', label: 'Departemen' },
  { key: 'check_in', label: 'Check In' },
  { key: 'check_out', label: 'Check Out' },
  { key: 'status', label: 'Status' },
  { key: 'late_minutes', label: 'Terlambat' },
  { key: 'overtime_minutes', label: 'Lembur' },
]

const attendanceRecords = ref([
  { id: 1, employee_name: 'Budi Santoso', nip: 'EMP001', department: 'IT', date: '2026-05-27', check_in: '07:55', check_out: '17:05', status: 'Hadir', late_minutes: 0, overtime_minutes: 30 },
  { id: 2, employee_name: 'Siti Nurhaliza', nip: 'EMP002', department: 'HR', date: '2026-05-27', check_in: '08:15', check_out: '17:00', status: 'Terlambat', late_minutes: 15, overtime_minutes: 0 },
  { id: 3, employee_name: 'Ahmad Fauzi', nip: 'EMP003', department: 'Finance', date: '2026-05-27', check_in: '07:50', check_out: '18:30', status: 'Hadir', late_minutes: 0, overtime_minutes: 120 },
  { id: 4, employee_name: 'Dewi Lestari', nip: 'EMP004', department: 'Marketing', date: '2026-05-27', check_in: '08:00', check_out: '17:00', status: 'Hadir', late_minutes: 0, overtime_minutes: 0 },
  { id: 5, employee_name: 'Rudi Hartono', nip: 'EMP005', department: 'IT', date: '2026-05-27', check_in: '', check_out: '', status: 'Sakit', late_minutes: 0, overtime_minutes: 0 },
  { id: 6, employee_name: 'Anisa Rahman', nip: 'EMP006', department: 'Operations', date: '2026-05-27', check_in: '07:45', check_out: '17:15', status: 'Hadir', late_minutes: 0, overtime_minutes: 45 },
  { id: 7, employee_name: 'Hendra Gunawan', nip: 'EMP007', department: 'Finance', date: '2026-05-27', check_in: '', check_out: '', status: 'Alfa', late_minutes: 0, overtime_minutes: 0 },
  { id: 8, employee_name: 'Maya Indah', nip: 'EMP008', department: 'HR', date: '2026-05-27', check_in: '07:30', check_out: '19:00', status: 'Hadir', late_minutes: 0, overtime_minutes: 150 },
  { id: 9, employee_name: 'Fajar Pratama', nip: 'EMP009', department: 'IT', date: '2026-05-27', check_in: '08:30', check_out: '17:00', status: 'Terlambat', late_minutes: 30, overtime_minutes: 0 },
  { id: 10, employee_name: 'Rina Wijaya', nip: 'EMP010', department: 'Marketing', date: '2026-05-27', check_in: '', check_out: '', status: 'Izin', late_minutes: 0, overtime_minutes: 0 },
  { id: 11, employee_name: 'Doni Kusuma', nip: 'EMP011', department: 'Operations', date: '2026-05-27', check_in: '07:55', check_out: '17:00', status: 'Hadir', late_minutes: 0, overtime_minutes: 0 },
  { id: 12, employee_name: 'Putri Anggraini', nip: 'EMP012', department: 'IT', date: '2026-05-27', check_in: '08:05', check_out: '17:30', status: 'Terlambat', late_minutes: 5, overtime_minutes: 30 },
  { id: 13, employee_name: 'Bayu Aditya', nip: 'EMP013', department: 'Finance', date: '2026-05-27', check_in: '07:50', check_out: '17:10', status: 'Hadir', late_minutes: 0, overtime_minutes: 20 },
  { id: 14, employee_name: 'Citra Dewi', nip: 'EMP014', department: 'HR', date: '2026-05-27', check_in: '', check_out: '', status: 'Sakit', late_minutes: 0, overtime_minutes: 0 },
  { id: 15, employee_name: 'Eko Prasetyo', nip: 'EMP015', department: 'Marketing', date: '2026-05-27', check_in: '07:55', check_out: '17:05', status: 'Hadir', late_minutes: 0, overtime_minutes: 30 },
  { id: 16, employee_name: 'Fitri Handayani', nip: 'EMP016', department: 'Operations', date: '2026-05-27', check_in: '08:10', check_out: '17:00', status: 'Terlambat', late_minutes: 10, overtime_minutes: 0 },
  { id: 17, employee_name: 'Gilang Ramadhan', nip: 'EMP017', department: 'IT', date: '2026-05-27', check_in: '07:40', check_out: '18:00', status: 'Hadir', late_minutes: 0, overtime_minutes: 60 },
  { id: 18, employee_name: 'Hesti Purwanti', nip: 'EMP018', department: 'Finance', date: '2026-05-27', check_in: '', check_out: '', status: 'Izin', late_minutes: 0, overtime_minutes: 0 },
  { id: 19, employee_name: 'Irfan Maulana', nip: 'EMP019', department: 'Marketing', date: '2026-05-27', check_in: '07:50', check_out: '17:05', status: 'Hadir', late_minutes: 0, overtime_minutes: 30 },
  { id: 20, employee_name: 'Joko Susilo', nip: 'EMP020', department: 'Operations', date: '2026-05-27', check_in: '', check_out: '', status: 'Alfa', late_minutes: 0, overtime_minutes: 0 },
])

function statusBadgeVariant(status) {
  const map = { Hadir: 'success', Terlambat: 'warning', Izin: 'info', Alfa: 'danger', Sakit: 'primary' }
  return map[status] || 'neutral'
}

function statusLabel(status) {
  const map = { Hadir: 'Hadir', Terlambat: 'Terlambat', Izin: 'Izin', Alfa: 'Alfa', Sakit: 'Sakit' }
  return map[status] || status
}

function handlePageChange(page) {
  pagination.currentPage = page
}

function goToImport() {
  router.visit('/attendance/import')
}
</script>
