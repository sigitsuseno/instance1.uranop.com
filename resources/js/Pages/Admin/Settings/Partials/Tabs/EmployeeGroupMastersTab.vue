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

const groups = ref([])
const form = reactive({ id: null, group_label: '', name: '', code: '', description: '', is_active: true })
const isEditing = ref(false)

const tableHeaders = [
  { key: 'group_label', label: 'Label/Kelompok' },
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Nama Master' },
  { key: 'description', label: 'Deskripsi' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '80px' },
]

async function fetchGroups() {
  try {
    const response = await api.get('/api/v1/settings/employee-data/groups')
    groups.value = response.data || []
  } catch (err) {
    notification.error('Gagal mengambil data master group')
  }
}

onMounted(() => {
  fetchGroups()
})

function editGroup(item) {
  isEditing.value = true
  form.id = item.id
  form.group_label = item.group_label
  form.name = item.name
  form.code = item.code
  form.description = item.description || ''
  form.is_active = item.is_active
}

function resetForm() {
  isEditing.value = false
  form.id = null
  form.group_label = ''
  form.name = ''
  form.code = ''
  form.description = ''
  form.is_active = true
}

async function saveGroup() {
  try {
    const payload = { ...form }
    if (form.id) {
      await api.put(`/api/v1/settings/employee-data/groups/${form.id}`, payload)
      notification.success('Master Group berhasil diupdate')
    } else {
      await api.post(`/api/v1/settings/employee-data/groups`, payload)
      notification.success('Master Group berhasil ditambahkan')
    }
    resetForm()
    fetchGroups()
  } catch (err) {
    notification.error('Gagal menyimpan Master Group')
  }
}

async function deleteGroup(id) {
  if (confirm('Hapus master group ini?')) {
    try {
      await api.destroy(`/api/v1/settings/employee-data/groups/${id}`)
      notification.success('Master Group berhasil dihapus')
      fetchGroups()
    } catch (err) {
      notification.error('Gagal menghapus master group')
    }
  }
}
</script>

<template>
  <div class="space-y-6">
    <BaseCard>
      <template #title>Kelola Master Group</template>
      <div class="space-y-4">
        <!-- Form Add/Edit -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end bg-(--bg-soft) p-4 rounded-md">
          <TextInput v-model="form.group_label" label="Label Group" placeholder="Ex: Shift" />
          <TextInput v-model="form.code" label="Kode" placeholder="Ex: SFT_PAGI" />
          <TextInput v-model="form.name" label="Nama Master" placeholder="Ex: Shift Pagi" />
          
          <div class="flex gap-2">
            <BaseButton variant="primary" size="sm" @click="saveGroup">
              {{ form.id ? 'Update' : 'Tambah' }}
            </BaseButton>
            <BaseButton v-if="isEditing" variant="secondary" size="sm" @click="resetForm">
              Batal
            </BaseButton>
          </div>
        </div>

        <DataTable :headers="tableHeaders" :items="groups" class="mt-4">
          <template #item.is_active="{ value }">
            <Badge :variant="value ? 'success' : 'neutral'">{{ value ? 'Aktif' : 'Nonaktif' }}</Badge>
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
