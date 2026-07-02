<template>
  <div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Slip Gaji</h1>
        <p class="text-sm text-(--text-muted) mt-1">Cetak slip gaji karyawan per periode</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="selectedPeriodId" class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer" @change="onPeriodChange">
          <option value="">Pilih Periode</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <BaseButton variant="success" :disabled="!selectedPeriodId || records.length === 0" @click="doBulkPrint">
          <template #icon-left>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          </template>
          Cetak Semua ({{ isSplit ? '4' : '6' }}/lembar)
        </BaseButton>
      </div>
    </div>

    <div v-if="selectedPeriod" class="px-4 py-3 rounded-md bg-(--primary)/5 border border-(--primary)/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
      <div class="flex flex-wrap items-center gap-4 text-sm">
        <span class="font-bold text-(--primary)">{{ selectedPeriod.name }}</span>
        <span class="text-(--text-muted) font-medium">{{ selectedPeriod.date_range }}</span>
        <Badge :variant="selectedPeriod.is_split ? 'warning' : 'success'">{{ selectedPeriod.is_split ? 'Split Periode' : 'Periode Normal' }}</Badge>
        <span class="text-(--text-muted) font-medium">{{ records.length }} Karyawan</span>
      </div>
      <div v-if="selectedPeriod.is_split" class="flex gap-2">
        <button v-for="seg in ['A', 'B']" :key="seg" @click="switchSegment(seg)" class="h-8 px-4 rounded-md text-xs font-semibold transition-all cursor-pointer" :class="activeSegment === seg ? 'bg-(--primary) text-white shadow-sm' : 'bg-(--bg-card) text-(--text-muted) hover:text-(--text-main) border border-(--border-soft)'">Seg-{{ seg === 'A' ? '1' : '2' }}</button>
      </div>
    </div>

    <div v-if="records.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 shadow-sm"><div class="text-xs text-(--text-muted) mb-1">Total Karyawan</div><div class="text-2xl font-bold text-(--text-main)">{{ stats.total_karyawan ?? 0 }}</div></div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 shadow-sm"><div class="text-xs text-(--text-muted) mb-1">Total Gaji Kotor</div><div class="text-lg font-bold text-(--primary)">{{ formatCurrency(stats.total_gaji_kotor, true) }}</div></div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 shadow-sm"><div class="text-xs text-(--text-muted) mb-1">Total Potongan</div><div class="text-lg font-bold text-red-600">{{ formatCurrency(stats.total_potongan, true) }}</div></div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 shadow-sm"><div class="text-xs text-(--text-muted) mb-1">Total Bersih</div><div class="text-lg font-bold text-green-600">{{ formatCurrency(stats.total_bersih, true) }}</div></div>
    </div>

    <BaseCard v-if="selectedPeriod" padding="p-0" class="overflow-hidden border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
      <div class="px-4 py-3 border-b border-(--border-soft) flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-(--bg-main)/30">
        <div><h3 class="text-sm font-semibold text-(--text-main)">Daftar Slip Gaji</h3><p class="text-xs text-(--text-muted) mt-0.5" v-if="records.length > 0">Menampilkan {{ filteredRecords.length }} dari {{ records.length }} data</p><p class="text-xs text-(--text-muted) mt-0.5" v-else>Tidak ada data slip gaji untuk periode ini.</p></div>
        <div class="relative w-full sm:w-72" v-if="records.length > 0">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-soft)"><IconSearch class="h-4 w-4" /></span>
          <input v-model="searchQuery" type="text" placeholder="Cari nama, NIP..." class="w-full h-10 pl-9 pr-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all" />
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead><tr class="bg-(--bg-elevated) text-(--text-main)">
            <th class="border border-(--border-soft) px-2.5 py-3 text-center font-semibold w-10">No</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-center font-semibold w-20">ID No</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-left font-semibold min-w-[160px]">Nama Karyawan</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-left font-semibold min-w-[100px]">Bagian</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold min-w-[100px]">Gaji Pokok</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-center font-semibold w-12">HK</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold min-w-[90px]">Upah Lembur</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold min-w-[90px]">Premi Hadir</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold min-w-[100px]">Gaji Kotor</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold text-red-600 min-w-[90px]">Potongan</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-right font-extrabold text-(--primary) bg-(--primary)/5 min-w-[110px]">Take Home</th>
            <th class="border border-(--border-soft) px-2.5 py-3 text-center font-semibold w-16">Cetak</th>
          </tr></thead>
          <tbody>
            <tr v-if="filteredRecords.length === 0"><td colspan="12" class="px-4 py-12 text-center text-(--text-muted) text-sm bg-(--bg-card)">
              <div v-if="loading" class="flex items-center justify-center gap-2"><svg class="animate-spin h-5 w-5 text-(--primary)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>Memproses data...</div>
              <div v-else>Belum ada data slip gaji untuk periode ini. Pastikan data gaji sudah di-generate dan dikunci pada menu <strong>Gaji Karyawan</strong>.</div>
            </td></tr>
            <tr v-for="(record, idx) in filteredRecords" :key="record.id" class="hover-row transition-colors" :class="idx % 2 === 0 ? 'bg-(--bg-card)' : 'bg-(--bg-main)/40'">
              <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ idx + 1 }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-center font-mono text-xs text-(--text-muted)">{{ record.employee_code }}</td>
              <td class="border border-(--border-soft) px-2.5 py-2"><div class="font-semibold text-(--text-main)">{{ record.employee_name }}</div><div class="text-[10px] text-(--text-muted)">{{ record.position }}</div></td>
              <td class="border border-(--border-soft) px-2.5 py-2 text-(--text-main)">{{ record.department }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.gaji_pokok) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-main) font-medium">{{ record.hari_kerja }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.upah_lembur) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.premi_hadir) }}</td>
              <td class="border border-(--border-soft) px-2.5 py-2 text-right font-mono font-bold text-(--primary)">{{ formatCurrency(record.gaji_kotor) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-red-600">{{ formatCurrency(record.bpjs_tk + record.bpjs_ks + record.bpjs_pen + record.pph + record.cashbon + record.pot_kehadiran) }}</td>
              <td class="border border-(--border-soft) px-2.5 py-2 text-right font-mono font-extrabold text-(--primary) bg-(--primary)/5">{{ formatCurrency(record.gaji_bersih) }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-center"><button @click="printSingle(record)" class="w-8 h-8 flex items-center justify-center rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-muted) hover:text-(--primary) hover:border-(--primary)/40 transition-colors mx-auto cursor-pointer" title="Cetak Slip Gaji"><i class="bx bx-printer text-base"></i></button></td>
            </tr>
          </tbody>
          <tfoot v-if="filteredRecords.length > 0"><tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
            <td colspan="4" class="border border-(--border-soft) px-2.5 py-3.5 text-right font-bold">TOTAL</td>
            <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.gaji_pokok, true) }}</td>
            <td class="border border-(--border-soft) px-2 py-3.5 text-center">{{ totals.hari_kerja }}</td>
            <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.upah_lembur, true) }}</td>
            <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.premi_hadir, true) }}</td>
            <td class="border border-(--border-soft) px-2.5 py-3.5 text-right font-mono font-bold">{{ formatCurrency(totals.gaji_kotor, true) }}</td>
            <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-red-600">{{ formatCurrency(totals.potongan, true) }}</td>
            <td class="border border-(--border-soft) px-2.5 py-3.5 text-right font-mono text-(--primary) bg-(--primary)/5">{{ formatCurrency(totals.gaji_bersih, true) }}</td>
            <td class="border border-(--border-soft) px-2 py-3.5"></td>
          </tr></tfoot>
        </table>
      </div>
    </BaseCard>

    <BaseCard v-else class="py-16 border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
      <div class="text-center space-y-4 max-w-sm mx-auto">
        <div class="w-16 h-16 mx-auto rounded-full bg-(--bg-elevated) flex items-center justify-center text-(--text-muted)"><IconFileInvoice class="w-8 h-8" /></div>
        <div><h3 class="text-lg font-bold text-(--text-main)">Pilih Periode</h3><p class="text-sm text-(--text-muted) mt-1">Pilih periode dari dropdown di atas untuk melihat dan mencetak slip gaji karyawan</p></div>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseCard from '@/Components/BaseCard.vue'
