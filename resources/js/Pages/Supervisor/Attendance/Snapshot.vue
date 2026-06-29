<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useApi } from '../../../composables/useApi';

const router = useRouter();
const route = useRoute();
const { get, post } = useApi();

const employees = ref([]);
const stats = ref({});
const filters = ref({});
const payrollPeriods = ref([]);
const selectedPeriod = ref('');
const startDate = ref('');
const endDate = ref('');
const pagination = ref(null);
const searchForm = ref({
    search: '',
});

const selectedEmployees = ref([]);
const isLoading = ref(false);
const showSuccess = ref(false);
const successMessage = ref('');

async function fetchData(params = {}) {
    isLoading.value = true;
    try {
        const queryParams = new URLSearchParams();
        for (const [key, value] of Object.entries(params)) {
            if (value) queryParams.append(key, value);
        }
        
        const response = await get(`/api/v1/supervisor/attendance/snapshoot?${queryParams.toString()}`);
        
        employees.value = response.employees || [];
        stats.value = response.stats || {};
        filters.value = response.filters || {};
        payrollPeriods.value = response.payrollPeriods || [];
        selectedPeriod.value = response.selectedPeriodId || '';
        pagination.value = response.pagination || null;
        
        startDate.value = filters.value.start_date || '';
        endDate.value = filters.value.end_date || '';
        searchForm.value.search = filters.value.search || '';
    } catch (error) {
        console.error('Error fetching snapshot data:', error);
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    fetchData(route.query);
});

function applyPeriod() {
    if (!selectedPeriod.value) return;
    const period = payrollPeriods.value.find(p => p.id === selectedPeriod.value);
    if (!period) return;
    
    startDate.value = period.start_date;
    endDate.value = period.end_date;
    
    submitFilter();
}

function submitFilter() {
    const params = {
        start_date: startDate.value,
        end_date: endDate.value,
        search: searchForm.value.search,
    };
    
    router.push({ path: route.path, query: params });
    fetchData(params);
}

function resetFilter() {
    searchForm.value.search = '';
    submitFilter();
}

function formatDateRange() {
    if (!startDate.value || !endDate.value) return '';
    const start = new Date(startDate.value);
    const end = new Date(endDate.value);
    const formatDate = (date) => date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    return `${formatDate(start)} - ${formatDate(end)}`;
}

async function saveAllSnapshot() {
    if (!startDate.value || !endDate.value) {
        alert('Silakan pilih periode terlebih dahulu');
        return;
    }
    if (employees.value.length === 0) {
        alert('Tidak ada data karyawan');
        return;
    }
    
    isLoading.value = true;
    try {
        const response = await post('/api/v1/supervisor/attendance/snapshoot/bulk', {
            save_all: true,
            period_start: startDate.value,
            period_end: endDate.value,
        });
        
        if (response.success) {
            showSuccessMessage(response.message);
            fetchData(route.query); // reload data
        } else {
            alert(response.message || 'Gagal menyimpan snapshot');
        }
    } catch (error) {
        console.error('Error saving all snapshot:', error);
        alert('Terjadi kesalahan saat menyimpan snapshot: ' + error.message);
    } finally {
        isLoading.value = false;
    }
}

function showSuccessMessage(message) {
    successMessage.value = message;
    showSuccess.value = true;
    setTimeout(() => {
        showSuccess.value = false;
    }, 3000);
}

function formatMinutes(minutes) {
    if (!minutes) return '0m';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0) {
        return `${hours}j ${mins}m`;
    }
    return `${mins}m`;
}

function goToPage(page) {
    const params = {
        ...route.query,
        page: page,
    };
    router.push({ path: route.path, query: params });
    fetchData(params);
}

