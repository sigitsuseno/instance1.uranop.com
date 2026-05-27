<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <button
          class="p-2 rounded-md text-(--text-muted) hover:text-(--text-main) hover:bg-(--bg-elevated) transition-colors"
          @click="$inertia.visit('/admin/payroll/periods')"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
          </svg>
        </button>
        <div>
          <h1 class="text-xl font-semibold text-(--text-main)">{{ period.name }}</h1>
          <p class="text-sm text-(--text-muted)">{{ period.period_code }} &middot; {{ period.date_range }}</p>
          <Badge :variant="statusVariant(period.status)" class="mt-1">{{ statusLabel(period.status) }}</Badge>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="sm" @click="exportReport('excel')">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Export Excel
        </BaseButton>
        <BaseButton variant="secondary" size="sm" @click="exportReport('pdf')">
          <template #icon-left>
            <IconFileInvoice class="w-4 h-4" />
          </template>
          Export PDF
        </BaseButton>
        <BaseButton variant="secondary" size="sm" @click="printSlips">
          <template #icon-left>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 6 2 18 2 18 9" />
              <path d="M6 12H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2" />
              <rect x="6" y="14" width="12" height="8" />
            </svg>
          </template>
          Cetak Slip Gaji
        </BaseButton>
        <BaseButton variant="warning" size="sm" @click="showLockConfirm = true" v-if="period.status !== 'completed'">
          <template #icon-left>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
          </template>
          Kunci Periode
        </BaseButton>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
      <BaseCard v-for="card in summaryCards" :key="card.label">
        <div class="text-center">
          <p class="text-xs text-(--text-muted) mb-1">{{ card.label }}</p>
          <p class="text-lg font-semibold text-(--text-main)">Rp {{ card.value.toLocaleString('id-ID') }}</p>
          <p class="text-xs text-(--text-soft) mt-0.5">{{ card.sub }}</p>
        </div>
      </BaseCard>
    </div>

    <BaseCard>
      <template #title>Daftar Gaji Karyawan</template>
      <template #subtitle>{{ period.total_employees }} karyawan dalam periode ini</template>
      <template #actions>
        <TextInput v-model="searchQuery" placeholder="Cari karyawan..." type="text" class="w-56">
          <template #icon>
            <IconSearch class="w-4 h-4" />
          </template>
        </TextInput>
      </template>

      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-(--bg-elevated)">
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">NIP</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Nama</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Departemen</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jabatan</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Gaji Pokok</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tunjangan</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Lembur</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Potongan</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Gaji Bersih</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(emp, i) in filteredEmployees"
              :key="emp.id"
              class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/50 transition-colors cursor-pointer"
              @click="selectedEmployee = emp"
            >
              <td class="px-4 py-3 text-sm text-(--text-main)">{{ i + 1 }}</td>
              <td class="px-4 py-3 text-sm text-(--text-muted) font-mono">{{ emp.nip }}</td>
              <td class="px-4 py-3 text-sm font-medium text-(--text-main)">{{ emp.employee_name }}</td>
              <td class="px-4 py-3 text-sm text-(--text-main)">{{ emp.department }}</td>
              <td class="px-4 py-3 text-sm text-(--text-main)">{{ emp.position }}</td>
              <td class="px-4 py-3 text-sm text-(--text-main) text-right">Rp {{ emp.basic_salary.toLocaleString('id-ID') }}</td>
              <td class="px-4 py-3 text-sm text-(--text-main) text-right">Rp {{ emp.allowances.toLocaleString('id-ID') }}</td>
              <td class="px-4 py-3 text-sm text-(--text-main) text-right">Rp {{ emp.overtime_pay.toLocaleString('id-ID') }}</td>
              <td class="px-4 py-3 text-sm text-(--danger) text-right">-Rp {{ emp.deductions.toLocaleString('id-ID') }}</td>
              <td class="px-4 py-3 text-sm font-semibold text-(--primary) text-right">Rp {{ emp.net_salary.toLocaleString('id-ID') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

    <BaseModal :show="!!selectedEmployee" :title="selectedEmployee ? `Slip Gaji - ${selectedEmployee.employee_name}` : ''" size="lg" @close="selectedEmployee = null">
      <div v-if="selectedEmployee" class="space-y-4">
        <div class="flex justify-between items-start pb-4 border-b border-(--border-soft)">
          <div>
            <p class="font-semibold text-(--text-main)">PT. Perusahaan Kita</p>
            <p class="text-xs text-(--text-muted)">Jl. Sudirman No. 123, Jakarta</p>
          </div>
          <Badge variant="success">Periode {{ period.name }}</Badge>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><span class="text-(--text-muted)">Nama:</span> <span class="text-(--text-main)">{{ selectedEmployee.employee_name }}</span></div>
          <div><span class="text-(--text-muted)">NIP:</span> <span class="text-(--text-main)">{{ selectedEmployee.nip }}</span></div>
          <div><span class="text-(--text-muted)">Departemen:</span> <span class="text-(--text-main)">{{ selectedEmployee.department }}</span></div>
          <div><span class="text-(--text-muted)">Jabatan:</span> <span class="text-(--text-main)">{{ selectedEmployee.position }}</span></div>
        </div>
        <div class="border-t border-(--border-soft) pt-4">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-(--border-soft)">
                <th class="py-2 text-left text-(--text-muted) font-medium">Komponen</th>
                <th class="py-2 text-right text-(--text-muted) font-medium">Jumlah</th>
              </tr>
            </thead>
            <tbody>
              <tr class="border-b border-(--border-soft)"><td class="py-2 text-(--text-main)">Gaji Pokok</td><td class="py-2 text-right text-(--text-main)">Rp {{ selectedEmployee.basic_salary.toLocaleString('id-ID') }}</td></tr>
              <tr class="border-b border-(--border-soft)"><td class="py-2 text-(--text-main)">Tunjangan</td><td class="py-2 text-right text-(--text-main)">Rp {{ selectedEmployee.allowances.toLocaleString('id-ID') }}</td></tr>
              <tr class="border-b border-(--border-soft)"><td class="py-2 text-(--text-main)">Lembur</td><td class="py-2 text-right text-(--text-main)">Rp {{ selectedEmployee.overtime_pay.toLocaleString('id-ID') }}</td></tr>
              <tr class="border-b border-(--border-soft)"><td class="py-2 text-(--danger)">Potongan</td><td class="py-2 text-right text-(--danger)">-Rp {{ selectedEmployee.deductions.toLocaleString('id-ID') }}</td></tr>
              <tr><td class="py-2 font-semibold text-(--text-main)">Gaji Bersih</td><td class="py-2 text-right font-semibold text-(--primary)">Rp {{ selectedEmployee.net_salary.toLocaleString('id-ID') }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="selectedEmployee = null">Tutup</BaseButton>
        <BaseButton variant="primary" @click="printSlip(selectedEmployee)">Cetak Slip</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="showLockConfirm"
      title="Kunci Periode"
      message="Setelah periode dikunci, seluruh data gaji tidak dapat diubah. Anda yakin ingin melanjutkan?"
      confirm-text="Ya, Kunci"
      variant="warning"
      @confirm="handleLockPeriod"
      @cancel="showLockConfirm = false"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import Badge from '../../../../Components/Badge.vue'
import TextInput from '../../../../Components/TextInput.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import { IconDownload, IconFileInvoice, IconSearch } from '../../../../Components/Icons/index.js'

const period = ref({
  id: 5,
  period_code: 'PAY-2026-05',
  name: 'Mei 2026',
  start_date: '2026-05-01',
  end_date: '2026-05-31',
  date_range: '01 Mei - 31 Mei 2026',
  status: 'completed',
  total_employees: 15,
  total_amount: 108625000,
  total_basic: 72000000,
  total_allowances: 18500000,
  total_overtime: 8500000,
  total_bpjs: 3500000,
  total_pph21: 2125000,
  total_thr: 4000000,
  total_deductions: 5625000,
})

const summaryCards = computed(() => [
  { label: 'Gaji Pokok', value: period.value.total_basic, sub: 'Pendapatan' },
  { label: 'Tunjangan', value: period.value.total_allowances, sub: 'Tetap + Tidak Tetap' },
  { label: 'Lembur', value: period.value.total_overtime, sub: 'Total Jam Lembur' },
  { label: 'BPJS', value: period.value.total_bpjs, sub: 'Kesehatan + TK' },
  { label: 'PPh 21', value: period.value.total_pph21, sub: 'Pajak Penghasilan' },
  { label: 'THR', value: period.value.total_thr, sub: 'Tunjangan Hari Raya' },
])

const employees = ref([
  { id: 1, employee_name: 'Ahmad Fauzi', nip: 'EMP-001', department: 'Teknologi Informasi', position: 'Senior Developer', basic_salary: 8500000, allowances: 2500000, overtime_pay: 500000, deductions: 750000, net_salary: 10750000 },
  { id: 2, employee_name: 'Siti Nurhaliza', nip: 'EMP-002', department: 'Keuangan', position: 'Finance Manager', basic_salary: 12000000, allowances: 3500000, overtime_pay: 300000, deductions: 1200000, net_salary: 14600000 },
  { id: 3, employee_name: 'Budi Santoso', nip: 'EMP-003', department: 'SDM', position: 'HR Supervisor', basic_salary: 7500000, allowances: 2000000, overtime_pay: 0, deductions: 550000, net_salary: 8950000 },
  { id: 4, employee_name: 'Dewi Lestari', nip: 'EMP-004', department: 'Pemasaran', position: 'Marketing Lead', basic_salary: 9000000, allowances: 2800000, overtime_pay: 450000, deductions: 800000, net_salary: 11450000 },
  { id: 5, employee_name: 'Rudi Hartono', nip: 'EMP-005', department: 'Teknologi Informasi', position: 'Backend Developer', basic_salary: 8000000, allowances: 2200000, overtime_pay: 350000, deductions: 700000, net_salary: 9850000 },
  { id: 6, employee_name: 'Rina Marlina', nip: 'EMP-006', department: 'Keuangan', position: 'Accountant', basic_salary: 7000000, allowances: 1800000, overtime_pay: 200000, deductions: 500000, net_salary: 8500000 },
  { id: 7, employee_name: 'Hendra Gunawan', nip: 'EMP-007', department: 'Operasional', position: 'Ops Manager', basic_salary: 9500000, allowances: 3000000, overtime_pay: 600000, deductions: 900000, net_salary: 12200000 },
  { id: 8, employee_name: 'Fitriani', nip: 'EMP-008', department: 'SDM', position: 'Recruitment Specialist', basic_salary: 6500000, allowances: 1500000, overtime_pay: 0, deductions: 450000, net_salary: 7550000 },
  { id: 9, employee_name: 'Agus Wijaya', nip: 'EMP-009', department: 'Teknologi Informasi', position: 'Frontend Developer', basic_salary: 7800000, allowances: 2000000, overtime_pay: 400000, deductions: 650000, net_salary: 9550000 },
  { id: 10, employee_name: 'Lina Kusuma', nip: 'EMP-010', department: 'Pemasaran', position: 'Content Writer', basic_salary: 5500000, allowances: 1200000, overtime_pay: 250000, deductions: 350000, net_salary: 6600000 },
  { id: 11, employee_name: 'Doni Prasetyo', nip: 'EMP-011', department: 'Teknologi Informasi', position: 'DevOps Engineer', basic_salary: 8800000, allowances: 2400000, overtime_pay: 550000, deductions: 780000, net_salary: 10970000 },
  { id: 12, employee_name: 'Maya Anggraini', nip: 'EMP-012', department: 'Keuangan', position: 'Tax Specialist', basic_salary: 8200000, allowances: 2100000, overtime_pay: 150000, deductions: 720000, net_salary: 9730000 },
  { id: 13, employee_name: 'Rahmat Hidayat', nip: 'EMP-013', department: 'Operasional', position: 'Staff Operasional', basic_salary: 4800000, allowances: 1000000, overtime_pay: 350000, deductions: 280000, net_salary: 5870000 },
  { id: 14, employee_name: 'Anisa Putri', nip: 'EMP-014', department: 'Pemasaran', position: 'Graphic Designer', basic_salary: 6200000, allowances: 1600000, overtime_pay: 200000, deductions: 420000, net_salary: 7580000 },
  { id: 15, employee_name: 'Eko Nugroho', nip: 'EMP-015', department: 'SDM', position: 'Training Coordinator', basic_salary: 6800000, allowances: 1700000, overtime_pay: 0, deductions: 500000, net_salary: 8000000 },
])

const searchQuery = ref('')
const selectedEmployee = ref(null)
const showLockConfirm = ref(false)

const filteredEmployees = computed(() => {
  if (!searchQuery.value) return employees.value
  const q = searchQuery.value.toLowerCase()
  return employees.value.filter((e) => e.employee_name.toLowerCase().includes(q) || e.nip.toLowerCase().includes(q) || e.department.toLowerCase().includes(q))
})

function statusVariant(status) {
  const map = { completed: 'success', in_progress: 'warning', draft: 'neutral' }
  return map[status] || 'neutral'
}

function statusLabel(status) {
  const map = { completed: 'Selesai', in_progress: 'Dalam Proses', draft: 'Draft' }
  return map[status] || status
}

function exportReport(type) {
  alert(`Export data ke format ${type.toUpperCase()}`)
}

function printSlips() {
  alert('Mencetak semua slip gaji...')
}

function printSlip(emp) {
  alert(`Mencetak slip gaji ${emp?.employee_name}`)
  selectedEmployee.value = null
}

function handleLockPeriod() {
  period.value.status = 'completed'
  showLockConfirm.value = false
}
</script>
