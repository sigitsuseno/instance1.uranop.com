<script setup>
import { ref, onMounted } from 'vue';
import { useApi } from '../../../../composables/useApi';
import TabRekapLembur from './TabRekapLembur.vue';
import TabPerhitungan from './TabPerhitungan.vue';
import TabResume from './TabResume.vue';
import BaseButton from '../../../../Components/BaseButton.vue';
import SelectInput from '../../../../Components/SelectInput.vue';
import BaseCard from '../../../../Components/BaseCard.vue';

const { get } = useApi();

const employees = ref([]);
const resumeData = ref([]);
const dates = ref([]);
const period = ref({ start: '', end: '', period_id: '' });
const isLoading = ref(false);

const activeTab = ref('rekap-lembur');
const selectedPeriod = ref('');
const payrollPeriods = ref([]);
const searchForm = ref({ search: '' });

// Group checkbox filter — mirip Laporan Lembur
const selectedGroups = ref([]);
const availableGroups = ref([]);

function setTab(tab) {
    activeTab.value = tab;
}

const fetchGroups = async () => {
    try {
        const res = await get('/api/v1/settings/employee-data/groups');
        const allGroups = res.data || [];
        // Filter hanya "Imported Shift/Group"
        availableGroups.value = allGroups
            .filter(g => g.group_label === 'Imported Shift/Group')
            .map(g => ({ code: g.code, name: g.name }));

        // Default: hanya JKT, ALLIN, GD
        const defaultGroups = ['GRP-JKT', 'GRP-ALLIN', 'GRP-GD'];
        selectedGroups.value = availableGroups.value
            .filter(g => defaultGroups.includes(g.code))
            .map(g => g.code);
    } catch (e) {
        console.error('Gagal fetch groups:', e);
    }
};

const fetchPeriods = async () => {
    try {
        const response = await get('/api/v1/payroll/periods');
        if (response && response.data) {
            payrollPeriods.value = response.data.map(p => ({
                id: p.id,
                name: p.name,
                start_date: p.start_date,
                end_date: p.end_date
            }));
            if (payrollPeriods.value.length > 0) {
                selectedPeriod.value = payrollPeriods.value[0].id;
                fetchData();
            }
        }
    } catch (e) {
        console.error('Failed to fetch periods', e);
    }
};

const fetchData = async () => {
    if (!selectedPeriod.value || !selectedGroups.value.length) return;
    isLoading.value = true;
    try {
        const searchParam = searchForm.value.search ? `&search=${encodeURIComponent(searchForm.value.search)}` : '';
        const groupsParam = selectedGroups.value.map(g => `groups[]=${encodeURIComponent(g)}`).join('&');
        const queryString = `period_id=${selectedPeriod.value}${searchParam}&${groupsParam}`;
        const response = await get(`/api/v1/reports/uang-makan?${queryString}`);

        if (response.success) {
            employees.value = response.data;
            resumeData.value = response.resumeData;
            dates.value = response.dates;
            period.value = response.period;
        }
    } catch (error) {
        console.error('Error fetching Uang Makan data:', error);
    } finally {
        isLoading.value = false;
    }
};

function buildQueryString() {
    const searchParam = searchForm.value.search ? `&search=${encodeURIComponent(searchForm.value.search)}` : '';
    const groupsParam = selectedGroups.value.map(g => `groups[]=${encodeURIComponent(g)}`).join('&');
    return `period_id=${selectedPeriod.value}${searchParam}&${groupsParam}`;
}

async function exportExcel() {
    if (!selectedPeriod.value) return;
    try {
        const token = localStorage.getItem('token');
        const url = `/api/v1/reports/uang-makan/export?${buildQueryString()}`;
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (!response.ok) throw new Error('Export failed');
        const blob = await response.blob();
        const downloadUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.setAttribute('download', `Uang_Makan_${period.value.start}_${period.value.end}.xlsx`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(downloadUrl);
    } catch (err) {
        console.error('Export error:', err);
    }
}

async function openPrint() {
    if (!selectedPeriod.value) return;
    try {
        const token = localStorage.getItem('token');
        const url = `/api/v1/reports/uang-makan/print?${buildQueryString()}`;
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const html = await response.text();
        const printWindow = window.open('', '_blank');
        printWindow.document.write(html);
        printWindow.document.close();
    } catch (err) {
        console.error('Print error:', err);
    }
}

onMounted(async () => {
    await fetchGroups();
    fetchPeriods();
});

const companyName = 'ALL IN KARANGJATI';

</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-(--bg-card) p-6 rounded-xl border border-(--border-soft) shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-(--text-main)">Laporan Uang Makan</h1>
                <p class="text-sm text-(--text-muted) mt-1">Rekapitulasi dan Perhitungan Uang Makan</p>
            </div>
            <div class="flex items-center gap-3">
                <SelectInput
                    v-model="selectedPeriod"
                    :options="payrollPeriods.map(p => ({ value: p.id, label: p.name }))"
                    class="w-48"
                    @change="fetchData"
                />
                <BaseButton variant="secondary" size="sm" @click="exportExcel" :disabled="isLoading">
                    📥 Export Excel
                </BaseButton>
                <BaseButton variant="secondary" size="sm" @click="openPrint" :disabled="isLoading">
                    🖨️ Print
                </BaseButton>
            </div>
        </div>

        <!-- Group Checkbox Filter — mirip Laporan Lembur -->
        <BaseCard>
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
                        @change="fetchData"
                        class="w-4 h-4 rounded text-(--primary) focus:ring-(--primary-glow) border-(--border-soft)"
                    />
                    <span class="text-sm font-medium text-(--text-main) group-hover:text-(--primary) transition-colors">
                        {{ group.name }}
                    </span>
                </label>
            </div>
        </BaseCard>

        <div class="bg-(--bg-card) overflow-hidden shadow-sm rounded-xl border border-(--border-soft) mb-6">
            <div class="border-b border-(--border-soft)">
                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                    <button
                        @click="setTab('rekap-lembur')"
                        :class="[activeTab === 'rekap-lembur' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors']"
                    >
                        Rekap Lembur
                    </button>

                    <button
                        @click="setTab('perhitungan')"
                        :class="[activeTab === 'perhitungan' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors']"
                    >
                        Perhitungan Uang Makan
                    </button>

                    <button
                        @click="setTab('resume')"
                        :class="[activeTab === 'resume' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors']"
                    >
                        Resume
                    </button>
                </nav>
            </div>
        </div>

        <div v-if="isLoading" class="p-8 text-center text-gray-500">
            Loading data...
        </div>

        <div v-else>
            <div v-show="activeTab === 'rekap-lembur'">
                <TabRekapLembur
                    :employees="employees"
                    :dates="dates"
                    :period="period"
                />
            </div>
            <div v-show="activeTab === 'perhitungan'">
                <TabPerhitungan
                    :employees="employees"
                    :period="period"
                />
            </div>
            <div v-show="activeTab === 'resume'">
                <TabResume :resumeData="resumeData" />
            </div>
        </div>
    </div>
</template>
