<script setup>
import { ref, computed } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import Badge from '../../../Components/Badge.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import {
  IconCalendarCheck,
  IconClock,
  IconFileInvoice,
  IconDownload,
} from '../../../Components/Icons/index.js'

const selectedDate = ref('2026-05-27')

const attendanceData = ref([
  { id: 1, employeeName: 'Andi Prasetyo', nip: '2024001', department: 'Produksi', date: '2026-05-27', checkIn: '07:45', checkOut: '17:00', status: 'Hadir', lateMinutes: 0, overtimeMinutes: 60 },
  { id: 2, employeeName: 'Budi Santoso', nip: '2024002', department: 'Produksi', date: '2026-05-27', checkIn: '08:10', checkOut: '17:00', status: 'Terlambat', lateMinutes: 40, overtimeMinutes: 0 },
  { id: 3, employeeName: 'Citra Dewi', nip: '2024003', department: 'QC', date: '2026-05-27', checkIn: '07:50', checkOut: '16:30', status: 'Terlambat', lateMinutes: 20, overtimeMinutes: 0 },
  { id: 4, employeeName: 'Dian Permata', nip: '2024004', department: 'Gudang', date: '2026-05-27', checkIn: '07:30', checkOut: '17:00', status: 'Hadir', lateMinutes: 0, overtimeMinutes: 0 },
  { id: 5, employeeName: 'Eko Wahyudi', nip: '2024005', department: 'Produksi', date: '2026-05-27', checkIn: '07:40', checkOut: '17:30', status: 'Hadir', lateMinutes: 0, overtimeMinutes: 30 },
  { id: 6, employeeName: 'Fitriani', nip: '2024006', department: 'QC', date: '2026-05-27', checkIn: '--', checkOut: '--', status: 'Izin', lateMinutes: 0, overtimeMinutes: 0 },
  { id: 7, employeeName: 'Gunawan', nip: '2024007', department: 'Gudang', date: '2026-05-27', checkIn: '--', checkOut: '--', status: 'Alfa', lateMinutes: 0, overtimeMinutes: 0 },
  { id: 8, employeeName: 'Hendra Gunawan', nip: '2024008', department: 'Produksi', date: '2026-05-27', checkIn: '07:25', checkOut: '18:00', status: 'Hadir', lateMinutes: 0, overtimeMinutes: 120 },
  { id: 9, employeeName: 'Indah Sari', nip: '2024009', department: 'QC', date: '2026-05-27', checkIn: '07:50', checkOut: '16:00', status: 'Hadir', lateMinutes: 0, overtimeMinutes: 0 },
  { id: 10, employeeName: 'Joko Purnomo', nip: '2024010', department: 'Produksi', date: '2026-05-27', checkIn: '08:05', checkOut: '17:00', status: 'Terlambat', lateMinutes: 35, overtimeMinutes: 0 },
  { id: 11, employeeName: 'Kartika Dewi', nip: '2024011', department: 'Gudang', date: '2026-05-27', checkIn: '07:30', checkOut: '17:00', status: 'Hadir', lateMinutes: 0, overtimeMinutes: 45 },
  { id: 12, employeeName: 'Lukman Hakim', nip: '2024012', department: 'Produksi', date: '2026-05-27', checkIn: '--', checkOut: '--', status: 'Izin', lateMinutes: 0, overtimeMinutes: 0 },
  { id: 13, employeeName: 'Maya Anggraini', nip: '2024013', department: 'QC', date: '2026-05-27', checkIn: '07:15', checkOut: '17:30', status: 'Hadir', lateMinutes: 0, overtimeMinutes: 90 },
  { id: 14, employeeName: 'Nugroho Adi', nip: '2024014', department: 'Produksi', date: '2026-05-27', checkIn: '07:40', checkOut: '17:00', status: 'Hadir', lateMinutes: 0, overtimeMinutes: 0 },
  { id: 15, employeeName: 'Oka Prasetya', nip: '2024015', department: 'Gudang', date: '2026-05-27', checkIn: '07:35', checkOut: '17:00', status: 'Hadir', lateMinutes: 0, overtimeMinutes: 0 },
])

