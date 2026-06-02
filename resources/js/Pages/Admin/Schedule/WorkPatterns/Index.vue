<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Pola Kerja</h1>
        <p class="text-sm text-(--text-muted) mt-1">Pola dasar jadwal kerja karyawan — digunakan untuk generate roster</p>
      </div>
      <BaseButton variant="primary" @click="openForm(null)">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Pola
      </BaseButton>
    </div>

    <BaseCard>
      <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat data pola kerja...</p>
      </div>

      <template v-else>
        <DataTable :headers="headers" :items="workPatterns" showSearch>
          <template #item.code="{ value }">
            <span class="inline-flex items-center px-2 py-1 rounded-md bg-(--primary)/10 text-(--primary) font-mono text-xs">
              {{ value }}
            </span>
          </template>
          <template #item.name="{ item }">
            <div class="font-medium text-(--text-main)">{{ item.name }}</div>
            <div v-if="item.description" class="text-xs text-(--text-muted) truncate max-w-[300px] mt-1">{{ item.description }}</div>
          </template>
          <template #item.employee_type="{ item, value }">
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-(--primary)/10 text-(--primary)">
              {{ item.work_pattern_type ? (item.work_pattern_type.label || item.work_pattern_type.name) : value }}
            </span>
          </template>
          <template #item.config="{ item }">
            <div class="space-y-1 text-xs text-(--text-muted)">
              <div class="flex items-center gap-1">
                <span>📅 {{ item.work_day }} Hari/Minggu</span>
              </div>
              <div class="flex items-center gap-1">
                <span>⏱️ Sabtu: {{ getSatTypeLabel(item.sat_type) }}</span>
              </div>
              <div class="flex items-center gap-1">
                <span>✂️ Cut-off: Tanggal {{ item.cut_off_date }}</span>
              </div>
            </div>
          </template>
          <template #item.details_count="{ item }">
            <Badge :variant="item.details && item.details.length > 0 ? 'success' : 'warning'">
              {{ item.details ? item.details.length : 0 }} Detail
            </Badge>
          </template>
          <template #item.status="{ item }">
            <button @click="toggleStatus(item)" class="px-2 py-1 text-xs rounded-md border border-(--border-soft) transition-all"
              :class="item.is_active ? 'bg-green-500/10 text-green-600' : 'bg-(--bg-elevated) text-(--text-muted)'">
              {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
            </button>
          </template>
          <template #item.actions="{ item }">
            <div class="flex items-center gap-1">
              <button
                class="inline-flex items-center h-8 px-2 text-xs font-semibold text-(--primary) bg-(--primary)/10 hover:bg-(--primary)/20 rounded-md transition-colors gap-1"
                title="Atur Siklus Detail"
                @click="openDetailsPage(item)"
              >
                <span>Siklus</span>
              </button>
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
      </template>
    </BaseCard>

    <!-- Modal Form -->
    <BaseModal :show="!!formVisible" :title="editingItem?.id ? 'Edit Pola Kerja' : 'Tambah Pola Kerja'" size="lg" @close="formVisible = null">
      <div class="space-y-4">
        <div class="grid grid-cols-3 gap-4">
          <div class="col-span-2">
            <TextInput v-model="form.name" label="Nama Pola Kerja" placeholder="Contoh: Pola 5 Hari Kerja Staff" />
          </div>
          <div>
            <TextInput v-model="form.code" label="Kode Pola" placeholder="Contoh: PL-5D" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium mb-1 text-(--text-muted)">Tipe Pola</label>
            <select
              v-model="form.employee_type"
              class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300"
            >
              <option v-for="t in workPatternTypes" :key="t.code" :value="t.code">
                {{ t.label || t.name }}
              </option>
            </select>
          </div>
          <SelectInput
            v-model="form.sat_type"
            label="Tipe Hari Sabtu"
            :options="[
              { value: 'off', label: 'Libur (Off)' },
              { value: 'half', label: 'Setengah Hari (Half)' },
              { value: 'full', label: 'Kerja Penuh (Full)' },
            ]"
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model.number="form.work_day" label="Hari Kerja Per Minggu" type="number" min="1" max="7" />
          <TextInput v-model.number="form.cut_off_date" label="Tanggal Cut-off" type="number" min="1" max="31" />
        </div>

        <TextInput v-model="form.description" label="Deskripsi Pola Kerja" placeholder="Deskripsi singkat mengenai aturan pola kerja ini" />

        <div class="space-y-2">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_active" class="rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow)" />
            <span class="text-sm font-medium text-(--text-main)">Aktif</span>
          </label>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="formVisible = null">Batal</BaseButton>
        <BaseButton variant="primary" @click="savePattern">Simpan</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="!!confirmDelete"
      title="Hapus Pola Kerja"
      :message="`Apakah Anda yakin ingin menghapus pola kerja '${confirmDelete?.name}'?`"
      confirm-text="Hapus"
      variant="danger"
      @confirm="deletePattern"
      @cancel="confirmDelete = null"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import { useScheduleStore } from '../../../../Stores/schedule'
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

