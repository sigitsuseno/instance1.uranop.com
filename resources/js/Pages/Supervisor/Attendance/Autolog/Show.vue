<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useApi } from '../../../../composables/useApi';

const route = useRoute();
const { get } = useApi();

const employee = ref({});
const dailyData = ref([]);
const summary = ref({});
const period = ref({});
const isLoading = ref(true);
const exportScope = ref('single');
const exportDate = ref(new Date().toISOString().split('T')[0]);

// Modal export terpilih
const showEmployeeModal = ref(false);
const modalEmployees = ref([]);
const selectedIds = ref([]);
const isLoadingEmployees = ref(false);
const isExportingSelected = ref(false);
const modalSearch = ref('');

// Groups yang libur/minggu jadwalnya kosong
const blankOnHolidayGroups = ['GRP-ALLIN', 'GRP-GD', 'GRP-SPR', 'GRP-PS1'];
const isBlankGroup = computed(() => {
    const groups = employee.value?.groups || [];
    return groups.some(g => blankOnHolidayGroups.includes(g));
});

const filteredEmployees = computed(() => {
    const q = modalSearch.value.toLowerCase().trim();
    if (!q) return modalEmployees.value;
    return modalEmployees.value.filter(e =>
        (e.employee_name || '').toLowerCase().includes(q) ||
        (e.employee_code || '').toLowerCase().includes(q)
    );
});

const selectedCount = computed(() => selectedIds.value.length);

const isAllSelected = computed(() =>
    filteredEmployees.value.length > 0 &&
    filteredEmployees.value.every(e => selectedIds.value.includes(e.id))
);

function jadwalMasuk(day) {
    if (isBlankGroup.value && (day.is_holiday || day.is_weekend)) return '--:--';
    return day.shift_start || '--:--';
}
function jadwalPulang(day) {
    if (isBlankGroup.value && (day.is_holiday || day.is_weekend)) return '--:--';
    return day.shift_end || '--:--';
}

async function fetchData() {
    isLoading.value = true;
    try {
        const id = route.params.id;
        const queryParams = new URLSearchParams(route.query).toString();
        const response = await get(`/api/v1/supervisor/attendance/absensi/${id}?${queryParams}`);
        
        employee.value = response.employee || {};
        dailyData.value = response.dailyData || [];
        summary.value = response.summary || {};
        period.value = response.period || {};
    } catch (error) {
        console.error('Error fetching autolog details:', error);
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    fetchData();
});

function formatDate(date) {
    if (!date) return '';
    return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function getStatusBadgeClass(status) {
    const badges = {
        present: 'bg-green-100 text-green-800',
        absent: 'bg-red-100 text-red-800',
        leave: 'bg-blue-100 text-blue-800',
        permit: 'bg-purple-100 text-purple-800',
        holiday: 'bg-gray-100 text-gray-800',
        off: 'bg-orange-100 text-orange-800',
        pending: 'bg-yellow-100 text-yellow-800',
    };
    return badges[status] || 'bg-gray-100 text-gray-800';
}

function getStatusLabel(status) {
    const labels = {
        present: 'Hadir',
        absent: 'Absen',
        leave: 'CT',
        permit: 'Izin',
        holiday: 'Libur',
        off: 'Off',
        pending: 'Menunggu',
    };
    return labels[status] || status;
}

function formatOvertime(minutes) {
    if (!minutes || minutes === 0) return '-';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours === 0) return `${mins} mnt`;
    if (mins === 0) return `${hours} jam`;
    return `${hours} jam ${mins} mnt`;
}

async function handlePrint() {
    const url = `/api/v1/supervisor/attendance/absensi/${employee.value.id}/print?start_date=${period.value.start}&end_date=${period.value.end}`;
    try {
        const token = localStorage.getItem('token');
        const headers = { 'Accept': 'application/pdf' };
        if (token) headers['Authorization'] = `Bearer ${token}`;
        
        const response = await fetch(url, { headers });
        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err.message || 'Gagal generate PDF');
        }
        const blob = await response.blob();
        
        // Ambil filename dari Content-Disposition header
        let filename = 'absensi.pdf';
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

