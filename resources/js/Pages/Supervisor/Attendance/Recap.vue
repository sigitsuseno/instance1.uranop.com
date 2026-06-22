<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useApi } from '../../../composables/useApi';

const router = useRouter();
const route = useRoute();
const { get } = useApi();

const employees = ref([]);
const dates = ref([]);
const period = ref({});
const filters = ref({});
const departments = ref([]);
const workPatterns = ref([]);
const payrollPeriods = ref([]);
const companyName = ref('');
const isLoading = ref(true);

const selectedPeriod = ref('');
const startDate = ref('');
const endDate = ref('');

const searchForm = ref({
    search: '',
    department_id: '',
    work_pattern_id: '',
});

async function fetchData(params = {}) {
    isLoading.value = true;
    try {
        const queryParams = new URLSearchParams();
        for (const [key, value] of Object.entries(params)) {
            if (value) queryParams.append(key, value);
        }
        
        const response = await get(`/api/v1/supervisor/attendance/rekap-absensi?${queryParams.toString()}`);
        
        employees.value = response.employees || [];
        dates.value = response.dates || [];
        period.value = response.period || {};
        filters.value = response.filters || {};
        departments.value = response.departments || [];
        workPatterns.value = response.workPatterns || [];
        payrollPeriods.value = response.payrollPeriods || [];
        companyName.value = response.companyName || '';
        
        selectedPeriod.value = period.value.period_id || '';
        startDate.value = period.value.start || '';
        endDate.value = period.value.end || '';
        
        searchForm.value.search = filters.value.search || '';
        searchForm.value.department_id = filters.value.department_id || '';
        searchForm.value.work_pattern_id = filters.value.work_pattern_id || '';
    } catch (error) {
        console.error('Error fetching recap data:', error);
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    fetchData(route.query);
});

function applyPeriod() {
    if (!selectedPeriod.value) return;
    const p = payrollPeriods.value.find(x => x.id === selectedPeriod.value);
    if (!p) return;

    startDate.value = p.start_date;
    endDate.value = p.end_date;

    submitFilter();
}

function submitFilter() {
    const params = {
        start_date: startDate.value,
        end_date: endDate.value,
        search: searchForm.value.search,
        department_id: searchForm.value.department_id,
        work_pattern_id: searchForm.value.work_pattern_id,
    };
    
    router.push({ path: route.path, query: params });
    fetchData(params);
}

function resetFilter() {
    searchForm.value.search = '';
    searchForm.value.department_id = '';
    searchForm.value.work_pattern_id = '';
    submitFilter();
}

function formatDateRange() {
    if (!startDate.value || !endDate.value) return '';
    const start = new Date(startDate.value);
    const end = new Date(endDate.value);
    const formatId = (date) => date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    return `${formatId(start)} - ${formatId(end)}`;
}

function exportExcel() {
    let url = `/api/v1/supervisor/attendance/rekap-absensi/export?start_date=${startDate.value}&end_date=${endDate.value}`;
    if (searchForm.value.search) url += `&search=${encodeURIComponent(searchForm.value.search)}`;
    if (searchForm.value.department_id) url += `&department_id=${searchForm.value.department_id}`;
    if (searchForm.value.work_pattern_id) url += `&work_pattern_id=${searchForm.value.work_pattern_id}`;
    window.location.href = url;
}

function openPrintPage() {
    let url = `/api/v1/supervisor/attendance/rekap-absensi/print?start_date=${startDate.value}&end_date=${endDate.value}`;
    if (searchForm.value.search) url += `&search=${encodeURIComponent(searchForm.value.search)}`;
    if (searchForm.value.department_id) url += `&department_id=${searchForm.value.department_id}`;
    if (searchForm.value.work_pattern_id) url += `&work_pattern_id=${searchForm.value.work_pattern_id}`;
    window.open(url, '_blank');
}

function getStatusClass(status) {
    if (!status || status === '-') return 'text-gray-300';
    if (status === 'H') return 'text-green-600 font-bold';
    if (status === 'SAKIT') return 'text-yellow-600';
    if (status === 'I') return 'text-purple-600';
    if (status === 'CUTI') return 'text-blue-600';
    if (status === 'LIBUR') return 'text-orange-600';
    if (status === 'OFF') return 'text-gray-400';
    return 'text-red-600';
}

function getDayClass(dayName) {
    if (dayName === 'Minggu') return 'bg-green-100';
    return '';
}