function openPrintPage() {
    let url = `/api/v1/supervisor/attendance/snapshoot/print?start_date=${startDate.value}&end_date=${endDate.value}`;
    if (searchForm.value.search) url += `&search=${encodeURIComponent(searchForm.value.search)}`;
    window.open(url, '_blank');
}
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8">
        <!-- Success Toast -->
        <div v-if="showSuccess" class="fixed top-4 right-4 z-50 bg-green-600 text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2">
            <i class="bx bx-check-circle"></i>
            <span>{{ successMessage }}</span>
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-(--text-main)">Attendance Snapshot</h1>
                <p class="text-sm text-(--text-muted) mt-1">
                    Ambil data dari attendance_autolog ke attendance_snapshot
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="openPrintPage"
                    class="px-4 py-2 bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) rounded-lg hover:bg-(--border-soft) transition-colors flex items-center gap-2"
                >
                    <i class="bx bx-printer"></i>
                    Print
                </button>
                <button
                    @click="saveAllSnapshot"
                    :disabled="isLoading || employees.length === 0"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 flex items-center gap-2"
                >
                    <i class="bx bx-camera"></i>
                    Simpan Snapshot
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
                <div class="text-sm text-(--text-muted)">Total Karyawan</div>
                <div class="text-2xl font-bold text-(--text-main)">{{ stats?.total_employees || 0 }}</div>
            </div>
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
                <div class="text-sm text-(--text-muted)">Hadir</div>
                <div class="text-2xl font-bold text-green-600">{{ stats?.present || 0 }}</div>
            </div>
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
                <div class="text-sm text-(--text-muted)">Absent</div>
                <div class="text-2xl font-bold text-red-600">{{ stats?.absent || 0 }}</div>
            </div>
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
                <div class="text-sm text-(--text-muted)">Cuti</div>
                <div class="text-2xl font-bold text-blue-600">{{ stats?.leave || 0 }}</div>
            </div>
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
                <div class="text-sm text-(--text-muted)">Terlambat</div>
                <div class="text-2xl font-bold text-orange-600">{{ stats?.late_days || 0 }}</div>
            </div>
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
                <div class="text-sm text-(--text-muted)">Lembur</div>
                <div class="text-2xl font-bold text-purple-600">{{ stats?.total_overtime_hours || 0 }}j</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4 mb-6">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm text-(--text-muted) mb-1">Periode Payroll</label>
                    <select
                        v-model="selectedPeriod"
                        @change="applyPeriod"
                        class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-lg text-(--text-main) focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Pilih Periode</option>
                        <option v-for="period in payrollPeriods" :key="period.id" :value="period.id">
                            {{ period.name }} ({{ period.start_date }} - {{ period.end_date }})
                        </option>
                    </select>
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-sm text-(--text-muted) mb-1">Tanggal Mulai</label>
                    <input
                        v-model="startDate"
                        type="date"
                        class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-lg text-(--text-main) focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-sm text-(--text-muted) mb-1">Tanggal Akhir</label>
                    <input
                        v-model="endDate"
                        type="date"
                        class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-lg text-(--text-main) focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-sm text-(--text-muted) mb-1">Cari</label>
                    <input
                        v-model="searchForm.search"
                        type="text"
                        placeholder="Nama atau kode karyawan..."
                        @keyup.enter="submitFilter"
                        class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-lg text-(--text-main) focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex gap-2">
                    <button
                        @click="submitFilter"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <i class="bx bx-filter"></i>
                    </button>
                    <button
                        @click="resetFilter"
                        class="px-4 py-2 bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) rounded-lg hover:bg-(--border-soft) transition-colors"
                    >
                        <i class="bx bx-reset"></i>
                    </button>
                </div>
            </div>
            <div v-if="startDate && endDate" class="mt-3 text-sm text-(--text-muted)">
                Periode: {{ formatDateRange() }}
            </div>
        </div>

        <div v-if="isLoading" class="flex justify-center my-12">
            <i class="bx bx-loader-alt animate-spin text-4xl text-indigo-600"></i>
        </div>

        <!-- Table -->
        <div v-else class="bg-(--bg-card) border border-(--border-soft) rounded-xl overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-(--bg-elevated) border-b border-(--border-soft)">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-(--text-muted)">Karyawan</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-(--text-muted)">Hadir</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-(--text-muted)">Absent</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-(--text-muted)">Cuti</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-(--text-muted)">Izin</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-(--text-muted)">Sakit</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-(--text-muted)">Terlambat</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-(--text-muted)">Lembur (Aktual)</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-(--text-muted)">Lembur (Hitung)</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-(--text-muted)">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--border-soft)">
                        <tr v-for="employee in employees" :key="employee.id" class="hover:bg-(--bg-elevated)">
                            <td class="px-4 py-3">
                                <div class="font-medium text-(--text-main)">{{ employee.employee_name }}</div>
                                <div class="text-sm text-(--text-muted)">{{ employee.employment_status === 'contract' ? 'PKWT' : 'PKWTT' }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-green-600 font-medium">{{ employee.present_days }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-red-600 font-medium">{{ employee.absent_days }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-blue-600 font-medium">{{ employee.leave_days }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-yellow-600 font-medium">{{ employee.permit_days }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-purple-600 font-medium">{{ employee.sick_days }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-orange-600 font-medium">{{ employee.late_days }}</span>
                                <div class="text-xs text-(--text-soft)">{{ formatMinutes(employee.total_late_minutes) }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-purple-600 font-medium">{{ employee.overtime_hours || 0 }}j</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-indigo-600 font-bold">{{ employee.calculated_overtime || 0 }}j</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="employee.has_snapshot" class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-100 text-green-700 text-sm rounded-lg">
                                    <i class="bx bx-check"></i> Tersimpan
                                </span>
                            </td>
                        </tr>
                        <tr v-if="employees.length === 0">
                            <td colspan="10" class="px-4 py-8 text-center text-(--text-muted)">
                                Tidak ada data karyawan
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="flex justify-center items-center gap-2 mb-6">
            <button
                @click="goToPage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="px-3 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded-lg text-(--text-main) disabled:opacity-50 disabled:cursor-not-allowed hover:bg-(--border-soft)"
            >
                <i class="bx bx-chevron-left"></i>
            </button>
            <span class="text-sm text-(--text-muted)">
                Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
            </span>
            <button
                @click="goToPage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-3 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded-lg text-(--text-main) disabled:opacity-50 disabled:cursor-not-allowed hover:bg-(--border-soft)"
            >
                <i class="bx bx-chevron-right"></i>
            </button>
        </div>

        <!-- Resume -->
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
            <h3 class="text-lg font-semibold text-(--text-main) mb-4">Resume</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-(--bg-elevated) rounded-lg p-4">
                    <div class="text-sm text-(--text-muted)">Total Aktual Lembur</div>
                    <div class="text-xl font-bold text-purple-600">{{ stats?.total_actual_overtime || 0 }} jam</div>
                </div>
                <div class="bg-(--bg-elevated) rounded-lg p-4">
                    <div class="text-sm text-(--text-muted)">Total Lembur (Setelah Perkalian)</div>
                    <div class="text-xl font-bold text-indigo-600">{{ stats?.total_calculated_overtime || 0 }} jam</div>
                </div>
            </div>
        </div>
    </div>
</template>
