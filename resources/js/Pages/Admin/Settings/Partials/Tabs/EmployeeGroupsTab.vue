<script setup>
import { ref, reactive, onMounted } from 'vue'
import BaseButton from '../../../../../Components/BaseButton.vue'
import BaseCard from '../../../../../Components/BaseCard.vue'
import TextInput from '../../../../../Components/TextInput.vue'
import SelectInput from '../../../../../Components/SelectInput.vue'
import DataTable from '../../../../../Components/Table/DataTable.vue'
import { IconPencil, IconPlus } from '../../../../../Components/Icons/index.js'
import { useNotification } from '../../../../../composables/useNotification'
import { useApi } from '../../../../../composables/useApi'
import Badge from '../../../../../Components/Badge.vue'

const notification = useNotification()
const api = useApi()

const groups = ref([])
const categories = ref([])
const groupForm = reactive({ id: null, category_id: '', name: '', code: '', description: '', is_active: true })
const isEditing = ref(false)

const tableHeaders = [
  { key: 'category.name', label: 'Kategori / Dimensi' },
  { key: 'code', label: 'Kode Grup' },
  { key: 'name', label: 'Nama Grup' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '80px' },
]

async function fetchCategories() {
  try {
    const response = await api.get('/api/v1/settings/employee-data/categories')
    categories.value = response.data || []
  } catch (err) {
    // silently fail or log
  }
}

async function fetchGroups() {
  try {
    const response = await api.get('/api/v1/settings/employee-data/groups')
    groups.value = response.data || []
  } catch (err) {
    notification.error('Gagal mengambil data grup')
  }
}

onMounted(() => {
  fetchCategories()
  fetchGroups()
})

function editGroup(item) {
  isEditing.value = true
  groupForm.id = item.id
  groupForm.category_id = item.category_id
  groupForm.name = item.name
  groupForm.code = item.code
  groupForm.description = item.description || ''
  groupForm.is_active = item.is_active
}

function resetGroupForm() {
  isEditing.value = false
  groupForm.id = null
  groupForm.category_id = ''
  groupForm.name = ''
  groupForm.code = ''
  groupForm.description = ''
  groupForm.is_active = true
}

async function saveGroup() {
  try {
    const payload = { ...groupForm }
    if (groupForm.id) {
      await api.put(`/api/v1/settings/employee-data/groups/${groupForm.id}`, payload)
      notification.success('Grup berhasil diupdate')
    } else {
      await api.post(`/api/v1/settings/employee-data/groups`, payload)
      notification.success('Grup berhasil ditambahkan')
    }
    resetGroupForm()
    fetchGroups()
  } catch (err) {
    notification.error('Gagal menyimpan grup')
  }
}

async function deleteGroup(id) {
  if (confirm('Hapus grup ini?')) {
    try {
      await api.delete(`/api/v1/settings/employee-data/groups/${id}`)
      notification.success('Grup berhasil dihapus')
      fetchGroups()
    } catch (err) {
      notification.error('Gagal menghapus grup')
    }
  }
}
</script>

<template>
  <div class="space-y-6">
    <BaseCard>
      <template #title>Kelola Grup Karyawan</template>
      <div class="space-y-4">
        <!-- Form Add/Edit -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end bg-(--bg-soft) p-4 rounded-md">
          <SelectInput 
            v-model="groupForm.category_id" 
            label="Pilih Kategori" 
            :options="categories.map(c => ({ value: c.id, label: c.name }))"
            class="sm:col-span-1"
          />
          <TextInput v-model="groupForm.code" label="Kode Grup" placeholder="Ex: JKT" />
          <TextInput v-model="groupForm.name" label="Nama Grup" placeholder="Ex: Jakarta" />
          
          <div class="flex gap-2">
            <BaseButton variant="primary" size="sm" @click="saveGroup" :disabled="!groupForm.category_id">
              {{ groupForm.id ? 'Update' : 'Tambah' }}
            </BaseButton>
            <BaseButton v-if="isEditing" variant="secondary" size="sm" @click="resetGroupForm">
              Batal
            </BaseButton>
          </div>
        </div>

        <DataTable :headers="tableHeaders" :items="groups" class="mt-4">
          <template #item.category.name="{ item }">
            <Badge variant="primary">{{ item.category?.name || '-' }}</Badge>
          </template>
          <template #item.actions="{ item }">
            <div class="flex items-center gap-2">
              <button @click="editGroup(item)" class="p-1 text-(--text-muted) hover:text-(--color-primary) transition-colors">
                <IconPencil class="w-4 h-4" />
              </button>
              <button @click="deleteGroup(item.id)" class="p-1 text-(--text-muted) hover:text-red-500 transition-colors">
                <i class="bx bx-trash text-lg"></i>
              </button>
            </div>
          </template>
        </DataTable>
      </div>
    </BaseCard>
  </div>
</template>
