<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useApi } from '../../../composables/useApi'

const router = useRouter()
const route = useRoute()
const { get, post } = useApi()

const employees = ref([])
const stats = ref({})
const period = ref({})
const filters = ref({})
const departments = ref([])
const workPatterns = ref([])
const payrollPeriods = ref([])
const pagination = ref({})
const isDataExists = ref(true)

const isLoading = ref(true)
const isAdjusting = ref(false)

const selectedPeriod = ref('')
const startDate = ref('')
const endDate = ref('')

const searchForm = ref({
    search: '',
    department_id: '',
    work_pattern_id: '',
})

async function fetchData(params = {}) {
    isLoading.value = true
    try {
        const queryParams = new URLSearchParams()
        for (const [key, value] of Object.entries(params)) {
            if (value) queryParams.append(key, value)
        }
        
        const response = await get(`/api/v1/supervisor/attendance/absensi?${queryParams.toString()}`)
        
        employees.value = response.employees || []
        stats.value = response.stats || {}
        period.value = response.period || {}
        filters.value = response.filters || {}
        departments.value = response.departments || []
        workPatterns.value = response.workPatterns || []
        payrollPeriods.value = response.payrollPeriods || []
        pagination.value = response.pagination || {}
        isDataExists.value = response.isDataExists !== false
        
        selectedPeriod.value = period.value.period_id || ''
        startDate.value = period.value.start || ''
        endDate.value = period.value.end || ''
        
        searchForm.value.search = filters.value.search || ''
        searchForm.value.department_id = filters.value.department_id || ''
        searchForm.value.work_pattern_id = filters.value.work_pattern_id || ''

    } catch (error) {
        console.error('Error fetching data:', error)
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchData(route.query)
})

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
    }
    
    router.push({ path: route.path, query: params })
    fetchData(params)
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
    const formatDate = (date) => date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    return `${formatDate(start)} - ${formatDate(end)}`;
}

function openPrintPage() {
    let url = `/api/v1/supervisor/attendance/absensi/print?start_date=${startDate.value}&end_date=${endDate.value}`;
    if (searchForm.value.search) url += `&search=${encodeURIComponent(searchForm.value.search)}`;
    if (searchForm.value.department_id) url += `&department_id=${searchForm.value.department_id}`;
    if (searchForm.value.work_pattern_id) url += `&work_pattern_id=${searchForm.value.work_pattern_id}`;
    openPrintWindow(url);
}

async function fetchWithAuth(url, acceptHeader = 'application/json') {
    const token = localStorage.getItem('token');
    const headers = { 'Accept': acceptHeader };
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const response = await fetch(url, { headers });
    if (!response.ok) {
        const err = await response.json().catch(() => ({}));
        throw new Error(err.message || 'Request gagal');
    }
    return response;
}

async function openPrintWindow(url) {
    try {
        const response = await fetchWithAuth(url, 'text/html');
        const html = await response.text();
        const printWindow = window.open('', '_blank');
        printWindow.document.write(html);
        printWindow.document.close();
    } catch (error) {
        alert(error.message);
    }
}

async function downloadExcel(url) {
    try {
        const response = await fetchWithAuth(url, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        const blob = await response.blob();
        
        // Ambil filename dari Content-Disposition header
        let filename = 'export.xlsx';
        const disposition = response.headers.get('Content-Disposition');
        if (disposition) {
            const match = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/);
            if (match) filename = decodeURIComponent(match[1]);
        }
        
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(downloadUrl);
    } catch (error) {
        alert(error.message);
    }
}

function openExportPage() {
    let url = `/api/v1/supervisor/attendance/absensi/export?start_date=${startDate.value}&end_date=${endDate.value}`;
    if (searchForm.value.search) url += `&search=${encodeURIComponent(searchForm.value.search)}`;
    if (searchForm.value.department_id) url += `&department_id=${searchForm.value.department_id}`;
    if (searchForm.value.work_pattern_id) url += `&work_pattern_id=${searchForm.value.work_pattern_id}`;
    downloadExcel(url);
}

