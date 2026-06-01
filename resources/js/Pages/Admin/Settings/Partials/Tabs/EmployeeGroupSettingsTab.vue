<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
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

const settings = ref([])
const masterGroups = ref([])
const isEditing = ref(false)

const form = reactive({
  id: null,
  tab_id: '',
  tab_name: '',
  group_label: '',
  icon: 'bx-folder',
  filters: {
    employment_status: []
  },
  sort_order: 0,
  is_active: true
})

const tableHeaders = [
  { key: 'tab_name', label: 'Nama Tab' },
  { key: 'tab_id', label: 'ID Tab' },
  { key: 'group_label', label: 'Label Master' },
  { key: 'icon', label: 'Icon' },
  { key: 'filters', label: 'Filter Kepegawaian' },
  { key: 'sort_order', label: 'Urutan' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '100px' },
]

const statusOptions = [
  { value: 'permanent', label: 'Permanen (PKWTT)' },
  { value: 'contract', label: 'Kontrak (PKWT)' },
  { value: 'probation', label: 'Percobaan' },
  { value: 'outsource', label: 'Outsource' },
  { value: 'freelance', label: 'Freelance' },
  { value: 'resigned', label: 'Resign' },
  { value: 'terminated', label: 'PHK' }
]

const statusLabels = {
  permanent: 'Permanen',
  contract: 'Kontrak',
  probation: 'Percobaan',
  outsource: 'Outsource',
  freelance: 'Freelance',
  resigned: 'Resign',
  terminated: 'PHK'
}

function getStatusLabel(status) {
  return statusLabels[status] || status
}

const groupLabelOptions = computed(() => {
  const labels = [...new Set(masterGroups.value.map(g => g.group_label))].filter(Boolean)
  return labels.map(label => ({ value: label, label: label }))
})

async function fetchSettings() {
  try {
    const response = await api.get('/api/v1/settings/employee-data/group-settings')
    settings.value = response.data || []
  } catch (err) {
    notification.error('Gagal mengambil data pengaturan tab')
  }
}

async function fetchMasterGroups() {
  try {
    const response = await api.get('/api/v1/settings/employee-data/groups')
    masterGroups.value = response.data || []
  } catch (err) {
    notification.error('Gagal mengambil data master group')
  }
}

onMounted(() => {
  fetchSettings()
  fetchMasterGroups()
})

function onTabNameInput() {
  if (!isEditing.value) {
    form.tab_id = form.tab_name
      .toLowerCase()
      .replace(/[^a-z0-9_]/g, '_')
      .replace(/_+/g, '_')
      .replace(/^_+|_+$/g, '')
  }
}

function editSetting(item) {
  isEditing.value = true
  form.id = item.id
  form.tab_id = item.tab_id
  form.tab_name = item.tab_name
  form.group_label = item.group_label || ''
  form.icon = item.icon || 'bx-folder'
  form.sort_order = item.sort_order || 0
  form.is_active = item.is_active
  
  // Set filters
  if (item.filters && item.filters.employment_status) {
    form.filters.employment_status = [...item.filters.employment_status]
  } else {
    form.filters.employment_status = []
  }
}

function resetForm() {
  isEditing.value = false
  form.id = null
  form.tab_id = ''
  form.tab_name = ''
  form.group_label = ''
  form.icon = 'bx-folder'
  form.filters.employment_status = []
  form.sort_order = 0
  form.is_active = true
}

async function saveSetting() {
  if (!form.tab_name || !form.tab_id || !form.group_label) {
    notification.error('Nama Tab, ID Tab, dan Label Master wajib diisi!')
    return
  }

  try {
    const payload = {
      tab_id: form.tab_id,
      tab_name: form.tab_name,
      group_label: form.group_label,
      icon: form.icon,
      sort_order: parseInt(form.sort_order) || 0,
      is_active: form.is_active,
      filters: form.filters.employment_status.length > 0 
        ? { employment_status: form.filters.employment_status } 
        : null
    }

    if (form.id) {
      await api.put(`/api/v1/settings/employee-data/group-settings/${form.id}`, payload)
      notification.success('Pengaturan Tab berhasil diupdate')
    } else {
      await api.post(`/api/v1/settings/employee-data/group-settings`, payload)
      notification.success('Pengaturan Tab berhasil ditambahkan')
    }
    resetForm()
    fetchSettings()
  } catch (err) {
    const errorMsg = err.response?.data?.message || 'Gagal menyimpan pengaturan tab'
    notification.error(errorMsg)
  }
}

