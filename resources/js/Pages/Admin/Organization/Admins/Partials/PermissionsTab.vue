<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-(--text-main)">Daftar Permissions</h2>
        <p class="text-sm text-(--text-muted)">Kelola daftar permission/hak akses aksi di sistem</p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Permission
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
          placeholder="Cari permission..."
          @keyup.enter="fetchPermissions()"
          class="w-full pl-10 pr-3 py-2 text-sm rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
        />
      </div>
      <BaseButton variant="secondary" @click="fetchPermissions()">Cari</BaseButton>
    </div>

    <div v-if="loading" class="p-6 flex justify-center">
      <span class="loading loading-spinner text-(--primary)"></span>
    </div>
    
    <DataTable v-else :headers="headers" :items="permissions">
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
      <Pagination :current-page="currentPage" :total-pages="totalPages" @page-change="fetchPermissions" />
    </div>

    <!-- Create/Edit Modal -->
    <BaseModal :show="modalOpen" :title="isEditing ? 'Edit Permission' : 'Tambah Permission'" @close="closeModal">
      <form @submit.prevent="handleSave" class="space-y-4">
        <TextInput v-model="form.name" label="Nama Permission" placeholder="Contoh: view users" required :error="errors.name" />
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
      title="Hapus Permission"
      :message="'Apakah Anda yakin ingin menghapus permission \'' + selectedItem?.name + '\'?'"
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
const permissions = ref([])

const currentPage = ref(1)
const totalPages = ref(1)

const modalOpen = ref(false)
const deleteDialogOpen = ref(false)
const isEditing = ref(false)
const selectedItem = ref(null)
const errors = ref({})

const headers = [
  { key: 'name', label: 'Nama Permission' },
  { key: 'guard_name', label: 'Guard' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'center' },
]

const emptyForm = () => ({
  name: '',
  guard_name: 'api',
})

const form = ref(emptyForm())

const fetchPermissions = async (page = 1) => {
  loading.value = true
  try {
    const response = await get(`/api/permissions?page=${page}&search=${searchQuery.value}`)
    permissions.value = response.data
    currentPage.value = response.current_page || 1
    totalPages.value = response.last_page || 1
  } catch (error) {
    notificationStore.addNotification('Gagal mengambil data permission', 'error')
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
      await put(`/api/permissions/${selectedItem.value.id}`, form.value)
      notificationStore.addNotification('Permission berhasil diperbarui', 'success')
    } else {
      await post('/api/permissions', form.value)
      notificationStore.addNotification('Permission berhasil ditambahkan', 'success')
    }
    closeModal()
    fetchPermissions(currentPage.value)
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    } else {
      notificationStore.addNotification('Gagal menyimpan permission', 'error')
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
    await destroy(`/api/permissions/${selectedItem.value.id}`)
    notificationStore.addNotification('Permission berhasil dihapus', 'success')
    fetchPermissions(currentPage.value)
  } catch (error) {
    notificationStore.addNotification('Gagal menghapus permission', 'error')
  } finally {
    deleteDialogOpen.value = false
    selectedItem.value = null
  }
}

onMounted(() => {
  fetchPermissions()
})
</script>
