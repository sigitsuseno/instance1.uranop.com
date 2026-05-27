<script setup>
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { IconGift, IconDownload } from '../../../Components/Icons/index.js'

const headers = [
  { key: 'employee_name', label: 'Nama' },
  { key: 'nip', label: 'NIP' },
  { key: 'department', label: 'Departemen' },
  { key: 'join_date', label: 'Tanggal Masuk' },
  { key: 'years_of_service', label: 'Masa Kerja' },
  { key: 'thr_amount', label: 'THR' },
  { key: 'status', label: 'Status' },
]

const items = [
  { employee_name: 'Budi Santoso', nip: 'EMP001', department: 'IT', join_date: '2023-01-15', years_of_service: '3 Tahun', thr_amount: 'Rp 8.000.000', status: 'Disalurkan' },
  { employee_name: 'Siti Rahayu', nip: 'EMP002', department: 'HR', join_date: '2022-03-10', years_of_service: '4 Tahun', thr_amount: 'Rp 7.500.000', status: 'Disalurkan' },
  { employee_name: 'Ahmad Rizki', nip: 'EMP003', department: 'Finance', join_date: '2024-06-20', years_of_service: '2 Tahun', thr_amount: 'Rp 6.000.000', status: 'Disalurkan' },
  { employee_name: 'Dewi Kusuma', nip: 'EMP004', department: 'Marketing', join_date: '2023-09-01', years_of_service: '2 Tahun', thr_amount: 'Rp 5.500.000', status: 'Disalurkan' },
  { employee_name: 'Rudi Hartono', nip: 'EMP005', department: 'Operations', join_date: '2021-01-05', years_of_service: '5 Tahun', thr_amount: 'Rp 10.000.000', status: 'Disalurkan' },
]
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-(--text-main)">THR (Tunjangan Hari Raya)</h2>
      <BaseButton variant="primary">
        <IconGift class="w-4 h-4" />
        Generate THR
      </BaseButton>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
      <div v-for="stat in [
        { label: 'Total Karyawan', value: 45 },
        { label: 'Total THR', value: 'Rp 375.000.000' },
        { label: 'Sudah Disalurkan', value: 45 },
      ]" :key="stat.label" class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4">
        <p class="text-sm text-(--text-muted)">{{ stat.label }}</p>
        <p class="text-2xl font-bold text-(--text-main) mt-1">{{ stat.value }}</p>
      </div>
    </div>

    <BaseCard>
      <template #title>Daftar THR Tim - 2026</template>
      <template #actions>
        <BaseButton variant="ghost" size="sm">
          <IconDownload class="w-4 h-4" />
          Export
        </BaseButton>
      </template>
      <DataTable :headers="headers" :items="items" :loading="false" />
    </BaseCard>
  </div>
</template>
