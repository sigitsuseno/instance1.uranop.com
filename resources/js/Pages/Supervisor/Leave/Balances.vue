<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Saldo Cuti</h1>
        <p class="text-sm text-(--text-muted) mt-1">Informasi sisa jatah cuti karyawan per periode.</p>
      </div>
      <div class="w-72 flex items-center gap-2">
        <label class="text-sm font-medium text-(--text-main) shrink-0">Periode:</label>
        <select
          v-model="selectedPeriodId"
          class="w-full px-3 py-2 pr-10 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300 h-10 appearance-none text-sm"
          @change="fetchBalances"
        >
          <option v-if="loadingPeriods" value="" disabled>Memuat periode...</option>
          <option v-else-if="periods.length === 0" value="" disabled>Tidak ada periode</option>
          <option v-for="period in periods" :key="period.id" :value="period.id">
            {{ period.name }} ({{ period.status }})
          </option>
        </select>
      </div>
    </div>

    <div class="flex items-center justify-end gap-2 mb-4">
      <BaseButton
        v-if="auth.isSuperadmin"
        variant="outline"
        size="sm"
        @click="executeNormalizePeriod"
        :disabled="normalizing"
        title="Sesuaikan period_id leave_request dengan range tanggal periode terpilih"
      >
        <template #icon-left><i class="bx bx-sort text-base"></i></template>
        Normalisasi Period
      </BaseButton>
      <BaseButton
        v-if="auth.isSuperadmin"
        variant="warning"
        size="sm"
        @click="showCalibrateDialog = true"
        :disabled="calibrating"
        title="Kalibrasi ulang saldo cuti berdasarkan data transaksi"
      >
        <template #icon-left><i class="bx bx-adjust text-base"></i></template>
        Kalibrasi Cuti
      </BaseButton>
      <BaseButton variant="secondary" size="sm" @click="exportExcel" title="Export Excel">
        <template #icon-left><i class="bx bx-spreadsheet text-base"></i></template>
      </BaseButton>
      <BaseButton variant="secondary" size="sm" @click="exportPdf" title="Export PDF">
        <template #icon-left><i class="bx bxs-file-pdf text-base"></i></template>
      </BaseButton>
    </div>

    <BaseCard>
      <DataTable
        :headers="headers"
        :items="balances"
        :loading="loading"
        emptyText="Tidak ada data saldo cuti untuk periode ini."
      >
        <template #item.entitlement="{ value }">
          <span class="font-medium text-(--primary)">{{ value }} hari</span>
        </template>
        <template #item.used="{ value }">
          <span class="font-medium text-(--warning)">{{ value }} hari</span>
        </template>
        <template #item.balance="{ value }">
          <span class="font-bold" :class="value > 0 ? 'text-(--success)' : 'text-(--text-soft)'">
            {{ value }} hari
          </span>
        </template>
      </DataTable>
    </BaseCard>

    <!-- Confirm Dialog Kalibrasi -->
    <ConfirmDialog
      :show="showCalibrateDialog"
      title="Kalibrasi Cuti"
      message="Proses ini akan menyelaraskan sisa saldo cuti (Cuti Tahunan) semua karyawan berdasarkan urutan transaksi yang ada. Semua data sisa cuti di pengajuan akan diperbarui. Lanjutkan?"
      confirmText="Ya, Kalibrasi"
      cancelText="Batal"
      variant="warning"
      :loading="calibrating"
      @confirm="executeCalibrate"
      @cancel="showCalibrateDialog = false"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { useApi } from '../../../composables/useApi'
import { useAuth } from '../../../composables/useAuth'
import { useNotification } from '../../../composables/useNotification'

const api = useApi()
const notify = useNotification()
const auth = useAuth()

const selectedPeriodId = ref('')
const periods = ref([])
const balances = ref([])
const loadingPeriods = ref(false)
const loading = ref(false)
const showCalibrateDialog = ref(false)
const calibrating = ref(false)
const normalizing = ref(false)

const headers = [
  { key: 'nip', label: 'NIP' },
  { key: 'employee_name', label: 'Nama Karyawan' },
  { key: 'department_name', label: 'Departemen' },
  { key: 'leave_type_name', label: 'Jenis Cuti' },
  { key: 'entitlement', label: 'Jatah Kuota' },
  { key: 'used', label: 'Terpakai' },
  { key: 'balance', label: 'Sisa Saldo' },
]

async function fetchPeriods() {
  loadingPeriods.value = true
  try {
    const res = await api.get('/api/v1/leave/periods')
    periods.value = res.data || []
    const active = periods.value.find((p) => p.status === 'active')
    if (active) selectedPeriodId.value = active.id
    else if (periods.value.length > 0) selectedPeriodId.value = periods.value[0].id
  } catch (err) {
    notify.error('Gagal memuat daftar periode.')
  } finally {
    loadingPeriods.value = false
  }
}

async function fetchBalances() {
  if (!selectedPeriodId.value) return
  loading.value = true
  try {
    const res = await api.get(`/api/v1/leave/balances?leave_period_id=${selectedPeriodId.value}`)
    balances.value = res.data || []
  } catch (err) {
    notify.error('Gagal memuat daftar saldo cuti.')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  if (!selectedPeriodId.value) {
    notify.error('Pilih periode terlebih dahulu.')
    return
  }
  const token = localStorage.getItem('token')
  const url = `/api/v1/leave/export/balances?leave_period_id=${selectedPeriodId.value}`
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => r.blob())
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', `Saldo_Cuti.xlsx`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(() => notify.error('Gagal export Excel.'))
}

function exportPdf() { notify.info('Export PDF akan diimplementasikan.') }

async function executeNormalizePeriod() {
  if (!selectedPeriodId.value) {
    notify.error('Pilih periode terlebih dahulu.')
    return
  }
  if (!confirm('Normalisasi period_id semua leave_request yang masuk range periode ini?')) return
  normalizing.value = true
  try {
    const res = await api.post('/api/v1/leave/normalize-period', {
      leave_period_id: selectedPeriodId.value,
    })
    notify.success(res.message || 'Normalisasi periode berhasil.')
    await fetchBalances()
  } catch (err) {
    notify.error(err.message || 'Gagal normalisasi periode.')
  } finally {
    normalizing.value = false
  }
}

async function executeCalibrate() {
  calibrating.value = true
  try {
    const payload = {}
    if (selectedPeriodId.value) {
      payload.leave_period_id = selectedPeriodId.value
    }
    const res = await api.post('/api/v1/leave/calibrate', payload)
    notify.success(res.message || 'Kalibrasi cuti berhasil.')
    showCalibrateDialog.value = false
    await fetchBalances()
  } catch (err) {
    notify.error(err.message || 'Gagal melakukan kalibrasi cuti.')
  } finally {
    calibrating.value = false
  }
}

onMounted(async () => {
  await fetchPeriods()
  fetchBalances()
})
</script>
