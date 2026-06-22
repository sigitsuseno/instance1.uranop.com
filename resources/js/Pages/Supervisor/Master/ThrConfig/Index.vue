<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Konfigurasi THR (Supervisor)</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Atur persentase perhitungan THR berdasarkan masa kerja (dalam bulan)
        </p>
      </div>
      <BaseButton variant="primary" size="sm" @click="openForm(null)">
        <template #icon-left>＋</template>
        Tambah Aturan THR
      </BaseButton>
    </div>

    <BaseCard>
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
            ✏️
          </button>
          <button
            class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors ml-2"
            @click="deleteConfig(item)"
          >
            🗑
          </button>
        </template>
      </DataTable>
    </BaseCard>

    <BaseModal :show="!!form" :title="editingItem?.id ? 'Edit Aturan THR' : 'Tambah Aturan THR'" @close="form = null">
      <div class="space-y-4 p-2">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs mb-1">Minimal Masa Kerja (Bulan) <span class="text-red-500">*</span></label>
            <input v-model="formData.min_months" type="number" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs mb-1">Maksimal Masa Kerja (Bulan)</label>
            <input v-model="formData.max_months" type="number" placeholder="Kosongkan jika ke atas" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>
        <div>
          <label class="block text-xs mb-1">Persentase Pengali (%) <span class="text-red-500">*</span></label>
          <input v-model="formData.percentage" type="number" step="0.01" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
        </div>
        
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
        <div class="flex justify-end gap-2 p-4 border-t">
          <BaseButton variant="secondary" @click="form = null">Batal</BaseButton>
          <BaseButton variant="primary" @click="save">Simpan</BaseButton>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import DataTable from '@/Components/Table/DataTable.vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseCard from '@/Components/BaseCard.vue'
import BaseModal from '@/Components/BaseModal.vue'
import Badge from '@/Components/Badge.vue'
import { useApi } from '@/composables/useApi'

const { get, post, put, destroy } = useApi()

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
    const response = await get('/api/v1/supervisor/master/thr-configs')
    configs.value = response.data?.data || response.data || []
  } catch (err) {
    alert('Gagal mengambil data pengaturan THR')
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
      await put(`/api/v1/supervisor/master/thr-configs/${editingItem.value.id}`, payload)
    } else {
      await post(`/api/v1/supervisor/master/thr-configs`, payload)
    }
    form.value = null
    fetchData()
  } catch (err) {
    alert('Gagal menyimpan aturan THR')
  }
}

async function deleteConfig(item) {
  if (!confirm(`Hapus aturan THR ini?`)) return
  try {
    await destroy(`/api/v1/supervisor/master/thr-configs/${item.id}`)
    fetchData()
  } catch (err) {
    alert('Gagal menghapus aturan THR')
  }
}
</script>
