<template>
  <div class="space-y-6">
    <BaseCard>
      <template #title>Pengaturan THR (Tunjangan Hari Raya)</template>
      <template #subtitle>Atur persentase perhitungan THR berdasarkan masa kerja (dalam bulan)</template>
      <template #actions>
        <BaseButton variant="primary" size="sm" @click="openForm(null)">
          <template #icon-left>
            <IconPlus class="w-4 h-4" />
          </template>
          Tambah Aturan THR
        </BaseButton>
      </template>

      <DataTable :headers="headers" :items="configs">
        <template #item.range="{ item }">
          <span class="font-medium">
            {{ item.min_months }} {{ item.max_months ? `- ${item.max_months}` : '+' }} Bulan
          </span>
        </template>
        <template #item.percentage="{ value }">
          <Badge variant="primary">{{ value }}%</Badge>
        </template>
        <template #item.is_prorated="{ value }">
          <span :class="value ? 'text-(--primary) font-medium' : 'text-(--text-muted)'">
            {{ value ? 'Ya (Proporsional)' : 'Tidak (Fix)' }}
          </span>
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
            @click="deleteConfig(item)"
          >
            <IconTrash class="w-4 h-4" />
          </button>
        </template>
      </DataTable>
    </BaseCard>

    <BaseModal :show="!!form" :title="editingItem?.id ? 'Edit Aturan THR' : 'Tambah Aturan THR'" @close="form = null">
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="formData.min_months" type="number" label="Minimal Masa Kerja (Bulan)" placeholder="Contoh: 1" />
          <TextInput v-model="formData.max_months" type="number" label="Maksimal Masa Kerja (Bulan)" placeholder="Kosongkan jika ke atas" />
        </div>
        <TextInput v-model="formData.percentage" type="number" step="0.01" label="Persentase Pengali (%)" placeholder="Contoh: 100" />
        
        <label class="flex items-center gap-2 text-sm text-(--text-main)">
          <input type="checkbox" v-model="formData.is_prorated" class="rounded border-(--border-soft)" />
          Hitung Prorata (Proporsional)
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
import { IconPlus, IconPencil, IconTrash } from '../../../../../Components/Icons/index.js'
import { useApi } from '../../../../../composables/useApi'
import { useNotification } from '../../../../../composables/useNotification'

const api = useApi()
const notification = useNotification()

const headers = [
  { key: 'range', label: 'Rentang Masa Kerja' },
  { key: 'is_prorated', label: 'Hitung Prorata' },
  { key: 'percentage', label: 'Persentase' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '100px' },
]

const configs = ref([])
const form = ref(null)
const editingItem = ref(null)
const formData = reactive({ min_months: 1, max_months: 11, is_prorated: true, percentage: 100, is_active: true })

async function fetchData() {
  try {
    const response = await api.get('/api/v1/settings/payroll-configs/thr')
    configs.value = response.data || []
  } catch (err) {
    notification.error('Gagal mengambil data pengaturan THR')
  }
}

onMounted(() => {
  fetchData()
})

function openForm(item) {
  editingItem.value = item
  if (item) {
    formData.min_months = item.min_months
    formData.max_months = item.max_months
    formData.is_prorated = item.is_prorated
    formData.percentage = item.percentage
    formData.is_active = item.is_active
  } else {
    formData.min_months = 1
    formData.max_months = 11
    formData.is_prorated = true
    formData.percentage = 100
    formData.is_active = true
  }
  form.value = {}
}

async function save() {
  try {
    const payload = { ...formData }
    if (payload.max_months === '') payload.max_months = null
    
    if (editingItem.value?.id) {
      await api.put(`/api/v1/settings/payroll-configs/thr/${editingItem.value.id}`, payload)
      notification.success('Aturan THR berhasil diupdate')
    } else {
      await api.post(`/api/v1/settings/payroll-configs/thr`, payload)
      notification.success('Aturan THR berhasil ditambahkan')
    }
    form.value = null
    fetchData()
  } catch (err) {
    notification.error('Gagal menyimpan aturan THR')
  }
}

async function deleteConfig(item) {
  if (!confirm(`Hapus aturan THR ini?`)) return
  try {
    await api.destroy(`/api/v1/settings/payroll-configs/thr/${item.id}`)
    notification.success('Aturan THR berhasil dihapus')
    fetchData()
  } catch (err) {
    notification.error('Gagal menghapus aturan THR')
  }
}
</script>
