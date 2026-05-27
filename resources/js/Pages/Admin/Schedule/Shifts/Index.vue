<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Shift Kerja</h1>
        <p class="text-sm text-(--text-muted) mt-1">Atur shift kerja karyawan</p>
      </div>
      <BaseButton variant="primary" @click="openForm(null)">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Shift
      </BaseButton>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="shifts" showSearch>
        <template #item.code="{ value }">
          <Badge variant="primary">{{ value }}</Badge>
        </template>
        <template #item.color="{ value }">
          <div class="flex items-center gap-2">
            <span class="w-4 h-4 rounded-full border border-(--border-soft) inline-block" :style="{ backgroundColor: value }"></span>
            <span class="text-xs text-(--text-muted) font-mono">{{ value }}</span>
          </div>
        </template>
        <template #item.start_time="{ item }">
          <span class="text-(--text-main)">{{ item.start_time }} - {{ item.end_time }}</span>
        </template>
        <template #item.break_start="{ item }">
          <span class="text-(--text-main)">{{ item.break_start }} - {{ item.break_end }}</span>
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
      <Pagination :current-page="1" :total-pages="1" :total="shifts.length" :per-page="10" @page-change="() => {}" />
    </BaseCard>

    <BaseModal :show="!!formVisible" :title="editingItem?.id ? 'Edit Shift' : 'Tambah Shift'" @close="formVisible = null">
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.name" label="Nama Shift" placeholder="Contoh: Shift Pagi" />
          <TextInput v-model="form.code" label="Kode" placeholder="Contoh: PG" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.start_time" label="Jam Masuk" type="time" />
          <TextInput v-model="form.end_time" label="Jam Pulang" type="time" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.break_start" label="Istirahat Mulai" type="time" />
          <TextInput v-model="form.break_end" label="Istirahat Selesai" type="time" />
        </div>
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Warna Shift</label>
          <div class="flex items-center gap-3">
            <input
              type="color"
              v-model="form.color"
              class="w-10 h-10 rounded-md border border-(--border-soft) cursor-pointer p-0.5"
            />
            <span class="text-sm text-(--text-muted) font-mono">{{ form.color }}</span>
            <div class="flex gap-1">
              <button
                v-for="preset in colorPresets"
                :key="preset"
                class="w-6 h-6 rounded-full border border-(--border-soft) transition-transform hover:scale-110"
                :style="{ backgroundColor: preset }"
                @click="form.color = preset"
              ></button>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="formVisible = null">Batal</BaseButton>
        <BaseButton variant="primary" @click="saveShift">Simpan</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="!!confirmDelete"
      title="Hapus Shift"
      :message="'Apakah Anda yakin ingin menghapus shift \x22' + confirmDelete?.name + '\x22?'"
      confirm-text="Hapus"
      variant="danger"
      @confirm="deleteShift"
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
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import { IconPlus, IconPencil, IconTrash } from '../../../../Components/Icons/index.js'

const colorPresets = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16']

const headers = [
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Nama' },
  { key: 'start_time', label: 'Jam Masuk' },
  { key: 'end_time', label: 'Jam Pulang' },
  { key: 'break_start', label: 'Istirahat' },
  { key: 'color', label: 'Warna' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '100px' },
]

const shifts = ref([
  { id: 1, name: 'Shift Pagi', code: 'PG', start_time: '07:00', end_time: '15:00', break_start: '12:00', break_end: '13:00', color: '#3b82f6' },
  { id: 2, name: 'Shift Siang', code: 'SG', start_time: '15:00', end_time: '23:00', break_start: '18:00', break_end: '19:00', color: '#f59e0b' },
  { id: 3, name: 'Shift Malam', code: 'ML', start_time: '23:00', end_time: '07:00', break_start: '02:00', break_end: '03:00', color: '#8b5cf6' },
  { id: 4, name: 'Non Shift', code: 'NS', start_time: '08:00', end_time: '17:00', break_start: '12:00', break_end: '13:00', color: '#10b981' },
])

const formVisible = ref(null)
const editingItem = ref(null)
const confirmDelete = ref(null)

const form = reactive({
  name: '',
  code: '',
  start_time: '',
  end_time: '',
  break_start: '',
  break_end: '',
  color: '#3b82f6',
})

function openForm(item) {
  editingItem.value = item
  if (item) {
    form.name = item.name
    form.code = item.code
    form.start_time = item.start_time
    form.end_time = item.end_time
    form.break_start = item.break_start
    form.break_end = item.break_end
    form.color = item.color
  } else {
    form.name = ''
    form.code = ''
    form.start_time = ''
    form.end_time = ''
    form.break_start = ''
    form.break_end = ''
    form.color = '#3b82f6'
  }
  formVisible.value = {}
}

function saveShift() {
  const item = {
    id: editingItem.value?.id || shifts.value.length + 1,
    name: form.name,
    code: form.code,
    start_time: form.start_time,
    end_time: form.end_time,
    break_start: form.break_start,
    break_end: form.break_end,
    color: form.color,
  }
  if (editingItem.value?.id) {
    const idx = shifts.value.findIndex((s) => s.id === editingItem.value.id)
    if (idx !== -1) shifts.value[idx] = item
  } else {
    shifts.value.push(item)
  }
  formVisible.value = null
  editingItem.value = null
}

function deleteShift() {
  if (confirmDelete.value) {
    shifts.value = shifts.value.filter((s) => s.id !== confirmDelete.value.id)
    confirmDelete.value = null
  }
}
</script>
