<script setup>
import { ref, computed } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { useCurrency } from '../../../composables/useCurrency'
import {
  IconDownload,
  IconEye,
  IconFileInvoice,
} from '../../../Components/Icons/index.js'

const { format } = useCurrency()

const selectedPeriod = ref('2026-05')

const periods = [
  { value: '2026-05', label: 'Mei 2026' },
  { value: '2026-04', label: 'April 2026' },
  { value: '2026-03', label: 'Maret 2026' },
]

const payrollData = ref([
  { id: 1, name: 'Andi Prasetyo', nip: '2024001', department: 'Produksi', basicSalary: 5500000, allowances: 1500000, deductions: 450000, netSalary: 6550000 },
  { id: 2, name: 'Budi Santoso', nip: '2024002', department: 'Produksi', basicSalary: 4800000, allowances: 1200000, deductions: 380000, netSalary: 5620000 },
  { id: 3, name: 'Citra Dewi', nip: '2024003', department: 'QC', basicSalary: 5000000, allowances: 1300000, deductions: 400000, netSalary: 5900000 },
  { id: 4, name: 'Dian Permata', nip: '2024004', department: 'Gudang', basicSalary: 4500000, allowances: 1000000, deductions: 350000, netSalary: 5150000 },
  { id: 5, name: 'Eko Wahyudi', nip: '2024005', department: 'Produksi', basicSalary: 5200000, allowances: 1400000, deductions: 420000, netSalary: 6180000 },
  { id: 6, name: 'Indah Sari', nip: '2024009', department: 'QC', basicSalary: 4700000, allowances: 1100000, deductions: 360000, netSalary: 5440000 },
  { id: 7, name: 'Kartika Dewi', nip: '2024011', department: 'Gudang', basicSalary: 4400000, allowances: 950000, deductions: 340000, netSalary: 5010000 },
  { id: 8, name: 'Maya Anggraini', nip: '2024013', department: 'QC', basicSalary: 5100000, allowances: 1350000, deductions: 410000, netSalary: 6040000 },
])

const summary = computed(() => {
  const total = payrollData.value.reduce((s, r) => s + r.netSalary, 0)
  const avg = Math.round(total / payrollData.value.length)
  const max = Math.max(...payrollData.value.map(r => r.netSalary))
  const min = Math.min(...payrollData.value.map(r => r.netSalary))
  return { total, avg, max, min }
})

const headers = [
  { key: 'name', label: 'Nama' },
  { key: 'nip', label: 'NIP' },
  { key: 'department', label: 'Departemen' },
  { key: 'basicSalary', label: 'Gaji Pokok' },
  { key: 'allowances', label: 'Tunjangan' },
  { key: 'deductions', label: 'Potongan' },
  { key: 'netSalary', label: 'Gaji Bersih' },
]

const currentPage = ref(1)
const perPage = ref(10)
const totalPages = computed(() => Math.ceil(payrollData.value.length / perPage.value))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return payrollData.value.slice(start, start + perPage.value)
})

const showPayslip = ref(false)
const selectedEmployee = ref(null)

function openPayslip(row) {
  selectedEmployee.value = row
  showPayslip.value = true
}

const slipItems = computed(() => {
  if (!selectedEmployee.value) return []
  const e = selectedEmployee.value
  return [
    { label: 'Gaji Pokok', amount: e.basicSalary },
    { label: 'Tunjangan Transport', amount: 500000 },
    { label: 'Tunjangan Makan', amount: 400000 },
    { label: 'Tunjangan Kehadiran', amount: 300000 },
    { label: 'Uang Lembur', amount: e.allowances - 500000 - 400000 - 300000 },
    { label: 'BPJS Kesehatan', amount: -(e.basicSalary * 0.01) },
    { label: 'BPJS Ketenagakerjaan', amount: -(e.basicSalary * 0.02) },
    { label: 'PPh 21', amount: -(e.deductions - (e.basicSalary * 0.03)) },
  ]
})

