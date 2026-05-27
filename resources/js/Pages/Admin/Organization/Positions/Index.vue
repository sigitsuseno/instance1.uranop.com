<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Jabatan</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola data jabatan dalam organisasi</p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Jabatan
      </BaseButton>
    </div>

    <BaseCard :padding="'p-0'">
      <div class="p-6 pb-0">
        <div class="relative w-64">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
            <IconSearch class="w-4 h-4" />
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari jabatan..."
            class="w-full pl-10 pr-3 py-2 text-sm rounded-md border bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
          />
        </div>
      </div>
      <DataTable :headers="headers" :items="filteredPositions">
        <template #item.status="{ value }">
          <Badge :variant="value === 'active' ? 'success' : 'warning'">
            {{ value === 'active' ? 'Aktif' : 'Nonaktif' }}
          </Badge>
        </template>
        <template #item.aksi="{ item }">
          <div class="flex items-center gap-1">
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
              @click="openEditModal(item)"
            >
              <IconPencil class="w-4 h-4" />
            </button>
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors"
              @click="openDeleteConfirm(item)"
            >
              <IconTrash class="w-4 h-4" />
            </button>
          </div>
        </template>
      </DataTable>
    </BaseCard>

    <BaseModal :show="modalOpen" :title="isEditing ? 'Edit Jabatan' : 'Tambah Jabatan'" @close="closeModal">
      <form @submit.prevent="handleSave" class="space-y-4">
        <TextInput v-model="form.name" label="Nama Jabatan" placeholder="Masukkan nama jabatan" required />
        <SelectInput
          v-model="form.department"
          label="Departemen"
          :options="departmentOptions"
          placeholder="Pilih departemen"
          required
        />
        <SelectInput
          v-model="form.level"
          label="Level"
          :options="levelOptions"
          placeholder="Pilih level"
          required
        />
      </form>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleSave">{{ isEditing ? 'Simpan' : 'Tambah' }}</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="deleteDialogOpen"
      title="Hapus Jabatan"
      :message="'Apakah Anda yakin ingin menghapus jabatan \'' + selectedItem?.name + '\'? Tindakan ini tidak dapat dibatalkan.'"
      variant="danger"
      confirm-text="Hapus"
      @confirm="handleDelete"
      @cancel="deleteDialogOpen = false"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import Badge from '../../../../Components/Badge.vue'
import { IconPlus, IconPencil, IconTrash, IconSearch } from '../../../../Components/Icons/index.js'

const searchQuery = ref('')
const modalOpen = ref(false)
const deleteDialogOpen = ref(false)
const isEditing = ref(false)
const selectedItem = ref(null)

const departmentOptions = [
  { value: 'IT', label: 'Teknologi Informasi' },
  { value: 'HRD', label: 'Sumber Daya Manusia' },
  { value: 'FIN', label: 'Keuangan' },
  { value: 'MKT', label: 'Pemasaran' },
  { value: 'OPS', label: 'Operasional' },
  { value: 'LG', label: 'Hukum' },
  { value: 'RND', label: 'Riset & Pengembangan' },
  { value: 'CS', label: 'Layanan Pelanggan' },
  { value: 'LOG', label: 'Logistik' },
  { value: 'COMP', label: 'Kepatuhan' },
]

const levelOptions = [
  { value: 'Staff', label: 'Staff' },
  { value: 'Supervisor', label: 'Supervisor' },
  { value: 'Manager', label: 'Manager' },
  { value: 'Senior Manager', label: 'Senior Manager' },
  { value: 'Director', label: 'Director' },
]

const positions = ref([
  { id: 1, name: 'Senior Developer', department: 'IT', level: 'Manager', employee_count: 5, status: 'active' },
  { id: 2, name: 'Junior Developer', department: 'IT', level: 'Staff', employee_count: 12, status: 'active' },
  { id: 3, name: 'HR Manager', department: 'HRD', level: 'Manager', employee_count: 3, status: 'active' },
  { id: 4, name: 'Finance Analyst', department: 'FIN', level: 'Supervisor', employee_count: 6, status: 'active' },
  { id: 5, name: 'Marketing Lead', department: 'MKT', level: 'Senior Manager', employee_count: 4, status: 'active' },
  { id: 6, name: 'Operations Staff', department: 'OPS', level: 'Staff', employee_count: 20, status: 'active' },
  { id: 7, name: 'Legal Counsel', department: 'LG', level: 'Supervisor', employee_count: 3, status: 'active' },
  { id: 8, name: 'R&D Specialist', department: 'RND', level: 'Staff', employee_count: 8, status: 'active' },
  { id: 9, name: 'Customer Service Rep', department: 'CS', level: 'Staff', employee_count: 18, status: 'inactive' },
  { id: 10, name: 'Logistics Coordinator', department: 'LOG', level: 'Supervisor', employee_count: 7, status: 'active' },
])

const headers = [
  { key: 'name', label: 'Nama Jabatan' },
  { key: 'department', label: 'Departemen' },
  { key: 'level', label: 'Level' },
  { key: 'employee_count', label: 'Jumlah Karyawan', align: 'center' },
  { key: 'status', label: 'Status' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'center' },
]

const filteredPositions = computed(() => {
  if (!searchQuery.value) return positions.value
  const q = searchQuery.value.toLowerCase()
  return positions.value.filter((p) =>
    p.name.toLowerCase().includes(q) ||
    p.department.toLowerCase().includes(q) ||
    p.level.toLowerCase().includes(q)
  )
})

const emptyForm = () => ({
  name: '',
  department: '',
  level: '',
})

const form = ref(emptyForm())

function openCreateModal() {
  isEditing.value = false
  selectedItem.value = null
  form.value = emptyForm()
  modalOpen.value = true
}

function openEditModal(item) {
  isEditing.value = true
  selectedItem.value = item
  form.value = {
    name: item.name,
    department: item.department,
    level: item.level,
  }
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  form.value = emptyForm()
  selectedItem.value = null
}

function handleSave() {
  if (!form.value.name || !form.value.department || !form.value.level) return

  if (isEditing.value && selectedItem.value) {
    const idx = positions.value.findIndex((p) => p.id === selectedItem.value.id)
    if (idx !== -1) {
      positions.value[idx] = {
        ...positions.value[idx],
        name: form.value.name,
        department: form.value.department,
        level: form.value.level,
      }
    }
  } else {
    const newId = Math.max(...positions.value.map((p) => p.id), 0) + 1
    positions.value.push({
      id: newId,
      name: form.value.name,
      department: form.value.department,
      level: form.value.level,
      employee_count: 0,
      status: 'active',
    })
  }

  closeModal()
}

function openDeleteConfirm(item) {
  selectedItem.value = item
  deleteDialogOpen.value = true
}

function handleDelete() {
  if (selectedItem.value) {
    positions.value = positions.value.filter((p) => p.id !== selectedItem.value.id)
  }
  deleteDialogOpen.value = false
  selectedItem.value = null
}
</script>
