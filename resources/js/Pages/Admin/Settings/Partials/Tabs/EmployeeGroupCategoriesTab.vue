<script setup>
import { ref, reactive, onMounted } from 'vue'
import BaseButton from '../../../../../Components/BaseButton.vue'
import BaseCard from '../../../../../Components/BaseCard.vue'
import TextInput from '../../../../../Components/TextInput.vue'
import DataTable from '../../../../../Components/Table/DataTable.vue'
import { IconPencil, IconPlus } from '../../../../../Components/Icons/index.js'
import { useNotification } from '../../../../../composables/useNotification'
import { useApi } from '../../../../../composables/useApi'
import Badge from '../../../../../Components/Badge.vue'

const notification = useNotification()
const api = useApi()

const categories = ref([])
const categoryForm = reactive({ id: null, name: '', code: '', description: '', is_multiple_choice: false, is_active: true })
const isEditing = ref(false)

const tableHeaders = [
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Kategori / Dimensi' },
  { key: 'description', label: 'Deskripsi' },
  { key: 'is_multiple_choice', label: 'Bisa Pilih > 1' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '80px' },
]

async function fetchCategories() {
  try {
    const response = await api.get('/api/v1/settings/employee-data/categories')
    categories.value = response.data || []
  } catch (err) {
    notification.error('Gagal mengambil data kategori')
  }
}

onMounted(() => {
  fetchCategories()
})

function editCategory(item) {
  isEditing.value = true
  categoryForm.id = item.id
  categoryForm.name = item.name
  categoryForm.code = item.code
  categoryForm.description = item.description || ''
  categoryForm.is_multiple_choice = item.is_multiple_choice
  categoryForm.is_active = item.is_active
}

function resetCategoryForm() {
  isEditing.value = false
  categoryForm.id = null
  categoryForm.name = ''
  categoryForm.code = ''
  categoryForm.description = ''
  categoryForm.is_multiple_choice = false
  categoryForm.is_active = true
}

async function saveCategory() {
  try {
    const payload = { ...categoryForm }
    if (categoryForm.id) {
      await api.put(`/api/v1/settings/employee-data/categories/${categoryForm.id}`, payload)
      notification.success('Kategori berhasil diupdate')
    } else {
      await api.post(`/api/v1/settings/employee-data/categories`, payload)
      notification.success('Kategori berhasil ditambahkan')
    }
    resetCategoryForm()
    fetchCategories()
  } catch (err) {
    notification.error('Gagal menyimpan kategori')
  }
}

async function deleteCategory(id) {
  if (confirm('Hapus kategori ini? Semua grup di dalamnya akan terhapus juga!')) {
    try {
      await api.delete(`/api/v1/settings/employee-data/categories/${id}`)
      notification.success('Kategori berhasil dihapus')
      fetchCategories()
    } catch (err) {
      notification.error('Gagal menghapus kategori')
    }
  }
}
</script>

<template>
  <div class="space-y-6">
    <BaseCard>
      <template #title>Kelola Kategori Grup (Dimensi)</template>
      <div class="space-y-4">
        <!-- Form Add/Edit -->
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end bg-(--bg-soft) p-4 rounded-md">
          <TextInput v-model="categoryForm.code" label="Kode" placeholder="Ex: GRP_GAJI" />
          <TextInput v-model="categoryForm.name" label="Nama Kategori" placeholder="Ex: Manajemen Gaji" class="sm:col-span-2" />
          
          <div class="flex items-center gap-2 mb-2 sm:mb-0">
            <input type="checkbox" id="multipleChoice" v-model="categoryForm.is_multiple_choice" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)">
            <label for="multipleChoice" class="text-sm text-(--text-main)">Bisa Pilih > 1?</label>
          </div>

          <div class="flex gap-2">
            <BaseButton variant="primary" size="sm" @click="saveCategory">
              {{ categoryForm.id ? 'Update' : 'Tambah' }}
            </BaseButton>
            <BaseButton v-if="isEditing" variant="secondary" size="sm" @click="resetCategoryForm">
              Batal
            </BaseButton>
          </div>
        </div>

        <DataTable :headers="tableHeaders" :items="categories" class="mt-4">
          <template #item.is_multiple_choice="{ value }">
            <Badge :variant="value ? 'success' : 'neutral'">{{ value ? 'Ya' : 'Tidak' }}</Badge>
          </template>
          <template #item.actions="{ item }">
            <div class="flex items-center gap-2">
              <button @click="editCategory(item)" class="p-1 text-(--text-muted) hover:text-(--color-primary) transition-colors">
                <IconPencil class="w-4 h-4" />
              </button>
              <button @click="deleteCategory(item.id)" class="p-1 text-(--text-muted) hover:text-red-500 transition-colors">
                <i class="bx bx-trash text-lg"></i>
              </button>
            </div>
          </template>
        </DataTable>
      </div>
    </BaseCard>
  </div>
</template>
