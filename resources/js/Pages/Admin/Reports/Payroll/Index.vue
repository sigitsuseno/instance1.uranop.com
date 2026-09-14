<template>
  <div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Laporan Payroll</h1>
        <p class="text-sm text-(--text-muted) mt-1">Laporan Payroll Karyawan beserta Summary/Resume dan Daftar Transfer Bank</p>
      </div>

      <!-- Export Lengkap: 5 sheet dalam satu file (Gaji Karyawan, Uang Makan, Kompensasi, Resume, Rekap Gaji) -->
      <div class="flex flex-wrap items-center gap-3">
        <select
          v-model="exportPeriod"
          class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer"
          title="Periode untuk Export Lengkap"
        >
          <option value="">Periode Terakhir</option>
          <option v-for="period in periods" :key="period.id" :value="period.id">{{ period.name }}</option>
        </select>

        <select
          v-if="isSplitPeriod"
          v-model="exportSegment"
          class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer"
        >
          <option value="">Semua Segment</option>
          <option value="A">Segmen 1</option>
          <option value="B">Segmen 2</option>
        </select>

        <BaseButton variant="primary" size="sm" :disabled="exporting" @click="exportLengkap">
          {{ exporting ? 'Menyiapkan…' : 'Export Lengkap' }}
        </BaseButton>
      </div>
    </div>

    <!-- Tab Bar -->
    <div class="border-b border-(--border-soft) mb-6">
      <nav class="flex gap-0 -mb-px">
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'payroll'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'payroll'"
        >
          Payroll
        </button>
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'resume'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'resume'"
        >
          Resume
        </button>
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'kirim-all'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'kirim-all'"
        >
          Kirim ALL
        </button>
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'kirim-all-in'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'kirim-all-in'"
        >
          Kirim Audit
        </button>
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'kirim-print'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'kirim-print'"
        >
          Kirim Kemilau
        </button>
        <!-- <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'kirim-all'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'kirim-all'"
        >
          Kirim ALL
        </button> -->
      </nav>
    </div>

    <!-- Tab Content -->
    <TabPayroll v-if="activeTab === 'payroll'" />
    <TabResume v-if="activeTab === 'resume'" />
    <TabKirimAll v-if="activeTab === 'kirim-all'" />
    <TabKirimAudit v-if="activeTab === 'kirim-all-in'" />
    <TabKirimBank v-if="activeTab === 'kirim-print'" group="print" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseButton from '../../../../Components/BaseButton.vue'
import TabPayroll from './TabPayroll.vue'
import TabResume from './TabResume.vue'
import TabKirimAudit from './TabKirimAudit.vue'
import TabKirimBank from './TabKirimBank.vue'
import TabKirimAll from './TabKirimAll.vue'

const { get } = useApi()
const notification = useNotificationStore()

const activeTab = ref('payroll')

const periods = ref([])
const exportPeriod = ref('')
const exportSegment = ref('')
const exporting = ref(false)

const isSplitPeriod = computed(() => {
  if (!exportPeriod.value) return false
  const p = periods.value.find(x => x.id === exportPeriod.value)
  return p ? p.is_split : false
})

async function fetchPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    periods.value = res.data || []
  } catch (err) {
    notification.error('Gagal mengambil data periode')
  }
}

async function exportLengkap() {
  exporting.value = true
  try {
    const params = new URLSearchParams()
    if (exportPeriod.value) params.append('period_id', exportPeriod.value)
    if (isSplitPeriod.value && exportSegment.value) params.append('segment', exportSegment.value)

    const token = localStorage.getItem('token')
    const response = await fetch(`/api/v1/laporan/payroll/export-lengkap?${params.toString()}`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    })

    if (!response.ok) {
      let message = `Gagal export (${response.status})`
      try {
        const data = await response.json()
        if (data?.message) message = data.message
      } catch {}
      throw new Error(message)
    }

    const blob = await response.blob()
    const downloadUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = downloadUrl
    link.setAttribute('download', 'GAJI_KUS.xlsx')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(downloadUrl)

    notification.success('Export Lengkap berhasil diunduh')
  } catch (err) {
    notification.error(err.message || 'Gagal export Excel')
  } finally {
    exporting.value = false
  }
}

onMounted(fetchPeriods)
</script>
