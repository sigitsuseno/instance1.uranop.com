<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import Badge from '../../../../Components/Badge.vue'

const router = useRouter()
const notification = useNotificationStore()
const { get, post } = useApi()

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

// Hanya tampilkan kontrak terakhir (is_latest = true) secara default
const onlyLatest = ref(true)

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

// Group Modal State
const showGroupModal = ref(false)
const groupLoading = ref(false)
const groupForm = ref({ name: '', payment_date: '' })
const deletingGroup = ref(null)

// Bulk State
const selectedIds = ref(new Set())
const selectAllRef = ref(null)

// Print State
const showPrintModal = ref(false)
const showPrintGroupModal = ref(false)
const printLoading = ref(false)
const printData = ref({ company: null, bulan: '', hrd: '', slips: [] })
const printSelectedGroups = ref(new Set())

// Export Excel / Group selection State
const showExportModal = ref(false)
const groupsLoading = ref(false)
const exportGroups = ref([])
const exportPeriod = ref({ start: '', end: '', end_plus_7: '', label: '' })
const exportSelectedGroups = ref(new Set())

// Computed
const paidCount = computed(() => contracts.value.filter(c => c.is_compensation_paid).length)
const unpaidCount = computed(() => contracts.value.filter(c => !c.is_compensation_paid).length)

const allSelected = computed(() => {
    const unpaid = contracts.value.filter(c => !c.is_compensation_paid)
    return unpaid.length > 0 && unpaid.every(c => selectedIds.value.has(c.id))
})

const someSelected = computed(() => {
    return selectedIds.value.size > 0 && !allSelected.value
})

const selectedCount = computed(() => selectedIds.value.size)

// Group kompensasi unik yang ada di kontrak periode terpilih (comp_group + paid_at)
const compGroups = computed(() => {
    const map = new Map()
    contracts.value.forEach(c => {
        if (c.comp_group) {
            map.set(c.comp_group, c.compensation_paid_at || null)
        }
    })
    return Array.from(map.entries()).map(([name, paid_at]) => ({ name, paid_at }))
})

// ========== API CALLS ==========
async function fetchData() {
    loading.value = true
    try {
        const [year, month] = selectedMonthYear.value.split('-')
        const params = new URLSearchParams()
        params.set('month', month)
        params.set('year', year)
        params.set('periode', selectedPeriode.value)
        if (onlyLatest.value) params.set('is_latest', '1')

        const res = await get(`/api/v1/employees/compensation?${params}`)
        if (res.data) {
            contracts.value = res.data.contracts || []
            period.value = res.data.period || { start: '', end: '', label: '' }
        }
    } catch (e) {
        notification.addNotification(e.message || 'Gagal memuat data kompensasi.', 'error')
        contracts.value = []
        period.value = { start: '', end: '', label: '' }
    } finally {
        loading.value = false
    }
}

// ========== ACTIONS ==========
function applyFilter() {
    selectedIds.value = new Set()
    fetchData()
}

function toggleSelectAll() {
    if (allSelected.value) {
        selectedIds.value = new Set()
    } else {
        const unpaid = contracts.value.filter(c => !c.is_compensation_paid)
        selectedIds.value = new Set(unpaid.map(c => c.id))
    }
}

function toggleSelect(id) {
    const next = new Set(selectedIds.value)
    if (next.has(id)) {
        next.delete(id)
    } else {
        next.add(id)
    }
    selectedIds.value = next
}