import Badge from '@/Components/Badge.vue'
import { IconSearch, IconFileInvoice } from '@/Components/Icons/index.js'
import { useApi } from '@/composables/useApi'
import { useNotificationStore } from '@/Stores/notification'

const { get } = useApi()
const notification = useNotificationStore()

const periods = ref([])
const selectedPeriodId = ref('')
const records = ref([])
const stats = ref({})
const loading = ref(false)
const activeSegment = ref(null)
const searchQuery = ref('')
const fixedWorkDay = ref(25)

const selectedPeriod = computed(() => periods.value.find(p => p.id === selectedPeriodId.value))
const isSplit = computed(() => selectedPeriod.value?.is_split ?? false)

const filteredRecords = computed(() => {
  if (!searchQuery.value) return records.value
  const q = searchQuery.value.toLowerCase()
  return records.value.filter(r => r.employee_name.toLowerCase().includes(q) || r.employee_code.toLowerCase().includes(q) || r.department.toLowerCase().includes(q))
})

const totals = computed(() => {
  const fSum = (key) => filteredRecords.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    gaji_pokok: fSum('gaji_pokok'), hari_kerja: filteredRecords.value.reduce((acc, r) => acc + (parseInt(r.hari_kerja) || 0), 0),
    upah_lembur: fSum('upah_lembur'), premi_hadir: fSum('premi_hadir'), gaji_kotor: fSum('gaji_kotor'),
    potongan: filteredRecords.value.reduce((acc, r) => acc + (r.bpjs_tk + r.bpjs_ks + r.bpjs_pen + r.pph + r.cashbon + r.pot_kehadiran), 0),
    gaji_bersih: fSum('gaji_bersih'),
  }
})