async function handleExport() {
    // Mode "Export Semua" → buka modal pilih karyawan
    if (exportScope.value === 'all') {
        openExportModal();
        return;
    }
    const url = `/api/v1/supervisor/attendance/absensi/${employee.value.id}/export?start_date=${period.value.start}&end_date=${period.value.end}`;
    try {
        const token = localStorage.getItem('token');
        const headers = { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' };
        if (token) headers['Authorization'] = `Bearer ${token}`;
        
        const response = await fetch(url, { headers });
        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err.message || 'Download gagal');
        }
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

async function handleExportByDate() {
    const url = `/api/v1/supervisor/attendance/absensi/export-by-date?date=${exportDate.value}`;
    try {
        const token = localStorage.getItem('token');
        const headers = { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' };
        if (token) headers['Authorization'] = `Bearer ${token}`;
        
        const response = await fetch(url, { headers });
        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err.message || 'Download gagal');
        }
        const blob = await response.blob();
        
        let filename = 'absensi.xlsx';
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

async function openExportModal() {
    showEmployeeModal.value = true;
    isLoadingEmployees.value = true;
    try {
        const response = await get(`/api/v1/supervisor/attendance/absensi/employees?start_date=${period.value.start}&end_date=${period.value.end}`);
        modalEmployees.value = response.employees || [];
    } catch (error) {
        console.error('Error fetching employees:', error);
        alert('Gagal memuat daftar karyawan');
    } finally {
        isLoadingEmployees.value = false;
    }
}

function closeExportModal() {
    if (isExportingSelected.value) return;
    showEmployeeModal.value = false;
    modalSearch.value = '';
    selectedIds.value = [];
}

function toggleEmployee(id) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(x => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
}

function toggleAll() {
    if (isAllSelected.value) {
        const filteredIds = new Set(filteredEmployees.value.map(e => e.id));
        selectedIds.value = selectedIds.value.filter(id => !filteredIds.has(id));
    } else {
        const ids = new Set(selectedIds.value);
        filteredEmployees.value.forEach(e => ids.add(e.id));
        selectedIds.value = [...ids];
    }
}

async function confirmExportSelected() {
    if (selectedIds.value.length === 0 || isExportingSelected.value) return;
    isExportingSelected.value = true;
    try {
        const token = localStorage.getItem('token');
        const headers = {
            'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Type': 'application/json',
        };
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const response = await fetch('/api/v1/supervisor/attendance/absensi/export-selected', {
            method: 'POST',
            headers,
            body: JSON.stringify({
                employee_ids: selectedIds.value,
                start_date: period.value.start,
                end_date: period.value.end,
            }),
        });
        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err.message || 'Download gagal');
        }
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

        closeExportModal();
    } catch (error) {
        alert(error.message);
    } finally {
        isExportingSelected.value = false;
    }
}

function formatDecimal(num) {
    if (!num || num === 0) return '0';
    return parseFloat(num).toFixed(2).replace(/\.?0+$/, '');
}

function formatConvertedHours(hours) {
    if (!hours || hours === 0) return '-';
    return `${formatDecimal(hours)} jam`;
}

function getMultiplierDetails(minutes, isFixed = false, isSat = false, isHoliday = false) {
    if (!minutes || minutes === 0) return [];
    const hours = minutes / 60;
    const details = [];

    if (isFixed && isHoliday) {
        const remaining = Math.max(0, hours - 1);
        if (hours > 1) {
            details.push(`(${formatDecimal(hours)} - 1) = ${formatDecimal(remaining)} jam`);
        }
        if (isSat) {
            for (let i = 1; i <= Math.ceil(remaining); i++) {
                const seg = Math.min(1, Math.max(0, remaining - (i - 1)));
                if (i <= 5) {
                    details.push(`Jam ke-${i}: ${formatDecimal(seg)} x 2 = ${formatDecimal(seg * 2)}`);
                } else if (i === 6) {
                    details.push(`Jam ke-${i}: ${formatDecimal(seg)} x 3 = ${formatDecimal(seg * 3)}`);
                } else {
                    details.push(`Jam ke-${i}: ${formatDecimal(seg)} x 4 = ${formatDecimal(seg * 4)}`);
                }
            }
        } else {
            details.push(`${formatDecimal(remaining)} x 2 = ${formatDecimal(remaining * 2)}`);
        }
    } else {
        const firstHour = Math.min(hours, 1);
        details.push(`${formatDecimal(firstHour)} x 1.5 = ${formatDecimal(firstHour * 1.5)}`);

        if (hours > 1) {
            const remainingHours = hours - 1;
            details.push(`${formatDecimal(remainingHours)} x 2.0 = ${formatDecimal(remainingHours * 2)}`);
        }
    }

    return details;
}

