<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Grade Gaji</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola data grade dan rentang gaji</p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Grade Gaji
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
            placeholder="Cari grade gaji..."
            class="w-full pl-10 pr-3 py-2 text-sm rounded-md border bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
          />
        </div>
      </div>
      <DataTable :headers="headers" :items="filteredGrades">
        <template #item.min_salary="{ value }">
          {{ formatRupiah(value) }}
        </template>
        <template #item.max_salary="{ value }">
          {{ formatRupiah(value) }}
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

    <BaseModal :show="modalOpen" :title="isEditing ? 'Edit Grade Gaji' : 'Tambah Grade Gaji'" @close="closeModal">
      <form @submit.prevent="handleSave" class="space-y-4">
        <TextInput v-model="form.name" label="Nama Grade" placeholder="Contoh: Grade A" required />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model.number="form.min_salary" label="Gaji Minimum" type="number" placeholder="5000000" required />
          <TextInput v-model.number="form.max_salary" label="Gaji Maksimum" type="number" placeholder="10000000" required />
        </div>
        <TextInput v-model="form.description" label="Deskripsi" placeholder="Masukkan deskripsi grade" required />
      </form>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleSave">{{ isEditing ? 'Simpan' : 'Tambah' }}</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="deleteDialogOpen"
      title="Hapus Grade Gaji"
      :message="'Apakah Anda yakin ingin menghapus grade \'' + selectedItem?.name + '\'? Tindakan ini tidak dapat dibatalkan.'"
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
import { IconPlus, IconPencil, IconTrash, IconSearch } from '../../../../Components/Icons/index.js'

const searchQuery = ref('')
const modalOpen = ref(false)
const deleteDialogOpen = ref(false)
const isEditing = ref(false)
const selectedItem = ref(null)

const salaryGrades = ref([
  { id: 1, name: 'Grade A', min_salary: 5000000, max_salary: 10000000, description: 'Level Eksekutif', employee_count: 15 },
  { id: 2, name: 'Grade B', min_salary: 8000000, max_salary: 15000000, description: 'Level Manajemen Senior', employee_count: 12 },
  { id: 3, name: 'Grade C', min_salary: 12000000, max_salary: 25000000, description: 'Level Direktur', employee_count: 5 },
  { id: 4, name: 'Grade D1', min_salary: 3500000, max_salary: 6000000, description: 'Level Staff Junior', employee_count: 28 },
  { id: 5, name: 'Grade D2', min_salary: 4500000, max_salary: 7500000, description: 'Level Staff Senior', employee_count: 22 },
  { id: 6, name: 'Grade E1', min_salary: 3000000, max_salary: 4500000, description: 'Level Entry / Magang', employee_count: 10 },
  { id: 7, name: 'Grade E2', min_salary: 3800000, max_salary: 5500000, description: 'Level Administratif', employee_count: 18 },
  { id: 8, name: 'Grade F', min_salary: 15000000, max_salary: 35000000, description: 'Level C-Level / VP', employee_count: 4 },
])

const headers = [
  { key: 'name', label: 'Grade' },
  { key: 'min_salary', label: 'Gaji Min' },
  { key: 'max_salary', label: 'Gaji Max' },
  { key: 'description', label: 'Deskripsi' },
  { key: 'employee_count', label: 'Jumlah Karyawan', align: 'center' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'center' },
]

const filteredGrades = computed(() => {
  if (!searchQuery.value) return salaryGrades.value
  const q = searchQuery.value.toLowerCase()
  return salaryGrades.value.filter((g) =>
    g.name.toLowerCase().includes(q) ||
    g.description.toLowerCase().includes(q)
  )
})

function formatRupiah(value) {
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(value)
}

const emptyForm = () => ({
  name: '',
  min_salary: '',
  max_salary: '',
  description: '',
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
    min_salary: item.min_salary,
    max_salary: item.max_salary,
    description: item.description,
  }
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  form.value = emptyForm()
  selectedItem.value = null
}

function handleSave() {
  if (!form.value.name || !form.value.min_salary || !form.value.max_salary || !form.value.description) return

  if (isEditing.value && selectedItem.value) {
    const idx = salaryGrades.value.findIndex((g) => g.id === selectedItem.value.id)
    if (idx !== -1) {
      salaryGrades.value[idx] = {
        ...salaryGrades.value[idx],
        name: form.value.name,
        min_salary: Number(form.value.min_salary),
        max_salary: Number(form.value.max_salary),
        description: form.value.description,
      }
    }
  } else {
    const newId = Math.max(...salaryGrades.value.map((g) => g.id), 0) + 1
    salaryGrades.value.push({
      id: newId,
      name: form.value.name,
      min_salary: Number(form.value.min_salary),
      max_salary: Number(form.value.max_salary),
      description: form.value.description,
      employee_count: 0,
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
    salaryGrades.value = salaryGrades.value.filter((g) => g.id !== selectedItem.value.id)
  }
  deleteDialogOpen.value = false
  selectedItem.value = null
}
</script>
