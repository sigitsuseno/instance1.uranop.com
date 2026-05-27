<template>
  <div class="p-6 space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-(--text-main)">Selamat Datang, Admin!</h1>
      <p class="text-(--text-muted) mt-1">Berikut ringkasan data HRIS hari ini.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-6 relative overflow-hidden">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-(--primary) rounded-l-md"></div>
        <div class="flex items-start justify-between pl-1">
          <div>
            <p class="text-sm text-(--text-muted)">Total Karyawan</p>
            <p class="text-2xl font-bold text-(--text-main) mt-1">{{ stats.totalKaryawan }}</p>
            <p class="text-xs text-(--text-muted) mt-1">Karyawan aktif</p>
          </div>
          <div class="p-2.5 rounded-md bg-(--primary)/10 text-(--primary)">
            <IconUsers class="w-5 h-5" />
          </div>
        </div>
      </div>

      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-6 relative overflow-hidden">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-(--success) rounded-l-md"></div>
        <div class="flex items-start justify-between pl-1">
          <div>
            <p class="text-sm text-(--text-muted)">Hadir Hari Ini</p>
            <p class="text-2xl font-bold text-(--text-main) mt-1">{{ stats.hadirHariIni }}</p>
            <p class="text-xs text-(--text-muted) mt-1">dari {{ stats.totalKaryawan }} karyawan</p>
          </div>
          <div class="p-2.5 rounded-md bg-(--success)/10 text-(--success)">
            <IconCalendarCheck class="w-5 h-5" />
          </div>
        </div>
      </div>

      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-6 relative overflow-hidden">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-(--warning) rounded-l-md"></div>
        <div class="flex items-start justify-between pl-1">
          <div>
            <p class="text-sm text-(--text-muted)">Menunggu Cuti</p>
            <p class="text-2xl font-bold text-(--text-main) mt-1">{{ stats.menungguCuti }}</p>
            <p class="text-xs text-(--text-muted) mt-1">Perlu persetujuan</p>
          </div>
          <div class="p-2.5 rounded-md bg-(--warning)/10 text-(--warning)">
            <IconClock class="w-5 h-5" />
          </div>
        </div>
      </div>

      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-6 relative overflow-hidden">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-purple-500 rounded-l-md"></div>
        <div class="flex items-start justify-between pl-1">
          <div>
            <p class="text-sm text-(--text-muted)">Total Payroll</p>
            <p class="text-2xl font-bold text-(--text-main) mt-1">{{ stats.totalPayroll }}</p>
            <p class="text-xs text-(--text-muted) mt-1">Bulan ini</p>
          </div>
          <div class="p-2.5 rounded-md bg-purple-500/10 text-purple-500">
            <IconDollarSign class="w-5 h-5" />
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2">
        <BaseCard>
          <template #title>Permohonan Cuti Terbaru</template>
          <template #actions>
            <BaseButton variant="ghost" size="sm">Lihat Semua</BaseButton>
          </template>
          <DataTable :headers="leaveHeaders" :items="recentLeaves">
            <template #item.status="{ value }">
              <Badge :variant="leaveStatusVariant(value)">{{ value }}</Badge>
            </template>
          </DataTable>
        </BaseCard>
      </div>

      <div>
        <BaseCard>
          <template #title>Kontrak Akan Berakhir</template>
          <template #actions>
            <BaseButton variant="ghost" size="sm">Lihat Semua</BaseButton>
          </template>
          <div class="space-y-3">
            <div
              v-for="contract in contractsExpiring"
              :key="contract.id"
              class="flex items-center justify-between border-b border-(--border-soft) pb-3 last:border-0 last:pb-0"
            >
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-(--text-main) truncate">{{ contract.name }}</p>
                <p class="text-xs text-(--text-muted)">{{ contract.kontrak }} &middot; Berakhir {{ contract.berakhir }}</p>
              </div>
              <Badge :variant="sisaVariant(contract.sisa)">{{ contract.sisa }} hari</Badge>
            </div>
          </div>
        </BaseCard>
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseCard from '../../Components/BaseCard.vue'
import BaseButton from '../../Components/BaseButton.vue'
import DataTable from '../../Components/Table/DataTable.vue'
import Badge from '../../Components/Badge.vue'
import { IconUsers, IconCalendarCheck, IconClock, IconDollarSign } from '../../Components/Icons/index.js'

const stats = {
  totalKaryawan: 156,
  hadirHariIni: 142,
  menungguCuti: 8,
  totalPayroll: 'Rp 425.000.000',
}

const recentLeaves = [
  { id: 1, name: 'Andi Pratama', type: 'Cuti Tahunan', date: '15 Jun 2026', status: 'Disetujui' },
  { id: 2, name: 'Siti Rahayu', type: 'Cuti Sakit', date: '10 Jun 2026', status: 'Menunggu' },
  { id: 3, name: 'Budi Santoso', type: 'Cuti Melahirkan', date: '5 Jun 2026', status: 'Disetujui' },
  { id: 4, name: 'Dewi Lestari', type: 'Cuti Pribadi', date: '2 Jun 2026', status: 'Ditolak' },
  { id: 5, name: 'Rudi Hermawan', type: 'Cuti Tahunan', date: '28 Mei 2026', status: 'Disetujui' },
]

const leaveHeaders = [
  { key: 'name', label: 'Nama Karyawan' },
  { key: 'type', label: 'Jenis Cuti' },
  { key: 'date', label: 'Tanggal' },
  { key: 'status', label: 'Status' },
]

const contractsExpiring = [
  { id: 1, name: 'Hendra Gunawan', kontrak: 'PKWTT', berakhir: '15 Jun 2026', sisa: 19 },
  { id: 2, name: 'Fitriani', kontrak: 'PKWT', berakhir: '10 Jun 2026', sisa: 14 },
  { id: 3, name: 'Agus Wijaya', kontrak: 'PKWT', berakhir: '5 Jun 2026', sisa: 9 },
  { id: 4, name: 'Ratna Dewi', kontrak: 'PKWTT', berakhir: '30 Mei 2026', sisa: 3 },
  { id: 5, name: 'Dimas Ardian', kontrak: 'PKWT', berakhir: '28 Mei 2026', sisa: 1 },
]

function leaveStatusVariant(status) {
  switch (status) {
    case 'Disetujui': return 'success'
    case 'Menunggu': return 'warning'
    case 'Ditolak': return 'danger'
    default: return 'neutral'
  }
}

function sisaVariant(sisa) {
  if (sisa > 14) return 'success'
  if (sisa >= 7) return 'warning'
  return 'danger'
}
</script>
