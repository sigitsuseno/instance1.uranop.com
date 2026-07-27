<!-- resources/js/Pages/Admin/Reports/Kehadiran/Index.vue -->
<!-- Laporan Kehadiran — Matrix Roster Harian (Pure Vue SPA) -->
<!-- Phase 3: Refactored with ReportPageLayout + Setting Modal + Count Column -->

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import ReportPageLayout from '@/Components/ReportPage/ReportPageLayout.vue'
import ReportSettingsModal from '@/Components/ReportPage/ReportSettingsModal.vue'
import AbsensiSettings from '@/Components/ReportPage/settings/AbsensiSettings.vue'

const route = useRoute()
const router = useRouter()
const { get } = useApi()

// ── State ────────────────────────────────────
const periods = ref([])
const dates = ref([])
const records = ref([])
const loading = ref(false)
const selectedPeriodId = ref(null)
const showSettings = ref(false)

// Group filtering
const availableGroups = ref([])
const selectedGroups = ref([])

// ── Section Grouping ─────────────────────────
const KELOMPOK = {
    'A. JAKARTA':  ['GRP-JKT'],
    'B. ALL IN':   ['GRP-ALLIN', 'GRP-GD', 'GRP-SPR', 'GRP-SPC'],
    'C. PRINTING': ['GRP-PS1', 'GRP-SS'],
}

const groupCodes = computed(() => availableGroups.value.map(g => g.code))

const sections = computed(() => {
    const result = []
    for (const [label, codes] of Object.entries(KELOMPOK)) {
        const data = records.value.filter(r =>
            r.group_codes?.some(gc => codes.includes(gc))
        )
        const regular = data.filter(r => !r.is_titipan)
        const titipan = data.filter(r => r.is_titipan)
        result.push({ label, data, regular, titipan })
    }
    return result
})

const selectedGroupNames = computed(() => {
    if (!selectedGroups.value.length) return ''
    return availableGroups.value
        .filter(g => selectedGroups.value.includes(g.code))
        .map(g => g.name)
        .join(', ')
})

// ── Data Fetching ───────────────────────────
async function fetchData() {
    loading.value = true
    const params = new URLSearchParams()
    if (selectedPeriodId.value) params.set('period_id', selectedPeriodId.value)
    selectedGroups.value.forEach(g => params.append('groups[]', g))
    router.replace({ query: { period_id: selectedPeriodId.value, groups: selectedGroups.value.join(',') } })
    try {
        const res = await get(`/api/v1/laporan/kehadiran?${params}`)
        if (res.success) {
            periods.value = res.data.periods
            dates.value = res.data.dates
            records.value = res.data.records
            if (!selectedPeriodId.value && res.data.filters?.period_id) {
                selectedPeriodId.value = res.data.filters.period_id
            }
        }
    } catch (e) {
        console.error('Gagal ambil data kehadiran:', e)
    } finally {
        loading.value = false
    }
}

// ── Count Display ────────────────────────────
function isSectionA(label) {
    return label?.startsWith('A.')
}

function showUangMakan(label) {
    return label?.startsWith('A.') || label?.startsWith('B.')
}

function getCount(att, sectionLabel = '') {
    if (!att) return '-'
    // Section B: hide OT/LM
    if (sectionLabel.startsWith('B.')) return '-'
    const val = att.is_holiday ? att.lm : att.overtime
    if (val === null || val === undefined || val === 0) return '-'
    return (Number(val) / 60).toFixed(1)
}

// ── Status Color ─────────────────────────────
function getStatusClass(status) {
    switch (status) {
        case 'H':   return 'text-green-600 dark:text-green-400 font-bold'
        case 'L':   return 'text-blue-600 dark:text-blue-400 font-bold'
        case 'A':   return 'text-red-600 dark:text-red-400 font-bold'
        case 'C':   return 'text-amber-600 dark:text-amber-400 font-bold'
        case 'I':   return 'text-purple-600 dark:text-purple-400 font-bold'
        case 'S':   return 'text-orange-600 dark:text-orange-400 font-bold'
        case 'Off': return 'text-(--text-soft)'
        default:    return 'text-(--text-main)'
    }
}

function getCountClass(att, sectionLabel = '') {
    if (!att) return 'text-(--text-soft) text-[10px]'
    // Section B: hide OT/LM
    if (sectionLabel.startsWith('B.')) return 'text-(--text-soft) text-[10px]'
    const val = att.is_holiday ? att.lm : att.overtime
    if (!val) return 'text-(--text-soft) text-[10px]'
    return 'text-(--text-main) text-[10px] font-medium'
}