function formatCurrency(value, showZero = false) {
  if (!value && value !== 0) return '-'
  if (parseFloat(value) === 0 && !showZero) return '-'
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value)
}

async function fetchPeriods() {
  try { const res = await get('/api/v1/payroll/periods'); periods.value = res.data || [] } catch (error) { console.error('Error fetching periods', error) }
}

async function fetchPayslips() {
  if (!selectedPeriodId.value) { records.value = []; stats.value = {}; return }
  loading.value = true
  try {
    let url = `/api/v1/supervisor/payroll/payslips?period_id=${selectedPeriodId.value}`
    if (activeSegment.value) url += `&segment=${activeSegment.value}`
    const res = await get(url)
    records.value = res.data || []
    stats.value = res.stats || {}
    fixedWorkDay.value = res.fixed_work_day || 25
  } catch (error) { records.value = []; stats.value = {} }
  finally { loading.value = false }
}

async function switchSegment(seg) { activeSegment.value = seg; await fetchPayslips() }

async function onPeriodChange() {
  const period = periods.value.find(p => p.id === selectedPeriodId.value)
  activeSegment.value = period?.is_split ? 'A' : null
  searchQuery.value = ''
  await fetchPayslips()
}

function printSingle(record) {
  const slips = isSplit.value ? [buildSplitSlipData(record)] : [buildNormalSlipData(record)]
  const html = generateBulkPrintHtml(slips, isSplit.value)
  openPrintWindow(html)
}

