<template>
  <div class="space-y-6">
    <BaseCard>
      <template #title>Daftar Komponen Gaji</template>
      <template #actions>
        <BaseButton variant="primary" size="sm" @click="openForm(null)">
          <template #icon-left>
            <IconPlus class="w-4 h-4" />
          </template>
          Tambah Komponen
        </BaseButton>
      </template>

      <DataTable :headers="headers" :items="components">
        <template #item.type="{ value }">
          <Badge :variant="value === 'allowance' ? 'success' : 'danger'">{{ value === 'allowance' ? 'Pendapatan' : 'Potongan' }}</Badge>
        </template>
        <template #item.is_taxable="{ value }">
          <span :class="value ? 'text-(--success)' : 'text-(--text-muted)'">{{ value ? 'Ya' : 'Tidak' }}</span>
        </template>
        <template #item.is_active="{ value }">
          <Badge :variant="value ? 'success' : 'neutral'">{{ value ? 'Aktif' : 'Nonaktif' }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <button
            class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
            @click="openForm(item)"
          >
            <IconPencil class="w-4 h-4" />
          </button>
          <button
            class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors ml-2"
            @click="deleteComponent(item)"
          >
            <IconTrash class="w-4 h-4" />
          </button>
        </template>
      </DataTable>
    </BaseCard>

    <BaseModal :show="!!form" :title="editingItem?.id ? 'Edit Komponen Gaji' : 'Tambah Komponen Gaji'" @close="form = null">
      <div class="space-y-4">
        <TextInput v-model="formData.code" label="Kode" placeholder="Contoh: BASIC_SALARY" />
        <TextInput v-model="formData.name" label="Nama" placeholder="Contoh: Gaji Pokok" />
        <SelectInput v-model="formData.type" label="Tipe" :options="[{ value: 'allowance', label: 'Pendapatan' }, { value: 'deduction', label: 'Potongan' }]" />
        <label class="flex items-center gap-2 text-sm text-(--text-main)">
          <input type="checkbox" v-model="formData.is_taxable" class="rounded border-(--border-soft)" />
          Komponen Kena Pajak
        </label>
        <label class="flex items-center gap-2 text-sm text-(--text-main)">
          <input type="checkbox" v-model="formData.is_active" class="rounded border-(--border-soft)" />
          Aktif
        </label>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="form = null">Batal</BaseButton>
        <BaseButton variant="primary" @click="save">Simpan</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import DataTable from '../../../../../Components/Table/DataTable.vue'
import BaseButton from '../../../../../Components/BaseButton.vue'
import BaseCard from '../../../../../Components/BaseCard.vue'
import BaseModal from '../../../../../Components/BaseModal.vue'
import Badge from '../../../../../Components/Badge.vue'
import TextInput from '../../../../../Components/TextInput.vue'
import SelectInput from '../../../../../Components/SelectInput.vue'
import { IconPlus, IconPencil, IconTrash } from '../../../../../Components/Icons/index.js'
import { useApi } from '../../../../../composables/useApi'
import { useNotification } from '../../../../../composables/useNotification'

const api = useApi()
const notification = useNotification()

const headers = [
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Nama' },
  { key: 'type', label: 'Tipe' },
  { key: 'is_taxable', label: 'Kena Pajak' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '100px' },
]

const components = ref([])
const form = ref(null)
const editingItem = ref(null)
const formData = reactive({ code: '', name: '', type: 'allowance', is_taxable: false, is_active: true })

async function fetchData() {
  try {
    const response = await api.get('/api/v1/settings/payroll-configs/components')
    components.value = response.data || []
  } catch (err) {
    notification.error('Gagal mengambil data komponen gaji')
  }
}

onMounted(() => {
  fetchData()
})

function openForm(item) {
  editingItem.value = item
  if (item) {
    formData.code = item.code
    formData.name = item.name
    formData.type = item.type
    formData.is_taxable = item.is_taxable
    formData.is_active = item.is_active
  } else {
    formData.code = ''
    formData.name = ''
    formData.type = 'allowance'
    formData.is_taxable = false
    formData.is_active = true
  }
  form.value = {}
}

async function save() {
  try {
    const payload = { ...formData }
    if (editingItem.value?.id) {
      await api.put(`/api/v1/settings/payroll-configs/components/${editingItem.value.id}`, payload)
      notification.success('Komponen berhasil diupdate')
    } else {
      await api.post(`/api/v1/settings/payroll-configs/components`, payload)
      notification.success('Komponen berhasil ditambahkan')
    }
    form.value = null
    fetchData()
  } catch (err) {
    notification.error('Gagal menyimpan komponen')
  }
}

async function deleteComponent(item) {
  if (!confirm(`Hapus komponen ${item.name}?`)) return
  try {
    await api.destroy(`/api/v1/settings/payroll-configs/components/${item.id}`)
    notification.success('Komponen berhasil dihapus')
    fetchData()
  } catch (err) {
    notification.error('Gagal menghapus komponen')
  }
}
</script>