function formatUangMakan(val) {
    if (!val || val === 0) return '-'
    return 'Rp ' + Number(val).toLocaleString('id-ID')
}

function formatInsentif(val) {
    if (!val || val === 0) return '-'
    return 'Rp ' + Number(val).toLocaleString('id-ID')
}

function getSectionUangMakanTotal(sectionData) {
    return sectionData.reduce((sum, row) => sum + (row.total_uang_makan || 0), 0)
}

function getSectionInsentifTotal(sectionData) {
    return sectionData.reduce((sum, row) => sum + (row.total_insentif || 0), 0)
}

function hasSprInSection(sectionData) {
    return sectionData.some(row => row.group_codes?.includes('GRP-SPR'))
}

// ── Print / Export ───────────────────────────
function handlePrint() {
    window.print()
}

function handleExport() {
    const token = localStorage.getItem('token')
    const params = new URLSearchParams()
    if (selectedPeriodId.value) params.set('period_id', selectedPeriodId.value)
    selectedGroups.value.forEach(g => params.append('groups[]', g))

    const url = `/api/v1/laporan/kehadiran/export?${params.toString()}`
    fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
        .then(r => {
            if (!r.ok) throw new Error('Export failed')
            return r.blob()
        })
        .then(blob => {
            const downloadUrl = URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = downloadUrl
            link.setAttribute('download', `Laporan_Kehadiran.xlsx`)
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            URL.revokeObjectURL(downloadUrl)
        })
        .catch(err => {
            console.error('Gagal export:', err)
            alert('Gagal export Excel.')
        })
}

// ── Settings ─────────────────────────────────
function onSettingsSaved(payload) {
    if (payload.employee_groups && payload.employee_groups.length > 0) {
        selectedGroups.value = payload.employee_groups
    }
    showSettings.value = false
}

// ── Init ─────────────────────────────────────
onMounted(async () => {
    // 0. Restore period dari URL — sebelum async ops, agar watch sudah punya ID
    if (route.query.period_id) selectedPeriodId.value = Number(route.query.period_id)

    // 1. Load all available groups (for modal checkboxes)
    try {
        const groupsRes = await get('/api/v1/settings/employee-data/groups')
        const allGroups = groupsRes.data || []
        availableGroups.value = allGroups
            .filter(g => g.group_label === 'Imported Shift/Group')
            .map(g => ({ code: g.code, name: g.name }))
    } catch (err) {
        console.error('Gagal fetch groups:', err)
    }

    // 2. Load saved group selection from report config
    try {
        const configRes = await get('/api/v1/settings/report-configs/absensi')
        const savedGroups = configRes.employee_groups || configRes.data?.employee_groups || []

        if (savedGroups.length > 0) {
            selectedGroups.value = savedGroups
        } else {
            // Fallback default
            selectedGroups.value = ['GRP-PS1']
        }
    } catch (err) {
        console.error('Gagal fetch report config:', err)
        selectedGroups.value = ['GRP-PS1']
    }

    // Auto-fetch if groups exist
    // (watch akan skip karena loading masih true dari fetchData ini)
    if (selectedGroups.value.length) {
        fetchData()
    }
})

// Re-fetch when groups change via settings save
watch(selectedGroups, () => {
    if (!loading.value && selectedPeriodId.value && selectedGroups.value.length) {
        fetchData()
    }
}, { deep: true })
</script>