// Wrapper baru per konsep: lembur=Mon-Sab, lm=holiday(SHIFT)/holiday+Minggu(non-SHIFT)
function getMultiplierForDay(day) {
    if (!day) return [];
    const isLmDay = day.is_lm_day ?? (day.work_pattern_type === 'SHIFT' ? !!day.is_holiday : (!!day.is_holiday || !!day.is_sun));
    const raw = isLmDay ? (day.lm || day.lembur) : (day.lembur || day.lm);
    if (!raw) return [];
    const hours = raw / 60;
    // LM
    if (isLmDay) {
        if (day.work_pattern_type === 'SHIFT') {
            return getMultiplierDetails(raw, true, !!day.is_sat, true);
        } else {
            // non-SHIFT LM: potong 60 menit istirahat, flat x2
            const effectiveMinutes = Math.max(0, raw - 60);
            const effectiveHours = effectiveMinutes / 60;
            if (effectiveHours <= 0) return [`${formatDecimal(hours)} -1j istirahat = 0 jam`];
            const details = [];
            if (raw > 60) details.push(`(${formatDecimal(hours)} -1j) = ${formatDecimal(effectiveHours)} jam`);
            details.push(`${formatDecimal(effectiveHours)} x 2 = ${formatDecimal(effectiveHours*2)}`);
            return details;
        }
    }
    // Lembur hari kerja
    return getMultiplierDetails(raw, false, !!day.is_sat, false);
}

function getRawForDay(day) {
    if (!day) return 0;
    const isLmDay = day.is_lm_day ?? (day.work_pattern_type === 'SHIFT' ? !!day.is_holiday : (!!day.is_holiday || !!day.is_sun));
    return isLmDay ? (day.lm || day.lembur) : (day.lembur || day.lm);
}

function isLmDayForRow(day) {
    return day.is_lm_day ?? (day.work_pattern_type === 'SHIFT' ? !!day.is_holiday : (!!day.is_holiday || !!day.is_sun));
}
</script>