function getDayAbbrev(dayName) {
    const map = { 'Minggu': 'Min', 'Senin': 'Sen', 'Selasa': 'Sel', 'Rabu': 'Rab', 'Kamis': 'Kam', 'Jumat': 'Jum', 'Sabtu': 'Sab' };
    return map[dayName] || dayName.substring(0, 3);
}
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-(--text-main)">Rekap Absensi</h1>
                <p class="text-sm text-(--text-muted) mt-1">
                    <i class="bx bx-calendar-alt mr-1"></i>
                    Periode: {{ formatDateRange() }}
                </p>
            </div>
            <div class="flex gap-2">
                <button @click="openPrintPage"
                    class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition"
                    :disabled="!startDate || !endDate">
                    <i class="bx bx-printer text-lg"></i>
                    Print PDF
                </button>
                <button @click="exportExcel"
                    class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition"
                    :disabled="!startDate || !endDate">
                    <i class="bx bx-download text-lg"></i>
                    Export Excel
                </button>
            </div>
        </div>

        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    <div>
                        <label class="block text-xs font-medium text-(--text-muted) mb-1">
                            <i class="bx bx-calendar mr-1"></i> Periode Penggajian
                        </label>
                        <select v-model="selectedPeriod" @change="applyPeriod"
                            class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-indigo-500 min-w-[280px]">
                            <option value="">Pilih Periode</option>
                            <option v-for="p in payrollPeriods" :key="p.id" :value="p.id">
                                {{ p.name }} ({{ new Date(p.start_date).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) }} - {{ new Date(p.end_date).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) }})
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-wrap gap-3">
                <div class="relative">
                    <i class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft)"></i>
                    <input v-model="searchForm.search" type="text" placeholder="Cari karyawan..."
                        class="pl-9 pr-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm w-64 focus:ring-2 focus:ring-indigo-500"
                        @keyup.enter="submitFilter" />
                </div>
                <select v-model="searchForm.department_id" @change="submitFilter"
                    class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">Semua Departemen</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                </select>
                <select v-model="searchForm.work_pattern_id" @change="submitFilter"
                    class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">Semua Pola Kerja</option>
                    <option v-for="wp in workPatterns" :key="wp.id" :value="wp.id">{{ wp.name }}</option>
                </select>
                <button v-if="searchForm.search || searchForm.department_id || searchForm.work_pattern_id" @click="resetFilter"
                    class="p-2 rounded-lg border border-(--border-soft) hover:bg-red-50 hover:text-red-600 transition" title="Reset Filter">
                    <i class="bx bx-reset text-lg"></i>
                </button>
            </div>
        </div>

        <div v-if="isLoading" class="flex justify-center my-12">
            <i class="bx bx-loader-alt animate-spin text-4xl text-indigo-600"></i>
        </div>

        <div v-else class="overflow-x-auto bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm max-h-[70vh]">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="sticky top-0 z-20 shadow-sm">
                    <tr class="bg-(--bg-elevated)">
                        <th class="px-2 py-2 text-center text-xs font-bold text-(--text-main) border-r border-b border-(--border-soft) w-10 sticky left-0 bg-(--bg-elevated) z-30" rowspan="2">NO</th>
                        <th class="px-3 py-2 text-left text-xs font-bold text-(--text-main) border-r border-b border-(--border-soft) sticky left-[40px] bg-(--bg-elevated) z-30" rowspan="2">NAMA</th>
                        <th v-for="d in dates" :key="d.date" :colspan="2"
                            :class="['px-1 py-2 text-center border-r border-b border-(--border-soft) text-[10px] font-bold text-(--text-main)', getDayClass(d.day_name)]">
                            {{ new Date(d.date).getDate() }}
                        </th>
                    </tr>
                    <tr class="bg-(--bg-elevated)">
                        <template v-for="d in dates" :key="d.date">
                            <th :class="['px-1 py-1 text-center border-r border-b border-(--border-soft) text-[9px] font-medium text-(--text-muted)', getDayClass(d.day_name)]">
                                {{ getDayAbbrev(d.day_name) }}
                            </th>
                            <th :class="['px-1 py-1 text-center border-r border-b border-(--border-soft) text-[9px] font-bold text-(--text-soft)', getDayClass(d.day_name)]">
                                L
                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="emp in employees" :key="emp.employee_id"
                        class="border-b border-(--border-soft) hover:bg-indigo-50/30 transition">
                        <td class="px-2 py-1.5 text-center text-xs text-(--text-main) border-r border-(--border-soft) sticky left-0 bg-(--bg-card) z-10">
                            {{ emp.no }}
                        </td>
                        <td class="px-3 py-1.5 text-xs text-(--text-main) font-medium border-r border-(--border-soft) sticky left-[40px] bg-(--bg-card) z-10">
                            {{ emp.employee_name }}
                        </td>
                        <template v-for="d in dates" :key="d.date">
                            <td :class="['px-1 py-1.5 text-center border-r border-(--border-soft) text-xs', getDayClass(d.day_name), getStatusClass(emp.days[d.date]?.status)]">
                                {{ emp.days[d.date]?.status || '' }}
                            </td>
                            <td :class="['px-1 py-1.5 text-center border-r border-(--border-soft) text-xs', getDayClass(d.day_name), emp.days[d.date]?.lembur > 0 ? 'text-orange-600 font-medium' : 'text-gray-300']">
                                {{ emp.days[d.date]?.lembur > 0 ? emp.days[d.date].lembur : '-' }}
                            </td>
                        </template>
                    </tr>
                    <tr v-if="employees?.length === 0">
                        <td :colspan="dates.length * 2 + 2" class="px-4 py-12 text-center text-(--text-muted)">
                            <i class="bx bx-data text-4xl mb-3 block text-gray-300"></i>
                            Tidak ada data untuk periode ini
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
