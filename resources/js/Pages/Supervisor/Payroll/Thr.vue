<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { IconGift, IconDownload } from '../../../Components/Icons/index.js'
import { useApi } from '../../../composables/useApi'
import { useNotification } from '../../../composables/useNotification'

const { get, post, put, destroy } = useApi()
const notify = useNotification()

const items = ref([])
const stats = ref({ total_karyawan: 0, total_thr: 0, sudah_approved: 0, sudah_paid: 0 })
const loading = ref(false)
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const company = ref(null)
const branch = ref(null)
const thrYear = ref(new Date().getFullYear())
const searchQuery = ref('')
const isGenerating = ref(false)
const showGenModal = ref(false)
const showEditModal = ref(false)
const showPrintModal = ref(false)
const editRow = ref(null)
const editLoading = ref(false)
const printRow = ref(null)
const genForm = reactive({ thr_year: new Date().getFullYear(), reference_date: '' })
const editForm = reactive({ tunjangan: 0, tunjangan_masa_kerja: 0, catatan: '' })

const headers = [
  { key: 'employee_name', label: 'Nama' }, { key: 'employee_nip', label: 'NIP' },
  { key: 'lama_bekerja', label: 'Masa Kerja' }, { key: 'gaji_pokok', label: 'Gaji Pokok' },
  { key: 'premi', label: 'Tunjangan' }, { key: 'tunjangan_masa_kerja', label: 'Tj. Masa Kerja' },
  { key: 'total_thr', label: 'Total THR' }, { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false },
]

function fmt(val) { if (!val && val !== 0) return '–'; return 'Rp ' + Number(val).toLocaleString('id-ID') }
function fmtDate(d) { if (!d) return '–'; return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) }

const yearOptions = computed(() => { const y = new Date().getFullYear(); return [y - 1, y, y + 1] })

const editPreview = computed(() => {
  if (!editRow.value) return null
  const gp = parseFloat(editRow.value.gaji_pokok) || 0
  const t = parseFloat(editForm.tunjangan) || 0
  const tmk = parseFloat(editForm.tunjangan_masa_kerja) || 0
  const basis = gp + t + tmk
  let raw = 0
  if (editRow.value.total_bulan >= 12) { raw = basis }
  else { const days = (editRow.value.total_bulan * 30) + editRow.value.sisa_hari; raw = ((basis / 12) / 30) * days }
  const rounded = Math.ceil(raw / 100) * 100
  const pembulatan = rounded - raw
  return { raw, pembulatan, total: rounded, basis }
})

async function fetchData(page = 1) {
  loading.value = true
  try {
    const query = new URLSearchParams(); query.append('page', page); query.append('thr_year', thrYear.value)
    if (searchQuery.value) query.append('search', searchQuery.value)
    const res = await get(`/api/v1/payroll/thr?${query.toString()}`)
    if (res.success) {
      items.value = res.data.data; company.value = res.company; branch.value = res.branch
      pagination.value = { current_page: res.data.current_page, last_page: res.data.last_page, total: res.data.total }
      stats.value = res.stats
    }
  } catch (e) { notify.error('Gagal mengambil data THR') }
  finally { loading.value = false }
}

function submitFilter() { fetchData(1) }
function resetFilter() { searchQuery.value = ''; fetchData(1) }
function handlePageChange(page) { fetchData(page) }

function openGenModal() { genForm.thr_year = thrYear.value; genForm.reference_date = ''; showGenModal.value = true }

async function generateThr() {
  if (!genForm.reference_date) { notify.error('Tanggal referensi wajib diisi'); return }
  isGenerating.value = true
  try {
    const res = await post('/api/v1/payroll/thr/generate', genForm)
    if (res.success) { notify.success(res.message); showGenModal.value = false; fetchData(1) }
    else { notify.error(res.message) }
  } catch (e) { notify.error(e.message || 'Gagal generate THR') }
  finally { isGenerating.value = false }
}

function openEdit(row) { editRow.value = row; editForm.tunjangan = row.premi; editForm.tunjangan_masa_kerja = row.tunjangan_masa_kerja; editForm.catatan = row.catatan || ''; showEditModal.value = true }

