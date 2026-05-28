<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-(--text-main)">Daftar Roles</h2>
        <p class="text-sm text-(--text-muted)">Kelola group level akses (misal: superadmin, hrmanager)</p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Role
      </BaseButton>
    </div>

    <div class="flex gap-4">
      <div class="relative w-64">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
          <IconSearch class="w-4 h-4" />
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari role..."
          @keyup.enter="fetchRoles()"
          class="w-full pl-10 pr-3 py-2 text-sm rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
        />
      </div>
      <BaseButton variant="secondary" @click="fetchRoles()">Cari</BaseButton>
    </div>

    <div v-if="loading" class="p-6 flex justify-center">
      <span class="loading loading-spinner text-(--primary)"></span>
    </div>
    
    <DataTable v-else :headers="headers" :items="roles">
      <template #item.aksi="{ item }">
        <div class="flex items-center gap-1 justify-center">
          <button @click="openEditModal(item)" class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors">
            <IconPencil class="w-4 h-4" />
          </button>
          <button @click="openDeleteConfirm(item)" class="p-1.5 rounded-md text-(--text-muted) hover:text-red-600 hover:bg-red-600/10 transition-colors">
            <IconTrash class="w-4 h-4" />
          </button>
        </div>
      </template>
    </DataTable>

    <div v-if="totalPages > 1" class="pt-4">
      <Pagination :current-page="currentPage" :total-pages="totalPages" @page-change="fetchRoles" />
    </div>

    <!-- Create/Edit Modal -->
    <BaseModal :show="modalOpen" :title="isEditing ? 'Edit Role' : 'Tambah Role'" @close="closeModal">
      <form @submit.prevent="handleSave" class="space-y-4">
        <TextInput v-model="form.name" label="Nama Role" placeholder="Contoh: hrmanager" required :error="errors.name" />
        <TextInput v-model="form.guard_name" label="Guard Name" placeholder="Contoh: api" :error="errors.guard_name" />
      </form>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleSave" :disabled="saving">
          <span v-if="saving" class="loading loading-spinner loading-sm mr-2"></span>
          {{ isEditing ? 'Simpan' : 'Tambah' }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Delete Confirm -->
    <ConfirmDialog
      :show="deleteDialogOpen"
      title="Hapus Role"
      :message="'Apakah Anda yakin ingin menghapus role \'' + selectedItem?.name + '\'?'"
      variant="danger"
      confirm-text="Hapus"
      @confirm="handleDelete"
      @cancel="deleteDialogOpen = false"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../../../../../composables/useApi'
import { useNotificationStore } from '../../../../../Stores/notification'

import BaseButton from '../../../../../Components/BaseButton.vue'
import BaseModal from '../../../../../Components/BaseModal.vue'
import ConfirmDialog from '../../../../../Components/ConfirmDialog.vue'
import TextInput from '../../../../../Components/TextInput.vue'
import DataTable from '../../../../../Components/Table/DataTable.vue'
import Pagination from '../../../../../Components/Table/Pagination.vue'
import { IconPlus, IconSearch, IconPencil, IconTrash } from '../../../../../Components/Icons/index.js'

const { get, post, put, destroy } = useApi()
const notificationStore = useNotificationStore()

const searchQuery = ref('')
const loading = ref(true)
const saving = ref(false)
const roles = ref([])

const currentPage = ref(1)
const totalPages = ref(1)

const modalOpen = ref(false)
const deleteDialogOpen = ref(false)
const isEditing = ref(false)
const selectedItem = ref(null)
const errors = ref({})

const headers = [
  { key: 'name', label: 'Nama Role' },
  { key: 'guard_name', label: 'Guard' },
  { key: 'permissions_count', label: 'Jml Permission', align: 'center' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'center' },
]

const emptyForm = () => ({
  name: '',
  guard_name: 'api',
})

const form = ref(emptyForm())

const fetchRoles = async (page = 1) => {
  loading.value = true
  try {
    const response = await get(`/api/roles?page=${page}&search=${searchQuery.value}`)
    roles.value = response.data
    currentPage.value = response.current_page || 1
    totalPages.value = response.last_page || 1
  } catch (error) {
    notificationStore.addNotification('Gagal mengambil data role', 'error')
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  isEditing.value = false
  selectedItem.value = null
  form.value = emptyForm()
  errors.value = {}
  modalOpen.value = true
}

function openEditModal(item) {
  isEditing.value = true
  selectedItem.value = item
  form.value = {
    name: item.name,
    guard_name: item.guard_name,
  }
  errors.value = {}
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  form.value = emptyForm()
  selectedItem.value = null
  errors.value = {}
}

async function handleSave() {
  saving.value = true
  errors.value = {}
  try {
    if (isEditing.value) {
      await put(`/api/roles/${selectedItem.value.id}`, form.value)
      notificationStore.addNotification('Role berhasil diperbarui', 'success')
    } else {
      await post('/api/roles', form.value)
      notificationStore.addNotification('Role berhasil ditambahkan', 'success')
    }
    closeModal()
    fetchRoles(currentPage.value)
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    } else if (error.response?.status === 403) {
      notificationStore.addNotification(error.response.data.message || 'Tidak diizinkan', 'error')
    } else {
      notificationStore.addNotification('Gagal menyimpan role', 'error')
    }
  } finally {
    saving.value = false
  }
}

function openDeleteConfirm(item) {
  selectedItem.value = item
  deleteDialogOpen.value = true
}

async function handleDelete() {
  try {
    await destroy(`/api/roles/${selectedItem.value.id}`)
    notificationStore.addNotification('Role berhasil dihapus', 'success')
    fetchRoles(currentPage.value)
  } catch (error) {
    if (error.response?.status === 403) {
      notificationStore.addNotification('Tidak dapat menghapus superadmin', 'error')
    } else {
      notificationStore.addNotification('Gagal menghapus role', 'error')
    }
  } finally {
    deleteDialogOpen.value = false
    selectedItem.value = null
  }
}

onMounted(() => {
  fetchRoles()
})
</script>
