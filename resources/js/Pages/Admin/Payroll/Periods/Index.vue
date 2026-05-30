<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Periode Generate Gaji</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola periode penggajian karyawan</p>
      </div>
      <BaseButton variant="primary" @click="showCreateModal = true">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Generate Periode Baru
      </BaseButton>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center">
            <IconFileInvoice class="w-5 h-5 text-(--primary)" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Total Periode</p>
            <p class="text-lg font-semibold text-(--text-main)">{{ periods.length }}</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--success)/10 flex items-center justify-center">
            <IconChartBar class="w-5 h-5 text-(--success)" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Selesai</p>
            <p class="text-lg font-semibold text-(--text-main)">{{ completedPeriods }}</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--warning)/10 flex items-center justify-center">
            <IconClock class="w-5 h-5 text-(--warning)" />
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Dalam Proses</p>
            <p class="text-lg font-semibold text-(--text-main)">{{ inProgressPeriods }}</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center">
            <span class="text-sm font-bold text-(--primary)">Rp</span>
          </div>
          <div>
            <p class="text-xs text-(--text-muted)">Total Pengeluaran</p>
            <p class="text-lg font-semibold text-(--text-main)">Rp 0</p>
          </div>
        </div>
      </BaseCard>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="periods" showSearch>
        <template #item.period_code="{ value }">
          <span class="font-medium text-(--primary)">{{ value }}</span>
        </template>
        <template #item.total_amount="{ value }">
          <span class="font-medium">Rp {{ (value || 0).toLocaleString('id-ID') }}</span>
        </template>
        <template #item.status="{ value }">
          <Badge :variant="statusVariant(value)">{{ statusLabel(value) }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
              title="Lihat Detail"
              @click="$inertia.visit(`/admin/payroll/periods/${item.id}`)"
            >
              <IconEye class="w-4 h-4" />
            </button>
            <button
              v-if="item.status !== 'completed' && item.status !== 'closed'"
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--warning) hover:bg-(--warning)/10 transition-colors"
              title="Kunci Periode"
              @click="confirmLock = item"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              </svg>
            </button>
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors"
              title="Hapus"
              @click="confirmDelete = item"
            >
               <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            </button>
          </div>
        </template>
      </DataTable>
      <Pagination
        :current-page="1"
        :total-pages="1"
        :total="periods.length"
        :per-page="10"
        @page-change="() => {}"
      />
    </BaseCard>

    <BaseModal :show="showCreateModal" title="Generate Periode Baru" @close="showCreateModal = false">
      <div class="space-y-4">
        <TextInput v-model="form.name" label="Nama Periode" placeholder="Contoh: Juni 2026" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.start_date" label="Tanggal Mulai" type="date" />
          <TextInput v-model="form.end_date" label="Tanggal Selesai" type="date" />
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showCreateModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleCreate" :disabled="loading">Generate</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="!!confirmLock"
      title="Kunci Periode"
      :message="'Apakah Anda yakin ingin mengunci periode ' + confirmLock?.name + '? Periode yang terkunci tidak dapat diubah.'"
      confirm-text="Ya, Kunci"
      variant="warning"
      @confirm="handleLock"
      @cancel="confirmLock = null"
    />

    <ConfirmDialog
      :show="!!confirmDelete"
      title="Hapus Periode"
      :message="'Apakah Anda yakin ingin menghapus periode ' + confirmDelete?.name + '?'"
      confirm-text="Ya, Hapus"
      variant="danger"
      @confirm="handleDelete"
      @cancel="confirmDelete = null"
    />
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

const headers = [
  { key: 'period_code', label: 'Kode' },
  { key: 'name', label: 'Periode' },
  { key: 'date_range', label: 'Tanggal' },
  { key: 'total_employees', label: 'Karyawan' },
  { key: 'total_amount', label: 'Total', align: 'right' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '120px' },
]

const periods = ref([])
const loading = ref(false)

const showCreateModal = ref(false)
const confirmLock = ref(null)
const confirmDelete = ref(null)

const form = ref({
  name: '',
  start_date: '',
  end_date: '',
})

const completedPeriods = computed(() => periods.value.filter(p => p.status === 'closed' || p.status === 'completed').length)
const inProgressPeriods = computed(() => periods.value.filter(p => p.status === 'in_progress' || p.status === 'processing').length)

async function fetchPeriods() {
  try {
    const response = await fetch('/api/v1/payroll/periods', {
      headers: {
        'Accept': 'application/json'
      }
    })
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
    const data = await response.json()
    periods.value = data.data || data
  } catch (error) {
    console.error('Error fetching periods', error)
  }
}

onMounted(() => {
  fetchPeriods()
})

function statusVariant(status) {
  const map = { completed: 'success', closed: 'success', in_progress: 'warning', processing: 'warning', draft: 'neutral', locked: 'warning' }
  return map[status] || 'neutral'
}

function statusLabel(status) {
  const map = { completed: 'Selesai', closed: 'Ditutup', in_progress: 'Dalam Proses', processing: 'Diproses', draft: 'Draft', locked: 'Terkunci' }
  return map[status] || status
}

async function handleCreate() {
  loading.value = true
  try {
    const response = await fetch('/api/v1/payroll/periods', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(form.value)
    })
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
    showCreateModal.value = false
    form.value = { name: '', start_date: '', end_date: '' }
    fetchPeriods()
  } catch (error) {
    console.error('Error creating period', error)
  } finally {
    loading.value = false
  }
}

async function handleLock() {
  if (confirmLock.value) {
    try {
      const response = await fetch(`/api/v1/payroll/periods/${confirmLock.value.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ status: 'closed' })
      })
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
      confirmLock.value = null
      fetchPeriods()
    } catch (error) {
      console.error('Error locking period', error)
    }
  }
}

async function handleDelete() {
  if (confirmDelete.value) {
    try {
      const response = await fetch(`/api/v1/payroll/periods/${confirmDelete.value.id}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json'
        }
      })
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
      confirmDelete.value = null
      fetchPeriods()
    } catch (error) {
      console.error('Error deleting period', error)
    }
  }
}
</script>