function doBulkPrint() {
  if (!records.value.length) { notification.error('Tidak ada data untuk dicetak'); return }
  let slips = isSplit.value ? records.value.map(r => buildSplitSlipData(r)).filter(Boolean) : records.value.map(r => buildNormalSlipData(r)).filter(Boolean)
  const html = generateBulkPrintHtml(slips, isSplit.value)
  openPrintWindow(html)
}

function openPrintWindow(html) {
  const win = window.open('', '_blank', 'width=900,height=700')
  if (!win) { notification.error('Popup diblokir browser. Izinkan popup dan coba lagi.'); return }
  win.document.write(html); win.document.close(); win.focus()
  setTimeout(() => win.print(), 300)
}

function buildNormalSlipData(r) {
  const hk = fixedWorkDay.value
  const ratePerHari = hk > 0 ? r.gaji_pokok / hk : 0
  const hkAmount = hk * ratePerHari
  const deductDays = r.deduct_day || 0
  const potKehadiran = r.pot_kehadiran || Math.round(deductDays * ratePerHari)
  const subTotalEarnings = hkAmount + r.tj_masa_kerja + r.tunjangan + r.premi_hadir + r.upah_lembur + (r.revisi || 0)
  const subTotalDeduction = r.bpjs_tk + r.bpjs_ks + r.bpjs_pen + r.pph + r.cashbon + potKehadiran
  return { isSplit: false, employeeName: r.employee_name, employeeCode: r.employee_code, periodName: selectedPeriod.value?.name || '', remainingLeave: r.remaining_leave || 0, ratePerHari: Math.round(ratePerHari), hkDays: hk, hkAmount: Math.round(hkAmount), tjMasaKerja: r.tj_masa_kerja, tunjangan: r.tunjangan, premiHadir: r.premi_hadir, overtimeHours: (r.lm_count || 0) + (r.lembur_count || 0), overtimeAmount: r.upah_lembur, revisi: r.revisi || 0, subTotalEarnings: Math.round(subTotalEarnings), bpjsTK: r.bpjs_tk, bpjsKes: r.bpjs_ks, bpjsPensiun: r.bpjs_pen, pph: r.pph, cashbon: r.cashbon, deductDays: deductDays, potonganKehadiran: potKehadiran, pblt: r.pblt, subTotalDeduction: Math.round(subTotalDeduction), totalTerima: r.gaji_bersih }
}

function buildSplitPartData(r, part) {
  const hk = r.hk || fixedWorkDay.value
  const ratePerHari = fixedWorkDay.value > 0 ? r.gaji_pokok / fixedWorkDay.value : 0
  const hkAmount = hk * ratePerHari
  const deductDays = r.deduct_day || 0
  const potKehadiran = r.pot_kehadiran || Math.round(deductDays * ratePerHari)
  const subTotalEarnings = hkAmount + r.tj_masa_kerja + r.tunjangan + r.premi_hadir + r.upah_lembur + (r.revisi || 0)
  const subTotalDeduction = r.bpjs_tk + r.bpjs_ks + r.bpjs_pen + r.pph + r.cashbon + potKehadiran
  return { part, ratePerHari: Math.round(ratePerHari), hkDays: hk, hkAmount: Math.round(hkAmount), tjMasaKerja: r.tj_masa_kerja, tunjangan: r.tunjangan, premiHadir: r.premi_hadir, overtimeHours: (r.lm_count || 0) + (r.lembur_count || 0), overtimeAmount: r.upah_lembur, revisi: r.revisi || 0, subTotalEarnings: Math.round(subTotalEarnings), bpjsTK: r.bpjs_tk, bpjsKes: r.bpjs_ks, bpjsPensiun: r.bpjs_pen, pph: r.pph, cashbon: r.cashbon, deductDays: deductDays, potonganKehadiran: potKehadiran, pblt: r.pblt, subTotalDeduction: Math.round(subTotalDeduction), totalTerima: r.gaji_bersih }
}

