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
      <div class="p-6 pb-0 flex gap-4">
        <div class="relative w-64">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
            <IconSearch class="w-4 h-4" />
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari departemen..."
            @keyup.enter="fetchDepartments()"
            class="w-full pl-10 pr-3 py-2 text-sm rounded-md border bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
          />
        </div>
        <BaseButton variant="secondary" @click="fetchDepartments()">Cari</BaseButton>
      </div>
      
      <div v-if="loading" class="p-6 flex justify-center">
        <span class="loading loading-spinner text-primary"></span>
      </div>
      <DataTable v-else :headers="headers" :items="departments">
        <template #item.manager_name="{ item }">
          {{ item.manager_name || '-' }}
        </template>
        <template #item.is_active="{ value }">
          <Badge :variant="value ? 'success' : 'warning'">
            {{ value ? 'Aktif' : 'Nonaktif' }}
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
              class="p-1.5 rounded-md text-(--text-muted) hover:text-red-600 hover:bg-red-600/10 transition-colors"
              @click="openDeleteConfirm(item)"
            >
              <IconTrash class="w-4 h-4" />
            </button>
          </div>
        </template>
      </DataTable>
      <div v-if="totalPages > 1" class="p-6 pt-0">
        <Pagination :current-page="currentPage" :total-pages="totalPages" @page-change="fetchDepartments" />
      </div>
    </BaseCard>

    <BaseModal :show="modalOpen" :title="isEditing ? 'Edit Departemen' : 'Tambah Departemen'" @close="closeModal">
      <form @submit.prevent="handleSave" class="space-y-4">
        <TextInput v-model="form.name" label="Nama Departemen" placeholder="Masukkan nama departemen" required :error="errors.name" />
        <TextInput v-model="form.code" label="Kode Departemen" placeholder="Contoh: IT" required :error="errors.code" />
        <TextInput v-model="form.description" label="Deskripsi" placeholder="Deskripsi opsional" :error="errors.description" />
        
        <!-- Parent Department Select -->
        <div>
          <label class="block text-sm font-medium mb-1 text-(--text-main)">Induk Departemen</label>
          <select 
            v-model="form.parent_id"
            class="w-full rounded-md border-(--border-soft) bg-(--bg-card) text-(--text-main) shadow-sm focus:border-(--primary) focus:ring-(--primary) sm:text-sm"
          >
            <option :value="null">-- Tidak Ada --</option>
            <option v-for="dept in parentOptions" :key="dept.id" :value="dept.id">
              {{ dept.name }}
            </option>
          </select>
          <p v-if="errors.parent_id" class="mt-1 text-sm text-red-600">{{ errors.parent_id[0] }}</p>
        </div>
        
        <div class="flex items-center gap-2 mt-4">
          <input type="checkbox" id="isActive" v-model="form.is_active" class="rounded text-(--primary) focus:ring-(--primary) border-(--border-soft) bg-(--bg-card)" />
          <label for="isActive" class="text-sm font-medium text-(--text-main)">Status Aktif</label>
        </div>
      </form>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleSave" :disabled="saving">
          <span v-if="saving" class="loading loading-spinner loading-sm mr-2"></span>
          {{ isEditing ? 'Simpan' : 'Tambah' }}
        </BaseButton>
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
import { ref, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import TextInput from '../../../../Components/TextInput.vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import Badge from '../../../../Components/Badge.vue'
import Pagination from '../../../../Components/Table/Pagination.vue'
import { IconPlus, IconPencil, IconTrash, IconSearch } from '../../../../Components/Icons/index.js'

const { get, post, put, destroy } = useApi()
const notificationStore = useNotificationStore()

const searchQuery = ref('')
const loading = ref(true)
const saving = ref(false)
const departments = ref([])
const parentOptions = ref([])

const currentPage = ref(1)
const totalPages = ref(1)

const modalOpen = ref(false)
const deleteDialogOpen = ref(false)
const isEditing = ref(false)
const selectedItem = ref(null)
const errors = ref({})

const headers = [
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Nama' },
  { key: 'parent_name', label: 'Induk' },
  { key: 'manager_name', label: 'Kepala' },
  { key: 'is_active', label: 'Status' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'center' },
]

const emptyForm = () => ({
  name: '',
  code: '',
  description: '',
  parent_id: null,
  is_active: true,
})

const form = ref(emptyForm())

const fetchDepartments = async (page = 1) => {
  loading.value = true
  try {
    const response = await get(`/api/organization/departments?page=${page}&search=${searchQuery.value}`)
    departments.value = response.data
    currentPage.value = response.meta.current_page
    totalPages.value = response.meta.last_page
    
    // Also update parentOptions for the select dropdown (we can just fetch all or use the same list)
    if (page === 1 && !searchQuery.value) {
      parentOptions.value = response.data
    }
  } catch (error) {
    notificationStore.addNotification('Gagal mengambil data departemen', 'error')
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
    code: item.code,
    description: item.description,
    parent_id: item.parent_id,
    is_active: item.is_active,
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
      await put(`/api/organization/departments/${selectedItem.value.id}`, form.value)
      notificationStore.addNotification('Departemen berhasil diperbarui', 'success')
    } else {
      await post('/api/organization/departments', form.value)
      notificationStore.addNotification('Departemen berhasil ditambahkan', 'success')
    }
    closeModal()
    fetchDepartments(currentPage.value)
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    } else {
      notificationStore.addNotification('Gagal menyimpan departemen', 'error')
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
    await destroy(`/api/organization/departments/${selectedItem.value.id}`)
    notificationStore.addNotification('Departemen berhasil dihapus', 'success')
    fetchDepartments(currentPage.value)
  } catch (error) {
    notificationStore.addNotification('Gagal menghapus departemen', 'error')
  } finally {
    deleteDialogOpen.value = false
    selectedItem.value = null
  }
}

onMounted(() => {
  fetchDepartments()
})
</script>
