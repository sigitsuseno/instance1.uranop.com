<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Departemen</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola data departemen organisasi</p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Departemen
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
            placeholder="Cari departemen..."
            class="w-full pl-10 pr-3 py-2 text-sm rounded-md border bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
          />
        </div>
      </div>
      <DataTable :headers="headers" :items="filteredDepartments">
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

    <BaseModal :show="modalOpen" :title="isEditing ? 'Edit Departemen' : 'Tambah Departemen'" @close="closeModal">
      <form @submit.prevent="handleSave" class="space-y-4">
        <TextInput v-model="form.name" label="Nama Departemen" placeholder="Masukkan nama departemen" required />
        <TextInput v-model="form.code" label="Kode Departemen" placeholder="Contoh: IT" required />
        <TextInput v-model="form.head" label="Kepala Departemen" placeholder="Nama kepala departemen" required />
      </form>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleSave">{{ isEditing ? 'Simpan' : 'Tambah' }}</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="deleteDialogOpen"
      title="Hapus Departemen"
      :message="'Apakah Anda yakin ingin menghapus departemen \'' + selectedItem?.name + '\'? Tindakan ini tidak dapat dibatalkan.'"
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
import DataTable from '../../../../Components/Table/DataTable.vue'
import Badge from '../../../../Components/Badge.vue'
import { IconPlus, IconPencil, IconTrash, IconSearch } from '../../../../Components/Icons/index.js'

const searchQuery = ref('')
const modalOpen = ref(false)
const deleteDialogOpen = ref(false)
const isEditing = ref(false)
const selectedItem = ref(null)

const departments = ref([
  { id: 1, name: 'Departemen Teknologi Informasi', code: 'IT', head: 'Budi Santoso', employee_count: 25, status: 'active' },
  { id: 2, name: 'Departemen Sumber Daya Manusia', code: 'HRD', head: 'Siti Rahayu', employee_count: 12, status: 'active' },
  { id: 3, name: 'Departemen Keuangan', code: 'FIN', head: 'Rudi Hermawan', employee_count: 18, status: 'active' },
  { id: 4, name: 'Departemen Pemasaran', code: 'MKT', head: 'Dewi Lestari', employee_count: 15, status: 'active' },
  { id: 5, name: 'Departemen Operasional', code: 'OPS', head: 'Agus Wijaya', employee_count: 30, status: 'active' },
  { id: 6, name: 'Departemen Hukum', code: 'LG', head: 'Hendra Gunawan', employee_count: 8, status: 'active' },
  { id: 7, name: 'Departemen Riset & Pengembangan', code: 'RND', head: 'Ratna Dewi', employee_count: 14, status: 'active' },
  { id: 8, name: 'Departemen Layanan Pelanggan', code: 'CS', head: 'Fitriani', employee_count: 22, status: 'active' },
  { id: 9, name: 'Departemen Logistik', code: 'LOG', head: 'Dimas Ardian', employee_count: 16, status: 'inactive' },
  { id: 10, name: 'Departemen Kepatuhan', code: 'COMP', head: 'Andi Pratama', employee_count: 6, status: 'active' },
])

const headers = [
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Nama' },
  { key: 'head', label: 'Kepala' },
  { key: 'employee_count', label: 'Jumlah Karyawan', align: 'center' },
  { key: 'status', label: 'Status' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'center' },
]

const filteredDepartments = computed(() => {
  if (!searchQuery.value) return departments.value
  const q = searchQuery.value.toLowerCase()
  return departments.value.filter((d) =>
    d.name.toLowerCase().includes(q) ||
    d.code.toLowerCase().includes(q) ||
    d.head.toLowerCase().includes(q)
  )
})

const emptyForm = () => ({
  name: '',
  code: '',
  head: '',
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
    code: item.code,
    head: item.head,
  }
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  form.value = emptyForm()
  selectedItem.value = null
}

function handleSave() {
  if (!form.value.name || !form.value.code || !form.value.head) return

  if (isEditing.value && selectedItem.value) {
    const idx = departments.value.findIndex((d) => d.id === selectedItem.value.id)
    if (idx !== -1) {
      departments.value[idx] = {
        ...departments.value[idx],
        name: form.value.name,
        code: form.value.code,
        head: form.value.head,
      }
    }
  } else {
    const newId = Math.max(...departments.value.map((d) => d.id), 0) + 1
    departments.value.push({
      id: newId,
      name: form.value.name,
      code: form.value.code,
      head: form.value.head,
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
    departments.value = departments.value.filter((d) => d.id !== selectedItem.value.id)
  }
  deleteDialogOpen.value = false
  selectedItem.value = null
}
</script>