function buildSplitSlipData(r) {
  const p1Data = activeSegment.value === 'A' ? r : (r.other_segment || null)
  const p2Data = activeSegment.value === 'B' ? r : (r.other_segment || null)
  const part1 = p1Data ? buildSplitPartData(p1Data, 1) : null
  const part2 = p2Data ? buildSplitPartData(p2Data, 2) : null
  return { isSplit: true, employeeName: r.employee_name, employeeCode: r.employee_code, remainingLeave: r.remaining_leave || 0, part1, part2, totalTerima: (part1?.totalTerima || 0) + (part2?.totalTerima || 0) }
}

function generateBulkPrintHtml(slips, isSplitMode) {
  const f = (v) => Number(Math.abs(v) || 0).toLocaleString('id-ID')
  const amt = (val, opt = {}) => {
    let num = f(Math.abs(val))
    if (opt.deduct || val < 0) num = `(${num})`
    else if (opt.pblt) num = `+ ${num}`
    let cls = 'amt'; if (opt.bt) cls += ' bt'
    let style = opt.pblt ? 'color:green;font-weight:bold' : ''
    let rp = opt.noDot ? 'Rp' : 'Rp.'
    return `<div class="${cls}" style="${style}"><span>${rp}</span><span>${num}</span></div>`
  }
  const periodName = selectedPeriod.value?.name || ''
  const renderNormalSlip = (s) => `<div class="slip"><div class="sisa-cuti">Sisa Cuti: ${s.remainingLeave} hari</div><div class="co-header"><b class="co-name">PT. KEMILAU UNGARAN SUKSES</b><div class="co-sub">EMBROIDERY &amp; PRINTING FACTORY</div><div class="co-addr">Jl. Ngobo/Jl. PTPN IX No.1 Gudang Dolog BGR Karangjati, Bergas Kab. Smg 50552</div><div class="co-addr">(0298) 522686, 525052</div></div><div class="slip-title">SLIP GAJI</div><div class="emp-info"><span>${s.employeeName}</span><span>BULAN: ${periodName}<br>No ACCOUNT: ${s.employeeCode}</span></div><div class="tbl-hdr"><span>KETERANGAN PENDAPATAN</span><span>JUMLAH</span></div><div class="row b"><span>GAJI POKOK</span><span></span>${amt(s.hkAmount, {noDot: true})}</div><div class="row"><span class="lbl">HK</span><span>${s.hkDays} x Rp ${f(s.ratePerHari)}</span>${amt(s.hkAmount)}</div><div class="row"><span class="lbl">TJ MASA KERJA</span><span></span>${amt(s.tjMasaKerja)}</div><div class="row"><span class="lbl">TUNJANGAN</span><span></span>${amt(s.tunjangan)}</div><div class="row"><span class="lbl">PREMI HADIR</span><span></span>${amt(s.premiHadir)}</div><div class="row"><span class="lbl">LEMBURAN</span><span>${s.overtimeHours} JAM</span>${amt(s.overtimeAmount)}</div><div class="row"><span class="lbl">REVISI</span><span></span>${amt(s.revisi)}</div><div class="row"><span></span><span></span>${amt(s.subTotalEarnings, {bt: true})}</div><div class="sp"></div><div class="row"><span class="lbl">BPJS TK</span><span></span>${amt(s.bpjsTK, {deduct: true})}</div><div class="row"><span class="lbl">BPJS KS</span><span></span>${amt(s.bpjsKes, {deduct: true})}</div><div class="row"><span class="lbl">BPJS PENSIUN</span><span></span>${amt(s.bpjsPensiun, {deduct: true})}</div><div class="row"><span class="lbl">PPH</span><span></span>${amt(s.pph, {deduct: true})}</div><div class="row"><span class="lbl">CASHBON</span><span></span>${amt(s.cashbon, {deduct: true})}</div><div class="row"><span class="lbl">POT. KEHADIRAN</span><span>${s.deductDays} HARI</span>${amt(s.potonganKehadiran, {deduct: true})}</div><div class="row xs"><span></span><span style="text-align:right">PBLT</span>${amt(s.pblt, {pblt: true})}</div><div class="row mb-1"><span></span><span></span>${amt(s.subTotalDeduction, {deduct: true, bt: true})}</div><div class="row total"><span></span><span>TOTAL TERIMA</span>${amt(s.totalTerima)}</div><div class="ftr-date">Tgl. ${new Date().toLocaleDateString('id-ID')}</div><div class="ftr-sign"><div class="sc"><div>HRD,</div><div class="sg"></div><div>ONG KRISTIN</div></div><div class="sc"><div>Diterima oleh,</div><div class="sg"></div><div>${s.employeeName}</div></div></div></div>`
  const renderSplitPart = (p, label) => {
    if (!p) return ''
    return `<div class="split-part-title">${label}</div><div class="row b"><span>GAJI POKOK</span><span></span>${amt(p.hkAmount, {noDot: true})}</div><div class="row"><span class="lbl">HK</span><span> ${p.hkDays} x Rp ${f(p.ratePerHari)}</span>${amt(p.hkAmount)}</div><div class="row"><span class="lbl">TJ MASA KERJA</span><span></span>${amt(p.tjMasaKerja)}</div><div class="row"><span class="lbl">TUNJANGAN</span><span></span>${amt(p.tunjangan)}</div><div class="row"><span class="lbl">PREMI HADIR</span><span></span>${amt(p.premiHadir)}</div><div class="row"><span class="lbl">LEMBURAN</span><span>${p.overtimeHours} JAM</span>${amt(p.overtimeAmount)}</div><div class="row"><span class="lbl">REVISI</span><span></span>${amt(p.revisi)}</div><div class="row"><span></span><span></span>${amt(p.subTotalEarnings, {bt: true})}</div><div class="sp"></div>${p.part === 2 ? `<div class="row"><span class="lbl">BPJS TK</span><span></span>${amt(p.bpjsTK, {deduct: true})}</div><div class="row"><span class="lbl">BPJS KS</span><span></span>${amt(p.bpjsKes, {deduct: true})}</div><div class="row"><span class="lbl">BPJS PENSIUN</span><span></span>${amt(p.bpjsPensiun, {deduct: true})}</div><div class="row"><span class="lbl">PPH</span><span></span>${amt(p.pph, {deduct: true})}</div>` : ''}<div class="row"><span class="lbl">CASHBON</span><span></span>${amt(p.cashbon, {deduct: true})}</div><div class="row"><span class="lbl">POT. KEHADIRAN</span><span>${p.deductDays} HARI</span>${amt(p.potonganKehadiran, {deduct: true})}</div><div class="row xs"><span></span><span style="text-align:right">PBLT</span>${amt(p.pblt, {pblt: true})}</div><div class="row mb-1"><span></span><span></span>${amt(p.subTotalDeduction, {deduct: true, bt: true})}</div><div class="row part-total"><span></span><span>Sub Total</span>${amt(p.totalTerima)}</div>`
  }
  const renderSplitSlip = (s) => `<div class="slip slip-split"><div class="sisa-cuti">Sisa Cuti: ${s.remainingLeave} hari</div><div class="co-header"><b class="co-name">PT. KEMILAU UNGARAN SUKSES</b><div class="co-sub">EMBROIDERY &amp; PRINTING FACTORY</div><div class="co-addr">Jl. Ngobo/Jl. PTPN IX No.1 Gudang Dolog BGR Karangjati, Bergas Kab. Smg 50552</div><div class="co-addr">(0298) 522686, 525052</div></div><div class="slip-title">SLIP GAJI</div><div class="emp-info"><span>${s.employeeName}</span><span>No ACCOUNT: ${s.employeeCode}</span></div><div class="tbl-hdr"><span>KETERANGAN PENDAPATAN</span><span>JUMLAH</span></div><div class="split-container">${renderSplitPart(s.part1, 'PART 1')}<div class="sp border-b border-dashed border-gray-400 my-1"></div>${renderSplitPart(s.part2, 'PART 2')}</div><div class="row total split-total mt-1"><span></span><span>TOTAL TERIMA</span>${amt(s.totalTerima)}</div><div class="ftr-date">Tgl. ${new Date().toLocaleDateString('id-ID')}</div><div class="ftr-sign"><div class="sc"><div>HRD,</div><div class="sg"></div><div>ONG KRISTIN</div></div><div class="sc"><div>Diterima oleh,</div><div class="sg"></div><div>${s.employeeName}</div></div></div></div>`
  const pages = []
  const perPage = isSplitMode ? 4 : 6
  for (let i = 0; i < slips.length; i += perPage) pages.push(slips.slice(i, i + perPage))
  const css = `*{margin:0;padding:0;box-sizing:border-box}body{font-family:Arial,sans-serif;background:#fff}.page{width:210mm;display:grid;grid-template-columns:1fr 1fr;gap:2mm;padding:5mm;page-break-after:always}.page:last-child{page-break-after:auto}.slip{border:1px solid #000;padding:2mm;position:relative;font-size:7pt;overflow:hidden}.slip-split{font-size:6.5pt}.slip-split .row{font-size:6.5pt}.sisa-cuti{position:absolute;top:0;right:0;border-left:1px solid #000;border-bottom:1px solid #000;font-size:6pt;padding:1px 3px}.co-header{border-bottom:1px solid #000;padding-bottom:1mm;margin-bottom:1mm}.co-name{font-size:8pt;text-transform:uppercase}.co-sub{font-style:italic;font-size:6.5pt}.co-addr{font-size:6pt;color:#555}.slip-title{text-align:center;font-weight:bold;font-size:8pt;border-bottom:1px solid #000;margin-bottom:1mm}.emp-info{display:grid;grid-template-columns:1fr 1fr;font-size:7pt;font-weight:500;padding:1mm 0;border-bottom:1px dashed #ccc;margin-bottom:1mm}.tbl-hdr{display:flex;justify-content:space-between;border-top:1px solid #000;border-bottom:1px solid #000;font-weight:bold;font-size:7pt;padding:1px 0;margin-bottom:1mm}.row{display:grid;grid-template-columns:2fr 1.5fr 1.5fr;font-size:7pt;line-height:1.3}.row.b{font-weight:bold}.row.xs{font-size:5.5pt}.row .lbl::after{content:\":\";margin-left:2px}.bt{border-top:1px solid #000}.row.total{border-top:1px solid #000;border-bottom:1px solid #000;font-weight:bold;font-size:7.5pt}.row.part-total{font-weight:bold;font-style:italic}.split-total{font-size:8pt!important;background:#f0f0f0;padding:2px 0}.amt{display:flex;justify-content:space-between;width:100%}.sp{height:1.5mm}.mb-1{margin-bottom:1mm}.mt-1{margin-top:1mm}.my-1{margin-top:1mm;margin-bottom:1mm}.split-part-title{font-weight:bold;text-decoration:underline;margin:1mm 0;text-transform:uppercase}.ftr-date{text-align:right;font-size:6pt;padding:1mm 1mm 0}.ftr-sign{display:flex;gap:4mm;font-size:6pt;padding:0 3mm 1mm}.sc{text-align:center;width:40%}.sg{height:8mm}@media print{body{margin:0}.page{page-break-after:always}.page:last-child{page-break-after:auto}}`
  const renderSlip = isSplitMode ? renderSplitSlip : renderNormalSlip
  return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Slip Gaji - ${periodName}</title><style>${css}</style></head><body>${pages.map((g) => `<div class="page">${g.map(renderSlip).join('')}</div>`).join('')}</body></html>`
}

onMounted(() => { fetchPeriods() })
</script>

<style scoped>
.hover-row:hover { background-color: rgba(var(--primary-glow), 0.05) !important; }
</style>
