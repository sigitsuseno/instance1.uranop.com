<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Shift Kerja</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola master shift kerja karyawan</p>
      </div>
      <BaseButton :disabled="auth.isManajemen" variant="primary" @click="auth.isManajemen ? null : openForm(null)">
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
        <template #item.name="{ item }">
          <div class="flex items-center gap-2">
            <span class="w-3.5 h-3.5 rounded-full border border-(--border-soft) inline-block shrink-0" :style="{ backgroundColor: item.color || '#3b82f6' }"></span>
            <span class="text-(--text-main) font-medium">{{ item.name }}</span>
          </div>
        </template>
        <template #item.work_pattern="{ item }">
          <span class="text-xs text-(--text-muted) font-medium">
            {{ getWorkPatternName(item.work_pattern_id) || '-' }}
          </span>
        </template>
        <template #item.working_hours="{ item }">
          <span class="text-(--text-main) font-mono text-xs">{{ item.work_hour_start }} - {{ item.work_hour_end }}</span>
        </template>
        <template #item.check_in_range="{ item }">
          <span class="text-xs text-(--text-muted) font-mono">{{ item.check_in_start || '-' }} - {{ item.check_in_end || '-' }}</span>
        </template>
        <template #item.check_out_range="{ item }">
          <div class="flex items-center gap-1 font-mono text-xs text-(--text-muted)">
            <span>
              {{ item.is_overnight ? (item.check_out_overnight_start || '-') + ' - ' + (item.check_out_overnight_end || '-') : (item.check_out_start || '-') + ' - ' + (item.check_out_end || '-') }}
            </span>
            <span v-if="item.is_overnight" class="text-xs" title="Shift Melewati Tengah Malam">🌙</span>
          </div>
        </template>
        <template #item.status="{ item }">
          <div class="flex items-center gap-1">
            <Badge :variant="item.is_active ? 'success' : 'neutral'">
              {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
            </Badge>
            <Badge v-if="item.is_dayoff" variant="danger">Libur</Badge>
          </div>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <button
              class="p-1.5 rounded-md transition-colors"
              :class="auth.isManajemen ? 'text-(--text-muted)/50 cursor-not-allowed' : 'text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10'"
              :disabled="auth.isManajemen"
              title="Edit"
              @click="auth.isManajemen ? null : openForm(item)"
            >
              <IconPencil class="w-4 h-4" />
            </button>
            <button
              class="p-1.5 rounded-md transition-colors"
              :class="auth.isManajemen ? 'text-(--text-muted)/50 cursor-not-allowed' : 'text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10'"
              :disabled="auth.isManajemen"
              title="Hapus"
              @click="auth.isManajemen ? null : (confirmDelete = item)"
            >
              <IconTrash class="w-4 h-4" />
            </button>
          </div>
        </template>
      </DataTable>
      <Pagination :current-page="1" :total-pages="1" :total="shifts.length" :per-page="10" @page-change="() => {}" />
    </BaseCard>

    <BaseModal :show="!!formVisible" :title="editingItem?.id ? 'Edit Shift' : 'Tambah Shift'" size="lg" @close="formVisible = null">
      <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
        <!-- Basic Info -->
        <div class="grid grid-cols-4 gap-4">
          <div class="col-span-2">
            <TextInput v-model="form.name" label="Nama Shift" placeholder="Contoh: Shift Pagi Reguler" />
          </div>
          <div>
            <TextInput v-model="form.code" label="Kode Shift" placeholder="Contoh: PG" />
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Pola Kerja</label>
            <select v-model="form.work_pattern_id" class="w-full h-10 px-3 rounded-md bg-(--bg-card) border border-(--border-strong) text-sm text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)">
              <option :value="null">-- Semua Pola --</option>
              <option v-for="pattern in store.workPatterns" :key="pattern.id" :value="pattern.id">
                {{ pattern.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <TextInput v-model="form.external_code" label="External Code" placeholder="P, S, M, L" />
            <p class="text-[10px] text-(--text-muted) mt-1">Kode untuk import absensi</p>
          </div>
          <div>
            <TextInput v-model="form.work_hour_start" label="Jam Mulai" type="time" step="1" />
          </div>
          <div>
            <TextInput v-model="form.work_hour_end" label="Jam Selesai" type="time" step="1" />
          </div>
        </div>

        <!-- Range Check-in -->
        <div class="border-t border-(--border-soft) pt-3">
          <span class="text-sm font-semibold text-(--text-main) block mb-2">Range Check-in</span>
          <div class="grid grid-cols-2 gap-4">
            <TextInput v-model="form.check_in_start" label="Mulai Check-in" type="time" step="1" />
            <TextInput v-model="form.check_in_end" label="Akhir Check-in" type="time" step="1" />
          </div>
        </div>

        <!-- Range Check-out -->
        <div class="border-t border-(--border-soft) pt-3">
          <span class="text-sm font-semibold text-(--text-main) block mb-2">Range Check-out</span>
          <div class="grid grid-cols-2 gap-4">
            <TextInput v-model="form.check_out_start" label="Mulai Check-out" type="time" step="1" />
            <TextInput v-model="form.check_out_end" label="Akhir Check-out" type="time" step="1" />
          </div>
        </div>

        <!-- Overnight Shift -->
        <div class="border-t border-(--border-soft) pt-3">
          <label class="flex items-center gap-2 mb-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_overnight" class="rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow)" />
            <span class="text-sm font-medium text-(--text-main)">Shift Melewati Tengah Malam</span>
          </label>
          <div v-if="form.is_overnight" class="grid grid-cols-2 gap-4 ml-6">
            <TextInput v-model="form.check_out_overnight_start" label="Check-out Overnight Mulai" type="time" step="1" />
            <TextInput v-model="form.check_out_overnight_end" label="Check-out Overnight Selesai" type="time" step="1" />
          </div>
        </div>

        <!-- Rules -->
        <div class="border-t border-(--border-soft) pt-3">
          <span class="text-sm font-semibold text-(--text-main) block mb-2">Aturan Shift</span>
          <div class="grid grid-cols-3 gap-4">
            <TextInput v-model.number="form.tolerance_minutes" label="Toleransi (menit)" type="number" min="0" />
            <TextInput v-model.number="form.min_work_hours" label="Min Jam Kerja" type="number" min="0" />
            <div>
              <label class="block text-sm font-medium text-(--text-main) mb-1">Bisa Lembur</label>
              <select v-model="form.has_overtime" class="w-full h-10 px-3 rounded-md bg-(--bg-card) border border-(--border-strong) text-sm text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)">
                <option :value="true">Ya</option>
                <option :value="false">Tidak</option>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="form.has_modifier" class="rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow)" />
              <span class="text-sm font-medium text-(--text-main)">Punya Modifier</span>
              <span class="text-xs text-(--text-muted)">(perlakuan khusus perhitungan lembur)</span>
            </label>
          </div>
          <div v-if="form.has_modifier" class="grid grid-cols-1 gap-3 mt-3 ml-6 p-3 border-l-2 border-(--primary) bg-(--bg-elevated) rounded-r-lg">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="form.is_special" class="rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow)" />
              <span class="text-sm font-medium text-(--text-main)">Slot Spesial</span>
              <span class="text-xs text-(--text-muted)">(ada jam alternatif)</span>
            </label>
            <div v-if="form.is_special" class="grid grid-cols-2 gap-3">
              <TextInput v-model="form.special_hour_start" label="Jam Spesial Mulai" type="time" />
              <TextInput v-model="form.special_hour_end" label="Jam Spesial Selesai" type="time" />
            </div>
          </div>
        </div>

        <!-- Status Details -->
        <div class="border-t border-(--border-soft) pt-3 space-y-2">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_dayoff" class="rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow)" />
            <span class="text-sm font-medium text-(--text-main)">Ini adalah shift libur</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_active" class="rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow)" />
            <span class="text-sm font-medium text-(--text-main)">Aktif</span>
          </label>
        </div>

        <!-- Color Preset picker -->
        <div class="border-t border-(--border-soft) pt-3">
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
      :message="`Apakah Anda yakin ingin menghapus shift '${confirmDelete?.name}'?`"
      confirm-text="Hapus"
      variant="danger"
      @confirm="deleteShift"
      @cancel="confirmDelete = null"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useScheduleStore } from '../../../../Stores/schedule'
