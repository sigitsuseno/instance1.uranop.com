<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Pola Jadwal Kerja</h1>
        <p class="text-sm text-(--text-muted) mt-1">Atur pola kerja mingguan karyawan</p>
      </div>
      <BaseButton variant="primary" @click="openForm(null)">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Pola
      </BaseButton>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="workPatterns" showSearch>
        <template #item.type="{ value }">
          <Badge :variant="typeVariant(value)">{{ value }}</Badge>
        </template>
        <template #item.working_days="{ value }">
          <span class="text-(--text-main)">{{ value }} Hari</span>
        </template>
        <template #item.total_hours="{ value }">
          <span class="text-(--text-main)">{{ value }} Jam</span>
        </template>
        <template #item.status="{ value }">
          <Badge :variant="value === 'active' ? 'success' : 'neutral'">{{ value === 'active' ? 'Aktif' : 'Nonaktif' }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
              title="Edit"
              @click="openForm(item)"
            >
              <IconPencil class="w-4 h-4" />
            </button>
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors"
              title="Hapus"
              @click="confirmDelete = item"
            >
              <IconTrash class="w-4 h-4" />
            </button>
          </div>
        </template>
      </DataTable>
      <Pagination :current-page="1" :total-pages="1" :total="workPatterns.length" :per-page="10" @page-change="() => {}" />
    </BaseCard>

    <BaseModal :show="!!formVisible" :title="editingItem?.id ? 'Edit Pola Jadwal Kerja' : 'Tambah Pola Jadwal Kerja'" size="lg" @close="formVisible = null">
      <div class="space-y-4">
        <TextInput v-model="form.name" label="Nama Pola" placeholder="Contoh: Pola 5 Hari Kerja" />
        <SelectInput
          v-model="form.type"
          label="Tipe"
          :options="[
            { value: 'Regular', label: 'Regular' },
            { value: 'Shift', label: 'Shift' },
            { value: 'Custom', label: 'Custom' },
          ]"
        />
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-2">Hari Kerja</label>
          <div class="grid grid-cols-7 gap-2">
            <label
              v-for="day in days"
              :key="day.value"
              :class="[
                'flex flex-col items-center gap-1 p-2 rounded-md border cursor-pointer transition-colors text-sm',
                form.working_day_list.includes(day.value)
                  ? 'border-(--primary) bg-(--primary)/5 text-(--primary)'
                  : 'border-(--border-soft) text-(--text-muted) hover:border-(--border-soft)',
              ]"
            >
              <input type="checkbox" :value="day.value" v-model="form.working_day_list" class="sr-only" />
              <span class="font-medium">{{ day.label }}</span>
            </label>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.start_time" label="Jam Masuk" type="time" />
          <TextInput v-model="form.end_time" label="Jam Pulang" type="time" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.break_start" label="Istirahat Mulai" type="time" />
          <TextInput v-model="form.break_end" label="Istirahat Selesai" type="time" />
        </div>
        <TextInput v-model="form.description" label="Deskripsi" placeholder="Deskripsi pola kerja" />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="formVisible = null">Batal</BaseButton>
        <BaseButton variant="primary" @click="savePattern">Simpan</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="!!confirmDelete"
      title="Hapus Pola Kerja"
      :message="'Apakah Anda yakin ingin menghapus pola \x22' + confirmDelete?.name + '\x22?'"
      confirm-text="Hapus"
      variant="danger"
      @confirm="deletePattern"
      @cancel="confirmDelete = null"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import Pagination from '../../../../Components/Table/Pagination.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import Badge from '../../../../Components/Badge.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import { IconPlus, IconPencil, IconTrash } from '../../../../Components/Icons/index.js'

const days = [
  { value: 'monday', label: 'Sen' },
  { value: 'tuesday', label: 'Sel' },
  { value: 'wednesday', label: 'Rab' },
  { value: 'thursday', label: 'Kam' },
  { value: 'friday', label: 'Jum' },
  { value: 'saturday', label: 'Sab' },
  { value: 'sunday', label: 'Min' },
]

