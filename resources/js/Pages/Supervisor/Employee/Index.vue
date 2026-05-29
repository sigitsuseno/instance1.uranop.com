<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { useDate } from '../../../composables/useDate'
import { useApi } from '../../../composables/useApi'
import {
  IconSearch,
  IconEye,
  IconDownload,
  IconUsers,
  IconRefresh,
} from '../../../Components/Icons/index.js'

const { format: formatDate } = useDate()
const { get } = useApi()

const loading = ref(false)
const employeeData = ref([])
const stats = ref({ total: 0, permanent: 0, contract: 0 })
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const headers = [
  { key: 'employee_code', label: 'NIP/Kode' },
  { key: 'name', label: 'Nama' },
  { key: 'department', label: 'Departemen' },
  { key: 'position', label: 'Jabatan' },
  { key: 'employment_status', label: 'Status' },
  { key: 'phone', label: 'Telepon' },
]

const currentPage = ref(1)

const showDetail = ref(false)
const selectedEmployee = ref(null)

async function fetchEmployees() {
  loading.value = true
  try {
    const res = await get(`/api/v1/employees?page=${currentPage.value}`)
    employeeData.value = res.data
    pagination.value = res.meta || {}
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const res = await get('/api/v1/employees/stats')
    stats.value = res.data || { total: 0, permanent: 0, contract: 0 }
  } catch (e) {
    console.error(e)
  }
}

function openDetail(row) {
  selectedEmployee.value = row
  showDetail.value = true
}

function statusBadge(status) {
  return status === 'permanent' ? 'success' : status === 'contract' ? 'warning' : 'info'
}

function handlePageChange(page) {
  currentPage.value = page
  fetchEmployees()
}

onMounted(() => {
  fetchEmployees()
  fetchStats()
})
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
            <p class="text-lg font-bold text-(--text-main)">{{ stats.total || 0 }}</p>
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
            <p class="text-lg font-bold text-(--text-main)">{{ stats.permanent || 0 }}</p>
          </div>
        </div>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-5">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 rounded-md bg-(--warning)/10 flex items-center justify-center">
            <IconUsers class="w-5 h-5 text-(--warning)" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Karyawan Kontrak</p>
            <p class="text-lg font-bold text-(--text-main)">{{ stats.contract || 0 }}</p>
          </div>
        </div>
      </div>
    </div>

    <BaseCard>
      <div v-if="loading" class="flex items-center justify-center py-16 text-(--text-muted)">
        <IconRefresh class="w-5 h-5 animate-spin mr-2" />
        <span class="text-sm">Memuat data...</span>
      </div>

      <template v-else>
        <DataTable :headers="headers" :items="employeeData" :show-search="false" @row-click="openDetail">
          <template #item.employee_code="{ item }">
            {{ item.employee_code || item.nik || '-' }}
          </template>
          <template #item.name="{ value }">
            <span class="font-medium text-(--text-main)">{{ value }}</span>
          </template>
          <template #item.department="{ item }">
            {{ item.department?.name || '-' }}
          </template>
          <template #item.position="{ item }">
            {{ item.position?.name || '-' }}
          </template>
          <template #item.employment_status="{ value }">
            <Badge :variant="statusBadge(value)">
              {{ value === 'permanent' ? 'Tetap' : value === 'contract' ? 'Kontrak' : value === 'probation' ? 'Probation' : value }}
            </Badge>
          </template>
          <template #item.phone="{ value }">
            {{ value || '-' }}
          </template>
          <template #empty>
            <div class="text-center py-12 text-(--text-muted)">
              <IconUsers class="w-10 h-10 mx-auto mb-3 opacity-30" />
              <p class="text-sm">Tidak ada karyawan ditemukan.</p>
            </div>
          </template>
        </DataTable>
        <Pagination
          :current-page="pagination.current_page ?? 1"
          :total-pages="pagination.last_page ?? 1"
          :total="pagination.total ?? 0"
          :per-page="pagination.per_page ?? 15"
          @page-change="handlePageChange"
        />
      </template>
    </BaseCard>

    <BaseModal :show="showDetail" title="Detail Karyawan" size="lg" @close="showDetail = false">
      <template v-if="selectedEmployee">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">NIP/Kode</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.employee_code || selectedEmployee.nik || '-' }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Nama Lengkap</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.name }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Departemen</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.department?.name || '-' }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Jabatan</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.position?.name || '-' }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Status Kepegawaian</p>
            <Badge :variant="statusBadge(selectedEmployee.employment_status)">
              {{ selectedEmployee.employment_status === 'permanent' ? 'Tetap' : selectedEmployee.employment_status === 'contract' ? 'Kontrak' : selectedEmployee.employment_status === 'probation' ? 'Probation' : selectedEmployee.employment_status }}
            </Badge>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Tanggal Bergabung</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.join_date ? formatDate(selectedEmployee.join_date) : '-' }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Telepon</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.phone || '-' }}</p>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <p class="text-xs text-(--text-muted) mb-1">Email</p>
            <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.email || '-' }}</p>
          </div>
        </div>
      </template>
      <template #footer>
        <BaseButton variant="secondary" size="sm" @click="showDetail = false">Tutup</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