const stats = computed(() => {
  const hadir = attendanceData.value.filter(d => d.status === 'Hadir').length
  const terlambat = attendanceData.value.filter(d => d.status === 'Terlambat').length
  const izin = attendanceData.value.filter(d => d.status === 'Izin').length
  const alfa = attendanceData.value.filter(d => d.status === 'Alfa').length
  return { hadir, terlambat, izin, alfa }
})

const headers = [
  { key: 'employeeName', label: 'Nama' },
  { key: 'nip', label: 'NIP' },
  { key: 'department', label: 'Departemen' },
  { key: 'checkIn', label: 'Check In' },
  { key: 'checkOut', label: 'Check Out' },
  { key: 'status', label: 'Status' },
  { key: 'lateMinutes', label: 'Terlambat' },
  { key: 'overtimeMinutes', label: 'Lembur' },
]

const currentPage = ref(1)
const perPage = ref(10)
const totalPages = computed(() => Math.ceil(attendanceData.value.length / perPage.value))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return attendanceData.value.slice(start, start + perPage.value)
})

function statusBadge(status) {
  switch (status) {
    case 'Hadir': return 'success'
    case 'Terlambat': return 'warning'
    case 'Izin': return 'info'
    case 'Alfa': return 'danger'
    default: return 'neutral'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-xl font-semibold text-(--text-main)">Data Kehadiran</h3>
        <p class="text-sm text-(--text-muted) mt-1">Data kehadiran tim Anda</p>
      </div>
      <div class="flex items-center gap-3">
        <input
          type="date"
          :value="selectedDate"
          @input="selectedDate = $event.target.value"
          class="px-3 py-2 rounded-md border bg-(--bg-card) text-(--text-main) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors text-sm"
        />
        <BaseButton variant="secondary" size="sm">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Export
        </BaseButton>
      </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
        <div class="flex items-center gap-2">
          <IconCalendarCheck class="w-4 h-4 text-(--success)" />
          <span class="text-xs text-(--text-muted) uppercase tracking-wider">Hadir</span>
        </div>
        <p class="text-xl font-bold text-(--text-main) mt-1">{{ stats.hadir }}</p>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
        <div class="flex items-center gap-2">
          <IconClock class="w-4 h-4 text-(--warning)" />
          <span class="text-xs text-(--text-muted) uppercase tracking-wider">Terlambat</span>
        </div>
        <p class="text-xl font-bold text-(--text-main) mt-1">{{ stats.terlambat }}</p>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
        <div class="flex items-center gap-2">
          <IconFileInvoice class="w-4 h-4 text-(--primary)" />
          <span class="text-xs text-(--text-muted) uppercase tracking-wider">Izin</span>
        </div>
        <p class="text-xl font-bold text-(--text-main) mt-1">{{ stats.izin }}</p>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
        <div class="flex items-center gap-2">
          <IconClock class="w-4 h-4 text-(--danger)" />
          <span class="text-xs text-(--text-muted) uppercase tracking-wider">Alfa</span>
        </div>
        <p class="text-xl font-bold text-(--text-main) mt-1">{{ stats.alfa }}</p>
      </div>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="paginated" :show-search="true">
        <template #item.employeeName="{ value }">
          <span class="font-medium text-(--text-main)">{{ value }}</span>
        </template>
        <template #item.status="{ value }">
          <Badge :variant="statusBadge(value)">{{ value }}</Badge>
        </template>
        <template #item.lateMinutes="{ value }">
          <span v-if="value > 0" class="text-(--warning) font-medium">{{ value }} mnt</span>
          <span v-else class="text-(--text-muted)">-</span>
        </template>
        <template #item.overtimeMinutes="{ value }">
          <span v-if="value > 0" class="text-(--primary) font-medium">{{ value }} mnt</span>
          <span v-else class="text-(--text-muted)">-</span>
        </template>
      </DataTable>
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total="attendanceData.length"
        :per-page="perPage"
        @page-change="currentPage = $event"
      />
    </BaseCard>
  </div>
</template>
