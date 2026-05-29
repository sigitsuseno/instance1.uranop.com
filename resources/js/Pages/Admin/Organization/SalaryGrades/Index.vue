<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-(--primary)/10 flex items-center justify-center text-(--primary) shadow-sm">
          <i class="bx bx-bar-chart-square text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-(--text-main)">Grade Gaji</h1>
          <p class="text-sm text-(--text-muted) mt-1">Kelola data grade dan rentang (range) gaji karyawan</p>
        </div>
      </div>
      <BaseButton variant="primary" @click="openCreateModal" class="shadow-lg shadow-(--primary-glow)">
        <template #icon-left>
          <i class="bx bx-plus text-lg"></i>
        </template>
        Tambah Grade Gaji
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
            placeholder="Cari grade gaji..."
            class="w-full pl-10 pr-4 py-2.5 text-sm rounded-lg border border-(--border-strong) bg-(--bg-elevated) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all"
          />
        </div>
        <div class="flex items-center gap-2">
          <BaseButton variant="secondary" @click="fetchGrades()">
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
        <p class="text-(--text-muted)">Memuat data grade gaji...</p>
      </div>
      
      <DataTable 
        v-else 
        :headers="headers" 
        :items="filteredGrades" 
        empty-text="Belum ada grade gaji yang ditambahkan"
      >
        <template #item.name="{ item }">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-(--bg-elevated) border border-(--border-soft) flex items-center justify-center text-(--text-muted)">
              <i class="bx bx-star text-lg"></i>
            </div>
            <div class="font-medium text-(--text-main)">{{ item.name }}</div>
          </div>
        </template>

        <template #item.min_salary="{ value }">
          <span class="font-medium text-(--text-main)">{{ formatRupiah(value) }}</span>
        </template>
        
        <template #item.max_salary="{ value }">
          <span class="font-medium text-(--text-main)">{{ formatRupiah(value) }}</span>
        </template>

        <template #item.description="{ value }">
          <span class="text-sm text-(--text-muted)">{{ value || '-' }}</span>
        </template>

        <template #item.employee_count="{ value }">
          <Badge variant="ghost" class="px-2.5 py-1 font-medium bg-(--bg-elevated) text-(--text-main) border-(--border-soft)">
            <template #icon>
              <i class="bx bx-user text-xs mr-1"></i>
            </template>
            {{ value || 0 }} Karyawan
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
    </BaseCard>

    <!-- Create/Edit Modal -->
    <BaseModal :show="modalOpen" :title="isEditing ? 'Edit Grade Gaji' : 'Tambah Grade Gaji Baru'" size="md" @close="closeModal">
      <form @submit.prevent="handleSave">
        <!-- Form Info Header -->
        <div class="flex items-start gap-4 mb-6 p-4 rounded-xl bg-(--bg-elevated)/50 border border-(--border-soft)">
          <div class="w-10 h-10 rounded-lg bg-(--primary)/10 text-(--primary) flex items-center justify-center shrink-0">
            <i class="bx bx-info-circle text-xl"></i>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-(--text-main)">Rentang Gaji Pokok</h4>
            <p class="text-xs text-(--text-muted) mt-1">Sistem akan menolak input gaji pokok karyawan jika di luar dari nilai Minimum dan Maksimum grade-nya.</p>
          </div>
        </div>

        <div class="space-y-5">
          <TextInput 
            v-model="form.name" 
            label="Nama Grade" 
            placeholder="Contoh: Grade A" 
            required 
            :error="errors?.name" 
          />
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-medium text-(--text-main) mb-1">Gaji Minimum <span class="text-red-500">*</span></label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted) font-medium text-sm">
                  Rp
                </div>
                <input
                  v-model.number="form.min_salary"
                  type="number"
                  placeholder="5000000"
                  required
                  class="w-full pl-9 pr-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300"
                />
              </div>
              <p v-if="errors?.min_salary" class="mt-1 text-xs text-red-600">{{ errors.min_salary[0] }}</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-(--text-main) mb-1">Gaji Maksimum <span class="text-red-500">*</span></label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted) font-medium text-sm">
                  Rp
                </div>
                <input
                  v-model.number="form.max_salary"
                  type="number"
                  placeholder="10000000"
                  required
                  class="w-full pl-9 pr-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300"
                />
              </div>
              <p v-if="errors?.max_salary" class="mt-1 text-xs text-red-600">{{ errors.max_salary[0] }}</p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1 text-(--text-main)">Deskripsi</label>
            <textarea 
              v-model="form.description" 
              rows="3"
              placeholder="Deskripsikan karakteristik grade ini"
              class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300 resize-none"
              :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors?.description }"
            ></textarea>
            <p v-if="errors?.description" class="mt-1 text-xs text-red-600">{{ errors.description[0] }}</p>
          </div>
        </div>
      </form>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleSave" :disabled="saving" class="min-w-[120px]">
          <span v-if="saving" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"></span>
          {{ isEditing ? 'Simpan Perubahan' : 'Tambah Grade' }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :show="deleteDialogOpen"
      title="Hapus Grade Gaji"
      :message="`Apakah Anda yakin ingin menghapus grade '${selectedItem?.name}'? Karyawan dengan grade ini mungkin akan kehilangan referensi. Tindakan ini tidak dapat dibatalkan.`"
      variant="danger"
      confirm-text="Ya, Hapus Grade"
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
import Badge from '../../../../Components/Badge.vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

const api = useApi()
const notification = useNotificationStore()

const loading = ref(true)
const saving = ref(false)
const searchQuery = ref('')
const modalOpen = ref(false)
const deleteDialogOpen = ref(false)
const isEditing = ref(false)
const selectedItem = ref(null)
const errors = ref({})

const salaryGrades = ref([])

async function fetchGrades() {
  loading.value = true
  try {
    const res = await api.get('/api/organization/salary-grades')
    salaryGrades.value = res.data || []
  } catch (e) {
    notification.addNotification('Gagal memuat grade gaji', 'error')
  } finally {
    loading.value = false
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
  { key: 'employee_count', label: 'Jumlah Karyawan', align: 'center', width: '150px' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'right', width: '100px' },
]

const filteredGrades = computed(() => {
  if (!searchQuery.value) return salaryGrades.value
  const q = searchQuery.value.toLowerCase()
  return salaryGrades.value.filter((g) =>
    g.name?.toLowerCase().includes(q) ||
    g.description?.toLowerCase().includes(q)
  )
})

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
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
  errors.value = {}
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
  errors.value = {}
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  setTimeout(() => {
    form.value = emptyForm()
    selectedItem.value = null
    errors.value = {}
  }, 200)
}

async function handleSave() {
  if (!form.value.name || !form.value.min_salary || !form.value.max_salary || !form.value.description) return

  saving.value = true
  errors.value = {}
  try {
    if (isEditing.value && selectedItem.value) {
      const res = await api.put(`/api/organization/salary-grades/${selectedItem.value.id}`, form.value)
      const idx = salaryGrades.value.findIndex((g) => g.id === selectedItem.value.id)
      if (idx !== -1) {
        salaryGrades.value[idx] = res.data || res
      }
      notification.addNotification('Berhasil menyimpan perubahan', 'success')
    } else {
      const res = await api.post('/api/organization/salary-grades', form.value)
      salaryGrades.value.push(res.data || res)
      notification.addNotification('Berhasil menambahkan grade gaji', 'success')
    }
    closeModal()
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    } else {
      notification.addNotification('Gagal menyimpan grade gaji', 'error')
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
  if (selectedItem.value) {
    try {
      await api.destroy(`/api/organization/salary-grades/${selectedItem.value.id}`)
      salaryGrades.value = salaryGrades.value.filter((g) => g.id !== selectedItem.value.id)
      notification.addNotification('Berhasil menghapus grade gaji', 'success')
    } catch (e) {
      notification.addNotification('Gagal menghapus grade gaji', 'error')
    }
  }
  deleteDialogOpen.value = false
  selectedItem.value = null
}
</script>
