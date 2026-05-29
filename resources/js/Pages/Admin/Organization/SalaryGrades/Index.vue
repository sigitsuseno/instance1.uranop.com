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
import { ref, computed, onMounted } from 'vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import TextInput from '../../../../Components/TextInput.vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import { IconPlus, IconPencil, IconTrash, IconSearch } from '../../../../Components/Icons/index.js'
import { useApi } from '../../../../composables/useApi'
import { useNotification } from '../../../../composables/useNotification'

const api = useApi()
const notification = useNotification()

const searchQuery = ref('')
const modalOpen = ref(false)
const deleteDialogOpen = ref(false)
const isEditing = ref(false)
const selectedItem = ref(null)

const salaryGrades = ref([])

async function fetchGrades() {
  try {
    const res = await api.get('/api/v1/settings/salary-grades')
    salaryGrades.value = res.data
  } catch (e) {
    notification.error('Gagal memuat grade gaji')
  }
}

onMounted(() => {
  fetchGrades()
})


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

async function handleSave() {
  if (!form.value.name || !form.value.min_salary || !form.value.max_salary || !form.value.description) return

  try {
    if (isEditing.value && selectedItem.value) {
      const res = await api.put(`/api/v1/settings/salary-grades/${selectedItem.value.id}`, form.value)
      const idx = salaryGrades.value.findIndex((g) => g.id === selectedItem.value.id)
      if (idx !== -1) {
        salaryGrades.value[idx] = res.data
      }
      notification.success('Berhasil menyimpan perubahan')
    } else {
      const res = await api.post('/api/v1/settings/salary-grades', form.value)
      salaryGrades.value.push(res.data)
      notification.success('Berhasil menambahkan grade gaji')
    }
    closeModal()
  } catch (error) {
    notification.error('Gagal menyimpan grade gaji')
  }
}

function openDeleteConfirm(item) {
  selectedItem.value = item
  deleteDialogOpen.value = true
}

async function handleDelete() {
  if (selectedItem.value) {
    try {
      await api.destroy(`/api/v1/settings/salary-grades/${selectedItem.value.id}`)
      salaryGrades.value = salaryGrades.value.filter((g) => g.id !== selectedItem.value.id)
      notification.success('Berhasil menghapus grade gaji')
    } catch (e) {
      notification.error('Gagal menghapus grade gaji')
    }
  }
  deleteDialogOpen.value = false
  selectedItem.value = null
}
</script>
