<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import Badge from '../../../../Components/Badge.vue'

const router = useRouter()
const notification = useNotificationStore()
const { get, patch } = useApi()

// State
const loading = ref(false)
const contracts = ref([])
const period = ref({ start: '', end: '', label: '' })

const now = new Date()
let initMonth = now.getMonth() + 1
let initYear = now.getFullYear()

// Jika tanggal hari ini >= 25, maka kita sudah masuk payroll bulan depannya
if (now.getDate() >= 25) {
    initMonth += 1
    if (initMonth > 12) {
        initMonth = 1
        initYear += 1
    }
}

const selectedMonth = ref(initMonth)
const selectedYear = ref(initYear)
const selectedPeriode = ref('auto')

// Generate available months for filter
const availableMonths = computed(() => {
    const months = []
    // Hardcode dari Desember 2025 hingga Januari 2027
    let currentYear = 2025
    let currentMonth = 11 // Desember (0-indexed)

    while (currentYear < 2027 || (currentYear === 2027 && currentMonth === 0)) {
        const d = new Date(currentYear, currentMonth, 1)
        months.push({
            value: d.getMonth() + 1,
            year: d.getFullYear(),
            label: d.toLocaleString('id-ID', { month: 'long', year: 'numeric' })
        })

        currentMonth++
        if (currentMonth > 11) {
            currentMonth = 0
            currentYear++
        }
    }
    return months
})

const selectedMonthYear = ref(`${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}`)

// Dialog State
const showConfirmDialog = ref(false)
const dialogAction = ref('') // 'mark-paid' or 'mark-unpaid'
const selectedContract = ref(null)

// Print State
const showPrintModal = ref(false)
const printLoading = ref(false)
const printData = ref({ company: null, bulan: '', hrd: '', slips: [] })

// Computed
const paidCount = computed(() => contracts.value.filter(c => c.is_compensation_paid).length)
const unpaidCount = computed(() => contracts.value.filter(c => !c.is_compensation_paid).length)

// ========== API CALLS ==========
async function fetchData() {
    loading.value = true
    try {
        const [year, month] = selectedMonthYear.value.split('-')
        const params = new URLSearchParams()
        params.set('month', month)
        params.set('year', year)
        params.set('periode', selectedPeriode.value)

        const res = await get(`/api/v1/employees/compensation?${params}`)
        if (res.data) {
            contracts.value = res.data.contracts || []
            period.value = res.data.period || { start: '', end: '', label: '' }
        }
    } catch (e) {
        notification.addNotification('Gagal memuat data kompensasi.', 'error')
    } finally {
        loading.value = false
    }
}

// ========== ACTIONS ==========
function applyFilter() {
    fetchData()
}

function confirmMarkPaid(contract) {
    selectedContract.value = contract
    dialogAction.value = 'mark-paid'
    showConfirmDialog.value = true
}

function confirmMarkUnpaid(contract) {
    selectedContract.value = contract
    dialogAction.value = 'mark-unpaid'
    showConfirmDialog.value = true
}

async function handleConfirm() {
    if (!selectedContract.value) return

    try {
        const endpoint = `/api/v1/employees/compensation/${selectedContract.value.id}/${dialogAction.value}`
        const res = await patch(endpoint)
        notification.addNotification(res.message || 'Status kompensasi berhasil diperbarui.', 'success')
        fetchData()
    } catch (e) {
        notification.addNotification('Gagal memperbarui status kompensasi.', 'error')
    } finally {
        showConfirmDialog.value = false
        selectedContract.value = null
    }
}

function cancelConfirm() {
    showConfirmDialog.value = false
    selectedContract.value = null
}

