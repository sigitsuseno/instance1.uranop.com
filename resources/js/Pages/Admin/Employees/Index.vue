<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../../../Stores/notification'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseCard from '../../../Components/BaseCard.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import Badge from '../../../Components/Badge.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import {
  IconPlus,
  IconPencil,
  IconTrash,
  IconEye,
  IconDownload,
  IconUpload,
  IconUsers,
} from '../../../Components/Icons/index.js'

const router = useRouter()
const notification = useNotificationStore()

const currentPage = ref(1)
const perPage = 10
const searchQuery = ref('')
const filterDepartment = ref('')
const filterStatus = ref('')
const filterEmploymentStatus = ref('')
const showDeleteDialog = ref(false)
const selectedEmployee = ref(null)

const employees = ref(
  Array.from({ length: 25 }, (_, i) => {
    const id = i + 1
    const departments = ['IT', 'HR', 'Finance', 'Marketing', 'Operations']
    const positionsByDept = {
      IT: ['Junior Developer', 'Senior Developer', 'Tech Lead', 'DevOps Engineer', 'QA Engineer'],
      HR: ['HR Staff', 'HR Supervisor', 'Recruitment Officer', 'Training Coordinator', 'HR Manager'],
      Finance: ['Finance Staff', 'Accountant', 'Finance Analyst', 'Tax Officer', 'Finance Manager'],
      Marketing: ['Marketing Staff', 'Content Writer', 'Graphic Designer', 'SEO Specialist', 'Marketing Manager'],
      Operations: ['Operations Staff', 'Admin Officer', 'Logistics Coordinator', 'Procurement Officer', 'Ops Manager'],
    }
    const statuses = ['Tetap', 'Kontrak', 'Probation']
    const names = [
      'Budi Santoso', 'Siti Aminah', 'Ahmad Fauzi', 'Dewi Lestari', 'Eko Prasetyo',
      'Fitri Handayani', 'Gunawan Wibowo', 'Hana Safira', 'Irfan Maulana', 'Joko Susilo',
      'Kartika Sari', 'Lukman Hakim', 'Mega Putri', 'Nanda Pratama', 'Olivia Rahma',
      'Putra Wijaya', 'Qori Andini', 'Rizky Fadilah', 'Sari Dewanti', 'Teguh Santosa',
      'Umar Faruq', 'Vina Melani', 'Wahyu Nugroho', 'Yuni Astuti', 'Zaki Mubarak',
    ]
    const emails = names.map((n) => n.toLowerCase().replace(/\s/g, '.') + '@example.com')
    const dept = departments[i % 5]
    const positions = positionsByDept[dept]
    const pos = positions[i % 5]
    const status = statuses[i % 3]
    const joinDate = new Date(2020, 0, 1 + i * 15)
    const isActive = i < 3 || (i > 4 && i < 20)

    return {
      id,
      nip: `EMP${String(id).padStart(3, '0')}`,
      name: names[i],
      email: emails[i],
      department: dept,
      position: pos,
      employment_status: status,
      join_date: joinDate.toISOString().split('T')[0],
      is_active: isActive,
    }
  })
)

const filteredEmployees = computed(() => {
  let result = [...employees.value]

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(
      (e) =>
        e.name.toLowerCase().includes(q) ||
        e.nip.toLowerCase().includes(q) ||
        e.email.toLowerCase().includes(q)
    )
  }

  if (filterDepartment.value) {
    result = result.filter((e) => e.department === filterDepartment.value)
  }

  if (filterStatus.value === 'active') {
    result = result.filter((e) => e.is_active)
  } else if (filterStatus.value === 'inactive') {
    result = result.filter((e) => !e.is_active)
  }

  if (filterEmploymentStatus.value) {
    result = result.filter((e) => e.employment_status === filterEmploymentStatus.value)
  }

  return result
})

const totalPages = computed(() => Math.ceil(filteredEmployees.value.length / perPage) || 1)

const paginatedEmployees = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredEmployees.value.slice(start, start + perPage)
})

const totalKaryawan = computed(() => employees.value.length)
const totalAktif = computed(() => employees.value.filter((e) => e.is_active).length)
const totalNonaktif = computed(() => employees.value.filter((e) => !e.is_active).length)
const totalKontrak = computed(() => employees.value.filter((e) => e.employment_status === 'Kontrak').length)

const tableHeaders = [
  { key: 'nip', label: 'NIP' },
  { key: 'name', label: 'Nama' },
  { key: 'email', label: 'Email' },
  { key: 'department', label: 'Departemen' },
  { key: 'position', label: 'Jabatan' },
  { key: 'is_active', label: 'Status' },
  { key: 'employment_status', label: 'Status Karyawan' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '140px' },
]

const departmentOptions = [
  { value: 'IT', label: 'IT' },
  { value: 'HR', label: 'HR' },
  { value: 'Finance', label: 'Finance' },
  { value: 'Marketing', label: 'Marketing' },
  { value: 'Operations', label: 'Operations' },
]

const statusOptions = [
  { value: 'active', label: 'Aktif' },
  { value: 'inactive', label: 'Nonaktif' },
]