const slipTotal = computed(() => {
  return slipItems.value.reduce((s, i) => s + i.amount, 0)
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-xl font-semibold text-(--text-main)">Generate Gaji</h3>
        <p class="text-sm text-(--text-muted) mt-1">Data penggajian tim Anda</p>
      </div>
      <div class="flex items-center gap-3">
        <SelectInput
          :model-value="selectedPeriod"
          :options="periods"
          placeholder="Pilih periode"
          @update:model-value="selectedPeriod = $event"
        />
        <BaseButton variant="secondary" size="sm">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Export Excel
        </BaseButton>
        <BaseButton variant="primary" size="sm">
          <template #icon-left>
            <IconFileInvoice class="w-4 h-4" />
          </template>
          Cetak Slip
        </BaseButton>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-5">
        <p class="text-xs text-(--text-muted) uppercase tracking-wider">Total Gaji</p>
        <p class="text-xl font-bold text-(--text-main) mt-1">{{ format(summary.total) }}</p>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-5">
        <p class="text-xs text-(--text-muted) uppercase tracking-wider">Rata-rata Gaji</p>
        <p class="text-xl font-bold text-(--text-main) mt-1">{{ format(summary.avg) }}</p>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-5">
        <p class="text-xs text-(--text-muted) uppercase tracking-wider">Tertinggi</p>
        <p class="text-xl font-bold text-(--text-main) mt-1">{{ format(summary.max) }}</p>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-5">
        <p class="text-xs text-(--text-muted) uppercase tracking-wider">Terendah</p>
        <p class="text-xl font-bold text-(--text-main) mt-1">{{ format(summary.min) }}</p>
      </div>
    </div>

    <BaseCard>
      <DataTable :headers="headers" :items="paginated" :show-search="true" @row-click="openPayslip">
        <template #item.name="{ value }">
          <span class="font-medium text-(--text-main)">{{ value }}</span>
        </template>
        <template #item.basicSalary="{ value }">
          {{ format(value) }}
        </template>
        <template #item.allowances="{ value }">
          <span class="text-(--success)">{{ format(value) }}</span>
        </template>
        <template #item.deductions="{ value }">
          <span class="text-(--danger)">{{ format(value) }}</span>
        </template>
        <template #item.netSalary="{ value }">
          <span class="font-semibold text-(--primary)">{{ format(value) }}</span>
        </template>
      </DataTable>
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total="payrollData.length"
        :per-page="perPage"
        @page-change="currentPage = $event"
      />
    </BaseCard>

    <BaseModal :show="showPayslip" title="Slip Gaji" size="lg" @close="showPayslip = false">
      <template v-if="selectedEmployee">
        <div class="bg-(--bg-elevated) rounded-md p-4 mb-6">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
              <p class="text-xs text-(--text-muted)">Nama</p>
              <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.name }}</p>
            </div>
            <div>
              <p class="text-xs text-(--text-muted)">NIP</p>
              <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.nip }}</p>
            </div>
            <div>
              <p class="text-xs text-(--text-muted)">Departemen</p>
              <p class="text-sm font-medium text-(--text-main)">{{ selectedEmployee.department }}</p>
            </div>
            <div>
              <p class="text-xs text-(--text-muted)">Periode</p>
              <p class="text-sm font-medium text-(--text-main)">Mei 2026</p>
            </div>
          </div>
        </div>

        <table class="w-full border-collapse mb-4">
          <thead>
            <tr class="bg-(--bg-elevated)">
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Komponen</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jumlah</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in slipItems" :key="item.label" class="border-b border-(--border-soft)">
              <td class="px-4 py-3 text-sm text-(--text-main)">{{ item.label }}</td>
              <td class="px-4 py-3 text-sm text-right" :class="item.amount >= 0 ? 'text-(--text-main)' : 'text-(--danger)'">
                {{ item.amount >= 0 ? format(item.amount) : '- ' + format(-item.amount) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td class="px-4 py-3 text-sm font-bold text-(--text-main) border-t-2 border-(--border-soft)">Total Gaji Bersih</td>
              <td class="px-4 py-3 text-sm font-bold text-right text-(--primary) border-t-2 border-(--border-soft)">{{ format(slipTotal) }}</td>
            </tr>
          </tfoot>
        </table>
      </template>
      <template #footer>
        <BaseButton variant="secondary" size="sm" @click="showPayslip = false">Tutup</BaseButton>
        <BaseButton variant="primary" size="sm">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Unduh PDF
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