function openGroupModal() {
    const d = new Date()
    groupForm.value = {
        name: '',
        payment_date: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`,
    }
    showGroupModal.value = true
}

async function closeGroupModal() {
    showGroupModal.value = false
    groupForm.value = { name: '', payment_date: '' }
}

async function confirmDeleteGroup(group) {
    if (!window.confirm(`Hapus group "${group.name}"? Semua kontrak di dalamnya akan dikembalikan ke status belum dibayar.`)) return
    deletingGroup.value = group.name
    try {
        const res = await post('/api/v1/employees/compensation/delete-group', { group_name: group.name })
        notification.addNotification(res.message || 'Group berhasil dihapus.', 'success')
        fetchData()
    } catch (e) {
        notification.addNotification(e.message || 'Gagal menghapus group.', 'error')
    } finally {
        deletingGroup.value = null
    }
}

async function saveGroup() {
    if (!groupForm.value.name.trim()) {
        notification.addNotification('Nama group wajib diisi.', 'error')
        return
    }
    if (!groupForm.value.payment_date) {
        notification.addNotification('Tanggal pembayaran wajib diisi.', 'error')
        return
    }

    groupLoading.value = true
    try {
        const ids = Array.from(selectedIds.value)
        const res = await post('/api/v1/employees/compensation/create-group', {
            ids,
            group_name: groupForm.value.name.trim(),
            payment_date: groupForm.value.payment_date,
        })
        notification.addNotification(res.message || 'Group kompensasi berhasil dibuat.', 'success')
        selectedIds.value = new Set()
        closeGroupModal()
        fetchData()
    } catch (e) {
        notification.addNotification(e.message || 'Gagal membuat group kompensasi.', 'error')
    } finally {
        groupLoading.value = false
    }
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
            notification.addNotification(data.message || 'Gagal mengekspor data.', 'error')
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
    showExportModal.value = true
    exportSelectedGroups.value = new Set()
    fetchGroups()
}

async function fetchGroups() {
    groupsLoading.value = true
    try {
        const [year, month] = selectedMonthYear.value.split('-')
        const params = new URLSearchParams()
        params.set('month', month)
        params.set('year', year)
        params.set('periode', selectedPeriode.value)

        const res = await get(`/api/v1/employees/compensation/export-groups?${params}`)
        if (res.data) {
            exportGroups.value = res.data.groups || []
            exportPeriod.value = res.data.period || { start: '', end: '', end_plus_7: '', label: '' }
        }
    } catch (e) {
        notification.addNotification(e.message || 'Gagal memuat daftar group kompensasi.', 'error')
        exportGroups.value = []
    } finally {
        groupsLoading.value = false
    }
}

function closeExportModal() {
    showExportModal.value = false
    exportSelectedGroups.value = new Set()
}

function toggleExportGroup(groupName) {
    const next = new Set(exportSelectedGroups.value)
    if (next.has(groupName)) {
        next.delete(groupName)
    } else {
        next.add(groupName)
    }
    exportSelectedGroups.value = next
}

function confirmExport() {
    if (exportSelectedGroups.value.size === 0) {
        notification.addNotification('Pilih minimal satu group untuk diekspor.', 'error')
        return
    }
    const [year, month] = selectedMonthYear.value.split('-')
    const params = new URLSearchParams()
    params.set('month', month)
    params.set('year', year)
    params.set('periode', selectedPeriode.value)
    params.set('is_latest', onlyLatest.value ? 1 : 0)
    Array.from(exportSelectedGroups.value).forEach(g => params.append('groups[]', g))

    const url = `/api/v1/employees/compensation/export?${params.toString()}`
    closeExportModal()
    downloadFile(url, `kompensasi-${year}-${month}.xlsx`)
}

function bulkPrint() {
    showPrintGroupModal.value = true
    printSelectedGroups.value = new Set()
    fetchGroups()
}

function closePrintGroupModal() {
    showPrintGroupModal.value = false
    printSelectedGroups.value = new Set()
}

function togglePrintGroup(groupName) {
    const next = new Set(printSelectedGroups.value)
    if (next.has(groupName)) {
        next.delete(groupName)
    } else {
        next.add(groupName)
    }
    printSelectedGroups.value = next
}

async function confirmPrint() {
    if (printSelectedGroups.value.size === 0) {
        notification.addNotification('Pilih minimal satu group untuk dicetak.', 'error')
        return
    }
    printLoading.value = true
    try {
        const [year, month] = selectedMonthYear.value.split('-')
        const params = new URLSearchParams()
        params.set('month', month)
        params.set('year', year)
        params.set('periode', selectedPeriode.value)
        if (onlyLatest.value) params.set('is_latest', '1')
        Array.from(printSelectedGroups.value).forEach(g => params.append('groups[]', g))

        const res = await get(`/api/v1/employees/compensation/print?${params}`)
        if (res.data) {
            printData.value = res.data
            showPrintGroupModal.value = false
            showPrintModal.value = true
        }
    } catch (e) {
        notification.addNotification(e.message || 'Gagal memuat data slip kompensasi.', 'error')
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

                <label class="flex items-center gap-2 h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) cursor-pointer select-none text-sm text-(--text-main)"
                    title="Jika dicentang, hanya menampilkan kontrak terakhir (is_latest) dari tiap karyawan">
                    <input type="checkbox" v-model="onlyLatest" @change="applyFilter"
                        class="w-4 h-4 rounded border-(--border-soft) text-(--primary) focus:ring-(--primary-glow) cursor-pointer">
                    Hanya Kontrak Terakhir
                </label>
            </div>
        </BaseCard>

        <!-- Group Kompensasi (muncul hanya jika ada group di periode terpilih) -->
        <BaseCard v-if="compGroups.length > 0" padding="p-3" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-bold bg-(--primary)/10 text-(--primary)">
                    <i class="bx bx-group"></i> Group Kompensasi
                </span>
                <div v-for="g in compGroups" :key="g.name"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-(--primary)/20 bg-(--bg-elevated)">
                    <div>
                        <p class="text-sm font-semibold text-(--text-main) leading-tight">{{ g.name }}</p>
                        <p v-if="g.paid_at" class="text-xs text-emerald-600 leading-tight">
                            <i class="bx bx-check mr-0.5"></i>{{ formatDate(g.paid_at) }}
                        </p>
                    </div>
                    <button @click="confirmDeleteGroup(g)"
                        :disabled="deletingGroup === g.name"
                        title="Hapus group (kembalikan ke belum dibayar)"
                        class="ml-1 w-7 h-7 flex items-center justify-center rounded-md text-red-500 hover:bg-red-500/10 hover:text-red-600 transition-colors disabled:opacity-50">
                        <i :class="deletingGroup === g.name ? 'bx bx-loader-alt bx-spin' : 'bx bx-trash'"></i>
                    </button>
                </div>
            </div>
        </BaseCard>

        <!-- Bulk Action Bar -->
        <div v-if="selectedIds.size > 0"
            class="flex items-center gap-3 px-4 py-2 rounded-lg bg-(--primary)/5 border border-(--primary)/20">
            <i class="bx bx-check-square text-(--primary) text-lg"></i>
            <span class="text-sm font-medium text-(--text-main)">
                {{ selectedCount }} kontrak dipilih
            </span>
            <div class="flex-1"></div>
            <BaseButton variant="ghost" @click="selectedIds = new Set()"
                class="h-8 px-3 text-xs text-(--text-muted) hover:text-(--text-main)">
                <i class="bx bx-x mr-1"></i>Batal
            </BaseButton>
            <BaseButton variant="primary" @click="openGroupModal"
                class="h-8 px-4 text-xs font-semibold">
                <i class="bx bx-group mr-1"></i> Buat Group
            </BaseButton>
        </div>

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
                            <th class="px-4 py-3 text-left w-10">
                                <input type="checkbox"
                                    :checked="allSelected"
                                    :indeterminate="someSelected"
                                    @change="toggleSelectAll"
                                    class="w-4 h-4 rounded border-(--border-soft) text-(--primary) focus:ring-(--primary-glow) cursor-pointer">
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Karyawan</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Kontrak</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Akhir Kontrak</th>
                            <th
                                class="px-4 py-3 text-right text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Nominal</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Sisa Hari</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-bold text-(--text-muted) uppercase tracking-wider">
                                Status</th>
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
                            <!-- Checkbox -->
                            <td class="px-4 py-3 whitespace-nowrap w-10">
                                <input type="checkbox"
                                    :checked="selectedIds.has(contract.id)"
                                    :disabled="contract.is_compensation_paid"
                                    @change="toggleSelect(contract.id)"
                                    class="w-4 h-4 rounded border-(--border-soft) text-(--primary) focus:ring-(--primary-glow) cursor-pointer"
                                    :class="{ 'opacity-40': contract.is_compensation_paid }">
                            </td>
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

                            <!-- Akhir Kontrak -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p class="text-sm font-medium text-(--text-main)">{{ formatDate(contract.end_date) }}</p>
                            </td>

                            <!-- Nominal -->
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <p class="text-sm font-semibold text-(--text-main)">
                                    {{ formatCurrency(contract.nominal) }}
                                </p>
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

                            <!-- Status -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span v-if="contract.is_compensation_paid"
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-emerald-500/10 text-emerald-600">
                                    <i class="bx bx-check mr-1"></i> {{ formatDate(contract.compensation_paid_at) }}
                                </span>
                                <span v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-amber-500/10 text-amber-600">
                                    UNPAID
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </BaseCard>

        <!-- Group Modal -->
        <Teleport to="body">
            <div v-if="showGroupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeGroupModal">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-(--border-soft)">
                        <div class="flex items-center gap-3">
                            <i class="bx bx-group text-2xl text-(--text-muted)"></i>
                            <div>
                                <h2 class="text-lg font-semibold text-(--text-main)">Group Laporan Kompensasi</h2>
                                <p class="text-sm text-(--text-muted)">{{ selectedCount }} kontrak dipilih</p>
                            </div>
                        </div>
                        <button @click="closeGroupModal" class="p-1.5 rounded-lg hover:bg-(--bg-hover) transition-colors">
                            <i class="bx bx-x text-xl text-(--text-muted)"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-(--text-main) mb-1.5">Nama Group</label>
                            <input type="text" v-model="groupForm.name" placeholder="cth: Kompensasi Juni 2026"
                                class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-(--text-main) mb-1.5">Tanggal Pembayaran</label>
                            <input type="date" v-model="groupForm.payment_date"
                                class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-sm" />
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-(--border-soft)">
                        <BaseButton variant="ghost" @click="closeGroupModal" :disabled="groupLoading">Batal</BaseButton>
                        <BaseButton variant="primary" @click="saveGroup" :disabled="groupLoading">
                            <i :class="groupLoading ? 'bx bx-loader-alt bx-spin mr-1' : 'bx bx-check-double mr-1'"></i>
                            {{ groupLoading ? 'Menyimpan...' : 'Simpan' }}
                        </BaseButton>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Export Excel Modal -->
        <Teleport to="body">
            <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeExportModal">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-(--border-soft)">
                        <div class="flex items-center gap-3">
                            <i class="bx bx-download text-2xl text-(--text-muted)"></i>
                            <div>
                                <h2 class="text-lg font-semibold text-(--text-main)">Export Excel Kompensasi</h2>
                                <p class="text-sm text-(--text-muted)">
                                    Pilih group yang ingin diekspor
                                    <span v-if="exportPeriod.start" class="font-semibold text-(--text-main)">
                                        ({{ formatDate(exportPeriod.start) }} - {{ formatDate(exportPeriod.end_plus_7) }})
                                    </span>
                                </p>
                            </div>
                        </div>
                        <button @click="closeExportModal"
                            class="p-1.5 rounded-lg hover:bg-(--bg-hover) transition-colors">
                            <i class="bx bx-x text-xl text-(--text-muted)"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-4">
                        <div v-if="groupsLoading" class="flex flex-col items-center justify-center py-10">
                            <div
                                class="w-8 h-8 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-2">
                            </div>
                            <span class="text-sm text-(--text-muted)">Memuat daftar group...</span>
                        </div>

                        <div v-else-if="exportGroups.length === 0"
                            class="py-10 text-center text-(--text-muted)">
                            <i class="bx bx-check-circle text-4xl mb-2 block"></i>
                            Tidak ada group kompensasi pada rentang periode ini.
                        </div>

                        <div v-else class="max-h-80 overflow-y-auto space-y-2 pr-1">
                            <label v-for="g in exportGroups" :key="g.comp_group"
                                class="flex items-center gap-3 p-3 rounded-lg border border-(--border-soft) bg-(--bg-elevated) cursor-pointer hover:bg-(--bg-hover) transition-colors">
                                <input type="checkbox" :checked="exportSelectedGroups.has(g.comp_group)"
                                    @change="toggleExportGroup(g.comp_group)"
                                    class="w-4 h-4 rounded border-(--border-soft) text-(--primary) focus:ring-(--primary-glow) cursor-pointer">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-(--text-main) truncate">{{ g.comp_group }}</p>
                                    <p class="text-xs text-(--text-muted)">
                                        Dibayar {{ formatDate(g.compensation_paid_at) }} &middot; {{
                                            g.total_contracts }} kontrak
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between gap-2 px-6 py-4 border-t border-(--border-soft)">
                        <span class="text-sm text-(--text-muted)">
                            {{ exportSelectedGroups.size }} group dipilih
                        </span>
                        <div class="flex items-center gap-2">
                            <BaseButton variant="ghost" @click="closeExportModal">Batal</BaseButton>
                            <BaseButton variant="primary" @click="confirmExport">
                                <template #icon-left>
                                    <i class="bx bx-download text-lg"></i>
                                </template>
                                Export
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Print Group Selection Modal -->
        <Teleport to="body">
            <div v-if="showPrintGroupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closePrintGroupModal">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-(--border-soft)">
                        <div class="flex items-center gap-3">
                            <i class="bx bx-printer text-2xl text-(--text-muted)"></i>
                            <div>
                                <h2 class="text-lg font-semibold text-(--text-main)">Cetak Massal Kompensasi</h2>
                                <p class="text-sm text-(--text-muted)">
                                    Pilih group yang ingin dicetak
                                    <span v-if="exportPeriod.start" class="font-semibold text-(--text-main)">
                                        ({{ formatDate(exportPeriod.start) }} - {{ formatDate(exportPeriod.end_plus_7) }})
                                    </span>
                                </p>
                            </div>
                        </div>
                        <button @click="closePrintGroupModal"
                            class="p-1.5 rounded-lg hover:bg-(--bg-hover) transition-colors">
                            <i class="bx bx-x text-xl text-(--text-muted)"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-4">
                        <div v-if="groupsLoading" class="flex flex-col items-center justify-center py-10">
                            <div
                                class="w-8 h-8 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-2">
                            </div>
                            <span class="text-sm text-(--text-muted)">Memuat daftar group...</span>
                        </div>

                        <div v-else-if="exportGroups.length === 0"
                            class="py-10 text-center text-(--text-muted)">
                            <i class="bx bx-check-circle text-4xl mb-2 block"></i>
                            Tidak ada group kompensasi pada rentang periode ini.
                        </div>

                        <div v-else class="max-h-80 overflow-y-auto space-y-2 pr-1">
                            <label v-for="g in exportGroups" :key="'print-' + g.comp_group"
                                class="flex items-center gap-3 p-3 rounded-lg border border-(--border-soft) bg-(--bg-elevated) cursor-pointer hover:bg-(--bg-hover) transition-colors">
                                <input type="checkbox" :checked="printSelectedGroups.has(g.comp_group)"
                                    @change="togglePrintGroup(g.comp_group)"
                                    class="w-4 h-4 rounded border-(--border-soft) text-(--primary) focus:ring-(--primary-glow) cursor-pointer">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-(--text-main) truncate">{{ g.comp_group }}</p>
                                    <p class="text-xs text-(--text-muted)">
                                        Dibayar {{ formatDate(g.compensation_paid_at) }} &middot; {{
                                            g.total_contracts }} kontrak
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between gap-2 px-6 py-4 border-t border-(--border-soft)">
                        <span class="text-sm text-(--text-muted)">
                            {{ printSelectedGroups.size }} group dipilih
                        </span>
                        <div class="flex items-center gap-2">
                            <BaseButton variant="ghost" @click="closePrintGroupModal" :disabled="printLoading">Batal</BaseButton>
                            <BaseButton variant="primary" @click="confirmPrint" :disabled="printLoading">
                                <template #icon-left>
                                    <i :class="printLoading ? 'bx bx-loader-alt bx-spin text-lg' : 'bx bx-printer text-lg'"></i>
                                </template>
                                {{ printLoading ? 'Memuat...' : 'Cetak' }}
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

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

                <!-- Slip Pages (F4: 210 x 330mm, pad 7mm, gap 14mm, 6 slip/halaman) -->
                <div class="print-area">
                    <div v-for="(chunk, pageIdx) in chunkArray(printData.slips || [], 6)" :key="pageIdx"
                        class="slip-page">
                        <div v-for="(slip, slipIdx) in chunk" :key="slip.contract_id" class="slip">
                            <!-- KOP (nama & alamat dari tabel cabang) -->
                            <div class="kop">
                                <div class="kop-sub" v-if="printData.company?.name && printData.branch?.name && printData.company.name !== printData.branch.name">
                                    {{ (printData.company?.name || '').toUpperCase() }}
                                </div>
                                <div class="kop-name">EMBROIDERY & PRINTING FACTORY</div>
                                <div class="kop-addr">{{ printData.branch?.address || printData.company?.address || '' }}</div>
                                <div class="kop-phone">{{ printData.branch?.phone || printData.company?.phone || '' }}</div>
                            </div>

                            <!-- TITLE BAR -->
                            <div class="title-bar">
                                <div class="title-text">KOMPENSASI</div>
                                <div class="slip-num">{{ pageIdx * 6 + slipIdx + 1 }}</div>
                            </div>

                            <!-- INFO ROW -->
                            <div class="info-row">
                                <div class="info-left">{{ (slip.employee_name || '').toUpperCase() }}</div>
                                <div class="info-right">
                                    <div class="info-line">
                                        <span class="info-key">BULAN</span>
                                        <span class="colon">:</span>
                                        <span class="info-val">{{ (printData.bulan || '').toUpperCase() }}</span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-key">No ACCOUNT</span>
                                        <span class="colon">:</span>
                                        <span class="info-val">{{ slip.bank_account_number || '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION HEADER -->
                            <div class="section-header">
                                <span>K E T E R A N G A N</span>
                                <span>Jumlah</span>
                            </div>

                            <!-- BODY -->
                            <div class="body">
                                <!-- GAJI POKOK -->
                                <div class="flex items-center justify-start">
                                    <span class="w-1/2">GAJI POKOK</span>
                                    <span class="rp pr-2">Rp</span>
                                    <span class="w-1/4 text-right">{{ formatNumber(slip.gajiPokok, 2) }}</span>
                                </div>
                                <!-- TJ. MASA KERJA -->
                                <div class="flex items-center justify-start">
                                    <span class="w-1/2">TJ. MASA KERJA</span>
                                    <span class="rp pr-2">Rp</span>
                                    <span class="w-1/4 text-right">{{ formatNumber(slip.tjMasaKerja, 0) }}</span>
                                </div>
                                <!-- TGL AWAL -->
                                <div class="flex items-center justify-start">
                                    <span class="w-1/3 pl-6">TGL.AWAL</span>
                                    <span class="colon">:</span>
                                    <span class="value">{{ formatDate(slip.start_date) }}</span>
                                </div>
                                <!-- TGL AKHIR -->
                                <div class="flex items-center justify-start">
                                    <span class="w-1/3 pl-6">TGL.AKHIR</span>
                                    <span class="colon">:</span>
                                    <span class="value">{{ formatDate(slip.end_date) }}</span>
                                </div>
                                <!-- BULAN (duration × rate) -->
                                <div class="flex items-center justify-start">
                                    <span class="w-1/3 pl-6">BULAN</span>
                                    <div class="w-1/3">
                                        <span class="colon">:</span>
                                        <span class="pr-1 w-6">{{ slip.durationMonths }}</span>
                                        <span class="pr-1">x</span>
                                        <span class="rp">Rp</span>
                                        <span class="rate">{{ formatNumber(slip.monthlyRate, 2) }}</span>
                                    </div>
                                    <div class="w-1/4 flex justify-between items-center">
                                        <span class="pr-2">Rp.</span>
                                        <span class="subtotal">{{ formatNumber(slip.totalRaw, 2) }}</span>
                                    </div>
                                </div>

                                <!-- TOTAL SECTION -->
                                <div class="mt-8">
                                    <div class="flex items-center justify-start">
                                        <span class="w-1/3"></span>
                                        <span class="w-1/3 text-end pr-4">Pblt</span>
                                        <div class="w-1/4 flex justify-between items-center border-b">
                                            <span class="rp">Rp</span>
                                            <span class="pblt-amount">{{ formatNumber(slip.pembulatan, 0) }} +</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-start py-1">
                                        <span class="w-1/3"></span>
                                        <span class="w-1/3 text-end pr-4">TOTAL</span>
                                        <div class="w-1/4 flex justify-between items-center">
                                            <span class="rp">Rp</span>
                                            <span class="total-amount">{{ formatNumber(slip.totalRounded, 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TOTAL TERIMA -->
                            <div class="flex items-center justify-start border-t border-b px-2.5 text-[10px] font-bold">
                                <span class="w-2/3 text-center py-1">TOTAL &nbsp; TERIMA</span>
                                <div class="w-1/4 flex justify-between items-center">
                                    <span class="rp">Rp</span>
                                    <span class="">{{ formatNumber(slip.totalRounded, 2) }}</span>
                                </div>
                            </div>

                            <!-- SIGNATURE -->
                            <div class="flex justify-between">
                                <div class="w-[38%] h-16 flex flex-col items-center pb-1 ">
                                    <div class="mt-6">HRD</div>
                                    <div class="text-[9px] font-bold mt-3">{{ (printData.hrd || '').toUpperCase() }}</div>
                                </div>
                                <div class="w-[38%] h-16 flex flex-col items-center pb-1 relative">
                                    <div class="mt-6">Diterima oleh :</div>
                                    <div class="text-[9px] font-bold absolute bottom-1 left-0 right-0 text-center">{{ (slip.employee_name || '').toUpperCase() }}</div>
                                </div>
                                <div class="w-[24%] h-16 flex flex-col items-center pb-1 ">
                                    <div class="mt-6">TGL.</div>
                                    <div class="text-[8px] mt-3">{{ slip.compensation_paid_at ? formatDate(slip.compensation_paid_at) : '' }}</div>
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
/* 2 cols × 3 rows = 6 slips      */
/* padding 5mm, gap 8mm          */
/* ============================== */
.print-area {
    padding: 8px;
    background: #f3f4f6;
}

.slip-page {
    background: #fff;
    display: grid;
    /* grid-template-columns: 96mm 96mm;
    grid-template-rows: 100mm 100mm 100mm; */
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr 1fr;
    gap: 8mm;
    width: 200mm;
    height: 320mm;
    margin: 0 auto;
    page-break-after: always;
}

.slip-page:last-child {
    page-break-after: auto;
}

/* ============================== */
/* INDIVIDUAL SLIP                */
/* ============================== */
.slip {
    border: 0.5px solid #000;
    /* padding: 1.2mm; */
    min-height: 96mm;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    font-family: 'Times New Roman', Times, serif;
    font-size: 7pt;
    line-height: 1.25;
    color: #000;
    background: #fff;
    overflow: hidden;
}

.slip-empty {
    border: 0.5px solid #ccc;
    background: #fafafa;
}

/* ---- KOP ---- */
.kop {
    display: flex;
    flex-direction: column;
    padding: 0 0.5mm 0.5mm 3mm;
    border-bottom: 0.5px solid #000;
}

.kop-name {

    font-size: 7pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3px;
        font-style: italic;
}

.kop-sub {
    font-size: 10pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.2px;
}

.kop-addr {
    font-size: 6pt;
    margin-top: 0.4mm;
}

.kop-phone {
    font-size: 6pt;
}

/* ---- TITLE BAR ---- */
.title-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 0.5px solid #000;
    padding: 1mm 3mm 0.9mm;
}

.title-text {
    flex: 1;
    font-size: 10pt;
    font-weight: bold;
    text-align: center;
}

.slip-num {
    border: 0.5px solid #000;
    padding: 0.4mm 3mm;
    font-weight: bold;
    font-size: 9pt;
}

/* ---- INFO ROW ---- */
.info-row {
    display: flex;
}

.info-left {
    flex: 0 0 34mm;
    border-right: 0.5px solid #000;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-weight: bold;
    font-size: 8pt;
    text-transform: uppercase;
    line-height: 1.15;
    padding: 1mm 1mm;
    word-break: break-word;
}

.info-right {
    flex: 1;
    padding: 1mm 3mm;
    display: flex;
    flex-direction: column;
    font-size: 7pt;
}

.info-line {
    display: flex;
}

.info-key {
    flex: 0 0 24mm;
}

.info-line .colon {
    width: 3mm;
    text-align: center;
}

.info-val {
    flex: 1;
}

/* ---- SECTION HEADER ---- */
.section-header {
    display: flex;
    justify-content: space-between;
    border-top: 0.5px solid #000;
    border-bottom: 0.5px solid #000;
    padding: 0.8mm 3mm;
    font-weight: bold;
    font-size: 7.5pt;
    text-transform: uppercase;
}

/* ---- BODY ---- */
.body {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 1mm 3mm 0;
}

.body-row {
    display: flex;
    align-items: baseline;
    font-size: 7pt;
    min-height: 3.2mm;
}

.body-row .label {
    flex: 0 0 34mm;
    text-transform: uppercase;
}

.body-row .rp {
    flex: 0 0 8mm;
}

.body-row .colon {
    width: 3mm;
    text-align: center;
}

.body-row .value {
    flex: 1;
}

.body-row .amt {
    flex: 1;
    text-align: right;
    padding: 0 1mm 0.2mm;
}

.body-row .amt.gaji {
    background: #d5d9c8;
}

.body-row .amt.tj {
    background: #fff27a;
}

.bulan-row {
    display: flex;
    align-items: baseline;
    padding: 0.7mm 0;
    font-size: 7pt;
    min-height: 3.2mm;
}

.bulan-row .label {
    flex: 0 0 34mm;
    text-transform: uppercase;
}

.bulan-row .colon {
    width: 3mm;
    text-align: center;
}

.bulan-row .dur {
    flex: 0 0 7mm;
    text-align: right;
}

.bulan-row .bx-sign {
    flex: 0 0 5mm;
    text-align: center;
}

.bulan-row .rp {
    flex: 0 0 8mm;
}

.bulan-row .rate {
    flex: 1;
}

.bulan-row .subtotal {
    flex: none;
    text-align: right;
    min-width: 26mm;
    margin-left: auto;
    padding-left: 3mm;
}

/* ---- TOTAL SECTION ---- */
.total-section {
    border-top: 0.5px solid #000;
    margin-top: auto;
    padding-top: 0.5mm;
}

.pblt-row {
    display: flex;
    justify-content: flex-end;
    align-items: baseline;
    padding: 0.5mm 0;
    font-weight: bold;
    font-size: 8pt;
}

.pblt-row .pblt-label {
    font-weight: normal;
    margin-right: 4mm;
}

.pblt-row .rp {
    font-size: 7pt;
    margin-right: 3mm;
}

.pblt-row .pblt-amount {
    border-bottom: 0.5px solid #000;
    min-width: 24mm;
    text-align: right;
    padding-right: 1mm;
}

.pblt-row .plus {
    margin-left: 2mm;
    font-size: 8pt;
}

.total-row {
    display: flex;
    justify-content: flex-end;
    align-items: baseline;
    padding: 0.5mm 0;
    font-weight: bold;
    font-size: 8pt;
}

.total-row .total-label {
    margin-right: 4mm;
    text-transform: uppercase;
}

.total-row .rp {
    font-size: 7pt;
    margin-right: 3mm;
}

.total-row .total-amount {
    min-width: 26mm;
    text-align: right;
    padding-right: 1mm;
}

/* ---- TOTAL TERIMA ---- */
.total-terima {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 0.5px solid #000;
    border-bottom: 0.5px solid #000;
    padding: 1mm 3mm;
    font-weight: bold;
    font-size: 9pt;
    text-transform: uppercase;
}

.total-terima .tt-label {
    flex: 1;
    text-align: center;
}

.total-terima .tt-amount {
    flex: none;
    padding-right: 1mm;
}

/* ---- SIGNATURE ---- */
.signature-row {
    display: flex;
    flex: none;
    min-height: 18mm;
    padding-top: 1mm;
}

.sig-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    text-align: center;
    padding: 1mm 1mm 0.5mm;
    font-size: 6.5pt;
}

.sig-col.sig-mid,
.sig-col.sig-tgl {
    border-left: 0.5px solid #000;
}

.sig-label {
    align-self: flex-start;
    margin: 0 0 1mm 0.5mm;
    font-size: 7pt;
}

.sig-space {
    flex: 1;
}

.sig-stamp {
    border: 1px solid #2b5cbf;
    border-radius: 2mm;
    color: #2b5cbf;
    font-weight: bold;
    font-size: 6pt;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1mm 2mm;
    transform: rotate(-8deg);
    opacity: 0.9;
    max-width: 92%;
    margin: 1mm auto 0;
}

.sig-name {
    font-weight: bold;
    font-size: 9pt;
    text-transform: uppercase;
    border-top: 0.5px solid #000;
    padding: 0.5mm 1mm 0;
    width: 100%;
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
        width: 200mm;
        height: 320mm;
        margin: 0 auto;
    }

    .slip-page {
        margin-bottom: 0;
    }

    @page {
        size: 210mm 330mm;
        /* F4 portrait */
        /* margin: 5mm; */
        padding: 5mm;
    }
}
</style>
