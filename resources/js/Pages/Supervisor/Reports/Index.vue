<script setup>
import { ref, computed } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import { useCurrency } from '../../../composables/useCurrency'
import {
  IconCalendarCheck,
  IconFileInvoice,
  IconUmbrella,
  IconDownload,
  IconChartBar,
} from '../../../Components/Icons/index.js'

const { format } = useCurrency()

const reports = ref([
  {
    id: 1,
    title: 'Laporan Absensi Tim',
    description: 'Rekap kehadiran dan keterlambatan per bulan',
    icon: IconCalendarCheck,
    color: 'primary',
  },
  {
    id: 2,
    title: 'Rangkuman Gaji',
    description: 'Ringkasan gaji pokok, tunjangan, dan potongan',
    icon: IconFileInvoice,
    color: 'success',
  },
  {
    id: 3,
    title: 'Laporan Cuti',
    description: 'Rekap cuti dan izin karyawan',
    icon: IconUmbrella,
    color: 'warning',
  },
])

const showModal = ref(false)
const activeReport = ref(null)
const selectedMonth = ref('2026-05')
const generating = ref(false)

const months = [
  { value: '2026-01', label: 'Januari 2026' },
  { value: '2026-02', label: 'Februari 2026' },
  { value: '2026-03', label: 'Maret 2026' },
  { value: '2026-04', label: 'April 2026' },
  { value: '2026-05', label: 'Mei 2026' },
  { value: '2026-06', label: 'Juni 2026' },
]

const previewData = computed(() => {
  if (!activeReport.value) return []

  if (activeReport.value.id === 1) {
    return [
      { name: 'Andi Prasetyo', department: 'Produksi', hadir: 20, terlambat: 2, izin: 0, alfa: 0 },
      { name: 'Budi Santoso', department: 'Produksi', hadir: 18, terlambat: 4, izin: 0, alfa: 0 },
      { name: 'Citra Dewi', department: 'QC', hadir: 19, terlambat: 3, izin: 0, alfa: 0 },
      { name: 'Dian Permata', department: 'Gudang', hadir: 21, terlambat: 1, izin: 0, alfa: 0 },
      { name: 'Eko Wahyudi', department: 'Produksi', hadir: 20, terlambat: 2, izin: 0, alfa: 0 },
    ]
  }

  if (activeReport.value.id === 2) {
    return [
      { name: 'Andi Prasetyo', department: 'Produksi', basicSalary: 5500000, allowances: 1500000, deductions: 450000, netSalary: 6550000 },
      { name: 'Budi Santoso', department: 'Produksi', basicSalary: 4800000, allowances: 1200000, deductions: 380000, netSalary: 5620000 },
      { name: 'Citra Dewi', department: 'QC', basicSalary: 5000000, allowances: 1300000, deductions: 400000, netSalary: 5900000 },
      { name: 'Dian Permata', department: 'Gudang', basicSalary: 4500000, allowances: 1000000, deductions: 350000, netSalary: 5150000 },
      { name: 'Eko Wahyudi', department: 'Produksi', basicSalary: 5200000, allowances: 1400000, deductions: 420000, netSalary: 6180000 },
    ]
  }

  if (activeReport.value.id === 3) {
    return [
      { name: 'Fitriani', department: 'QC', leaveType: 'Sakit', startDate: '2026-05-27', endDate: '2026-05-27', totalDays: 1, status: 'Disetujui' },
      { name: 'Gunawan', department: 'Gudang', leaveType: 'Izin', startDate: '2026-05-27', endDate: '2026-05-27', totalDays: 1, status: 'Disetujui' },
      { name: 'Lukman Hakim', department: 'Produksi', leaveType: 'Izin', startDate: '2026-05-27', endDate: '2026-05-27', totalDays: 1, status: 'Disetujui' },
      { name: 'Oka Prasetya', department: 'Gudang', leaveType: 'Sakit', startDate: '2026-05-25', endDate: '2026-05-26', totalDays: 2, status: 'Disetujui' },
      { name: 'Hendra Gunawan', department: 'Produksi', leaveType: 'Cuti Tahunan', startDate: '2026-06-10', endDate: '2026-06-14', totalDays: 5, status: 'Menunggu' },
    ]
  }

  return []
})

function openGenerate(report) {
  activeReport.value = report
  showModal.value = true
}

function generateReport() {
  generating.value = true
  setTimeout(() => {
    generating.value = false
    alert('Laporan berhasil digenerate! (Simulasi)')
  }, 1500)
}