const { get, post, put, destroy } = useApi()
const notificationStore = useNotificationStore()
const router = useRouter()

const headers = [
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Nama Pola' },
  { key: 'employee_type', label: 'Tipe Pola' },
  { key: 'config', label: 'Konfigurasi' },
  { key: 'details_count', label: 'Siklus' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '150px' },
]

const store = useScheduleStore()

const workPatterns = computed(() => store.workPatterns)
const workPatternTypes = ref([])
const loading = ref(false)

const fetchWorkPatterns = async () => {
  loading.value = true
  try {
    const [_, typesResponse] = await Promise.all([
      store.fetchWorkPatterns(),
      get('/api/v1/settings/work-pattern-types')
    ])
    workPatternTypes.value = typesResponse.data || []
    
    // Set default employee_type if not set and options exist
    if (workPatternTypes.value.length > 0 && !form.employee_type) {
      form.employee_type = workPatternTypes.value[0].code
    }
  } catch (error) {
    notificationStore.addNotification('Gagal mengambil data', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchWorkPatterns()
})

const formVisible = ref(null)
const editingItem = ref(null)
const confirmDelete = ref(null)

const form = reactive({
  name: '',
  code: '',
  employee_type: '',
  work_day: 5,
  sat_type: 'off',
  cut_off_date: 25,
  description: '',
  is_active: true,
})

function getSatTypeLabel(type) {
  const map = { off: 'Libur', half: '½ Hari Kerja', full: 'Kerja Penuh' }
  return map[type] || type
}

function openForm(item) {
  editingItem.value = item
  if (item) {
    form.name = item.name
    form.code = item.code
    form.employee_type = item.employee_type || (workPatternTypes.value[0]?.code || '')
    form.work_day = item.work_day || 5
    form.sat_type = item.sat_type || 'off'
    form.cut_off_date = item.cut_off_date || 25
    form.description = item.description || ''
    form.is_active = item.is_active ?? true
  } else {
    form.name = ''
    form.code = ''
    form.employee_type = workPatternTypes.value[0]?.code || ''
    form.work_day = 5
    form.sat_type = 'off'
    form.cut_off_date = 25
    form.description = ''
    form.is_active = true
  }
  formVisible.value = {}
}

async function savePattern() {
  const payload = {
    name: form.name,
    code: form.code,
    employee_type: form.employee_type,
    work_day: form.work_day,
    sat_type: form.sat_type,
    cut_off_date: form.cut_off_date,
    description: form.description,
    is_active: form.is_active,
  }
  
  try {
    if (editingItem.value?.id) {
      await put(`/api/schedule/work-patterns/${editingItem.value.id}`, payload)
      notificationStore.addNotification('Pola kerja berhasil diperbarui', 'success')
    } else {
      await post('/api/schedule/work-patterns', payload)
      notificationStore.addNotification('Pola kerja berhasil ditambahkan', 'success')
    }
    fetchWorkPatterns()
    formVisible.value = null
    editingItem.value = null
  } catch (error) {
    notificationStore.addNotification('Gagal menyimpan pola kerja', 'error')
  }
}

async function deletePattern() {
  if (confirmDelete.value) {
    try {
      await destroy(`/api/schedule/work-patterns/${confirmDelete.value.id}`)
      notificationStore.addNotification('Pola kerja berhasil dihapus', 'success')
      fetchWorkPatterns()
    } catch (error) {
      notificationStore.addNotification('Gagal menghapus pola kerja', 'error')
    }
    confirmDelete.value = null
  }
}

async function toggleStatus(pattern) {
  try {
    await put(`/api/schedule/work-patterns/${pattern.id}`, {
      ...pattern,
      is_active: !pattern.is_active
    })
    pattern.is_active = !pattern.is_active
    notificationStore.addNotification('Status pola kerja berhasil diubah', 'success')
  } catch (error) {
    notificationStore.addNotification('Gagal mengubah status', 'error')
  }
}

function openDetailsPage(pattern) {
  router.push({ name: 'schedule.work-patterns.details', params: { id: pattern.id } })
}
</script>
