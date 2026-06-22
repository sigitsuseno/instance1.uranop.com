<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-(--primary)/10 flex items-center justify-center text-(--primary) shadow-sm">
          <i class="bx bx-buildings text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-(--text-main)">Departemen (Supervisor)</h1>
          <p class="text-sm text-(--text-muted) mt-1">Kelola struktur organisasi dan departemen bayangan</p>
        </div>
      </div>
      <BaseButton variant="primary" @click="openCreateModal" class="shadow-lg shadow-(--primary-glow)">
        <template #icon-left>
          <i class="bx bx-plus text-lg"></i>
        </template>
        Tambah Departemen
      </BaseButton>
    </div>

    <!-- Main Card -->
    <BaseCard :padding="'p-0'" class="overflow-hidden border-(--border-soft) shadow-sm">
      <!-- Toolbar -->
      <div class="p-5 border-b border-(--border-soft) bg-(--bg-card) flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="relative w-full sm:w-72">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
            <i class="bx bx-search text-lg"></i>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari departemen atau kode..."
            @keyup.enter="fetchDepartments()"
            class="w-full pl-10 pr-4 py-2.5 text-sm rounded-lg border border-(--border-strong) bg-(--bg-elevated) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all"
          />
        </div>
        <div class="flex items-center gap-2">
          <BaseButton variant="secondary" @click="fetchDepartments()">
            <template #icon-left>
              <i class="bx bx-refresh text-lg"></i>
            </template>
            Refresh
          </BaseButton>
        </div>
      </div>
      
      <!-- Content -->
      <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat data departemen...</p>
      </div>
      
      <DataTable 
        v-else 
        :headers="headers" 
        :items="departments" 
        empty-text="Belum ada data departemen yang ditambahkan"
      >
        <template #item.name="{ item }">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-(--bg-elevated) border border-(--border-soft) flex items-center justify-center text-(--text-muted)">
              <i class="bx bx-folder text-lg"></i>
            </div>
            <div class="font-medium text-(--text-main)">{{ item.name }}</div>
          </div>
        </template>
        
        <template #item.manager_name="{ item }">
          <div v-if="item.manager_name" class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full bg-(--primary)/10 text-(--primary) flex items-center justify-center text-xs font-bold">
              {{ item.manager_name.charAt(0) }}
            </div>
            <span class="text-sm">{{ item.manager_name }}</span>
          </div>
          <span v-else class="text-(--text-soft) italic text-sm">Belum ditentukan</span>
        </template>

        <template #item.is_active="{ value }">
          <Badge :variant="value ? 'success' : 'ghost'" class="px-2.5 py-1">
            <template #icon v-if="value">
              <span class="w-1.5 h-1.5 rounded-full bg-white mr-1.5 inline-block"></span>
            </template>
            {{ value ? 'Aktif' : 'Nonaktif' }}
          </Badge>
        </template>

        <template #item.aksi="{ item }">
          <div class="flex items-center justify-end gap-2">
            <button
              class="w-8 h-8 flex items-center justify-center rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
              title="Edit"
              @click="openEditModal(item)"
            >
              <i class="bx bx-edit-alt text-lg"></i>
            </button>
            <button
              class="w-8 h-8 flex items-center justify-center rounded-md text-(--text-muted) hover:text-red-600 hover:bg-red-600/10 transition-colors"
              title="Hapus"
              @click="openDeleteConfirm(item)"
            >
              <i class="bx bx-trash text-lg"></i>
            </button>
          </div>
        </template>
      </DataTable>

      <div v-if="totalPages > 1" class="p-4 border-t border-(--border-soft) bg-(--bg-card)">
        <Pagination :current-page="currentPage" :total-pages="totalPages" @page-change="fetchDepartments" />
      </div>
    </BaseCard>

    <!-- Create/Edit Modal -->
    <BaseModal :show="modalOpen" :title="isEditing ? 'Edit Departemen' : 'Tambah Departemen Baru'" size="lg" @close="closeModal">
      <form @submit.prevent="handleSave">
        <!-- Form Info Header -->
        <div class="flex items-start gap-4 mb-6 p-4 rounded-xl bg-(--bg-elevated)/50 border border-(--border-soft)">
          <div class="w-10 h-10 rounded-lg bg-(--primary)/10 text-(--primary) flex items-center justify-center shrink-0">
            <i class="bx bx-info-circle text-xl"></i>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-(--text-main)">Informasi Departemen</h4>
            <p class="text-xs text-(--text-muted) mt-1">Pastikan kode departemen unik dan mudah diidentifikasi.</p>
          </div>
        </div>

        <div class="space-y-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <TextInput 
              v-model="form.name" 
              label="Nama Departemen" 
              placeholder="Contoh: Human Resources" 
              required 
              :error="errors.name" 
            />
            <TextInput 
              v-model="form.code" 
              label="Kode Departemen" 
              placeholder="Contoh: HRD" 
              required 
              :error="errors.code" 
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1 text-(--text-main)">Induk Departemen (Opsional)</label>
            <div class="relative">
              <select 
                v-model="form.parent_id"
                class="w-full h-10 pl-3 pr-10 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300 appearance-none"
              >
                <option :value="null">-- Tidak Ada Induk (Top Level) --</option>
                <option v-for="dept in parentOptions" :key="dept.id" :value="dept.id" :disabled="isEditing && dept.id === selectedItem?.id">
                  {{ dept.name }} {{ dept.code ? `(${dept.code})` : '' }}
                </option>
              </select>
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-(--text-muted)">
                <i class="bx bx-chevron-down text-lg"></i>
              </div>
            </div>
            <p v-if="errors.parent_id" class="mt-1 text-sm text-red-600">{{ errors.parent_id[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1 text-(--text-main)">Deskripsi</label>
            <textarea 
              v-model="form.description" 
              rows="3"
              placeholder="Jelaskan fungsi dari departemen ini (opsional)"
              class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300 resize-none"
              :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.description }"
            ></textarea>
            <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description[0] }}</p>
          </div>
          
          <div class="pt-2">
            <label class="flex items-center gap-3 p-3 rounded-lg border border-(--border-soft) bg-(--bg-card) hover:bg-(--bg-elevated) cursor-pointer transition-colors">
              <div class="relative flex items-center">
                <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                <div class="w-10 h-5 bg-gray-300 rounded-full peer peer-checked:bg-(--primary) transition-colors dark:bg-gray-600"></div>
                <div class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
              </div>
              <div class="flex flex-col">
                <span class="text-sm font-medium text-(--text-main)">Status Aktif</span>
                <span class="text-xs text-(--text-muted)">Departemen yang nonaktif tidak dapat dipilih saat mendaftarkan karyawan.</span>
              </div>
            </label>
          </div>
        </div>
      </form>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleSave" :disabled="saving" class="min-w-[120px]">
          <span v-if="saving" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"></span>
          {{ isEditing ? 'Simpan Perubahan' : 'Tambah Departemen' }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :show="deleteDialogOpen"
      title="Hapus Departemen"
      :message="`Apakah Anda yakin ingin menghapus departemen '${selectedItem?.name}'? Karyawan yang berada di departemen ini mungkin akan kehilangan referensi. Tindakan ini tidak dapat dibatalkan.`"
      variant="danger"
      confirm-text="Ya, Hapus Departemen"
      @confirm="handleDelete"
      @cancel="deleteDialogOpen = false"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'

import BaseCard from '@/Components/BaseCard.vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseModal from '@/Components/BaseModal.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import TextInput from '@/Components/TextInput.vue'
import DataTable from '@/Components/Table/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Pagination from '@/Components/Table/Pagination.vue'

const { get, post, put, destroy } = useApi()

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
  { key: 'code', label: 'Kode', width: '120px' },
  { key: 'name', label: 'Nama Departemen' },
  { key: 'parent_name', label: 'Induk Departemen' },
  { key: 'manager_name', label: 'Kepala Bagian' },
  { key: 'is_active', label: 'Status', width: '100px' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'right', width: '100px' },
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
    const response = await get(`/api/v1/supervisor/master/departments?page=${page}&search=${searchQuery.value}`)
    departments.value = response.data
    currentPage.value = response.meta.current_page
    totalPages.value = response.meta.last_page
    
    if (page === 1 && !searchQuery.value) {
      parentOptions.value = response.data
    }
  } catch (error) {
    alert('Gagal mengambil data departemen')
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
  setTimeout(() => {
    form.value = emptyForm()
    selectedItem.value = null
    errors.value = {}
  }, 200) // Wait for transition
}

async function handleSave() {
  saving.value = true
  errors.value = {}
  try {
    if (isEditing.value) {
      await put(`/api/v1/supervisor/master/departments/${selectedItem.value.id}`, form.value)
    } else {
      await post('/api/v1/supervisor/master/departments', form.value)
    }
    closeModal()
    fetchDepartments(currentPage.value)
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    } else {
      alert('Gagal menyimpan departemen')
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
    await destroy(`/api/v1/supervisor/master/departments/${selectedItem.value.id}`)
    fetchDepartments(currentPage.value)
  } catch (error) {
    alert('Gagal menghapus departemen')
  } finally {
    deleteDialogOpen.value = false
    selectedItem.value = null
  }
}

onMounted(() => {
  fetchDepartments()
})
</script>
