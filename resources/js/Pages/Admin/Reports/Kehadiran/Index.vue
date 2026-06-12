<!-- resources/js/Pages/Admin/Reports/Kehadiran/Index.vue -->
<!-- Laporan Kehadiran — Matrix Roster Harian (Pure Vue SPA) -->

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi.js'

const route = useRoute()
const router = useRouter()
const { get } = useApi()

// ── State ────────────────────────────────────
const periods = ref([])
const dates = ref([])
const records = ref([])
const loading = ref(false)
const selectedPeriodId = ref(null)

// Group filtering
const availableGroups = ref([])
const selectedGroups = ref([])

// ── Computed ─────────────────────────────────
const title = computed(() => 'Laporan Kehadiran')

const selectedGroupNames = computed(() => {
    if (!selectedGroups.value.length) return ''
    return availableGroups.value
        .filter(g => selectedGroups.value.includes(g.code))
        .map(g => g.name)
        .join(', ')
})

// ── Data ─────────────────────────────────────
async function fetchData() {
    if (!selectedPeriodId.value) return
    loading.value = true
    router.replace({ query: { period_id: selectedPeriodId.value, groups: selectedGroups.value.join(',') } })
    try {
        const params = new URLSearchParams({
            period_id: selectedPeriodId.value,
        })
        selectedGroups.value.forEach(g => params.append('groups[]', g))
        const res = await get(`/api/v1/laporan/kehadiran?${params}`)
        if (res.success) {
            periods.value = res.data.periods
            dates.value = res.data.dates
            records.value = res.data.records
        }
    } catch (e) {
        console.error('Gagal ambil data kehadiran:', e)
    } finally {
        loading.value = false
    }
}

// ── Color ────────────────────────────────────
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

// ── Init ─────────────────────────────────────
onMounted(async () => {
    // Fetch groups
    try {
        const res = await get('/api/v1/settings/employee-data/groups')
        const allGroups = res.data || []
        availableGroups.value = allGroups
            .filter(g => g.group_label === 'Imported Shift/Group')
            .map(g => ({ code: g.code, name: g.name }))

        // Default: PS1 checked
        selectedGroups.value = availableGroups.value
            .filter(g => g.code === 'GRP-PS1')
            .map(g => g.code)
    } catch (err) {
        console.error('Gagal fetch groups:', err)
    }

    // Restore query params
    if (route.query.period_id) selectedPeriodId.value = Number(route.query.period_id)
    if (route.query.groups) selectedGroups.value = route.query.groups.split(',').filter(Boolean)

    // Auto-fetch jika groups sudah ada
    if (selectedGroups.value.length) {
        fetchData()
    }
})

// Re-fetch when groups change (via checkbox toggle)
watch(selectedGroups, () => {
    if (selectedPeriodId.value && selectedGroups.value.length) {
        fetchData()
    }
}, { deep: true })
</script>

