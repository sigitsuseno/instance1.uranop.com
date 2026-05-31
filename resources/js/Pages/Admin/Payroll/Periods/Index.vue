<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Periode Generate Gaji</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola periode penggajian karyawan</p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Periode Baru
      </BaseButton>
    </div>

    <!-- Dashboard cards removed -->

    <BaseCard>
      <DataTable :headers="headers" :items="periods" showSearch>
        <template #item.period_code="{ value }">
          <span class="font-medium text-(--primary)">{{ value }}</span>
        </template>
        <template #item.date_range="{ item }">
          <span class="text-sm">{{ item.start_date }} s/d {{ item.end_date }}</span>
        </template>
        <template #item.is_split="{ value }">
          <Badge :variant="value ? 'primary' : 'neutral'">{{ value ? 'Ya' : 'Tidak' }}</Badge>
        </template>
        <template #item.status="{ value }">
          <Badge :variant="statusVariant(value)">{{ statusLabel(value) }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
              title="Edit" @click="openEditModal(item)">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors"
              title="Hapus" @click="confirmDelete = item">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18" />
                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                <line x1="10" y1="11" x2="10" y2="17" />
                <line x1="14" y1="11" x2="14" y2="17" />
              </svg>
            </button>
          </div>
        </template>
      </DataTable>
      <Pagination :current-page="1" :total-pages="1" :total="periods.length" :per-page="10" @page-change="() => { }" />
    </BaseCard>

    <BaseModal :show="showCreateModal" title="+ Periode Baru" @close="showCreateModal = false">
      <div class="space-y-4">
        <TextInput v-model="form.name" label="Nama Periode" placeholder="Contoh: Juni 2026" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.start_date" label="Tanggal Mulai" type="date" />
          <TextInput v-model="form.end_date" label="Tanggal Selesai" type="date" />
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" id="is_split" v-model="form.is_split" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
          <label for="is_split" class="text-sm font-medium text-(--text-main)">Split Periode</label>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showCreateModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleCreate" :disabled="loading">Generate</BaseButton>
      </template>
    </BaseModal>

    <BaseModal :show="showEditModal" title="Edit Periode" @close="showEditModal = false">
      <div class="space-y-4">
        <TextInput v-model="form.name" label="Nama Periode" placeholder="Contoh: Juni 2026" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.start_date" label="Tanggal Mulai" type="date" />
          <TextInput v-model="form.end_date" label="Tanggal Selesai" type="date" />
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" id="edit_is_split" v-model="form.is_split" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
          <label for="edit_is_split" class="text-sm font-medium text-(--text-main)">Split Periode</label>
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" id="edit_status" v-model="form.status" true-value="active" false-value="inactive" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
          <label for="edit_status" class="text-sm font-medium text-(--text-main)">Aktif</label>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showEditModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleEdit" :disabled="loading">Simpan</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog :show="!!confirmDelete" title="Hapus Periode"
      :message="'Apakah Anda yakin ingin menghapus periode ' + confirmDelete?.name + '?'" confirm-text="Ya, Hapus"
      variant="danger" @confirm="handleDelete" @cancel="confirmDelete = null" />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import Pagination from '../../../../Components/Table/Pagination.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import Badge from '../../../../Components/Badge.vue'
import TextInput from '../../../../Components/TextInput.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import { IconPlus, IconEye, IconDownload, IconFileInvoice, IconChartBar, IconClock } from '../../../../Components/Icons/index.js'
import { useApi } from '../../../../composables/useApi'

const { get, post, put, destroy } = useApi()

const headers = [
  { key: 'name', label: 'Nama Periode' },
  { key: 'date_range', label: 'Rentang Tanggal' },
  { key: 'is_split', label: 'Split' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '120px' },
]

const periods = ref([])
const loading = ref(false)

const showCreateModal = ref(false)
const showEditModal = ref(false)
const editingId = ref(null)
const cutOffDate = ref(null)
const confirmDelete = ref(null)

const form = ref({
  name: '',
  start_date: '',
  end_date: '',
  is_split: false,
  status: 'active'
})

async function fetchPeriods() {
  try {
    const data = await get('/api/v1/payroll/periods')
    periods.value = data.data || data
  } catch (error) {
    console.error('Error fetching periods', error)
  }
}

async function fetchSettings() {
  try {
    const data = await get('/api/v1/settings/payroll')
    if (data.data) {
      cutOffDate.value = data.data.cut_off_date
    }
  } catch (error) {
    console.error('Error fetching settings', error)
  }
}

function calculateDefaultDates(cutOff) {
  const today = new Date();
  const y = today.getFullYear();
  const m = today.getMonth();
  const d = today.getDate();

  if (!cutOff) {
    const start = new Date(y, m, 1);
    const end = new Date(y, m + 1, 0);
    const format = (dt) => `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
    return { start: format(start), end: format(end) };
  }

  const cutOffDay = parseInt(cutOff);
  let endMonth = m;
  let endYear = y;

  if (d > cutOffDay) {
    endMonth = m + 1;
    if (endMonth > 11) {
      endMonth = 0;
      endYear++;
    }
  }

  const end = new Date(endYear, endMonth, cutOffDay);
  const start = new Date(endYear, endMonth - 1, cutOffDay + 1);

  const format = (dt) => {
    const yr = dt.getFullYear();
    const mo = String(dt.getMonth() + 1).padStart(2, '0');
    const da = String(dt.getDate()).padStart(2, '0');
    return `${yr}-${mo}-${da}`;
  }

  return { start: format(start), end: format(end) };
}

function openCreateModal() {
  const dates = calculateDefaultDates(cutOffDate.value);
  form.value.start_date = dates.start;
  form.value.end_date = dates.end;

  // Also auto-generate a period name based on end date month
  if (form.value.end_date) {
    const end = new Date(form.value.end_date);
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    form.value.name = `Periode ${months[end.getMonth()]} ${end.getFullYear()}`;
  }

  form.value.is_split = false;
  form.value.status = 'active';

  showCreateModal.value = true;
}

function openEditModal(item) {
  editingId.value = item.id;
  form.value.name = item.name;
  form.value.start_date = item.start_date;
  form.value.end_date = item.end_date;
  form.value.is_split = !!item.is_split;
  form.value.status = item.status || 'active';
  showEditModal.value = true;
}

onMounted(() => {
  fetchPeriods()
  fetchSettings()
})

function statusVariant(status) {
  return status === 'active' ? 'success' : 'neutral'
}

function statusLabel(status) {
  return status === 'active' ? 'Aktif' : 'Tidak Aktif'
}

async function handleCreate() {
  loading.value = true
  try {
    await post('/api/v1/payroll/periods', form.value)
    showCreateModal.value = false
    form.value = { name: '', start_date: '', end_date: '', is_split: false, status: 'active' }
    fetchPeriods()
  } catch (error) {
    console.error('Error creating period', error)
  } finally {
    loading.value = false
  }
}

async function handleEdit() {
  loading.value = true
  try {
    await put(`/api/v1/payroll/periods/${editingId.value}`, form.value)
    showEditModal.value = false
    editingId.value = null
    form.value = { name: '', start_date: '', end_date: '', is_split: false, status: 'active' }
    fetchPeriods()
  } catch (error) {
    console.error('Error editing period', error)
  } finally {
    loading.value = false
  }
}

async function handleDelete() {
  if (confirmDelete.value) {
    try {
      await destroy(`/api/v1/payroll/periods/${confirmDelete.value.id}`)
      confirmDelete.value = null
      fetchPeriods()
    } catch (error) {
      console.error('Error deleting period', error)
    }
  }
}
</script>