<template>
    <ReportPageLayout
        title="Laporan Kehadiran"
        description="Rekapitulasi kehadiran harian — Matrix Roster"
        @openSettings="showSettings = true"
    >
        <!-- Actions: Print + Export + Karyawan Titipan -->
        <template #actions>
            <button
                @click="$router.push('/admin/reports/karyawan-titipan')"
                class="print-hide flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-sm font-medium transition-colors"
            >
                <i class="bx bx-group text-lg"></i> Karyawan Titipan
            </button>
            <button
                :disabled="!records.length"
                @click="handlePrint"
                class="print-hide flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-sm font-medium transition-colors"
            >
                <i class="bx bx-printer text-lg"></i> Print
            </button>
            <button
                :disabled="!records.length"
                @click="handleExport"
                class="print-hide flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-sm font-medium transition-colors"
            >
                <i class="bx bx-download text-lg"></i> Export Excel
            </button>
        </template>

        <!-- Filter Periode -->
        <template #filter>
            <div class="print-hide bg-(--bg-card) border border-(--border-soft) rounded-md p-4">
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="min-w-[240px]">
                        <label class="block text-xs font-semibold text-(--text-muted) mb-1.5 uppercase tracking-wide">Periode Payroll</label>
                        <select v-model="selectedPeriodId" @change="fetchData"
                            class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) outline-none">
                            <option :value="null" disabled>Pilih Periode</option>
                            <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }} ({{ p.start_date }} &rarr; {{ p.end_date }})</option>
                        </select>
                    </div>
                </div>
            </div>
        </template>

        <!-- Info Bar -->
        <div class="print-hide flex items-center gap-2 text-sm text-(--text-muted) mb-4">
            <span>{{ records.length }} karyawan</span>
            <span class="text-(--text-soft)">·</span>
            <span>{{ dates.length }} hari</span>
            <template v-if="selectedGroupNames">
                <span class="text-(--text-soft)">·</span>
                <span>{{ selectedGroupNames }}</span>
            </template>
        </div>

        <!-- Legend -->
        <div class="print-hide flex flex-wrap gap-4 text-xs text-(--text-muted) mb-4">
            <span><b class="text-green-600">H</b> Hadir</span>
            <span><b class="text-red-600">A</b> Absen</span>
            <span><b class="text-blue-600">L</b> Libur Masuk</span>
            <span><b class="text-(--text-soft)">Off</b> Libur</span>
            <span><b class="text-amber-600">C</b> Cuti</span>
            <span><b class="text-purple-600">I</b> Izin</span>
            <span><b class="text-orange-600">S</b> Sakit</span>
            <span class="text-(--text-soft)">|</span>
            <span class="text-(--text-soft)">Jam: Overtime (hari kerja) / LM (minggu/holiday)</span>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12 text-(--text-muted)">⏳ Memuat data...</div>

        <!-- Empty (no groups selected) -->
        <div v-else-if="!selectedGroups.length" class="bg-(--bg-card) border border-(--border-soft) rounded-md p-12 text-center">
            <p class="text-(--text-muted) text-lg">Pilih grup terlebih dahulu.</p>
            <p class="text-(--text-soft) text-sm mt-1">Klik tombol ⚙️ Setting untuk memilih group karyawan.</p>
        </div>

        <!-- Sections -->
        <template v-else-if="records.length">
            <div v-for="section in sections" :key="section.label" class="mb-8">
                <!-- Section Header -->
                <div class="flex items-center gap-3 mb-3 px-1">
                    <h3 class="text-base font-bold text-(--text-main)">{{ section.label }}</h3>
                    <span class="text-xs text-(--text-muted) bg-(--bg-elevated) px-2 py-0.5 rounded-full">
                        {{ section.regular.length }} karyawan
                        <template v-if="section.titipan.length"> + {{ section.titipan.length }} titipan</template>
                    </span>
                    <span v-if="isSectionA(section.label) && section.titipan.length"
                        class="text-[10px] text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/30 px-2 py-0.5 rounded-full font-medium">
                        {{ section.titipan.length }} titipan
                    </span>
                </div>

                <!-- No data for this section -->
                <div v-if="!section.data.length" class="bg-(--bg-card) border border-(--border-soft) rounded-md p-8 text-center mb-4">
                    <p class="text-(--text-muted) text-sm">Tidak ada data untuk kelompok ini.</p>
                </div>

                <!-- Matrix Table -->
                <div v-else class="bg-(--bg-card) border border-(--border-soft) rounded-md shadow-sm overflow-hidden">
                    <div class="overflow-x-auto max-h-[50vh]">
                        <table class="min-w-max border-collapse">
                            <thead class="sticky top-0 z-20">
                                <!-- Date row -->
                                <tr class="bg-(--bg-elevated)">
                                    <th class="sticky left-0 z-30 bg-(--bg-elevated) px-3 py-2.5 text-left text-xs font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.06)]" style="width:100px;" rowspan="2">NIP</th>
                                    <th class="sticky z-30 bg-(--bg-elevated) px-3 py-2.5 text-left text-xs font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.06)]" style="left:100px;width:200px;" rowspan="2">Nama</th>
                                    <th v-for="d in dates" :key="d.date"
                                        :colspan="isSectionA(section.label) ? 1 : 2"
                                        class="px-1.5 py-2 text-center text-[10px] font-bold uppercase border-b border-l border-(--border-soft)"
                                        :class="d.is_weekend ? 'bg-red-50 dark:bg-red-950/30 text-red-600' : 'text-(--text-muted)'"
                                        :title="d.day_name + ', ' + d.date">
                                        {{ d.day }}<div class="text-[8px] font-normal mt-0.5 leading-none">{{ d.day_name }}</div>
                                    </th>
                                </tr>
                                <!-- Sub-header: St | Jam -->
                                <tr class="bg-(--bg-elevated)">
                                    <template v-for="d in dates" :key="'sub-' + section.label + '-' + d.date">
                                        <th class="px-1 py-1 text-center text-[9px] font-semibold uppercase border-b border-l border-(--border-soft) text-(--text-muted)"
                                            :class="d.is_weekend ? 'bg-red-50 dark:bg-red-950/30' : ''">St</th>
                                        <th v-if="!isSectionA(section.label)" class="px-1 py-1 text-center text-[9px] font-semibold uppercase border-b border-l border-(--border-soft) text-(--text-muted)"
                                            :class="d.is_weekend ? 'bg-red-50 dark:bg-red-950/30' : ''">{{ d.is_weekend ? 'LM' : 'OT' }}</th>
                                    </template>
                                    <th v-if="showUangMakan(section.label)" class="px-2 py-1 text-center text-[9px] font-semibold uppercase border-b border-l border-(--border-soft) text-(--text-muted) bg-(--bg-elevated) sticky right-0 z-20 min-w-[80px]">Uang Makan</th>
                                    <th v-if="section.label.startsWith('B.') && hasSprInSection(section.data)" class="px-2 py-1 text-center text-[9px] font-semibold uppercase border-b border-l border-(--border-soft) text-(--text-muted) bg-(--bg-elevated) sticky right-0 z-20 min-w-[80px]">Insentif</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-(--border-soft)">
                                <tr v-for="row in section.data" :key="row.id"
                                    class="hover:bg-(--bg-elevated) transition-colors group"
                                    :class="row.is_titipan ? 'bg-purple-50/20 dark:bg-purple-950/10' : ''">
                                    <td class="sticky left-0 z-10 bg-(--bg-card) group-hover:bg-(--bg-elevated) px-3 py-2 font-mono text-base text-(--text-muted) border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.04)] transition-colors truncate"
                                        :class="row.is_titipan ? 'bg-purple-50/20 dark:bg-purple-950/10 group-hover:bg-purple-100/50 dark:group-hover:bg-purple-950/30' : ''">
                                        {{ row.employee_code }}
                                        <span v-if="row.is_titipan" class="inline-block ml-1 px-1 py-px text-[8px] font-semibold bg-purple-100 text-purple-600 dark:bg-purple-900/50 dark:text-purple-400 rounded align-middle" title="Karyawan Titipan">T</span>
                                    </td>
                                    <td class="sticky z-10 bg-(--bg-card) group-hover:bg-(--bg-elevated) px-3 py-2 font-medium text-base text-(--text-main) border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.04)] transition-colors truncate"
                                        :style="{ left:'100px' }"
                                        :class="row.is_titipan ? 'bg-purple-50/20 dark:bg-purple-950/10 group-hover:bg-purple-100/50 dark:group-hover:bg-purple-950/30' : ''">
                                        {{ row.name }}
                                    </td>
                                    <template v-for="d in dates" :key="d.date">
                                        <td class="px-1 py-2 text-center text-xs border-b border-l border-(--border-soft)"
                                            :class="row.attendance[d.date]?.is_holiday ? 'bg-red-50/30 dark:bg-red-950/15' : ''"
                                            :title="d.day_name + ', ' + d.date + (row.attendance[d.date]?.holiday_name ? ' — ' + row.attendance[d.date].holiday_name : '')">
                                            <span :class="getStatusClass(row.attendance[d.date]?.status)">{{ row.attendance[d.date]?.status || '-' }}</span>
                                        </td>
                                        <td v-if="!isSectionA(section.label)" class="px-1 py-2 text-center text-xs border-b border-l border-(--border-soft)"
                                            :class="row.attendance[d.date]?.is_holiday ? 'bg-red-50/30 dark:bg-red-950/15' : ''">
                                            <span :class="getCountClass(row.attendance[d.date], section.label)">{{ getCount(row.attendance[d.date], section.label) }}</span>
                                        </td>
                                    </template>
                                    <td v-if="showUangMakan(section.label)"
                                        class="px-2 py-2 text-center text-xs font-semibold text-(--text-main) border-b border-l border-(--border-soft) bg-(--bg-card) sticky z-10"
                                        :style="section.label.startsWith('B.') && hasSprInSection(section.data) ? { right: '80px' } : { right: '0' }">
                                        {{ formatUangMakan(row.total_uang_makan) }}
                                    </td>
                                    <td v-if="section.label.startsWith('B.') && hasSprInSection(section.data)" class="px-2 py-2 text-center text-xs font-semibold border-b border-l border-(--border-soft) bg-(--bg-card) sticky right-0 z-10"
                                        :class="row.group_codes?.includes('GRP-SPR') ? 'text-amber-700 dark:text-amber-400' : 'text-(--text-soft)'">
                                        {{ row.group_codes?.includes('GRP-SPR') ? formatInsentif(row.total_insentif) : '-' }}
                                    </td>
                                </tr>
                            </tbody>
                            <!-- Section A & B: Uang Makan Subtotal -->
                            <tfoot v-if="showUangMakan(section.label) && section.data.length">
                                <tr class="bg-(--bg-elevated) border-t-2 border-(--border-soft)">
                                    <td class="sticky left-0 z-10 bg-(--bg-elevated) px-3 py-2 border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.06)]" colspan="2">
                                        <span class="text-xs font-bold text-(--text-main)">Total Uang Makan</span>
                                        <span class="text-[10px] text-(--text-muted) ml-2">({{ section.data.length }} karyawan)</span>
                                    </td>
                                    <!-- Empty cells for each date -->
                                    <td v-for="d in dates" :key="'foot-' + d.date"
                                        class="px-1 py-2 text-center text-xs border-b border-l border-(--border-soft)"
                                        :class="d.is_weekend ? 'bg-red-50/30 dark:bg-red-950/15' : ''">
                                    </td>
                                    <!-- Total Uang Makan -->
                                    <td class="px-2 py-2 text-center text-sm font-bold text-green-700 dark:text-green-400 border-b border-l border-(--border-soft) bg-(--bg-elevated) sticky z-10"
                                        :style="section.label.startsWith('B.') && hasSprInSection(section.data) ? { right: '80px' } : { right: '0' }">
                                        {{ formatUangMakan(getSectionUangMakanTotal(section.data)) }}
                                    </td>
                                    <!-- Total Insentif (khusus GRP-SPR di section B) -->
                                    <td v-if="section.label.startsWith('B.') && hasSprInSection(section.data)" class="px-2 py-2 text-center text-sm font-bold text-amber-700 dark:text-amber-400 border-b border-l border-(--border-soft) bg-(--bg-elevated) sticky right-0 z-10">
                                        {{ formatInsentif(getSectionInsentifTotal(section.data)) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- Empty (no records) -->
        <div v-else class="bg-(--bg-card) border border-(--border-soft) rounded-md p-12 text-center">
            <p class="text-(--text-muted) text-lg">Belum ada data untuk ditampilkan.</p>
            <p class="text-(--text-soft) text-sm mt-1">Pilih periode payroll dan grup terlebih dahulu.</p>
        </div>

        <!-- Settings Modal -->
        <ReportSettingsModal
            v-if="showSettings"
            report-type="absensi"
            report-label="Laporan Kehadiran"
            :available-groups="groupCodes"
            @close="showSettings = false"
            @saved="onSettingsSaved"
        >
            <template #config="{ config, updateConfig }">
                <AbsensiSettings
                    :config="config"
                    @update:config="updateConfig"
                />
            </template>
        </ReportSettingsModal>
    </ReportPageLayout>
</template>

<style>
@media print {
    /* Hide sidebar, nav, footer, topbar */
    aside, nav, header, .sidebar, .navbar, .topbar, .header-nav, footer {
        display: none !important;
    }
    
    /* Hide print-hide elements */
    .print-hide {
        display: none !important;
    }
    
    /* Reset admin layout margins & padding */
    .ml-55, .ml-20 {
        margin-left: 0 !important;
    }
    
    body, .min-h-screen {
        padding: 0 !important;
        margin: 0 !important;
    }
    
    main, .p-6 {
        padding: 5mm !important;
        min-height: auto !important;
    }
    
    /* Remove table height restrictions */
    [class*="max-h-"] {
        max-height: none !important;
    }
    
    .overflow-x-auto, .overflow-y-auto {
        overflow: visible !important;
    }
    
    /* Full width tables */
    .report-page {
        padding: 0 !important;
    }
    
    table {
        font-size: 7px !important;
    }
    
    /* Remove shadows */
    .shadow-sm, [class*="shadow-"] {
        box-shadow: none !important;
    }
    
    /* Sticky elements become normal in print */
    .sticky {
        position: static !important;
    }
    
    /* Ensure page breaks don't cut tables */
    table, tr, td, th {
        page-break-inside: auto;
    }
    tr {
        page-break-inside: avoid;
    }
}
</style>
<style scoped>
table { border-collapse: separate; border-spacing: 0; }
</style>