<template>
    <div class="space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-(--text-main)">Laporan Kehadiran</h1>
                <p class="text-sm text-(--text-muted) mt-0.5">
                    Rekapitulasi kehadiran harian — Matrix Roster
                    <span class="text-(--text-soft)">·</span>
                    {{ records.length }} karyawan
                    <span class="text-(--text-soft)">·</span>
                    {{ dates.length }} hari
                    <template v-if="selectedGroupNames">
                        <span class="text-(--text-soft)">·</span>
                        {{ selectedGroupNames }}
                    </template>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    :disabled="!records.length"
                    class="flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-sm font-medium transition-colors"
                >
                    <i class="bx bx-printer text-lg"></i> Print
                </button>
                <button
                    :disabled="!records.length"
                    class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-sm font-medium transition-colors"
                >
                    <i class="bx bx-download text-lg"></i> Export Excel
                </button>
            </div>
        </div>

        <!-- Group Filters -->
        <div v-if="availableGroups.length" class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4">
            <div class="flex flex-wrap items-center gap-4">
                <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider">Filter Grup:</span>
                <label
                    v-for="group in availableGroups"
                    :key="group.code"
                    class="flex items-center gap-2 cursor-pointer group select-none"
                >
                    <input
                        type="checkbox"
                        :value="group.code"
                        v-model="selectedGroups"
                        class="w-4 h-4 rounded text-(--primary) focus:ring-(--primary-glow) border-(--border-soft)"
                    />
                    <span class="text-sm font-medium text-(--text-main) group-hover:text-(--primary) transition-colors">
                        {{ group.name }}
                    </span>
                </label>
            </div>
        </div>

        <!-- Filter Periode -->
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4">
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

        <!-- Legend -->
        <div class="flex flex-wrap gap-4 text-xs text-(--text-muted)">
            <span><b class="text-green-600">H</b> Hadir</span>
            <span><b class="text-red-600">A</b> Absen</span>
            <span><b class="text-blue-600">L</b> Libur Masuk</span>
            <span><b class="text-(--text-soft)">Off</b> Libur</span>
            <span><b class="text-amber-600">C</b> Cuti</span>
            <span><b class="text-purple-600">I</b> Izin</span>
            <span><b class="text-orange-600">S</b> Sakit</span>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12 text-(--text-muted)">⏳ Memuat data...</div>

        <!-- Empty (no groups selected) -->
        <div v-else-if="!selectedGroups.length" class="bg-(--bg-card) border border-(--border-soft) rounded-md p-12 text-center">
            <p class="text-(--text-muted) text-lg">Pilih grup terlebih dahulu.</p>
            <p class="text-(--text-soft) text-sm mt-1">Centang satu atau lebih grup di filter atas untuk menampilkan data.</p>
        </div>

        <!-- Table -->
        <div v-else-if="records.length" class="bg-(--bg-card) border border-(--border-soft) rounded-md shadow-sm overflow-hidden">
            <div class="overflow-x-auto max-h-[65vh]">
                <table class="min-w-max border-collapse">
                    <thead class="sticky top-0 z-20">
                        <tr class="bg-(--bg-elevated)">
                            <th class="sticky left-0 z-30 bg-(--bg-elevated) px-3 py-2.5 text-left text-xs font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.06)] w-20">NIP</th>
                            <th class="sticky z-30 bg-(--bg-elevated) px-3 py-2.5 text-left text-xs font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.06)]" style="left:100px;width:180px;">Nama</th>
                            <th v-for="d in dates" :key="d.date"
                                class="px-1.5 py-2.5 text-center text-[10px] font-bold uppercase border-b border-l border-(--border-soft) min-w-[32px]"
                                :class="d.is_weekend ? 'bg-red-50 dark:bg-red-950/30 text-red-600' : 'text-(--text-muted)'"
                                :title="d.day_name + ', ' + d.date">
                                {{ d.day }}<div class="text-[8px] font-normal mt-0.5 leading-none">{{ d.day_name }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--border-soft)">
                        <tr v-for="row in records" :key="row.id" class="hover:bg-(--bg-elevated) transition-colors group">
                            <td class="sticky left-0 z-10 bg-(--bg-card) group-hover:bg-(--bg-elevated) px-3 py-2 font-mono text-xs text-(--text-muted) border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.04)] transition-colors">{{ row.employee_code }}</td>
                            <td class="sticky z-10 bg-(--bg-card) group-hover:bg-(--bg-elevated) px-3 py-2 font-medium text-sm text-(--text-main) border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.04)] transition-colors truncate" :style="{ left:'100px' }">{{ row.name }}</td>
                            <td v-for="d in dates" :key="d.date"
                                class="px-1 py-2 text-center text-xs border-b border-l border-(--border-soft)"
                                :class="row.attendance[d.date]?.is_holiday ? 'bg-red-50/30 dark:bg-red-950/15' : ''"
                                :title="d.day_name + ', ' + d.date + (row.attendance[d.date]?.holiday_name ? ' — ' + row.attendance[d.date].holiday_name : '')">
                                <span :class="getStatusClass(row.attendance[d.date]?.status)">{{ row.attendance[d.date]?.status || '-' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty (no records) -->
        <div v-else class="bg-(--bg-card) border border-(--border-soft) rounded-md p-12 text-center">
            <p class="text-(--text-muted) text-lg">Belum ada data untuk ditampilkan.</p>
            <p class="text-(--text-soft) text-sm mt-1">Pilih periode payroll dan grup terlebih dahulu.</p>
        </div>
    </div>
</template>

<style scoped>
table { border-collapse: separate; border-spacing: 0; }
</style>
