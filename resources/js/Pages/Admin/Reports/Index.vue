<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-(--text-main)">Laporan</h1>
      <p class="text-sm text-(--text-muted) mt-1">Generate dan unduh laporan HRIS</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
      <BaseCard
        v-for="report in reports"
        :key="report.id"
        hoverable
        @click="openReportModal(report)"
      >
        <div class="flex flex-col items-center text-center py-4">
          <div class="w-14 h-14 rounded-md flex items-center justify-center mb-3" :class="report.bgClass">
            <component :is="report.icon" class="w-7 h-7" :class="report.iconClass" />
          </div>
          <h3 class="font-semibold text-(--text-main) mb-1">{{ report.title }}</h3>
          <p class="text-xs text-(--text-muted)">{{ report.description }}</p>
          <BaseButton variant="primary" size="sm" class="mt-3" @click.stop="openReportModal(report)">
            Generate
          </BaseButton>
        </div>
      </BaseCard>
    </div>

    <BaseModal
      :show="!!selectedReport"
      :title="selectedReport?.title || 'Generate Laporan'"
      size="xl"
      @close="selectedReport = null"
    >
      <div v-if="selectedReport">
        <div class="grid grid-cols-2 gap-4 mb-4">
          <TextInput v-model="reportFilter.start_date" label="Tanggal Mulai" type="date" />
          <TextInput v-model="reportFilter.end_date" label="Tanggal Akhir" type="date" />
        </div>
        <SelectInput
          v-if="selectedReport.id === 'salary'"
          v-model="reportFilter.period"
          label="Periode Penggajian"
          :options="[
            { value: 'all', label: 'Semua Periode' },
            { value: 'PAY-2026-05', label: 'Mei 2026' },
            { value: 'PAY-2026-04', label: 'April 2026' },
          ]"
        />
        <SelectInput
          v-if="selectedReport.id !== 'tax' && selectedReport.id !== 'bpjs'"
          v-model="reportFilter.department"
          label="Departemen"
          :options="[
            { value: 'all', label: 'Semua Departemen' },
            { value: 'IT', label: 'Teknologi Informasi' },
            { value: 'Finance', label: 'Keuangan' },
          ]"
        />

        <div class="overflow-x-auto mt-4">
          <table class="w-full border-collapse border border-(--border-soft) rounded-md overflow-hidden">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th v-for="col in getReportHeaders()" :key="col.key" class="px-3 py-2 text-left text-xs font-semibold text-(--text-muted) uppercase">
                  {{ col.label }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in getReportData()" :key="i" class="border-t border-(--border-soft) hover:bg-(--bg-elevated)/50">
                <td v-for="col in getReportHeaders()" :key="col.key" class="px-3 py-2 text-sm text-(--text-main)">
                  {{ row[col.key] }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="selectedReport = null">Tutup</BaseButton>
        <BaseButton variant="primary" @click="downloadReport">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Download Laporan
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive, h } from 'vue'
import { useRouter } from 'vue-router'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import { IconDownload, IconFileInvoice, IconChartBar, IconClock, IconGift, IconCog } from '../../../Components/Icons/index.js'

const router = useRouter()
const selectedReport = ref(null)

const reportFilter = reactive({
  start_date: '',
  end_date: '',
  period: 'all',
  department: 'all',
})

const reports = [
  {
    id: 'attendance',
    title: 'Laporan Absensi',
    description: 'Ringkasan kehadiran, keterlambatan, dan lembur karyawan',
    icon: IconClock,
    bgClass: 'bg-(--primary)/10',
    iconClass: 'text-(--primary)',
    headers: [
      { key: 'employee_name', label: 'Nama' },
      { key: 'department', label: 'Departemen' },
      { key: 'working_days', label: 'Hari Kerja' },
      { key: 'present', label: 'Hadir' },
      { key: 'late', label: 'Terlambat' },
      { key: 'absent', label: 'Absen' },
      { key: 'overtime_hours', label: 'Jam Lembur' },
    ],
    data: [
      { employee_name: 'Ahmad Fauzi', department: 'IT', working_days: 22, present: 20, late: 1, absent: 2, overtime_hours: 12 },
      { employee_name: 'Siti Nurhaliza', department: 'Keuangan', working_days: 22, present: 22, late: 0, absent: 0, overtime_hours: 5 },
      { employee_name: 'Budi Santoso', department: 'SDM', working_days: 22, present: 21, late: 2, absent: 1, overtime_hours: 0 },
      { employee_name: 'Dewi Lestari', department: 'Pemasaran', working_days: 22, present: 19, late: 3, absent: 3, overtime_hours: 8 },
      { employee_name: 'Rudi Hartono', department: 'IT', working_days: 22, present: 21, late: 1, absent: 1, overtime_hours: 15 },
    ],
  },
  {
    id: 'salary',
    title: 'Laporan Gaji',
    description: 'Rekapitulasi gaji pokok, tunjangan, potongan per periode',
    icon: IconFileInvoice,
    bgClass: 'bg-(--success)/10',
    iconClass: 'text-(--success)',
    headers: [
      { key: 'period', label: 'Periode' },
      { key: 'employee_name', label: 'Nama' },
      { key: 'basic_salary', label: 'Gaji Pokok' },
      { key: 'allowances', label: 'Tunjangan' },
      { key: 'deductions', label: 'Potongan' },
      { key: 'net_salary', label: 'Gaji Bersih' },
    ],
    data: [
      { period: 'Mei 2026', employee_name: 'Ahmad Fauzi', basic_salary: 'Rp 8.500.000', allowances: 'Rp 3.000.000', deductions: 'Rp 750.000', net_salary: 'Rp 10.750.000' },
      { period: 'Mei 2026', employee_name: 'Siti Nurhaliza', basic_salary: 'Rp 12.000.000', allowances: 'Rp 3.800.000', deductions: 'Rp 1.200.000', net_salary: 'Rp 14.600.000' },
      { period: 'Mei 2026', employee_name: 'Budi Santoso', basic_salary: 'Rp 7.500.000', allowances: 'Rp 2.000.000', deductions: 'Rp 550.000', net_salary: 'Rp 8.950.000' },
      { period: 'April 2026', employee_name: 'Dewi Lestari', basic_salary: 'Rp 9.000.000', allowances: 'Rp 3.250.000', deductions: 'Rp 800.000', net_salary: 'Rp 11.450.000' },
      { period: 'April 2026', employee_name: 'Rudi Hartono', basic_salary: 'Rp 8.000.000', allowances: 'Rp 2.550.000', deductions: 'Rp 700.000', net_salary: 'Rp 9.850.000' },
    ],
  },
  {
    id: 'tax',
    title: 'Laporan Pajak (PPh 21)',
    description: 'Perhitungan PPh 21 karyawan per bulan',
    icon: IconChartBar,
    bgClass: 'bg-(--warning)/10',
    iconClass: 'text-(--warning)',
    headers: [
      { key: 'employee_name', label: 'Nama' },
      { key: 'nip', label: 'NIP' },
      { key: 'npwp', label: 'NPWP' },
      { key: 'gross_income', label: 'Penghasilan Bruto' },
      { key: 'ptkp', label: 'PTKP' },
      { key: 'pkp', label: 'PKP' },
      { key: 'pph21', label: 'PPh 21' },
    ],
    data: [
      { employee_name: 'Ahmad Fauzi', nip: 'EMP-001', npwp: '12.345.678.9-012.000', gross_income: 'Rp 11.500.000', ptkp: 'Rp 5.250.000', pkp: 'Rp 3.250.000', pph21: 'Rp 162.500' },
      { employee_name: 'Siti Nurhaliza', nip: 'EMP-002', npwp: '98.765.432.1-098.000', gross_income: 'Rp 15.800.000', ptkp: 'Rp 5.250.000', pkp: 'Rp 7.550.000', pph21: 'Rp 525.000' },
      { employee_name: 'Budi Santoso', nip: 'EMP-003', npwp: '45.678.901.2-345.000', gross_income: 'Rp 9.500.000', ptkp: 'Rp 4.500.000', pkp: 'Rp 2.000.000', pph21: 'Rp 100.000' },
      { employee_name: 'Dewi Lestari', nip: 'EMP-004', npwp: '78.901.234.5-678.000', gross_income: 'Rp 12.250.000', ptkp: 'Rp 4.500.000', pkp: 'Rp 4.750.000', pph21: 'Rp 237.500' },
      { employee_name: 'Rudi Hartono', nip: 'EMP-005', npwp: '23.456.789.0-123.000', gross_income: 'Rp 10.550.000', ptkp: 'Rp 5.250.000', pkp: 'Rp 2.300.000', pph21: 'Rp 115.000' },
    ],
  },
  {
    id: 'bpjs',
    title: 'Laporan BPJS',
    description: 'Iuran BPJS Kesehatan dan Ketenagakerjaan',
    icon: IconCog,
    bgClass: 'bg-(--primary)/10',
    iconClass: 'text-(--primary)',
    headers: [
      { key: 'employee_name', label: 'Nama' },
      { key: 'bpjs_health_company', label: 'Kesehatan (Prsh)' },
      { key: 'bpjs_health_employee', label: 'Kesehatan (Kry)' },
      { key: 'jht_company', label: 'JHT (Prsh)' },
      { key: 'jht_employee', label: 'JHT (Kry)' },
      { key: 'jp_company', label: 'JP (Prsh)' },
      { key: 'jp_employee', label: 'JP (Kry)' },
    ],
    data: [
      { employee_name: 'Ahmad Fauzi', bpjs_health_company: 'Rp 340.000', bpjs_health_employee: 'Rp 85.000', jht_company: 'Rp 314.500', jht_employee: 'Rp 170.000', jp_company: 'Rp 170.000', jp_employee: 'Rp 85.000' },
      { employee_name: 'Siti Nurhaliza', bpjs_health_company: 'Rp 480.000', bpjs_health_employee: 'Rp 120.000', jht_company: 'Rp 444.000', jht_employee: 'Rp 240.000', jp_company: 'Rp 240.000', jp_employee: 'Rp 120.000' },
      { employee_name: 'Budi Santoso', bpjs_health_company: 'Rp 300.000', bpjs_health_employee: 'Rp 75.000', jht_company: 'Rp 277.500', jht_employee: 'Rp 150.000', jp_company: 'Rp 150.000', jp_employee: 'Rp 75.000' },
      { employee_name: 'Dewi Lestari', bpjs_health_company: 'Rp 360.000', bpjs_health_employee: 'Rp 90.000', jht_company: 'Rp 333.000', jht_employee: 'Rp 180.000', jp_company: 'Rp 180.000', jp_employee: 'Rp 90.000' },
      { employee_name: 'Rudi Hartono', bpjs_health_company: 'Rp 320.000', bpjs_health_employee: 'Rp 80.000', jht_company: 'Rp 296.000', jht_employee: 'Rp 160.000', jp_company: 'Rp 160.000', jp_employee: 'Rp 80.000' },
    ],
  },
  {
    id: 'leave',
    title: 'Laporan Cuti',
    description: 'Riwayat pengajuan dan sisa cuti karyawan',
    icon: IconGift,
    bgClass: 'bg-(--danger)/10',
    iconClass: 'text-(--danger)',
    headers: [
      { key: 'employee_name', label: 'Nama' },
      { key: 'leave_type', label: 'Jenis Cuti' },
      { key: 'start_date', label: 'Mulai' },
      { key: 'end_date', label: 'Selesai' },
      { key: 'total_days', label: 'Hari' },
      { key: 'reason', label: 'Alasan' },
      { key: 'status', label: 'Status' },
    ],
    data: [
      { employee_name: 'Ahmad Fauzi', leave_type: 'Cuti Tahunan', start_date: '01 Jun', end_date: '03 Jun', total_days: 3, reason: 'Liburan Keluarga', status: 'Disetujui' },
      { employee_name: 'Siti Nurhaliza', leave_type: 'Cuti Tahunan', start_date: '15 Jun', end_date: '16 Jun', total_days: 2, reason: 'Keperluan Pribadi', status: 'Disetujui' },
      { employee_name: 'Budi Santoso', leave_type: 'Cuti Sakit', start_date: '10 Mei', end_date: '11 Mei', total_days: 2, reason: 'Sakit', status: 'Disetujui' },
      { employee_name: 'Dewi Lestari', leave_type: 'Cuti Melahirkan', start_date: '01 Jul', end_date: '30 Sep', total_days: 90, reason: 'Melahirkan', status: 'Disetujui' },
      { employee_name: 'Rudi Hartono', leave_type: 'Cuti Tahunan', start_date: '20 Jun', end_date: '20 Jun', total_days: 1, reason: 'Keperluan Keluarga', status: 'Menunggu' },
    ],
  },
  {
    id: 'thr',
    title: 'Laporan THR',
    description: 'Perhitungan Tunjangan Hari Raya karyawan',
    icon: IconGift,
    bgClass: 'bg-(--success)/10',
    iconClass: 'text-(--success)',
    headers: [
      { key: 'employee_name', label: 'Nama' },
      { key: 'years_of_service', label: 'Masa Kerja' },
      { key: 'basic_salary', label: 'Gaji Pokok' },
      { key: 'thr_amount', label: 'Jumlah THR' },
      { key: 'payment_date', label: 'Tanggal Bayar' },
      { key: 'status', label: 'Status' },
    ],
    data: [
      { employee_name: 'Ahmad Fauzi', years_of_service: '5 Tahun', basic_salary: 'Rp 8.500.000', thr_amount: 'Rp 8.500.000', payment_date: '28 Mar 2026', status: 'Sudah Dibayar' },
      { employee_name: 'Siti Nurhaliza', years_of_service: '8 Tahun', basic_salary: 'Rp 12.000.000', thr_amount: 'Rp 12.000.000', payment_date: '28 Mar 2026', status: 'Sudah Dibayar' },
      { employee_name: 'Budi Santoso', years_of_service: '3 Tahun', basic_salary: 'Rp 7.500.000', thr_amount: 'Rp 7.500.000', payment_date: '28 Mar 2026', status: 'Sudah Dibayar' },
      { employee_name: 'Dewi Lestari', years_of_service: '6 Tahun', basic_salary: 'Rp 9.000.000', thr_amount: 'Rp 9.000.000', payment_date: '28 Mar 2026', status: 'Sudah Dibayar' },
      { employee_name: 'Rudi Hartono', years_of_service: '2 Tahun', basic_salary: 'Rp 8.000.000', thr_amount: 'Rp 8.000.000', payment_date: '28 Mar 2026', status: 'Sudah Dibayar' },
    ],
  },
  {
    id: 'uang_makan',
    title: 'Laporan Uang Makan',
    description: 'Rekapitulasi dan Perhitungan Uang Makan Karyawan',
    icon: IconFileInvoice,
    bgClass: 'bg-(--info)/10',
    iconClass: 'text-indigo-600',
  },
]

function openReportModal(report) {
  if (report.id === 'uang_makan') {
    router.push({ name: 'reports.uang-makan' })
    return
  }
  if (report.id === 'bpjs') {
    router.push({ name: 'reports.bpjs' })
    return
  }
  selectedReport.value = report
  reportFilter.start_date = ''
  reportFilter.end_date = ''
  reportFilter.period = 'all'
  reportFilter.department = 'all'
}

function getReportHeaders() {
  return selectedReport.value?.headers || []
}

function getReportData() {
  return selectedReport.value?.data || []
}

function downloadReport() {
  alert(`Download laporan "${selectedReport.value?.title}" ...`)
  selectedReport.value = null
}
</script>
