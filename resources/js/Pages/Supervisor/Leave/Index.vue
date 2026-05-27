<script setup>
import { ref, computed } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import Badge from '../../../Components/Badge.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { useDate } from '../../../composables/useDate'
import {
  IconDownload,
} from '../../../Components/Icons/index.js'

const { format: formatDate } = useDate()

const leaveData = ref([
  { id: 1, employeeName: 'Fitriani', nip: '2024006', leaveType: 'Sakit', startDate: '2026-05-27', endDate: '2026-05-27', totalDays: 1, reason: 'Demam berdarah', status: 'Disetujui' },
  { id: 2, employeeName: 'Gunawan', nip: '2024007', leaveType: 'Izin', startDate: '2026-05-27', endDate: '2026-05-27', totalDays: 1, reason: 'Keperluan keluarga', status: 'Disetujui' },
  { id: 3, employeeName: 'Hendra Gunawan', nip: '2024008', leaveType: 'Cuti Tahunan', startDate: '2026-06-10', endDate: '2026-06-14', totalDays: 5, reason: 'Liburan keluarga', status: 'Menunggu' },
  { id: 4, employeeName: 'Joko Purnomo', nip: '2024010', leaveType: 'Cuti Tahunan', startDate: '2026-06-20', endDate: '2026-06-22', totalDays: 3, reason: 'Acara pernikahan saudara', status: 'Menunggu' },
  { id: 5, employeeName: 'Lukman Hakim', nip: '2024012', leaveType: 'Izin', startDate: '2026-05-27', endDate: '2026-05-27', totalDays: 1, reason: 'Urusan administrasi', status: 'Disetujui' },
  { id: 6, employeeName: 'Nugroho Adi', nip: '2024014', leaveType: 'Cuti Melahirkan', startDate: '2026-07-01', endDate: '2026-09-28', totalDays: 90, reason: 'Persalinan', status: 'Disetujui' },
  { id: 7, employeeName: 'Oka Prasetya', nip: '2024015', leaveType: 'Sakit', startDate: '2026-05-25', endDate: '2026-05-26', totalDays: 2, reason: 'Sakit maag', status: 'Disetujui' },
  { id: 8, employeeName: 'Andi Prasetyo', nip: '2024001', leaveType: 'Cuti Tahunan', startDate: '2026-06-05', endDate: '2026-06-05', totalDays: 1, reason: 'Keperluan pribadi', status: 'Menunggu' },
])

const headers = [
  { key: 'employeeName', label: 'Nama' },
  { key: 'nip', label: 'NIP' },
  { key: 'leaveType', label: 'Jenis Cuti' },
  { key: 'startDate', label: 'Tanggal Mulai' },
  { key: 'endDate', label: 'Tanggal Selesai' },
  { key: 'totalDays', label: 'Total Hari' },
  { key: 'reason', label: 'Alasan' },
  { key: 'status', label: 'Status' },
]

const currentPage = ref(1)
const perPage = ref(10)
const totalPages = computed(() => Math.ceil(leaveData.value.length / perPage.value))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return leaveData.value.slice(start, start + perPage.value)
})

function statusBadge(status) {
  switch (status) {
    case 'Disetujui': return 'success'
    case 'Ditolak': return 'danger'
    case 'Menunggu': return 'warning'
    default: return 'neutral'
  }
}

function typeBadge(type) {
  switch (type) {
    case 'Cuti Tahunan': return 'primary'
    case 'Cuti Melahirkan': return 'primary'
    case 'Sakit': return 'warning'
    case 'Izin': return 'info'
    default: return 'neutral'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-xl font-semibold text-(--text-main)">Cuti</h3>
        <p class="text-sm text-(--text-muted) mt-1">Daftar pengajuan cuti tim Anda</p>
      </div>
      <BaseButton variant="secondary" size="sm">
        <template #icon-left>
          <IconDownload class="w-4 h-4" />
        </template>
        Export
      </BaseButton>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="paginated" :show-search="true">
        <template #item.employeeName="{ value }">
          <span class="font-medium text-(--text-main)">{{ value }}</span>
        </template>
        <template #item.leaveType="{ value }">
          <Badge :variant="typeBadge(value)">{{ value }}</Badge>
        </template>
        <template #item.startDate="{ value }">
          {{ formatDate(value) }}
        </template>
        <template #item.endDate="{ value }">
          {{ formatDate(value) }}
        </template>
        <template #item.totalDays="{ value }">
          {{ value }} hari
        </template>
        <template #item.reason="{ value }">
          <span class="text-sm text-(--text-muted) max-w-[200px] truncate block">{{ value }}</span>
        </template>
        <template #item.status="{ value }">
          <Badge :variant="statusBadge(value)">{{ value }}</Badge>
        </template>
      </DataTable>
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total="leaveData.length"
        :per-page="perPage"
        @page-change="currentPage = $event"
      />
    </BaseCard>
  </div>
</template>
