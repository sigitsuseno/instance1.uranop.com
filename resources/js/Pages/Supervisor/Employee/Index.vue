<script setup>
import { ref, computed } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { useDate } from '../../../composables/useDate'
import {
  IconSearch,
  IconEye,
  IconDownload,
  IconUsers,
} from '../../../Components/Icons/index.js'

const { format: formatDate } = useDate()

const employeeData = ref([
  { id: 1, nip: '2024001', name: 'Andi Prasetyo', department: 'Produksi', position: 'Operator Senior', employmentStatus: 'Tetap', joinDate: '2022-03-15', phone: '081234567801' },
  { id: 2, nip: '2024002', name: 'Budi Santoso', department: 'Produksi', position: 'Operator', employmentStatus: 'Tetap', joinDate: '2022-06-20', phone: '081234567802' },
  { id: 3, nip: '2024003', name: 'Citra Dewi', department: 'QC', position: 'QC Inspector', employmentStatus: 'Kontrak', joinDate: '2023-01-10', phone: '081234567803' },
  { id: 4, nip: '2024004', name: 'Dian Permata', department: 'Gudang', position: 'Staff Gudang', employmentStatus: 'Tetap', joinDate: '2021-09-05', phone: '081234567804' },
  { id: 5, nip: '2024005', name: 'Eko Wahyudi', department: 'Produksi', position: 'Operator', employmentStatus: 'Tetap', joinDate: '2022-11-12', phone: '081234567805' },
  { id: 6, nip: '2024006', name: 'Fitriani', department: 'QC', position: 'QC Supervisor', employmentStatus: 'Tetap', joinDate: '2020-05-18', phone: '081234567806' },
  { id: 7, nip: '2024007', name: 'Gunawan', department: 'Gudang', position: 'Kepala Gudang', employmentStatus: 'Tetap', joinDate: '2020-02-28', phone: '081234567807' },
  { id: 8, nip: '2024008', name: 'Hendra Gunawan', department: 'Produksi', position: 'Foreman', employmentStatus: 'Tetap', joinDate: '2021-07-22', phone: '081234567808' },
])

const headers = [
  { key: 'nip', label: 'NIP' },
  { key: 'name', label: 'Nama' },
  { key: 'department', label: 'Departemen' },
  { key: 'position', label: 'Jabatan' },
  { key: 'employmentStatus', label: 'Status' },
  { key: 'phone', label: 'Telepon' },
]

const currentPage = ref(1)
const perPage = ref(10)
const totalPages = computed(() => Math.ceil(employeeData.value.length / perPage.value))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return employeeData.value.slice(start, start + perPage.value)
})

const showDetail = ref(false)
const selectedEmployee = ref(null)

function openDetail(row) {
  selectedEmployee.value = row
  showDetail.value = true
}

function statusBadge(status) {
  return status === 'Tetap' ? 'success' : 'info'
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-xl font-semibold text-(--text-main)">Data Karyawan</h3>
        <p class="text-sm text-(--text-muted) mt-1">Daftar karyawan dalam tim Anda</p>
      </div>
      <div class="flex items-center gap-3">
        <BaseButton variant="secondary" size="sm">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Export
        </BaseButton>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-5">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 rounded-md bg-(--primary)/10 flex items-center justify-center">
            <IconUsers class="w-5 h-5 text-(--primary)" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Total Karyawan</p>
            <p class="text-lg font-bold text-(--text-main)">{{ employeeData.length }}</p>
          </div>
        </div>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-5">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 rounded-md bg-(--success)/10 flex items-center justify-center">
            <IconUsers class="w-5 h-5 text-(--success)" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Karyawan Tetap</p>
            <p class="text-lg font-bold text-(--text-main)">{{ employeeData.filter(e => e.employmentStatus === 'Tetap').length }}</p>
          </div>
        </div>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-5">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 rounded-md bg-(--primary)/10 flex items-center justify-center">
            <IconUsers class="w-5 h-5 text-(--primary)" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Karyawan Kontrak</p>
            <p class="text-lg font-bold text-(--text-main)">{{ employeeData.filter(e => e.employmentStatus === 'Kontrak').length }}</p>
          </div>
        </div>
      </div>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="paginated" :show-search="true" @row-click="openDetail">
        <template #item.name="{ value }">
          <span class="font-medium text-(--text-main)">{{ value }}</span>
        </template>
        <template #item.employmentStatus="{ value }">
          <Badge :variant="statusBadge(value)">{{ value }}</Badge>
        </template>
      </DataTable>
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total="employeeData.length"
        :per-page="perPage"
        @page-change="currentPage = $event"
      />
    </BaseCard>

    <BaseModal :show="showDetail" title="Detail Karyawan" size="lg" @close="showDetail = false">
      <template v-if="selectedEmployee">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">NIP</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.nip }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Nama Lengkap</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.name }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Departemen</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.department }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Jabatan</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.position }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Status Kepegawaian</p>
            <Badge :variant="statusBadge(selectedEmployee.employmentStatus)">{{ selectedEmployee.employmentStatus }}</Badge>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Tanggal Bergabung</p>
            <p class="text-sm font-medium text-(--text-main)">{{ formatDate(selectedEmployee.joinDate) }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Telepon</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.phone }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Email</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.name.toLowerCase().replace(' ', '.') }}@uranop.com</p>
          </div>
        </div>
      </template>
      <template #footer>
        <BaseButton variant="secondary" size="sm" @click="showDetail = false">Tutup</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
