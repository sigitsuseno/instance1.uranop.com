<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Rekap Cuti</h1>
        <p class="text-sm text-(--text-muted) mt-1">Rekapitulasi saldo cuti dan penutupan periode.</p>
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

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
      <!-- Recap Info -->
      <BaseCard>
        <template #title>Informasi Periode</template>
        <div class="grid grid-cols-2 gap-2 text-sm">
          <span class="text-(--text-muted)">Periode:</span>
          <span class="font-bold text-(--text-main)">{{ activePeriodName }}</span>
          <span class="text-(--text-muted)">Status:</span>
          <Badge :variant="periodBadgeVariant(currentPeriodStatus)">{{ currentPeriodStatus }}</Badge>
          <span class="text-(--text-muted)">Carry Forward:</span>
          <span class="font-semibold" :class="activePeriodIsCarryForward ? 'text-(--success)' : 'text-(--danger)'">
            {{ activePeriodIsCarryForward ? 'Diaktifkan (Jatah Cuti Dibawa)' : 'Dinonaktifkan (Jatah Cuti Hangus)' }}
          </span>
        </div>
      </BaseCard>

      <!-- Warning -->
      <BaseCard>
        <div class="space-y-2 text-sm">
          <h4 class="font-semibold flex items-center gap-2 text-(--warning)">
            <i class="bx bx-error-circle text-lg"></i>
            Perhatian Sebelum Menutup Periode:
          </h4>
          <p class="text-xs text-(--text-muted) leading-relaxed">
            Menutup periode cuti akan mengubah status menjadi <strong>closed</strong>.
          </p>
          <p class="text-xs text-(--text-muted) leading-relaxed" v-if="!activePeriodIsCarryForward">
            Karena <strong>Carry Forward Dinonaktifkan</strong>, seluruh sisa saldo cuti karyawan akan dihanguskan secara otomatis.
          </p>
          <p class="text-xs text-(--text-muted) leading-relaxed" v-else>
            Karena <strong>Carry Forward Diaktifkan</strong>, sisa jatah cuti tetap dibiarkan utuh di ledger.
          </p>
        </div>
      </BaseCard>
    </div>

    <!-- Close period button -->
    <div class="mb-6">
      <BaseButton
        variant="danger"
        @click="showConfirmRecap = true"
        :loading="recapping"
        :disabled="periods.length === 0 || !selectedPeriodId || currentPeriodStatus === 'closed'"
      >
        <template #icon-left><i class="bx bx-archive text-base"></i></template>
        Akhiri & Tutup Periode
      </BaseButton>
      <p v-if="periods.length === 0" class="text-xs text-(--danger) mt-2">* Belum ada data periode cuti.</p>
      <p v-else-if="currentPeriodStatus === 'closed'" class="text-xs text-(--text-soft) mt-2">* Periode sudah ditutup.</p>
    </div>

    <!-- Preview balances -->
    <BaseCard>
      <template #title>Preview Sisa Jatah Karyawan (Sisa > 0)</template>
      <DataTable
        :headers="headers"
        :items="recapBalances"
        :loading="loading"
        emptyText="Tidak ada karyawan dengan jatah cuti tersisa."
      >
        <template #item.entitlement="{ value }">{{ value }} hari</template>
        <template #item.used="{ value }">{{ value }} hari</template>
        <template #item.balance="{ value }">
          <span class="font-bold text-(--danger)">{{ value }} hari</span>
        </template>
      </DataTable>
    </BaseCard>

    <!-- Confirm -->
    <ConfirmDialog
      :show="showConfirmRecap"
      title="Akhiri & Tutup Periode Cuti"
      :message="`Apakah Anda yakin ingin MENGAKHIRI & MENUTUP periode '${activePeriodName}'? Tindakan ini akan mengubah status periode menjadi closed. ${!activePeriodIsCarryForward ? 'Sisa jatah cuti akan DIHANGUSKAN.' : ''}`"
      variant="danger"
      @confirm="executeRecap"
      @cancel="showConfirmRecap = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import Badge from '../../../Components/Badge.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { useApi } from '../../../composables/useApi'
import { useNotification } from '../../../composables/useNotification'

const api = useApi()
const notify = useNotification()

const selectedPeriodId = ref('')
const periods = ref([])
const balances = ref([])
const loadingPeriods = ref(false)
const loading = ref(false)
const recapping = ref(false)
const showConfirmRecap = ref(false)

const headers = [
  { key: 'nip', label: 'NIP' },
  { key: 'employee_name', label: 'Nama Karyawan' },
  { key: 'department_name', label: 'Departemen' },
  { key: 'leave_type_name', label: 'Jenis Cuti' },
  { key: 'entitlement', label: 'Jatah Kuota' },
  { key: 'used', label: 'Terpakai' },
  { key: 'balance', label: 'Sisa Saldo' },
]

const activePeriodName = computed(() => {
  const p = periods.value.find((x) => x.id === selectedPeriodId.value)
  return p ? p.name : '-'
})

const currentPeriodStatus = computed(() => {
  const p = periods.value.find((x) => x.id === selectedPeriodId.value)
  return p ? p.status : 'active'
})

const activePeriodIsCarryForward = computed(() => {
  const p = periods.value.find((x) => x.id === selectedPeriodId.value)
  return p ? !!p.is_carry_forward : false
})

const recapBalances = computed(() => {
  return balances.value.filter((x) => x.balance > 0)
})

function periodBadgeVariant(status) {
  const map = { active: 'success', recap: 'warning', closed: 'neutral' }
  return map[status] || 'neutral'
}

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

async function executeRecap() {
  showConfirmRecap.value = false
  recapping.value = true
  try {
    const res = await api.post('/api/v1/leave/recap-period', {
      leave_period_id: selectedPeriodId.value,
    })
    notify.success(res.message || 'Periode berhasil ditutup.')
    await fetchPeriods()
    fetchBalances()
  } catch (err) {
    notify.error(err.message || 'Gagal menutup periode.')
  } finally {
    recapping.value = false
  }
}

onMounted(async () => {
  await fetchPeriods()
  fetchBalances()
})
</script>