const headers = [
  { key: 'name', label: 'Nama' },
  { key: 'type', label: 'Tipe' },
  { key: 'working_days', label: 'Hari Kerja' },
  { key: 'total_hours', label: 'Total Jam' },
  { key: 'description', label: 'Deskripsi' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '100px' },
]

const workPatterns = ref([
  { id: 1, name: 'Pola 5 Hari Kerja', type: 'Regular', working_days: 5, total_hours: 40, description: 'Senin-Jumat, 08:00-17:00', status: 'active', working_day_list: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], start_time: '08:00', end_time: '17:00', break_start: '12:00', break_end: '13:00' },
  { id: 2, name: 'Pola 6 Hari Kerja', type: 'Regular', working_days: 6, total_hours: 40, description: 'Senin-Sabtu, 08:00-14:40', status: 'active', working_day_list: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], start_time: '08:00', end_time: '14:40', break_start: '12:00', break_end: '12:30' },
  { id: 3, name: 'Pola Shift Pagi', type: 'Shift', working_days: 5, total_hours: 40, description: 'Shift bergilir pagi, 07:00-15:00', status: 'active', working_day_list: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], start_time: '07:00', end_time: '15:00', break_start: '11:00', break_end: '12:00' },
  { id: 4, name: 'Pola Shift Malam', type: 'Shift', working_days: 5, total_hours: 40, description: 'Shift bergilir malam, 23:00-07:00', status: 'active', working_day_list: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], start_time: '23:00', end_time: '07:00', break_start: '02:00', break_end: '03:00' },
  { id: 5, name: 'Pola Remote Fleksibel', type: 'Custom', working_days: 5, total_hours: 40, description: 'Jam kerja fleksibel untuk remote worker', status: 'active', working_day_list: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], start_time: '07:00', end_time: '18:00', break_start: '12:00', break_end: '13:00' },
])

const formVisible = ref(null)
const editingItem = ref(null)
const confirmDelete = ref(null)

const form = reactive({
  name: '',
  type: 'Regular',
  working_day_list: [],
  start_time: '08:00',
  end_time: '17:00',
  break_start: '12:00',
  break_end: '13:00',
  description: '',
})

function typeVariant(type) {
  const map = { Regular: 'primary', Shift: 'warning', Custom: 'info' }
  return map[type] || 'neutral'
}

function openForm(item) {
  editingItem.value = item
  if (item) {
    form.name = item.name
    form.type = item.type
    form.working_day_list = [...(item.working_day_list || [])]
    form.start_time = item.start_time
    form.end_time = item.end_time
    form.break_start = item.break_start
    form.break_end = item.break_end
    form.description = item.description
  } else {
    form.name = ''
    form.type = 'Regular'
    form.working_day_list = []
    form.start_time = '08:00'
    form.end_time = '17:00'
    form.break_start = '12:00'
    form.break_end = '13:00'
    form.description = ''
  }
  formVisible.value = {}
}

function savePattern() {
  const item = {
    id: editingItem.value?.id || workPatterns.value.length + 1,
    name: form.name,
    type: form.type,
    working_days: form.working_day_list.length,
    total_hours: form.working_day_list.length * 8,
    description: form.description || `${form.name}, ${form.start_time}-${form.end_time}`,
    status: 'active',
    working_day_list: [...form.working_day_list],
    start_time: form.start_time,
    end_time: form.end_time,
    break_start: form.break_start,
    break_end: form.break_end,
  }
  if (editingItem.value?.id) {
    const idx = workPatterns.value.findIndex((p) => p.id === editingItem.value.id)
    if (idx !== -1) workPatterns.value[idx] = item
  } else {
    workPatterns.value.push(item)
  }
  formVisible.value = null
  editingItem.value = null
}

function deletePattern() {
  if (confirmDelete.value) {
    workPatterns.value = workPatterns.value.filter((p) => p.id !== confirmDelete.value.id)
    confirmDelete.value = null
  }
}
</script>