import { useAuth } from '../../../../composables/useAuth'
import DataTable from '../../../../Components/Table/DataTable.vue'
import Pagination from '../../../../Components/Table/Pagination.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import Badge from '../../../../Components/Badge.vue'
import TextInput from '../../../../Components/TextInput.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import { IconPlus, IconPencil, IconTrash } from '../../../../Components/Icons/index.js'

const store = useScheduleStore()
const auth = useAuth()

onMounted(() => {
  store.fetchShifts()
  if (store.workPatterns.length === 0) {
    store.fetchWorkPatterns()
  }
})

const colorPresets = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16']

const headers = [
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Nama Shift' },
  { key: 'work_pattern', label: 'Pola Kerja' },
  { key: 'external_code', label: 'External' },
  { key: 'working_hours', label: 'Jam Kerja' },
  { key: 'check_in_range', label: 'Check-in' },
  { key: 'check_out_range', label: 'Check-out' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '100px' },
]

const shifts = computed(() => store.shifts)

function getWorkPatternName(id) {
  if (!id) return null
  const pattern = store.workPatterns.find(p => p.id === id)
  return pattern ? pattern.name : null
}

const formVisible = ref(null)
const editingItem = ref(null)
const confirmDelete = ref(null)

const form = reactive({
  work_pattern_id: null,
  name: '',
  code: '',
  external_code: '',
  work_hour_start: '',
  work_hour_end: '',
  check_in_start: '',
  check_in_end: '',
  check_out_start: '',
  check_out_end: '',
  is_overnight: false,
  check_out_overnight_start: '',
  check_out_overnight_end: '',
  tolerance_minutes: 15,
  min_work_hours: 8,
  has_overtime: true,
  has_modifier: false,
  is_special: false,
  special_hour_start: '',
  special_hour_end: '',
  is_dayoff: false,
  is_active: true,
  color: '#3b82f6',
})