<template>
    <div class="p-4 sm:p-6 lg:p-8">
        <!-- Breadcrumbs -->
        <nav class="flex mb-6 text-sm">
            <ol class="flex items-center space-x-2 text-(--text-muted)">
                <li>
                    <router-link to="/supervisor/attendance" class="hover:text-indigo-600 transition">Absensi</router-link>
                </li>
                <li><i class="bx bx-chevron-right"></i></li>
                <li class="text-(--text-main) font-medium">Detail Karyawan</li>
            </ol>
        </nav>

        <div v-if="isLoading" class="flex justify-center my-12">
            <i class="bx bx-loader-alt animate-spin text-4xl text-indigo-600"></i>
        </div>

        <template v-else>
            <!-- Header -->
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-2xl p-6 mb-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl font-bold">
                            {{ employee.name ? employee.name.charAt(0) : 'U' }}
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-(--text-main)">{{ employee.name }}</h1>
                            <p class="text-(--text-muted) font-mono">{{ employee.code }}</p>
                            <div class="flex items-center gap-2 mt-1 text-sm text-(--text-soft)">
                                <span class="px-2 py-0.5 bg-(--bg-elevated) rounded-md">{{ employee.department }}</span>
                                <span class="px-2 py-0.5 bg-(--bg-elevated) rounded-md">{{ employee.position }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 flex-1 max-w-2xl">
                        <div class="bg-(--bg-elevated) p-3 rounded-xl text-center border border-(--border-soft)">
                            <p class="text-lg font-bold text-green-600">{{ summary.hadir || 0 }}</p>
                            <p class="text-[10px] text-(--text-muted) uppercase font-medium">Hadir</p>
                        </div>
                        <div class="bg-(--bg-elevated) p-3 rounded-xl text-center border border-(--border-soft)">
                            <p class="text-lg font-bold text-orange-600">{{ (summary.lembur / 60).toFixed(1).replace('.', ',') }} jam</p>
                            <p class="text-[10px] text-(--text-muted) uppercase font-medium">Lembur Mentah</p>
                        </div>
                        <div class="bg-(--bg-elevated) p-3 rounded-xl text-center border border-(--border-soft)">
                            <p class="text-lg font-bold text-indigo-600">{{ (summary.lembur_calc || 0).toFixed(1).replace('.', ',') }} jam</p>
                            <p class="text-[10px] text-(--text-muted) uppercase font-medium">Lembur Terhitung</p>
                        </div>
                        <div class="bg-(--bg-elevated) p-3 rounded-xl text-center border border-(--border-soft)">
                            <p class="text-lg font-bold text-blue-600">{{ summary.cuti || 0 }}</p>
                            <p class="text-[10px] text-(--text-muted) uppercase font-medium">Cuti</p>
                        </div>
                        <div class="bg-(--bg-elevated) p-3 rounded-xl text-center border border-(--border-soft)">
                            <p class="text-lg font-bold text-purple-600">{{ summary.izin || 0 }}</p>
                            <p class="text-[10px] text-(--text-muted) uppercase font-medium">Izin</p>
                        </div>
                        <div class="bg-(--bg-elevated) p-3 rounded-xl text-center border border-(--border-soft)">
                            <p class="text-lg font-bold text-teal-600">{{ summary.sakit || 0 }}</p>
                            <p class="text-[10px] text-(--text-muted) uppercase font-medium">Sakit</p>
                        </div>
                        <div class="bg-(--bg-elevated) p-3 rounded-xl text-center border border-(--border-soft)">
                            <p class="text-lg font-bold text-red-600">{{ summary.absen || 0 }}</p>
                            <p class="text-[10px] text-(--text-muted) uppercase font-medium">Absen</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Period Info -->
            <div class="flex items-center justify-between mb-4 px-2">
                <h2 class="text-lg font-bold text-(--text-main)">Rincian Harian</h2>
                <div class="text-sm text-(--text-muted) italic">
                    <i class="bx bx-calendar-check mr-1 text-indigo-500"></i>
                    Periode: {{ formatDate(period.start) }} - {{ formatDate(period.end) }}
                </div>
            </div>

            <!-- Daily Table -->
            <div class="bg-(--bg-card) border border-(--border-soft) rounded-2xl shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-(--bg-elevated) border-b border-(--border-soft)">
                            <th class="px-6 py-4 text-left font-semibold text-(--text-muted)">Hari / Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jadwal Masuk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jadwal Pulang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actual In</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actual Out</th>
                            <th class="px-6 py-4 text-center font-semibold text-(--text-muted)">Lembur</th>
                            <th class="px-6 py-4 text-center font-semibold text-(--text-muted)">Pengali</th>
                            <th class="px-6 py-4 text-center font-semibold text-(--text-muted)">Total Lembur</th>
                            <th class="px-6 py-4 text-center font-semibold text-(--text-muted)">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--border-soft)">
                        <tr v-for="day in dailyData" :key="day.date" 
                            class="hover:bg-indigo-50/20 transition group"
                            :class="{'bg-red-50/20': day.is_weekend}">
                            <td class="px-6 py-4">
                                <div class="font-medium text-(--text-main)">{{ day.date_display }}</div>
                                <div class="text-xs" :class="day.is_weekend ? 'text-red-500 font-bold' : 'text-(--text-muted)'">{{ day.day }}</div>
                            </td>
                            <td class="px-6 py-4 text-center font-mono text-gray-500">
                                {{ jadwalMasuk(day) }}
                            </td>
                            <td class="px-6 py-4 text-center font-mono text-gray-500">
                                {{ jadwalPulang(day) }}
                            </td>
                            <td class="px-6 py-4 text-center font-mono text-indigo-600 font-bold">
                                {{ day.check_in || '--:--' }}
                            </td>
                            <td class="px-6 py-4 text-center font-mono text-indigo-600 font-bold">
                                {{ day.check_out || '--:--' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <template v-if="isLmDayForRow(day)">
                                    <span v-if="day.lm > 0" class="text-purple-600 font-medium">
                                        <i class="bx bx-time mr-1"></i>{{ formatOvertime(day.lm) }} <span class="text-[10px] bg-purple-100 px-1 rounded">LM</span>
                                    </span>
                                    <span v-else-if="day.lembur > 0" class="text-orange-600 font-medium">
                                        <i class="bx bx-time mr-1"></i>{{ formatOvertime(day.lembur) }} <span class="text-[10px] bg-orange-100 px-1 rounded">LM*</span>
                                    </span>
                                    <span v-else class="text-gray-300">-</span>
                                </template>
                                <template v-else>
                                    <span v-if="day.lembur > 0" class="text-orange-600 font-medium">
                                        <i class="bx bx-time mr-1"></i>{{ formatOvertime(day.lembur) }}
                                    </span>
                                    <span v-else-if="day.lm > 0" class="text-purple-600 font-medium">
                                        <i class="bx bx-time mr-1"></i>{{ formatOvertime(day.lm) }} <span class="text-[10px] bg-purple-100 px-1 rounded">LM</span>
                                    </span>
                                    <span v-else class="text-gray-300">-</span>
                                </template>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div v-if="getRawForDay(day) > 0" class="text-[10px] leading-tight text-(--text-soft) font-mono">
                                    <p v-for="(detail, index) in getMultiplierForDay(day)" :key="index">
                                        {{ detail }}
                                    </p>
                                    <p v-if="isLmDayForRow(day)" class="text-[9px] text-purple-500 mt-1">LM: {{ day.work_pattern_type==='SHIFT' ? 'holiday' : 'holiday/Minggu' }}</p>
                                    <p v-else class="text-[9px] text-orange-500 mt-1">Lembur: Senin-Sabtu{{ day.work_pattern_type==='SHIFT' ? '+Minggu' : '' }}</p>
                                </div>
                                <span v-else class="text-gray-300">-</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="day.lembur_total_calc > 0" class="text-indigo-600 font-bold">
                                    {{ formatConvertedHours(day.lembur_total_calc) }}
                                </span>
                                <span v-else-if="day.lm_calc > 0 || day.lembur_calc > 0" class="text-indigo-600 font-bold">
                                    {{ formatConvertedHours((day.lm_calc||0)+(day.lembur_calc||0)) }}
                                </span>
                                <span v-else class="text-gray-300">-</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-block px-3 py-1 text-xs rounded-full font-medium" :class="getStatusBadgeClass(day.status)">
                                    {{ (day.status === 'leave' && day.leave_type_code) ? day.leave_type_code : getStatusLabel(day.status) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-8 flex justify-end gap-3 no-print">
                <!-- Export Per Tanggal (seluruh karyawan) -->
                <div class="flex items-center gap-2">
                    <input 
                        type="date" 
                        v-model="exportDate" 
                        class="px-3 py-2 border border-(--border-soft) rounded-lg text-sm bg-(--bg-card) text-(--text-main)"
                    />
                    <button @click="handleExportByDate" class="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition flex items-center gap-1 text-sm" title="Export kehadiran seluruh karyawan untuk tanggal yang dipilih">
                        <i class="bx bx-calendar-export text-base"></i>
                        <span>Export</span>
                    </button>
                </div>

                <!-- Export dengan pilihan scope -->
                <div class="relative flex rounded-xl overflow-hidden">
                    <button @click="handleExport" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white transition flex items-center gap-2" :title="exportScope === 'single' ? 'Export detail karyawan ini' : 'Export semua karyawan'">
                        <i class="bx bx-spreadsheet text-lg"></i>
                        <span class="text-sm font-medium">{{ exportScope === 'single' ? 'Export Karyawan Ini' : 'Export Semua' }}</span>
                    </button>
                    <div class="relative">
                        <button @click="exportScope = (exportScope === 'single' ? 'all' : 'single')" class="px-2 py-2 bg-teal-700 hover:bg-teal-800 text-white border-l border-teal-500 transition h-full" title="Ganti scope export">
                            <i class="bx bx-chevron-down text-sm"></i>
                        </button>
                    </div>
                </div>
                <button @click="handlePrint" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-xl transition flex items-center gap-2">
                    <i class="bx bx-printer text-lg"></i>
                    Cetak Laporan
                </button>
                <router-link to="/supervisor/attendance" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition flex items-center gap-2">
                    <i class="bx bx-arrow-back text-lg"></i>
                    Kembali ke Daftar
                </router-link>
            </div>
        </template>

        <!-- Modal: Pilih Karyawan untuk Export -->
        <div v-if="showEmployeeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="closeExportModal"></div>
            <div class="relative bg-(--bg-card) border border-(--border-soft) rounded-2xl shadow-xl w-full max-w-2xl flex flex-col max-h-[80vh]">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-(--border-soft) shrink-0">
                    <h3 class="text-lg font-bold text-(--text-main)">
                        <i class="bx bx-check-square mr-2 text-teal-600"></i>Pilih Karyawan
                    </h3>
                    <button @click="closeExportModal" :disabled="isExportingSelected" class="text-(--text-muted) hover:text-(--text-main) transition disabled:opacity-50">
                        <i class="bx bx-x text-2xl"></i>
                    </button>
                </div>

                <!-- Search + Select All -->
                <div class="px-6 py-3 border-b border-(--border-soft) flex flex-wrap items-center gap-3 shrink-0">
                    <div class="relative flex-1 min-w-[200px]">
                        <i class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft)"></i>
                        <input v-model="modalSearch" type="text" placeholder="Cari karyawan..." class="pl-9 pr-3 py-2 w-full border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-teal-500" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-(--text-muted) cursor-pointer select-none">
                        <input type="checkbox" :checked="isAllSelected" @change="toggleAll" class="accent-teal-600 w-4 h-4" />
                        Pilih Semua
                    </label>
                </div>

                <!-- List -->
                <div v-if="isLoadingEmployees" class="flex justify-center py-12">
                    <i class="bx bx-loader-alt animate-spin text-3xl text-teal-600"></i>
                </div>
                <div v-else class="overflow-y-auto flex-1 px-2 py-2">
                    <p v-if="filteredEmployees.length === 0" class="text-center text-(--text-muted) py-10">
                        <i class="bx bx-data text-4xl mb-3 block text-gray-300"></i>
                        Tidak ada karyawan
                    </p>
                    <label v-for="emp in filteredEmployees" :key="emp.id"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-indigo-50/30 cursor-pointer transition">
                        <input type="checkbox" :checked="selectedIds.includes(emp.id)" @change="toggleEmployee(emp.id)" class="accent-teal-600 w-4 h-4 shrink-0" />
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm text-(--text-main) truncate">{{ emp.employee_name }}</div>
                            <div class="text-xs text-(--text-muted) font-mono">{{ emp.employee_code }}</div>
                        </div>
                        <div class="text-xs text-(--text-soft) text-right shrink-0">
                            <div>{{ emp.department }}</div>
                            <div>{{ emp.position }}</div>
                        </div>
                    </label>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between px-6 py-4 border-t border-(--border-soft) shrink-0">
                    <span class="text-sm text-(--text-muted)">{{ selectedCount }} karyawan terpilih</span>
                    <div class="flex gap-2">
                        <button @click="closeExportModal" :disabled="isExportingSelected"
                            class="px-4 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) hover:bg-gray-50 transition text-sm disabled:opacity-50">
                            Batal
                        </button>
                        <button @click="confirmExportSelected" :disabled="selectedCount === 0 || isExportingSelected"
                            class="px-5 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition text-sm font-medium inline-flex items-center gap-2">
                            <i v-if="isExportingSelected" class="bx bx-loader-alt animate-spin"></i>
                            <i v-else class="bx bx-spreadsheet"></i>
                            {{ isExportingSelected ? 'Exporting...' : `Export (${selectedCount})` }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .no-print {
        display: none;
    }
}
</style>
