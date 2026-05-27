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
            <p class="text-lg font-semibold text-(--text-main)">12</p>
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
            <p class="text-lg font-semibold text-(--text-main)">8</p>
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
            <p class="text-lg font-semibold text-(--text-main)">3</p>
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
            <p class="text-lg font-semibold text-(--text-main)">Rp 5,2 M</p>
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
          <span class="font-medium">Rp {{ value.toLocaleString('id-ID') }}</span>
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
              v-if="item.status !== 'completed'"
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
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--success) hover:bg-(--success)/10 transition-colors"
              title="Export"
              @click="exportPeriod(item)"
            >
              <IconDownload class="w-4 h-4" />
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
        <TextInput v-model="form.period_name" label="Nama Periode" placeholder="Contoh: Juni 2026" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.start_date" label="Tanggal Mulai" type="date" />
          <TextInput v-model="form.end_date" label="Tanggal Selesai" type="date" />
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showCreateModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleCreate">Generate</BaseButton>
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
  </div>
</template>

<script setup>
import { ref } from 'vue'
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

const periods = ref([
  { id: 1, period_code: 'PAY-2026-01', name: 'Januari 2026', start_date: '2026-01-01', end_date: '2026-01-31', status: 'completed', total_employees: 152, total_amount: 418500000, date_range: '01 Jan - 31 Jan 2026' },
  { id: 2, period_code: 'PAY-2026-02', name: 'Februari 2026', start_date: '2026-02-01', end_date: '2026-02-28', status: 'completed', total_employees: 154, total_amount: 421200000, date_range: '01 Feb - 28 Feb 2026' },
  { id: 3, period_code: 'PAY-2026-03', name: 'Maret 2026', start_date: '2026-03-01', end_date: '2026-03-31', status: 'completed', total_employees: 155, total_amount: 423000000, date_range: '01 Mar - 31 Mar 2026' },
  { id: 4, period_code: 'PAY-2026-04', name: 'April 2026', start_date: '2026-04-01', end_date: '2026-04-30', status: 'completed', total_employees: 157, total_amount: 448700000, date_range: '01 Apr - 30 Apr 2026' },
  { id: 5, period_code: 'PAY-2026-05', name: 'Mei 2026', start_date: '2026-05-01', end_date: '2026-05-31', status: 'completed', total_employees: 156, total_amount: 425000000, date_range: '01 Mei - 31 Mei 2026' },
  { id: 6, period_code: 'PAY-2026-06', name: 'Juni 2026', start_date: '2026-06-01', end_date: '2026-06-30', status: 'in_progress', total_employees: 158, total_amount: 435800000, date_range: '01 Jun - 30 Jun 2026' },
])

const showCreateModal = ref(false)
const confirmLock = ref(null)

const form = ref({
  period_name: '',
  start_date: '',
  end_date: '',
})

function statusVariant(status) {
  const map = { completed: 'success', in_progress: 'warning', draft: 'neutral' }
  return map[status] || 'neutral'
}

function statusLabel(status) {
  const map = { completed: 'Selesai', in_progress: 'Dalam Proses', draft: 'Draft' }
  return map[status] || status
}

function handleCreate() {
  periods.value.unshift({
    id: periods.value.length + 1,
    period_code: `PAY-${form.value.start_date?.substring(0, 7)?.replace('-', '-') || '2026-07'}`,
    name: form.value.period_name || 'Periode Baru',
    start_date: form.value.start_date,
    end_date: form.value.end_date,
    status: 'draft',
    total_employees: 0,
    total_amount: 0,
    date_range: `${form.value.start_date || '-'} - ${form.value.end_date || '-'}`,
  })
  showCreateModal.value = false
  form.value = { period_name: '', start_date: '', end_date: '' }
}

function handleLock() {
  if (confirmLock.value) {
    const idx = periods.value.findIndex((p) => p.id === confirmLock.value.id)
    if (idx !== -1) periods.value[idx].status = 'completed'
    confirmLock.value = null
  }
}

function exportPeriod(item) {
  alert(`Export data untuk periode ${item.name}`)
}
</script>