async function deleteSetting(id) {
  if (confirm('Apakah Anda yakin ingin menghapus pengaturan tab grouping ini?')) {
    try {
      await api.delete(`/api/v1/settings/employee-data/group-settings/${id}`)
      notification.success('Pengaturan Tab berhasil dihapus')
      fetchSettings()
    } catch (err) {
      notification.error('Gagal menghapus pengaturan tab')
    }
  }
}
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Form Area -->
    <div class="lg:col-span-1">
      <BaseCard>
        <template #title>{{ isEditing ? 'Edit Tab Grouping' : 'Tambah Tab Grouping' }}</template>
        
        <div class="space-y-4 mt-2">
          <TextInput
            v-model="form.tab_name"
            label="Nama Tab"
            placeholder="Ex: Uang Makan, BPJS"
            required
            @input="onTabNameInput"
          />

          <TextInput
            v-model="form.tab_id"
            label="ID Tab"
            placeholder="Ex: meal_allowance, bpjs_group"
            required
            :disabled="isEditing"
            helper="Kunci unik berupa snake_case. Tidak bisa diubah setelah dibuat."
          />

          <SelectInput
            v-model="form.group_label"
            label="Hubungkan ke Label Master Group"
            :options="groupLabelOptions"
            placeholder="Pilih Label Master..."
            required
            helper="Kelompok kolom Kanban akan dibuat dari opsi Master Group yang memiliki label ini."
          />

          <div class="grid grid-cols-3 gap-2 items-end">
            <div class="col-span-2">
              <TextInput
                v-model="form.icon"
                label="Icon (Boxicon)"
                placeholder="Ex: bx-restaurant, bx-plus-medical"
              />
            </div>
            <div class="col-span-1 pb-2 flex justify-center">
              <div class="w-10 h-10 rounded-lg bg-(--bg-soft) border border-(--border-soft) flex items-center justify-center text-xl text-(--text-main)">
                <i class="bx" :class="form.icon || 'bx-folder'"></i>
              </div>
            </div>
          </div>

          <TextInput
            v-model="form.sort_order"
            type="number"
            label="Urutan Tampilan (Sort Order)"
            placeholder="0"
          />

          <!-- Filters Section -->
          <div class="border-t border-(--border-soft) pt-3">
            <label class="block text-sm font-medium text-(--text-main) mb-2">
              Filter Status Kepegawaian (Opsional)
            </label>
            <div class="grid grid-cols-2 gap-2">
              <div v-for="opt in statusOptions" :key="opt.value" class="flex items-center gap-2">
                <input
                  type="checkbox"
                  :id="`status-${opt.value}`"
                  :value="opt.value"
                  v-model="form.filters.employment_status"
                  class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary-glow)"
                />
                <label :for="`status-${opt.value}`" class="text-xs text-(--text-main) cursor-pointer select-none">
                  {{ opt.label }}
                </label>
              </div>
            </div>
            <p class="text-[10px] text-(--text-muted) mt-2">
              Jika tidak dicentang, semua tipe karyawan akan muncul di tab ini.
            </p>
          </div>

          <!-- Status Active -->
          <div class="flex items-center gap-2 pt-2">
            <input
              type="checkbox"
              id="is_active"
              v-model="form.is_active"
              class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary-glow)"
            />
            <label for="is_active" class="text-sm font-medium text-(--text-main) cursor-pointer select-none">
              Tab Aktif / Tampilkan
            </label>
          </div>

          <div class="flex gap-2 pt-3 border-t border-(--border-soft)">
            <BaseButton variant="primary" size="md" class="flex-1" @click="saveSetting">
              {{ isEditing ? 'Simpan Perubahan' : 'Tambah Tab' }}
            </BaseButton>
            <BaseButton v-if="isEditing" variant="secondary" size="md" @click="resetForm">
              Batal
            </BaseButton>
          </div>
        </div>
      </BaseCard>
    </div>

    <!-- Table Area -->
    <div class="lg:col-span-2">
      <BaseCard>
        <template #title>Daftar Tab Grouping Dinamis</template>
        <DataTable :headers="tableHeaders" :items="settings" class="mt-4">
          <template #item.icon="{ value }">
            <div class="flex items-center gap-2">
              <i class="bx text-lg" :class="value || 'bx-folder'"></i>
              <span class="text-xs text-(--text-muted)">{{ value }}</span>
            </div>
          </template>
          
          <template #item.filters="{ item }">
            <div v-if="item.filters && item.filters.employment_status && item.filters.employment_status.length" class="flex flex-wrap gap-1">
              <Badge v-for="status in item.filters.employment_status" :key="status" variant="info" class="text-[10px] px-1 py-0.5">
                {{ getStatusLabel(status) }}
              </Badge>
            </div>
            <span v-else class="text-xs text-(--text-muted)">Semua Karyawan</span>
          </template>

          <template #item.is_active="{ value }">
            <Badge :variant="value ? 'success' : 'neutral'">{{ value ? 'Aktif' : 'Nonaktif' }}</Badge>
          </template>

          <template #item.actions="{ item }">
            <div class="flex items-center gap-2">
              <button @click="editSetting(item)" class="p-1 text-(--text-muted) hover:text-(--color-primary) transition-colors" title="Edit">
                <IconPencil class="w-4 h-4" />
              </button>
              <button @click="deleteSetting(item.id)" class="p-1 text-(--text-muted) hover:text-red-500 transition-colors" title="Hapus">
                <i class="bx bx-trash text-lg"></i>
              </button>
            </div>
          </template>
        </DataTable>
      </BaseCard>
    </div>
  </div>
</template>