function statusBadge(status) {
  switch (status) {
    case 'Disetujui': return 'success'
    case 'Ditolak': return 'danger'
    case 'Menunggu': return 'warning'
    default: return 'neutral'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h3 class="text-xl font-semibold text-(--text-main)">Laporan</h3>
      <p class="text-sm text-(--text-muted) mt-1">Generate laporan untuk tim Anda</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="report in reports"
        :key="report.id"
        class="bg-(--bg-card) rounded-md border border-(--border-soft) p-6 hover:shadow-md transition-shadow"
      >
        <div :class="`w-12 h-12 rounded-md bg-(--${report.color})/10 flex items-center justify-center mb-4`">
          <component :is="report.icon" :class="`w-6 h-6 text-(--${report.color})`" />
        </div>
        <h4 class="text-base font-semibold text-(--text-main) mb-1">{{ report.title }}</h4>
        <p class="text-sm text-(--text-muted) mb-4">{{ report.description }}</p>
        <BaseButton :variant="report.color" size="sm" @click="openGenerate(report)">
          <template #icon-left>
            <IconChartBar class="w-4 h-4" />
          </template>
          Generate
        </BaseButton>
      </div>
    </div>

    <BaseModal :show="showModal" :title="activeReport?.title || ''" size="lg" @close="showModal = false">
      <template v-if="activeReport">
        <div class="flex items-center gap-3 mb-6">
          <SelectInput
            :model-value="selectedMonth"
            :options="months"
            label="Periode"
            @update:model-value="selectedMonth = $event"
          />
        </div>

        <div class="border border-(--border-soft) rounded-md overflow-hidden mb-4">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th v-if="activeReport.id === 1" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Nama</th>
                <th v-if="activeReport.id === 1" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Departemen</th>
                <th v-if="activeReport.id === 1" class="px-4 py-3 text-center text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Hadir</th>
                <th v-if="activeReport.id === 1" class="px-4 py-3 text-center text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Terlambat</th>
                <th v-if="activeReport.id === 1" class="px-4 py-3 text-center text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Izin</th>
                <th v-if="activeReport.id === 1" class="px-4 py-3 text-center text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Alfa</th>

                <th v-if="activeReport.id === 2" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Nama</th>
                <th v-if="activeReport.id === 2" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Departemen</th>
                <th v-if="activeReport.id === 2" class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Gaji Pokok</th>
                <th v-if="activeReport.id === 2" class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tunjangan</th>
                <th v-if="activeReport.id === 2" class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Potongan</th>
                <th v-if="activeReport.id === 2" class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Gaji Bersih</th>

                <th v-if="activeReport.id === 3" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Nama</th>
                <th v-if="activeReport.id === 3" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jenis Cuti</th>
                <th v-if="activeReport.id === 3" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tgl Mulai</th>
                <th v-if="activeReport.id === 3" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tgl Selesai</th>
                <th v-if="activeReport.id === 3" class="px-4 py-3 text-center text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Total Hari</th>
                <th v-if="activeReport.id === 3" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="activeReport.id === 1">
                <tr v-for="(row, idx) in previewData" :key="idx" class="border-b border-(--border-soft)">
                  <td class="px-4 py-3 text-sm font-medium text-(--text-main)">{{ row.name }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-muted)">{{ row.department }}</td>
                  <td class="px-4 py-3 text-sm text-center text-(--success) font-medium">{{ row.hadir }}</td>
                  <td class="px-4 py-3 text-sm text-center text-(--warning) font-medium">{{ row.terlambat }}</td>
                  <td class="px-4 py-3 text-sm text-center text-(--text-muted)">{{ row.izin }}</td>
                  <td class="px-4 py-3 text-sm text-center text-(--text-muted)">{{ row.alfa }}</td>
                </tr>
              </template>

              <template v-if="activeReport.id === 2">
                <tr v-for="(row, idx) in previewData" :key="idx" class="border-b border-(--border-soft)">
                  <td class="px-4 py-3 text-sm font-medium text-(--text-main)">{{ row.name }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-muted)">{{ row.department }}</td>
                  <td class="px-4 py-3 text-sm text-right text-(--text-main)">{{ format(row.basicSalary) }}</td>
                  <td class="px-4 py-3 text-sm text-right text-(--success)">{{ format(row.allowances) }}</td>
                  <td class="px-4 py-3 text-sm text-right text-(--danger)">{{ format(row.deductions) }}</td>
                  <td class="px-4 py-3 text-sm text-right font-semibold text-(--primary)">{{ format(row.netSalary) }}</td>
                </tr>
              </template>

              <template v-if="activeReport.id === 3">
                <tr v-for="(row, idx) in previewData" :key="idx" class="border-b border-(--border-soft)">
                  <td class="px-4 py-3 text-sm font-medium text-(--text-main)">{{ row.name }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-muted)">{{ row.leaveType }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ row.startDate }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ row.endDate }}</td>
                  <td class="px-4 py-3 text-sm text-center text-(--text-main)">{{ row.totalDays }} hari</td>
                  <td class="px-4 py-3 text-sm">
                    <Badge :variant="statusBadge(row.status)">{{ row.status }}</Badge>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </template>
      <template #footer>
        <BaseButton variant="secondary" size="sm" @click="showModal = false">Tutup</BaseButton>
        <BaseButton variant="primary" size="sm" :loading="generating" @click="generateReport">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Generate & Unduh
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