async function downloadFile(url, defaultFilename) {
    loading.value = true
    try {
        const token = localStorage.getItem('token')
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}` }
        })

        if (!response.ok) throw new Error('Gagal mengunduh file')

        const contentType = response.headers.get('content-type')
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json()
            notification.addNotification(data.message || 'Fitur ekspor akan segera tersedia.', 'warning')
            return
        }

        const blob = await response.blob()
        const downloadUrl = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = downloadUrl

        let filename = defaultFilename
        const disposition = response.headers.get('content-disposition')
        if (disposition && disposition.indexOf('attachment') !== -1) {
            const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/
            const matches = filenameRegex.exec(disposition)
            if (matches != null && matches[1]) {
                filename = matches[1].replace(/['"]/g, '')
            }
        }

        a.download = filename
        document.body.appendChild(a)
        a.click()
        a.remove()
        window.URL.revokeObjectURL(downloadUrl)
    } catch (e) {
        notification.addNotification('Gagal mengunduh file.', 'error')
    } finally {
        loading.value = false
    }
}

function exportExcel() {
    const [year, month] = selectedMonthYear.value.split('-')
    const url = `/api/v1/employees/compensation/export?month=${month}&year=${year}&periode=${selectedPeriode.value}`
    downloadFile(url, `kompensasi-${year}-${month}.xlsx`)
}

async function bulkPrint() {
    printLoading.value = true
    try {
        const [year, month] = selectedMonthYear.value.split('-')
        const params = new URLSearchParams()
        params.set('month', month)
        params.set('year', year)
        params.set('periode', selectedPeriode.value)

        const res = await get(`/api/v1/employees/compensation/print?${params}`)
        if (res.data) {
            printData.value = res.data
            showPrintModal.value = true
        }
    } catch (e) {
        notification.addNotification('Gagal memuat data slip kompensasi.', 'error')
    } finally {
        printLoading.value = false
    }
}

function executePrint() {
    window.print()
}

// ========== HELPERS ==========
const formatCurrency = (val) => {
    if (!val) return 'Rp 0'
    return 'Rp ' + Number(val).toLocaleString('id-ID')
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
}

const getDaysRemaining = (endDate) => {
    if (!endDate) return null
    const end = new Date(endDate)
    const now = new Date()
    end.setHours(0, 0, 0, 0)
    now.setHours(0, 0, 0, 0)
    return Math.ceil((end - now) / (1000 * 60 * 60 * 24))
}

const formatDaysRemaining = (days) => {
    if (days === null) return '-'
    if (days === 0) return 'Hari Ini'
    return `${days} hari`
}

const getContractTypeLabel = (type) => {
    const labels = {
        pkwt: 'PKWT',
        pkwtt: 'PKWTT',
        outsourcing: 'Outsourcing',
        freelance: 'Freelance',
    }
    return labels[type] || type
}

const getInitials = (name) => {
    if (!name) return '?'
    return name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase()
}

// Slip format helpers
const formatNumber = (val, decimals = 0) => {
    if (!val && val !== 0) return '0'
    return Number(val).toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
}

const formatDateShort = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: '2-digit' })
}

const chunkArray = (arr, size) => {
    const chunks = []
    for (let i = 0; i < arr.length; i += size) {
        chunks.push(arr.slice(i, i + size))
    }
    return chunks
}

// ========== INIT ==========
onMounted(() => {
    fetchData()
})
</script>

<template>
    <div class="space-y-6 ">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-md bg-(--primary)/10 flex items-center justify-center text-(--primary) shadow-sm">
                    <i class="bx bx-money text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-(--text-main)">Kompensasi Kontrak</h1>
                    <p class="text-sm text-(--text-muted) mt-1">
                        Monitoring kompensasi kontrak periode
                        <span v-if="period.start" class="font-semibold text-(--text-main)">
                            {{ formatDate(period.start) }} - {{ formatDate(period.end) }}
                        </span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <BaseButton variant="secondary" @click="bulkPrint" :disabled="printLoading">
                    <template #icon-left>
                        <i :class="printLoading ? 'bx bx-loader-alt bx-spin text-lg' : 'bx bx-printer text-lg'"></i>
                    </template>
                    {{ printLoading ? 'Memuat...' : 'Cetak Massal' }}
                </BaseButton>
                <BaseButton variant="secondary" @click="exportExcel">
                    <template #icon-left>
                        <i class="bx bx-download text-lg"></i>
                    </template>
                    Export Excel
                </BaseButton>
            </div>
        </div>

        <!-- Search & Filters Toolbar -->
        <BaseCard padding="p-2" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
            <div class="flex flex-wrap items-center gap-2">
                <!-- Periode Badge -->
                <div class="flex-1">
                    <Badge v-if="period.label" :variant="period.label.includes('Awal') ? 'primary' : 'warning'"
                        class="px-3 py-1">
                        <i class="bx bx-calendar mr-1"></i>
                        {{ period.label }}
                    </Badge>
                </div>

                <div class="w-48 relative">
                    <select v-model="selectedMonthYear" @change="applyFilter"
                        class="w-full pl-3 pr-8 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm appearance-none cursor-pointer">
                        <option v-for="m in availableMonths" :key="m.year + '-' + m.value"
                            :value="m.year + '-' + String(m.value).padStart(2, '0')">
                            {{ m.label }}
                        </option>
                    </select>
                    <div
                        class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-(--text-muted)">
                        <i class="bx bx-chevron-down text-lg"></i>
                    </div>
                </div>

                <div class="w-48 relative">
                    <select v-model="selectedPeriode" @change="applyFilter"
                        class="w-full pl-3 pr-8 h-10 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm appearance-none cursor-pointer">
                        <option value="auto">Otomatis (Hari ini)</option>
                        <option value="awal">Awal (25-7)</option>
                        <option value="akhir">Akhir (8-24)</option>
                    </select>
                    <div
                        class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-(--text-muted)">
                        <i class="bx bx-chevron-down text-lg"></i>
                    </div>
                </div>
            </div>
        </BaseCard>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
                <div
                    class="absolute right-0 top-0 w-24 h-24 bg-slate-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110">
                </div>
                <div class="flex items-center justify-between relative z-10">
                    <div>
                        <p class="text-xs font-bold text-(--text-muted) uppercase">Total Kontrak</p>
                        <p class="text-2xl font-black text-(--text-main) mt-1">{{ contracts.length }}</p>
                    </div>
                    <div class="w-10 h-10 bg-slate-500/10 text-slate-600 rounded-md flex items-center justify-center">
                        <i class="bx bx-file text-2xl"></i>
                    </div>
                </div>
            </BaseCard>

            <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
                <div
                    class="absolute right-0 top-0 w-24 h-24 bg-emerald-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110">
                </div>
                <div class="flex items-center justify-between relative z-10">
                    <div>
                        <p class="text-xs font-bold text-(--text-muted) uppercase">Sudah Dibayar</p>
                        <p class="text-2xl font-black text-emerald-600 mt-1">{{ paidCount }}</p>
                    </div>
                    <div
                        class="w-10 h-10 bg-emerald-500/10 text-emerald-600 rounded-md flex items-center justify-center">
                        <i class="bx bx-check-circle text-2xl"></i>
                    </div>
                </div>
            </BaseCard>

            <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
                <div
                    class="absolute right-0 top-0 w-24 h-24 bg-amber-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110">
                </div>
                <div class="flex items-center justify-between relative z-10">
                    <div>
                        <p class="text-xs font-bold text-amber-500 uppercase">Belum Dibayar</p>
                        <p class="text-2xl font-black text-amber-500 mt-1">{{ unpaidCount }}</p>
                    </div>
                    <div class="w-10 h-10 bg-amber-500/10 text-amber-500 rounded-md flex items-center justify-center">
                        <i class="bx bx-time-five text-2xl"></i>
                    </div>
                </div>
            </BaseCard>

            <BaseCard class="border-(--border-soft) shadow-sm relative overflow-hidden group">
                <div
                    class="absolute right-0 top-0 w-24 h-24 bg-(--primary)/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110">
                </div>
                <div class="flex items-center justify-between relative z-10">
                    <div>
                        <p class="text-xs font-bold text-(--text-muted) uppercase">Periode Berakhir</p>
                        <p class="text-lg font-black text-(--text-main) mt-1">{{ formatDate(period.end) }}</p>
                    </div>
                    <div
                        class="w-10 h-10 bg-(--primary)/10 text-(--primary) rounded-md flex items-center justify-center">
                        <i class="bx bx-calendar-check text-2xl"></i>
                    </div>
                </div>
            </BaseCard>
        </div>

        <!-- Table -->
        <BaseCard class="border-(--border-soft) shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-(--border-soft)">
                    <thead class="bg-(--bg-elevated)">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Karyawan</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Kontrak</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Gaji
                                Pokok</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Jatuh
                                Tempo</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Sisa
                                Hari</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Status Kompensasi</th>
                            <th
                                class="px-4 py-3 text-right text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-(--bg-card) divide-y divide-(--border-soft)">
                        <tr v-if="loading">
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div
                                    class="w-8 h-8 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mx-auto mb-2">
                                </div>
                                <span class="text-sm text-(--text-muted)">Memuat data...</span>
                            </td>
                        </tr>
                        <tr v-else-if="contracts.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-(--text-muted)">
                                <i class="bx bx-check-circle text-4xl mb-2 block"></i>
                                Tidak ada kontrak yang jatuh tempo di periode ini.
                            </td>
                        </tr>
                        <tr v-for="contract in contracts" :key="contract.id"
                            class="hover:bg-(--bg-elevated) transition-colors">
                            <!-- Karyawan -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center space-x-3">
                                    <div
                                        class="w-9 h-9 bg-(--primary)/10 rounded-full flex items-center justify-center text-xs font-bold text-(--primary)">
                                        {{ getInitials(contract.employee?.name) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-(--text-main)">{{ contract.employee?.name
                                        }}</p>
                                        <p class="text-xs text-(--text-muted)">
                                            {{ contract.employee?.employee_code }} &middot; {{
                                                contract.employee?.department ||
                                                '-' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Kontrak -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p class="text-sm font-medium text-(--text-main)">{{ contract.contract_number }}</p>
                                <p class="text-xs text-(--text-muted)">
                                    {{ getContractTypeLabel(contract.contract_type) }} &middot; {{
                                        contract.duration_months }}
                                    bln
                                </p>
                            </td>

                            <!-- Gaji Pokok -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p class="text-sm font-semibold text-(--text-main)">
                                    {{ formatCurrency(contract.employee?.base_salary) }}
                                </p>
                            </td>

                            <!-- Jatuh Tempo -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div>
                                    <p class="text-sm font-medium text-(--text-main)">{{ formatDate(contract.end_date)
                                    }}</p>
                                    <p class="text-xs text-(--text-muted)">{{ formatDate(contract.start_date) }} - {{
                                        formatDate(contract.end_date) }}</p>
                                </div>
                            </td>

                            <!-- Sisa Hari -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span v-if="getDaysRemaining(contract.end_date) !== null"
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold" :class="getDaysRemaining(contract.end_date) < 0
                                        ? 'bg-red-500/10 text-red-600'
                                        : getDaysRemaining(contract.end_date) <= 7
                                            ? 'bg-red-500/10 text-red-600'
                                            : getDaysRemaining(contract.end_date) <= 14
                                                ? 'bg-amber-500/10 text-amber-600'
                                                : 'bg-slate-500/10 text-slate-600'
                                        ">
                                    {{ formatDaysRemaining(getDaysRemaining(contract.end_date)) }}
                                </span>
                                <span v-else class="text-xs text-(--text-muted)">-</span>
                            </td>

                            <!-- Status Kompensasi -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div v-if="contract.is_compensation_paid" class="flex flex-col">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-emerald-500/10 text-emerald-600">
                                        <i class="bx bx-check mr-1"></i> Dibayar
                                    </span>
                                    <span class="text-[10px] text-(--text-muted) mt-1">
                                        {{ formatDate(contract.compensation_paid_at) }}
                                    </span>
                                </div>
                                <span v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-amber-500/10 text-amber-600">
                                    <i class="bx bx-time mr-1"></i> Belum
                                </span>
                            </td>

                            <!-- Aksi -->
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <BaseButton v-if="!contract.is_compensation_paid" variant="ghost"
                                    @click="confirmMarkPaid(contract)"
                                    class="h-8 px-2 text-xs bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 hover:text-emerald-700">
                                    <i class="bx bx-check mr-1 text-sm"></i> Tandai Dibayar
                                </BaseButton>
                                <BaseButton v-else variant="ghost" @click="confirmMarkUnpaid(contract)"
                                    class="h-8 px-2 text-xs bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 hover:text-amber-700">
                                    <i class="bx bx-undo mr-1 text-sm"></i> Batalkan
                                </BaseButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </BaseCard>

        <!-- Confirm Dialog -->
        <ConfirmDialog :show="showConfirmDialog"
            :title="dialogAction === 'mark-paid' ? 'Tandai Dibayar' : 'Batalkan Status Dibayar'"
            :message="dialogAction === 'mark-paid' ? 'Apakah Anda yakin kompensasi ini sudah dibayarkan?' : 'Apakah Anda yakin ingin membatalkan status pembayaran kompensasi ini?'"
            :confirm-text="dialogAction === 'mark-paid' ? 'Ya, Tandai Dibayar' : 'Ya, Batalkan'" cancel-text="Batal"
            :variant="dialogAction === 'mark-paid' ? 'success' : 'warning'" @confirm="handleConfirm"
            @cancel="cancelConfirm" />

        <!-- Print Teleport Modal -->
        <Teleport to="body">
            <div v-if="showPrintModal" class="print-modal fixed inset-0 bg-white z-[9999] overflow-y-auto text-black">
                <!-- Toolbar No Print -->
                <div class="print-toolbar-bar">
                    <h2 style="font-size:16px; font-weight:bold;">Preview Cetak Slip Kompensasi ({{
                        printData.slips?.length || 0
                    }} slip)</h2>
                    <div style="display:flex; gap:8px;">
                        <button class="btn-print" @click="executePrint">🖨 Cetak Sekarang</button>
                        <button class="btn-close" @click="showPrintModal = false">✕ Tutup</button>
                    </div>
                </div>

                <!-- Slip Pages -->
                <div class="print-area w-full max-w-4xl mx-auto">
                    <div v-for="(chunk, pageIdx) in chunkArray(printData.slips || [], 6)" :key="pageIdx"
                        class="slip-page">
                        <div v-for="(slip, slipIdx) in chunk" :key="slip.contract_id" class="slip">
                            <!-- HEADER -->
                            <div class="slip-header">
                                <div class="company-name">{{ (printData.company?.name || '').toUpperCase() }}</div>
                                <div class="company-addr">{{ printData.company?.address || '' }}</div>
                            </div>

                            <!-- TITLE BAR -->
                            <div class="title-bar">
                                <div class="title-text">K O M P E N S A S I</div>
                                <div class="slip-num">{{ pageIdx * 6 + slipIdx + 1 }}</div>
                            </div>

                            <!-- INFO ROW -->
                            <div class="info-row">
                                <div class="info-left">
                                    <div style="font-weight:bold; font-size:7px;">{{ (slip.employee_name ||
                                        '').toUpperCase() }}
                                    </div>
                                </div>
                                <div class="info-right">
                                    <div class="info-grid">
                                        <span>BULAN</span>
                                        <span class="colon">:</span>
                                        <span>{{ (printData.bulan || '').toUpperCase() }}</span>
                                        <span>No ACCOUNT</span>
                                        <span class="colon">:</span>
                                        <span>{{ slip.bank_account_number || '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION HEADER -->
                            <div class="section-header">
                                <span>K E T E R A N G A N</span>
                                <span>Jumlah</span>
                            </div>

                            <!-- GAJI POKOK -->
                            <div class="body-row">
                                <span class="label">GAJI POKOK</span>
                                <span class="rp">Rp</span>
                                <span class="amount-box green">{{ formatNumber(slip.gajiPokok) }}</span>
                            </div>

                            <!-- TJ. MASA KERJA -->
                            <div class="body-row">
                                <span class="label">TJ. MASA KERJA</span>
                                <span class="rp">Rp</span>
                                <span class="amount-box yellow">{{ formatNumber(slip.tjMasaKerja) }}</span>
                            </div>

                            <!-- TGL AWAL -->
                            <div class="body-row">
                                <span class="date-label">TGL AWAL</span>
                                <span class="date-colon">:</span>
                                <span class="date-value">{{ formatDateShort(slip.start_date) }}</span>
                            </div>

                            <!-- TGL AKHIR -->
                            <div class="body-row">
                                <span class="date-label">TGL AKHIR</span>
                                <span class="date-colon">:</span>
                                <span class="date-value">{{ formatDateShort(slip.end_date) }}</span>
                            </div>

                            <!-- BULAN (duration × rate) -->
                            <div class="bulan-row">
                                <span class="blabel">BULAN</span>
                                <span class="bcolon">:</span>
                                <span class="dur">{{ slip.durationMonths }}</span>
                                <span class="bx-sign">x</span>
                                <span class="rp">Rp</span>
                                <span class="rate">{{ formatNumber(slip.monthlyRate) }}</span>
                                <span class="subtotal">Rp {{ formatNumber(slip.totalRaw) }}</span>
                            </div>

                            <!-- TOTAL SECTION -->
                            <div class="total-section">
                                <div v-if="slip.pembulatan > 0" class="pblt-row">
                                    <span class="pblt-label">Pblt</span>
                                    <span style="font-size:6px">Rp</span>
                                    <span class="pblt-amount">{{ formatNumber(slip.pembulatan) }}</span>
                                    <span class="plus">+</span>
                                </div>
                                <div class="total-row">
                                    <span class="total-label">TOTAL</span>
                                    <span style="font-size:6px">Rp</span>
                                    <span class="total-amount">{{ formatNumber(slip.totalRounded) }}</span>
                                </div>
                            </div>

                            <!-- TOTAL TERIMA -->
                            <div class="total-terima">
                                <span>T O T A L &nbsp; T E R I M A</span>
                                <span>Rp {{ formatNumber(slip.totalRounded) }}</span>
                            </div>

                            <!-- SIGNATURE -->
                            <div class="signature-row">
                                <div class="sig-col">
                                    <div>HRD,</div>
                                    <div class="sig-name">{{ (printData.hrd || '').toUpperCase() }}</div>
                                </div>
                                <div class="sig-col sig-mid">
                                    <div>Diterima oleh,</div>
                                    <div class="sig-name">{{ (slip.employee_name || '').toUpperCase() }}</div>
                                </div>
                                <div class="sig-col">
                                    <div>TGL</div>
                                    <div class="sig-name">&nbsp;</div>
                                </div>
                            </div>
                        </div>

                        <!-- Fill empty slots if chunk < 6 -->
                        <div v-for="i in (6 - chunk.length)" :key="'empty-' + i" class="slip slip-empty"></div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style>
/* ============================== */
/* PRINT TOOLBAR (screen only)    */
/* ============================== */
.print-toolbar-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 20px;
    background: #f3f4f6;
    border-bottom: 2px solid #ccc;
    position: sticky;
    top: 0;
    z-index: 100;
}

.btn-print {
    background: #1d4ed8;
    color: white;
    border: none;
    padding: 10px 28px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    font-weight: bold;
}

.btn-print:hover {
    background: #1e40af;
}

.btn-close {
    background: #6b7280;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
}

.btn-close:hover {
    background: #4b5563;
}

/* ============================== */
/* SLIP PAGE — F4 (210×330mm)     */
/* 2 columns × 3 rows = 6 slips  */
/* ============================== */
.print-area {
    padding: 10px;
}

.slip-page {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr 1fr;
    gap: 24px;
    width: 100%;
    min-height: 318mm;
    page-break-after: always;
    margin-bottom: 20px;
    padding: 16px;
}

.slip-page:last-child {
    page-break-after: auto;
}

/* ============================== */
/* INDIVIDUAL SLIP                */
/* ============================== */
.slip {
    border: 0.5px solid #000;
    padding: 1px;
    display: flex;
    flex-direction: column;
    font-family: Arial, sans-serif;
    font-size: 6.5px;
    color: #000;
    background: #fff;
}

.slip-empty {
    border: 0.5px solid #ccc;
}

/* ---- HEADER ---- */
.slip-header {
    padding: 1px 3px;
    border-bottom: 0.5px solid #000;
    text-align: center;
}

.company-name {
    font-size: 7px;
    font-weight: bold;
    color: #cc0000;
}

.company-addr {
    font-size: 5.5px;
}

/* ---- TITLE BAR ---- */
.title-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 0.5px solid #000;
    padding: 1px 3px;
}

.title-text {
    font-size: 8px;
    font-weight: bold;
    letter-spacing: 2px;
}

.slip-num {
    border: 0.5px solid #000;
    padding: 0 4px;
    font-weight: bold;
    font-size: 7px;
}

/* ---- INFO ROW ---- */
.info-row {
    display: flex;
    border-bottom: 0.5px solid #000;
}

.info-left {
    flex: 1;
    padding: 1px 3px;
    border-right: 0.5px solid #000;
}

.info-right {
    flex: 1;
    padding: 1px 3px;
}

.info-grid {
    display: grid;
    grid-template-columns: auto auto 1fr;
    gap: 0 2px;
    line-height: 1.4;
}

.info-grid .colon {
    text-align: center;
}

/* ---- SECTION HEADER ---- */
.section-header {
    display: flex;
    justify-content: space-between;
    border-top: 0.5px solid #000;
    border-bottom: 0.5px solid #000;
    padding: 0.5px 3px;
    font-weight: bold;
    font-size: 6.5px;
}

/* ---- BODY ROWS ---- */
.body-row {
    display: flex;
    align-items: baseline;
    padding: 0.5px 3px;
    min-height: 11px;
}

.body-row .label {
    width: 68px;
}

.body-row .rp {
    width: 12px;
    font-size: 6px;
}

.body-row .amount-box {
    min-width: 55px;
    text-align: right;
    padding: 0 2px;
}

.body-row .amount-box.green {
    background: #b8ffb8;
}

.body-row .amount-box.yellow {
    background: #ffff66;
}

.body-row .date-label {
    width: 48px;
}

.body-row .date-colon {
    width: 6px;
}

.body-row .date-value {
    flex: 1;
}

.bulan-row {
    display: flex;
    padding: 0.5px 3px;
    align-items: baseline;
    min-height: 11px;
}

.bulan-row .blabel {
    width: 36px;
}

.bulan-row .bcolon {
    width: 6px;
}

.bulan-row .dur {
    width: 12px;
    text-align: right;
}

.bulan-row .bx-sign {
    width: 8px;
    text-align: center;
}

.bulan-row .rp {
    width: 12px;
    font-size: 6px;
}

.bulan-row .rate {
    flex: 1;
}

.bulan-row .subtotal {
    margin-left: auto;
    text-align: right;
    min-width: 50px;
}

/* ---- TOTAL SECTION ---- */
.total-section {
    border-top: 0.5px solid #000;
    margin-top: auto;
}

.pblt-row {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 0.5px 3px;
    gap: 4px;
}

.pblt-row .pblt-label {
    font-size: 6px;
}

.pblt-row .pblt-amount {
    border-bottom: 0.5px solid #000;
    min-width: 50px;
    text-align: right;
    padding-right: 1px;
}

.pblt-row .plus {
    font-size: 7px;
}

.total-row {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 0.5px 3px;
    gap: 4px;
    font-weight: bold;
}

.total-row .total-amount {
    min-width: 50px;
    text-align: right;
}

.total-terima {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 0.5px solid #000;
    border-bottom: 0.5px solid #000;
    padding: 0.5px 3px;
    font-weight: bold;
}

/* ---- SIGNATURE ---- */
.signature-row {
    display: flex;
    padding: 2px 3px 1px;
    gap: 0;
    min-height: 28px;
}

.sig-col {
    flex: 1;
    text-align: center;
    padding: 0 2px;
}

.sig-col.sig-mid {
    border-left: 0.5px solid #000;
    border-right: 0.5px solid #000;
}

.sig-col:first-child {
    /* no left border */
}

.sig-name {
    margin-top: 12px;
    font-weight: bold;
    font-size: 6px;
    border-top: 0.5px solid #000;
    padding-top: 1px;
}

/* ============================== */
/* PRINT MEDIA QUERY              */
/* ============================== */
@media print {

    /* Hide the app and show only the slip modal */
    #app {
        display: none !important;
    }

    .print-modal {
        position: static !important;
        width: 100% !important;
        height: auto !important;
        overflow: visible !important;
        padding: 0 !important;
        background: white !important;
        z-index: 1 !important;
    }

    .print-toolbar-bar {
        display: none !important;
    }

    .print-area {
        padding: 0;
    }

    .slip-page {
        margin-bottom: 0;
    }

    @page {
        size: 210mm 330mm;
        /* F4 portrait */
        margin: 6mm;
    }
}
</style>