function openForm(item) {
  editingItem.value = item
  if (item) {
    form.work_pattern_id = item.work_pattern_id || null
    form.name = item.name
    form.code = item.code
    form.external_code = item.external_code || ''
    form.work_hour_start = item.work_hour_start
    form.work_hour_end = item.work_hour_end
    form.check_in_start = item.check_in_start || ''
    form.check_in_end = item.check_in_end || ''
    form.check_out_start = item.check_out_start || ''
    form.check_out_end = item.check_out_end || ''
    form.is_overnight = !!item.is_overnight
    form.check_out_overnight_start = item.check_out_overnight_start || ''
    form.check_out_overnight_end = item.check_out_overnight_end || ''
    form.tolerance_minutes = item.tolerance_minutes ?? 15
    form.min_work_hours = item.min_work_hours ?? 8
    form.has_overtime = item.has_overtime ?? true
    form.has_modifier = item.has_modifier ?? false
    const meta = item.metadata || {}
    form.special_hour_start = meta.special_hour_start || ''
    form.special_hour_end = meta.special_hour_end || ''
    form.is_special = meta.is_special || false
    form.is_dayoff = !!item.is_dayoff
    form.is_active = item.is_active ?? true
    form.color = item.color || '#3b82f6'
  } else {
    form.work_pattern_id = null
    form.name = ''
    form.code = ''
    form.external_code = ''
    form.work_hour_start = '08:00:00'
    form.work_hour_end = '17:00:00'
    form.check_in_start = '07:30:00'
    form.check_in_end = '08:30:00'
    form.check_out_start = '17:00:00'
    form.check_out_end = '18:00:00'
    form.is_overnight = false
    form.check_out_overnight_start = ''
    form.check_out_overnight_end = ''
    form.tolerance_minutes = 15
    form.min_work_hours = 8
    form.has_overtime = true
    form.has_modifier = false
    form.is_special = false
    form.special_hour_start = ''
    form.special_hour_end = ''
    form.is_dayoff = false
    form.is_active = true
    form.color = '#3b82f6'
  }
  formVisible.value = {}
}

async function saveShift() {
  const payload = {
    work_pattern_id: form.work_pattern_id,
    name: form.name,
    code: form.code,
    external_code: form.external_code,
    work_hour_start: form.work_hour_start,
    work_hour_end: form.work_hour_end,
    check_in_start: form.check_in_start,
    check_in_end: form.check_in_end,
    check_out_start: form.check_out_start,
    check_out_end: form.check_out_end,
    is_overnight: form.is_overnight,
    check_out_overnight_start: form.is_overnight ? form.check_out_overnight_start : null,
    check_out_overnight_end: form.is_overnight ? form.check_out_overnight_end : null,
    tolerance_minutes: form.tolerance_minutes,
    min_work_hours: form.min_work_hours,
    has_overtime: form.has_overtime,
    has_modifier: form.has_modifier,
    is_dayoff: form.is_dayoff,
    is_active: form.is_active,
    metadata: {
      color: form.color,
      ...(form.has_modifier ? {
        work_hour_start: form.work_hour_start,
        work_hour_end: form.work_hour_end,
        is_special: form.is_special,
        special_hour_start: form.special_hour_start,
        special_hour_end: form.special_hour_end,
      } : {}),
    },
  }

  try {
    if (editingItem.value?.id) {
      await store.updateShift(editingItem.value.id, payload)
    } else {
      await store.saveShift(payload)
    }
    formVisible.value = null
    editingItem.value = null
  } catch (error) {
    console.error('Failed to save shift', error)
  }
}

async function deleteShift() {
  if (confirmDelete.value) {
    try {
      await store.deleteShift(confirmDelete.value.id)
      confirmDelete.value = null
    } catch (error) {
      console.error('Failed to delete shift', error)
    }
  }
}
</script>