async function saveEdit() {
  editLoading.value = true
  try {
    const res = await put(`/api/v1/payroll/thr/${editRow.value.id}`, editForm)
    if (res.success) { notify.success(res.message); showEditModal.value = false; fetchData(pagination.value.current_page) }
  } catch (e) { notify.error(e.message || 'Gagal menyimpan perubahan') }
  finally { editLoading.value = false }
}

async function deleteRow(id) {
  if (!confirm('Hapus data THR ini?')) return
  try { const res = await destroy(`/api/v1/payroll/thr/${id}`); if (res.success) { notify.success(res.message); fetchData(pagination.value.current_page) } }
  catch (e) { notify.error('Gagal menghapus data') }
}

function openPrint(row) { printRow.value = row; showPrintModal.value = true }
function doPrint() { window.print() }

const bulkPrintLoading = ref(false)

async function bulkPrint() {
  bulkPrintLoading.value = true
  try {
    const query = new URLSearchParams({ thr_year: thrYear.value, search: searchQuery.value, all: 1 })
    const res = await get(`/api/v1/payroll/thr?${query.toString()}`)
    if (!res.success || !res.data || res.data.length === 0) { notify.error('Tidak ada data untuk dicetak'); return }
    const dataToPrint = res.data
    const cmp = company.value || { name: 'PT. URA URA' }
    const brc = branch.value || { name: 'EMBROIDERY & PRINTING FACTORY', address: 'Jl. Raya ...' }
    const renderSlip = (item) => `<div class="slip"><div class="header"><div class="c-name">${cmp.name}</div><div class="b-name">${brc.name}</div><div class="b-addr">${brc.address}</div></div><div class="border-box"><div class="flex-row border-b" style="padding: 3px 6px;"><div class="w-8"></div><div class="title-thr">THR ${item.thr_year || new Date().getFullYear()}</div><div class="w-8 txt-right" style="font-weight:bold;">${item.id}</div></div><div class="grid-2 border-b"><div class="col-border-r"><div class="emp-name">${item.employee?.name || ''}</div></div><div class="col-info"><div class="flex-row"><span>BULAN</span><span class="fw-bold">: ${item.thr_month || '-'}</span></div><div class="flex-row"><span>No ACCOUNT</span><span class="fw-bold">: ${item.no_account || '-'}</span></div></div></div><div class="grid-2 border-b bg-gray"><div class="col-center fw-bold">K E T E R A N G A N</div><div class="col-right fw-bold col-border-l">Jumlah</div></div><div class="components border-b"><div class="flex-row"><span>GAJI POKOK</span><span class="mono fw-bold">Rp ${Number(item.gaji_pokok).toLocaleString('id-ID')}</span></div><div class="flex-row"><span>TUNJANGAN</span><span class="mono fw-bold">Rp ${Number(item.premi).toLocaleString('id-ID')}</span></div><div class="flex-row"><span>TJ. MASA KERJA</span><div class="flex-row"><span class="mono fw-bold">Rp ${Number(item.tunjangan_masa_kerja).toLocaleString('id-ID')}</span> &nbsp; <span class="fw-bold">+</span></div></div><div class="flex-row border-t-mt"><span class="mono fw-bold w-full txt-right">Rp ${Number(Number(item.gaji_pokok) + Number(item.premi) + Number(item.tunjangan_masa_kerja)).toLocaleString('id-ID')}</span></div></div><div class="components border-b"><div style="display:flex; gap:16px;"><div class="flex-1"><div class="flex-row"><span class="w-24">TGL AWAL</span><span>: ${fmtDate(item.join_date)}</span></div><div class="flex-row"><span class="w-24">TGL AKHIR</span><span>: ${fmtDate(item.reference_date)}</span></div><div class="flex-row"><span class="w-24">BULAN</span><span>: ${item.total_bulan}</span></div><div class="flex-row"><span class="w-24">LAMA BEKERJA</span><span>: ${item.lama_bekerja || '-'}</span></div></div><div class="txt-right" style="padding-top: 10px;"><div class="mono fw-bold">Rp<br>${Number(item.thr_amount).toLocaleString('id-ID')}</div></div></div></div><div class="components" style="padding-bottom:2px;"><div class="flex-row"><span class="fw-bold">Pbt</span><div class="txt-right flex-row"><div class="mono fw-bold" style="border-bottom:1px solid #9ca3af; padding-bottom:1px; margin-right:4px;">Rp ${Number(item.pembulatan).toLocaleString('id-ID')}</div><span class="fw-bold">+</span></div></div><div class="flex-row" style="margin-top: 2px;"><span class="fw-bold">TOTAL</span><div class="txt-right"><div class="mono fw-bold">Rp ${Number(item.total_thr).toLocaleString('id-ID')}</div></div></div></div></div></div>`
    const pages = []; for (let i = 0; i < dataToPrint.length; i += 6) { pages.push(dataToPrint.slice(i, i + 6)) }
    const css = `@page{size:215mm 330mm;margin:5mm}body{font-family:serif;font-size:7.5pt;margin:0;padding:0}.page{width:205mm;height:320mm;page-break-after:always;display:flex;flex-wrap:wrap;align-content:flex-start}.slip{width:calc(50% - 4mm);height:104mm;margin:2mm;box-sizing:border-box}.header{text-align:center;margin-bottom:2mm}.c-name{color:#b91c1c;font-weight:900;font-size:11pt;text-transform:uppercase}.b-name{font-size:6.5pt;font-style:italic;font-weight:bold}.b-addr{font-size:6.5pt}.border-box{border:1px solid #9ca3af;border-radius:4px;overflow:hidden}.border-b{border-bottom:1px solid #9ca3af}.border-t-mt{border-top:1px solid #e5e7eb;margin-top:2px;padding-top:2px;justify-content:flex-end}.grid-2{display:grid;grid-template-columns:1fr 1fr}.col-border-r{border-right:1px solid #9ca3af;padding:3px 6px}.col-border-l{border-left:1px solid #9ca3af}.col-info{padding:3px 6px}.col-center{text-align:center;padding:3px}.col-right{text-align:right;padding:3px 6px}.bg-gray{background-color:#f3f4f6}.flex-row{display:flex;justify-content:space-between;align-items:center}.components{padding:4px 6px;line-height:1.3}.fw-bold{font-weight:bold}.mono{font-family:monospace}.w-8{width:2rem}.w-24{width:4.5rem;display:inline-block}.w-full{width:100%}.txt-right{text-align:right}.emp-name{font-weight:bold;text-transform:uppercase}.title-thr{font-weight:bold;font-size:10pt;text-align:center;padding:0}.flex-1{flex:1}`
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Bulk Print Slip THR</title><style>${css}</style></head><body>${pages.map(g => '<div class="page">' + g.map(renderSlip).join('') + '</div>').join('')}<script>window.onload=function(){window.print()};<\/script></html>`
    const win = window.open('', '_blank'); win.document.open(); win.document.write(html); win.document.close()
  } catch (e) { notify.error(e.message || 'Gagal menyiapkan print data') }
  finally { bulkPrintLoading.value = false }
}

onMounted(() => { fetchData() })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div><h2 class="text-2xl font-bold text-(--text-main)">Perhitungan THR</h2><p class="text-sm text-(--text-muted) mt-0.5">Kalkulator Tunjangan Hari Raya</p></div>
      <BaseButton variant="primary" @click="openGenModal"><IconGift class="w-4 h-4" />Generate THR</BaseButton>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4"><p class="text-sm text-(--text-muted)">Total Karyawan</p><p class="text-2xl font-bold text-(--text-main) mt-1">{{ stats.total_karyawan }}</p></div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4"><p class="text-sm text-(--text-muted)">Total THR</p><p class="text-2xl font-bold text-primary-600 mt-1">{{ fmt(stats.total_thr) }}</p></div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4"><p class="text-sm text-(--text-muted)">Sudah Disetujui</p><p class="text-2xl font-bold text-blue-600 mt-1">{{ stats.sudah_approved }}</p></div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4"><p class="text-sm text-(--text-muted)">Sudah Dibayar</p><p class="text-2xl font-bold text-green-600 mt-1">{{ stats.sudah_paid }}</p></div>
    </div>
    <BaseCard class="mb-6">
      <div class="flex flex-col sm:flex-row gap-3 items-end p-4">
        <div class="flex-1"><label class="block text-xs font-medium text-(--text-muted) mb-1">Tahun THR</label><select v-model="thrYear" class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-lg text-sm focus:ring-2 focus:ring-primary-500/30 outline-none"><option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option></select></div>
        <div class="flex-[2]"><label class="block text-xs font-medium text-(--text-muted) mb-1">Cari Karyawan</label><input v-model="searchQuery" type="text" placeholder="Nama / NIP..." @keyup.enter="submitFilter" class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-lg text-sm focus:ring-2 focus:ring-primary-500/30 outline-none" /></div>
        <div class="flex gap-2"><BaseButton variant="primary" @click="submitFilter">Filter</BaseButton><BaseButton variant="outline" @click="resetFilter">Reset</BaseButton><BaseButton variant="danger" @click="bulkPrint" :disabled="!items.length || bulkPrintLoading"><i class="bx bx-printer mr-1"></i> Bulk Print</BaseButton></div>
      </div>
    </BaseCard>
    <BaseCard>
      <DataTable :headers="headers" :items="items" :loading="loading" :pagination="pagination" @page-change="handlePageChange">
        <template #item.employee_name="{ item }"><div class="font-medium">{{ item.employee?.name }}</div><div class="text-xs text-(--text-soft) mt-0.5" v-if="item.no_account">Rek: {{ item.no_account }}</div></template>
        <template #item.employee_nip="{ item }"><span class="text-sm">{{ item.employee?.nip || '-' }}</span></template>
        <template #item.lama_bekerja="{ item }"><div class="text-sm font-medium">{{ item.lama_bekerja || '-' }}</div><div class="text-xs text-(--text-soft)">{{ fmtDate(item.join_date) }}</div></template>
        <template #item.gaji_pokok="{ item }"><span class="font-mono">{{ fmt(item.gaji_pokok) }}</span></template>
        <template #item.premi="{ item }"><span class="font-mono" :class="item.premi > 0 ? 'text-yellow-600 font-semibold' : ''">{{ fmt(item.premi) }}</span></template>
        <template #item.tunjangan_masa_kerja="{ item }"><span class="font-mono">{{ fmt(item.tunjangan_masa_kerja) }}</span></template>
        <template #item.total_thr="{ item }"><div class="font-bold text-primary-600 font-mono">{{ fmt(item.total_thr) }}</div><div v-if="item.pembulatan > 0" class="text-[10px] text-(--text-muted)">pbt +{{ fmt(item.pembulatan) }}</div></template>
        <template #item.status="{ item }"><span class="px-2 py-1 text-xs rounded-full" :class="{'bg-yellow-100 text-yellow-800': item.status === 'draft', 'bg-blue-100 text-blue-800': item.status === 'approved', 'bg-green-100 text-green-800': item.status === 'paid'}">{{ item.status.toUpperCase() }}</span></template>
        <template #item.actions="{ item }"><div class="flex gap-2">
          <button @click="openPrint(item)" class="text-gray-500 hover:text-blue-600" title="Cetak Slip"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg></button>
          <button @click="openEdit(item)" class="text-gray-500 hover:text-primary-600" title="Edit Komponen"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
          <button @click="deleteRow(item.id)" class="text-gray-500 hover:text-red-600" title="Hapus"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
        </div></template>
      </DataTable>
    </BaseCard>

    <BaseModal :show="showGenModal" title="Generate THR" @close="showGenModal = false">
      <div class="space-y-4">
        <div><label class="block text-sm font-medium mb-1">Tahun THR</label><select v-model="genForm.thr_year" class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md"><option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option></select></div>
        <div><label class="block text-sm font-medium mb-1">Tanggal Referensi (H-1 Lebaran)</label><input type="date" v-model="genForm.reference_date" class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md" /><p class="text-xs text-(--text-muted) mt-1">Tanggal ini digunakan sebagai patokan hitung masa kerja.</p></div>
      </div>
      <template #footer><BaseButton variant="outline" @click="showGenModal = false">Batal</BaseButton><BaseButton variant="primary" @click="generateThr" :disabled="isGenerating">{{ isGenerating ? 'Memproses...' : 'Generate' }}</BaseButton></template>
    </BaseModal>

    <BaseModal :show="showEditModal" title="Edit Komponen THR" @close="showEditModal = false">
      <div class="space-y-4" v-if="editRow">
        <div><label class="block text-sm font-medium mb-1">Gaji Pokok</label><div class="px-3 py-2 bg-gray-50 border border-(--border-soft) rounded-md font-mono text-sm">{{ fmt(editRow.gaji_pokok) }}</div></div>
        <div><label class="block text-sm font-medium mb-1">Tunjangan</label><input type="number" v-model="editForm.tunjangan" min="0" step="1000" class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md font-mono" /></div>
        <div><label class="block text-sm font-medium mb-1">Tunjangan Masa Kerja</label><input type="number" v-model="editForm.tunjangan_masa_kerja" min="0" step="1000" class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md font-mono" /></div>
        <div v-if="editPreview" class="bg-gray-50 rounded-md p-3 border border-gray-200 space-y-1 text-sm mt-4">
          <div class="flex justify-between font-medium"><span>Basis THR</span><span class="font-mono">{{ fmt(editPreview.basis) }}</span></div>
          <div class="flex justify-between text-(--text-soft) text-xs"><span>* Gaji Pokok + Tunjangan + Tj. Masa Kerja</span></div>
          <div class="flex justify-between border-t border-gray-200 pt-1 mt-1"><span>Total Prorate</span><span class="font-mono">{{ fmt(editPreview.raw) }}</span></div>
          <div class="flex justify-between"><span>Pembulatan</span><span class="font-mono">{{ fmt(editPreview.pembulatan) }}</span></div>
          <div class="flex justify-between font-bold border-t border-gray-200 pt-1 mt-1 text-primary-600"><span>TOTAL THR</span><span class="font-mono">{{ fmt(editPreview.total) }}</span></div>
        </div>
        <div><label class="block text-sm font-medium mb-1 mt-4">Catatan</label><textarea v-model="editForm.catatan" rows="2" class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md"></textarea></div>
      </div>
      <template #footer><BaseButton variant="outline" @click="showEditModal = false">Batal</BaseButton><BaseButton variant="primary" @click="saveEdit" :disabled="editLoading">Simpan</BaseButton></template>
    </BaseModal>

    <BaseModal :show="showPrintModal" title="Slip THR" size="md" @close="showPrintModal = false">
      <div v-if="printRow">
        <div class="flex items-center justify-between p-4 border-b border-(--border-soft) no-print"><span class="text-sm font-semibold text-gray-700">Slip THR – {{ printRow.employee?.name }}</span><div class="flex gap-2"><button @click="doPrint" class="flex items-center gap-2 px-4 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">Cetak</button><button @click="showPrintModal = false" class="text-gray-400 hover:text-gray-600 ml-1"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"></path></svg></button></div></div>
        <div id="slip-thr" class="p-6 text-gray-900 text-sm font-serif print-area">
          <div class="text-center mb-3"><div class="text-red-700 font-black text-lg leading-tight uppercase">{{ company?.name || 'PT. URA URA' }}</div><div class="text-xs italic font-bold">{{ branch?.name || 'EMBROIDERY &amp; PRINTING FACTORY' }}</div><div class="text-xs mt-0.5 leading-snug">{{ branch?.address || 'Jl. Raya ...' }}</div></div>
          <div class="border border-gray-400 rounded">
            <div class="flex items-center justify-between border-b border-gray-400 px-3 py-1.5"><div class="w-8"></div><div class="text-center font-bold text-base">THR {{ printRow.thr_year || new Date().getFullYear() }}</div><div class="w-8 text-right font-bold text-base">{{ printRow.id }}</div></div>
            <div class="grid grid-cols-2 border-b border-gray-400 text-xs"><div class="px-3 py-1.5 border-r border-gray-400"><div class="font-bold uppercase">{{ printRow.employee?.name }}</div></div><div class="px-3 py-1.5 space-y-0.5"><div class="flex justify-between"><span>BULAN</span><span class="font-semibold">: {{ printRow.thr_month || '–' }}</span></div><div class="flex justify-between"><span>No ACCOUNT</span><span class="font-semibold">: {{ printRow.no_account || '–' }}</span></div></div></div>
            <div class="grid grid-cols-2 border-b border-gray-300 text-xs"><div class="px-3 py-1 font-semibold text-center">K E T E R A N G A N</div><div class="px-3 py-1 text-right font-semibold border-l border-gray-300">Jumlah</div></div>
            <div class="px-3 py-2 text-xs space-y-1 border-b border-gray-300"><div class="flex justify-between items-center"><span class="font-medium">GAJI POKOK</span><span class="bg-green-200 px-2 py-0.5 rounded font-mono font-semibold">Rp {{ Number(printRow.gaji_pokok).toLocaleString('id-ID') }}</span></div><div class="flex justify-between items-center"><span class="font-medium">TUNJANGAN</span><span class="bg-yellow-200 px-2 py-0.5 rounded font-mono font-semibold">Rp {{ Number(printRow.premi).toLocaleString('id-ID') }}</span></div><div class="flex justify-between items-center"><span class="font-medium">TJ. MASA KERJA</span><div class="flex items-center gap-1"><span class="bg-red-200 px-2 py-0.5 rounded font-mono font-semibold">Rp {{ Number(printRow.tunjangan_masa_kerja).toLocaleString('id-ID') }}</span><span class="font-bold">+</span></div></div><div class="flex justify-end pt-1 border-t border-gray-200"><span class="font-mono font-semibold">Rp {{ Number(Number(printRow.gaji_pokok) + Number(printRow.premi) + Number(printRow.tunjangan_masa_kerja)).toLocaleString('id-ID') }}</span></div></div>
            <div class="px-3 py-2 text-xs space-y-1 border-b border-gray-300"><div class="flex gap-6"><div class="space-y-1 flex-1"><div class="flex gap-2"><span class="w-24">TGL AWAL</span><span>: {{ fmtDate(printRow.join_date) }}</span></div><div class="flex gap-2"><span class="w-24">TGL AKHIR</span><span>: {{ fmtDate(printRow.reference_date) }}</span></div><div class="flex gap-2"><span class="w-24">BULAN</span><span>: {{ printRow.total_bulan }}</span></div><div class="flex gap-2"><span class="w-24">LAMA BEKERJA</span><span>: {{ printRow.lama_bekerja || '–' }}</span></div></div><div class="text-right"><div class="mt-6 font-mono font-semibold">Rp<br>{{ Number(printRow.thr_amount).toLocaleString('id-ID') }}</div></div></div></div>
            <div class="px-3 py-2 text-xs space-y-1 border-b border-gray-300"><div class="flex justify-between items-center"><span class="font-semibold">Pbt</span><div class="flex items-center gap-3"><div class="text-right"><div class="text-[10px] text-gray-500">Rp</div><div class="font-mono font-semibold border-b border-gray-400 pb-0.5">{{ Number(printRow.pembulatan).toLocaleString('id-ID') }}</div></div><span class="font-bold">+</span></div></div><div class="flex justify-between items-center pt-1"><span class="font-bold">TOTAL</span><div class="text-right"><div class="text-[10px] text-gray-500">Rp</div><div class="font-mono font-bold">{{ Number(printRow.total_thr).toLocaleString('id-ID', { minimumFractionDigits: 2 }) }}</div></div></div></div>
          </div>
        </div>
        <div class="mt-8 text-center text-xs"><p>Slip ini dicetak secara otomatis oleh sistem HRIS.</p></div>
      </div>
      <template #footer><BaseButton variant="outline" @click="showPrintModal = false" class="no-print">Tutup</BaseButton><BaseButton variant="primary" @click="doPrint" class="no-print">Cetak</BaseButton></template>
    </BaseModal>
  </div>
</template>

<style>
@media print {
  body * { visibility: hidden; }
  .print-area, .print-area * { visibility: visible; }
  .print-area { position: absolute; left: 0; top: 0; width: 100%; }
  .no-print { display: none !important; }
}
</style>
