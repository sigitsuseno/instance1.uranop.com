<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Aturan Jam Kerja &amp; Denda</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Konfigurasi deteksi keterlambatan, pulang cepat, dan nominal denda
        </p>
      </div>
      <BaseButton variant="primary" size="sm" @click="openForm(null)">
        <template #icon-left>＋</template>
        Tambah Aturan
      </BaseButton>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="configs">
        <template #item.name="{ item }">
          <div class="font-medium text-(--text-main)">{{ item.name }}</div>
          <div class="text-xs text-(--text-muted)">
            Pola: {{ item.work_pattern?.name || 'Semua Pola Kerja' }}
          </div>
        </template>
        <template #item.late_deducts_overtime="{ value }">
          <Badge :variant="value ? 'warning' : 'neutral'">{{ value ? 'Ya' : 'Tidak' }}</Badge>
        </template>
        <template #item.late_tolerance="{ value }">
          {{ value }} menit
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

    <BaseModal :show="!!form" :title="editingItem?.id ? 'Edit Aturan Denda' : 'Tambah Aturan Denda'" size="md" @close="form = null">
      <div class="space-y-4 p-2 max-h-[70vh] overflow-y-auto">
        <div>
          <label class="block text-xs mb-1">Nama Konfigurasi <span class="text-red-500">*</span></label>
          <input v-model="formData.name" type="text" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" placeholder="Contoh: Standar Denda" />
        </div>
        
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs mb-1">Toleransi Keterlambatan (menit)</label>
            <input v-model="formData.late_tolerance" type="number" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs mb-1">Potong Istirahat (menit)</label>
            <input v-model="formData.lm_rest_deduction" type="number" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 border-t pt-4">
          <div>
            <label class="block text-xs mb-1">Jam Kerja Normal (menit)</label>
            <input v-model="formData.normal_work_minutes" type="number" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs mb-1">Jam Kerja Sabtu (menit)</label>
            <input v-model="formData.saturday_work_minutes" type="number" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>

        <div class="border-t pt-4">
          <label class="flex items-center gap-2 text-sm text-(--text-main)">
            <input type="checkbox" v-model="formData.late_deducts_overtime" class="rounded border-(--border-soft)" />
            Keterlambatan mengurangi nilai uang makan/lembur
          </label>
        </div>

        <div>
          <label class="block text-xs mb-1 mt-4">Keterangan Tambahan</label>
          <textarea v-model="formData.description" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm"></textarea>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2 p-4 border-t mt-4">
          <BaseButton variant="secondary" @click="form = null">Batal</BaseButton>
          <BaseButton variant="primary" @click="save" :loading="saving">Simpan</BaseButton>
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
  { key: 'name', label: 'Nama Aturan' },
  { key: 'late_tolerance', label: 'Toleransi Telat' },
  { key: 'late_deducts_overtime', label: 'Denda Mengurangi Lembur' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '100px' },
]

const configs = ref([])
const form = ref(null)
const editingItem = ref(null)
const saving = ref(false)

const formData = reactive({
  name: '',
  work_pattern_id: null,
  normal_work_minutes: 420,
  saturday_work_minutes: 300,
  holiday_max_minutes: 420,
  shift_saturday_flat: 0,
  late_deducts_overtime: false,
  late_tolerance: 0,
  lm_rest_deduction: 60,
  rounding_interval: 15,
  rounding_threshold: 8,
  hourly_divisor: 173,
  description: '',
})

async function fetchData() {
  try {
    const response = await get('/api/v1/supervisor/master/scan-detection')
    configs.value = response.data?.data || response.data || []
  } catch (err) {
    alert('Gagal mengambil data aturan denda')
  }
}

onMounted(() => {
  fetchData()
})

function openForm(item) {
  editingItem.value = item
  if (item) {
    Object.assign(formData, item)
    formData.late_deducts_overtime = Boolean(Number(item.late_deducts_overtime))
  } else {
    Object.assign(formData, {
      name: '', work_pattern_id: null,
      normal_work_minutes: 420, saturday_work_minutes: 300, holiday_max_minutes: 420,
      shift_saturday_flat: 0, late_deducts_overtime: false, late_tolerance: 0,
      lm_rest_deduction: 60, rounding_interval: 15, rounding_threshold: 8,
      hourly_divisor: 173, description: ''
    })
  }
  form.value = {}
}

async function save() {
  saving.value = true
  try {
    const payload = { ...formData }
    if (editingItem.value?.id) {
      await put(`/api/v1/supervisor/master/scan-detection/${editingItem.value.id}`, payload)
    } else {
      await post(`/api/v1/supervisor/master/scan-detection`, payload)
    }
    form.value = null
    fetchData()
  } catch (err) {
    alert('Gagal menyimpan data')
  } finally {
    saving.value = false
  }
}

async function deleteConfig(item) {
  if (!confirm(`Hapus aturan ini?`)) return
  try {
    await destroy(`/api/v1/supervisor/master/scan-detection/${item.id}`)
    fetchData()
  } catch (err) {
    alert('Gagal menghapus data')
  }
}
</script>