async function runAdjustment() {
    if (!startDate.value || !endDate.value) {
        alert('Pilih periode terlebih dahulu');
        return;
    }
    
    if (!confirm('Apakah Anda yakin ingin menjalankan adjustment? Data leave dan overtime akan diperbarui.')) {
        return;
    }
    
    isAdjusting.value = true;
    try {
        await post('/api/v1/supervisor/attendance/absensi/adjustment', {
            start_date: startDate.value,
            end_date: endDate.value,
        });
        fetchData(route.query);
    } catch (error) {
        alert(error.message || 'Terjadi kesalahan saat adjustment');
    } finally {
        isAdjusting.value = false;
    }
}

function goToPage(urlStr) {
    if (!urlStr) return;
    try {
        const url = new URL(urlStr);
        const params = Object.fromEntries(url.searchParams.entries());
        fetchData(params);
        router.push({ path: route.path, query: params });
    } catch (e) {
        const parts = urlStr.split('?');
        if (parts.length > 1) {
            const params = new URLSearchParams(parts[1]);
            const p = Object.fromEntries(params.entries());
            fetchData(p);
            router.push({ path: route.path, query: p });
        }
    }
}

const hasPrevPage = () => !!pagination.value.links?.prev
const hasNextPage = () => !!pagination.value.links?.next
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8 h-full flex flex-col">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-(--text-main)">Absensi</h1>
                <p class="text-sm text-(--text-muted) mt-1">
                    <i class="bx bx-calendar-alt mr-1"></i>
                    Periode: {{ formatDateRange() }}
                </p>
                <p class="text-xs text-(--text-soft) mt-1">
                    Data absensi 
                </p>
            </div>
            
            <div class="flex gap-2">
                <button
                    @click="runAdjustment"
                    class="flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition disabled:opacity-50"
                    :disabled="!startDate || !endDate || isAdjusting"
                >
                    <i class="bx bx-sync text-lg" :class="{ 'animate-spin': isAdjusting }"></i>
                    {{ isAdjusting ? 'Processing...' : 'Perhitungan Lembur' }}
                </button>
                <button 
                    @click="openExportPage"
                    class="flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg transition disabled:opacity-50"
                    :disabled="!startDate || !endDate"
                >
                    <i class="bx bx-spreadsheet text-lg"></i>
                    Excel
                </button>
                <button 
                    @click="openPrintPage"
                    class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition disabled:opacity-50"
                    :disabled="!startDate || !endDate"
                >
                    <i class="bx bx-printer text-lg"></i>
                    Print
                </button>
            </div>
        </div>
        
        <div v-if="isLoading" class="flex justify-center my-12">
            <i class="bx bx-loader-alt animate-spin text-4xl text-indigo-600"></i>
        </div>

        <template v-else>
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6 shrink-0">
                <div class="bg-(--bg-card) border border-(--border-soft) rounded-lg p-3 text-center transition hover:shadow-md">
                    <p class="text-2xl font-bold text-(--text-main)">{{ stats.total_employees || 0 }}</p>
                    <p class="text-xs text-(--text-muted)">Karyawan</p>
                </div>
                <div class="bg-(--bg-card) border border-(--border-soft) rounded-lg p-3 text-center transition hover:shadow-md border-l-4 border-l-green-500">
                    <p class="text-2xl font-bold text-green-600">{{ stats.present || 0 }}</p>
                    <p class="text-xs text-(--text-muted)">Hadir</p>
                </div>
                <div class="bg-(--bg-card) border border-(--border-soft) rounded-lg p-3 text-center transition hover:shadow-md border-l-4 border-l-orange-500">
                    <p class="text-2xl font-bold text-orange-600">{{ stats.total_overtime || 0 }}</p>
                    <p class="text-xs text-(--text-muted)">Lembur (jam)</p>
                </div>
                <div class="bg-(--bg-card) border border-(--border-soft) rounded-lg p-3 text-center transition hover:shadow-md border-l-4 border-l-blue-500">
                    <p class="text-2xl font-bold text-blue-600">{{ stats.leave || 0 }}</p>
                    <p class="text-xs text-(--text-muted)">Cuti</p>
                </div>
                <div class="bg-(--bg-card) border border-(--border-soft) rounded-lg p-3 text-center transition hover:shadow-md border-l-4 border-l-purple-500">
                    <p class="text-2xl font-bold text-purple-600">{{ stats.permit || 0 }}</p>
                    <p class="text-xs text-(--text-muted)">Izin</p>
                </div>
                <div class="bg-(--bg-card) border border-(--border-soft) rounded-lg p-3 text-center transition hover:shadow-md border-l-4 border-l-teal-500">
                    <p class="text-2xl font-bold text-teal-600">{{ stats.sakit || 0 }}</p>
                    <p class="text-xs text-(--text-muted)">Sakit</p>
                </div>
                <div class="bg-(--bg-card) border border-(--border-soft) rounded-lg p-3 text-center transition hover:shadow-md border-l-4 border-l-red-500">
                    <p class="text-2xl font-bold text-red-600">{{ stats.absent || 0 }}</p>
                    <p class="text-xs text-(--text-muted)">Absen</p>
                </div>
            </div>
            
            <!-- Period Selection -->
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6 shrink-0">
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
            
            <!-- Filter Bar -->
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6 shrink-0">
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
                
                <!-- Info baris -->
                <div class="mt-3 text-xs text-(--text-muted)">
                    Menampilkan {{ pagination.from || 0 }}-{{ pagination.to || 0 }} dari {{ pagination.total || 0 }} karyawan
                </div>
            </div>
            
            <!-- Employees Table — scrollable -->
            <div class="flex-1 min-h-0 overflow-hidden bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm flex flex-col">
                <div class="overflow-auto flex-1">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="border-b border-(--border-soft) bg-(--bg-elevated)">
                                <th class="px-4 py-3 text-left text-xs font-medium text-(--text-muted) uppercase tracking-wider">Karyawan</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase tracking-wider">Hadir</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase tracking-wider">Lembur</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase tracking-wider">Cuti</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase tracking-wider">Izin</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase tracking-wider">Sakit</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase tracking-wider">Absen</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-soft)">
                            <tr v-for="emp in employees" :key="emp.id" class="hover:bg-indigo-50/30 transition group">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-(--text-main) group-hover:text-indigo-600">{{ emp.employee_name }}</div>
                                    <div class="text-xs text-(--text-muted)">{{ emp.employee_code }}</div>
                                    <div class="text-xs text-(--text-soft)">{{ emp.department }} | {{ emp.position }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded-lg bg-green-50 text-green-700 font-bold">{{ emp.hadir }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-orange-600 font-medium">
                                    <i class="bx bx-time-five mr-1"></i>{{ emp.lembur }} jam
                                </td>
                                <td class="px-4 py-3 text-center text-blue-600">{{ emp.cuti }}</td>
                                <td class="px-4 py-3 text-center text-purple-600">{{ emp.izin }}</td>
                                <td class="px-4 py-3 text-center text-teal-600">{{ emp.sakit }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="emp.absen > 0 ? 'text-red-600 font-bold' : 'text-gray-400'">{{ emp.absen }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <router-link :to="`/supervisor/attendance/autolog/${emp.id}?start_date=${startDate}&end_date=${endDate}`" 
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white rounded-lg text-xs font-medium transition shadow-sm">
                                        <i class="bx bx-show"></i> Detail Log
                                    </router-link>
                                </td>
                            </tr>
                            <tr v-if="employees?.length === 0">
                                <td colspan="8" class="px-4 py-12 text-center text-(--text-muted)">
                                    <i class="bx bx-data text-4xl mb-3 block text-gray-300"></i>
                                    Tidak ada data auditor log untuk periode ini
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination — fixed at bottom -->
                <div v-if="hasPrevPage() || hasNextPage()" class="flex justify-between items-center px-4 py-3 border-t border-(--border-soft) bg-(--bg-elevated) shrink-0">
                    <button 
                        v-if="hasPrevPage()" 
                        @click="goToPage(pagination.links.prev)" 
                        class="px-4 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) hover:bg-indigo-50 hover:text-indigo-600 inline-flex items-center gap-1 transition cursor-pointer">
                        <i class="bx bx-chevron-left text-lg"></i> Sebelumnya
                    </button>
                    <span v-else></span>
                    <button 
                        v-if="hasNextPage()" 
                        @click="goToPage(pagination.links.next)" 
                        class="px-4 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) hover:bg-indigo-50 hover:text-indigo-600 inline-flex items-center gap-1 transition cursor-pointer">
                        Selanjutnya <i class="bx bx-chevron-right text-lg"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