const employmentStatusOptions = [
  { value: 'Tetap', label: 'Tetap' },
  { value: 'Kontrak', label: 'Kontrak' },
  { value: 'Probation', label: 'Probation' },
]

function viewEmployee(id) {
  router.push(`/employees/${id}`)
}

function editEmployee(id) {
  router.push(`/employees/${id}/edit`)
}

function confirmDelete(employee) {
  selectedEmployee.value = employee
  showDeleteDialog.value = true
}

function handleDelete() {
  if (selectedEmployee.value) {
    employees.value = employees.value.filter((e) => e.id !== selectedEmployee.value.id)
    notification.success('Karyawan berhasil dihapus')
  }
  showDeleteDialog.value = false
  selectedEmployee.value = null
}

function cancelDelete() {
  showDeleteDialog.value = false
  selectedEmployee.value = null
}

function handlePageChange(page) {
  currentPage.value = page
}

function clearFilters() {
  searchQuery.value = ''
  filterDepartment.value = ''
  filterStatus.value = ''
  filterEmploymentStatus.value = ''
  currentPage.value = 1
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-(--text-main)">Data Karyawan</h1>
      <div class="flex items-center gap-3">
        <BaseButton variant="secondary">
          <template #icon-left>
            <IconUpload class="w-4 h-4" />
          </template>
          Import Excel
        </BaseButton>
        <BaseButton variant="secondary">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Export Excel
        </BaseButton>
        <BaseButton @click="router.push('/employees/create')">
          <template #icon-left>
            <IconPlus class="w-4 h-4" />
          </template>
          Tambah Karyawan
        </BaseButton>
      </div>
    </div>

    <div class="grid grid-cols-4 gap-4">
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-md bg-(--primary)/10 text-(--primary)">
            <IconUsers class="w-5 h-5" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Total Karyawan</p>
            <p class="text-xl font-bold text-(--text-main)">{{ totalKaryawan }}</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-md bg-(--success)/10 text-(--success)">
            <IconUsers class="w-5 h-5" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Aktif</p>
            <p class="text-xl font-bold text-(--text-main)">{{ totalAktif }}</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-md bg-(--danger)/10 text-(--danger)">
            <IconUsers class="w-5 h-5" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Nonaktif</p>
            <p class="text-xl font-bold text-(--text-main)">{{ totalNonaktif }}</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-md bg-(--warning)/10 text-(--warning)">
            <IconUsers class="w-5 h-5" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Kontrak</p>
            <p class="text-xl font-bold text-(--text-main)">{{ totalKontrak }}</p>
          </div>
        </div>
      </BaseCard>
    </div>

    <BaseCard>
      <div class="flex items-center gap-4 mb-4 flex-wrap">
        <div class="relative w-64">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari NIP, Nama, Email..."
            class="w-full pl-10 pr-3 py-2 text-sm rounded-md border bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
            @input="currentPage = 1"
          />
        </div>
        <SelectInput
          v-model="filterDepartment"
          :options="departmentOptions"
          placeholder="Semua Departemen"
          class="w-48"
          @update:model-value="currentPage = 1"
        />
        <SelectInput
          v-model="filterStatus"
          :options="statusOptions"
          placeholder="Semua Status"
          class="w-40"
          @update:model-value="currentPage = 1"
        />
        <SelectInput
          v-model="filterEmploymentStatus"
          :options="employmentStatusOptions"
          placeholder="Status Karyawan"
          class="w-44"
          @update:model-value="currentPage = 1"
        />
        <BaseButton variant="ghost" @click="clearFilters">
          Reset Filter
        </BaseButton>
      </div>

      <DataTable
        :headers="tableHeaders"
        :items="paginatedEmployees"
      >
        <template #item.is_active="{ value }">
          <Badge :variant="value ? 'success' : 'danger'">
            {{ value ? 'Aktif' : 'Nonaktif' }}
          </Badge>
        </template>
        <template #item.employment_status="{ value }">
          <Badge
            :variant="value === 'Tetap' ? 'primary' : value === 'Kontrak' ? 'warning' : 'info'"
          >
            {{ value }}
          </Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
              title="Lihat"
              @click="viewEmployee(item.id)"
            >
              <IconEye class="w-4 h-4" />
            </button>
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--warning) hover:bg-(--warning)/10 transition-colors"
              title="Edit"
              @click="editEmployee(item.id)"
            >
              <IconPencil class="w-4 h-4" />
            </button>
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors"
              title="Hapus"
              @click="confirmDelete(item)"
            >
              <IconTrash class="w-4 h-4" />
            </button>
          </div>
        </template>
      </DataTable>

      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total="filteredEmployees.length"
        :per-page="perPage"
        @page-change="handlePageChange"
      />
    </BaseCard>

    <ConfirmDialog
      :show="showDeleteDialog"
      title="Hapus Karyawan"
      :message="'Apakah Anda yakin ingin menghapus karyawan ' + selectedEmployee?.name + ' (' + selectedEmployee?.nip + ')?'"
      confirm-text="Ya, Hapus"
      cancel-text="Batal"
      variant="danger"
      @confirm="handleDelete"
      @cancel="cancelDelete"
    />
  </div>
</template>
