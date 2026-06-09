<script setup>
import { ref, onMounted, computed } from 'vue';
import { useApi } from '../../../../Composables/useApi';
import TabRekapLembur from './TabRekapLembur.vue';
import TabPerhitungan from './TabPerhitungan.vue';
import TabResume from './TabResume.vue';
import BaseButton from '../../../../Components/BaseButton.vue';
import SelectInput from '../../../../Components/SelectInput.vue';

const { get } = useApi();

const employees = ref([]);
const resumeData = ref([]);
const dates = ref([]);
const period = ref({ start: '', end: '', period_id: '' });
const isLoading = ref(false);

const activeTab = ref('rekap-lembur');
const selectedPeriod = ref('');
const payrollPeriods = ref([]); // Fetch this from API
const searchForm = ref({ search: '' });

function setTab(tab) {
    activeTab.value = tab;
}

const fetchPeriods = async () => {
    try {
        const response = await get('/api/v1/payroll/periods'); // adjust if different
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
        console.error("Failed to fetch periods", e);
    }
};

const fetchData = async () => {
    if (!selectedPeriod.value) return;
    isLoading.value = true;
    try {
        const searchParam = searchForm.value.search ? `&search=${encodeURIComponent(searchForm.value.search)}` : '';
        const response = await get(`/api/v1/reports/uang-makan?period_id=${selectedPeriod.value}${searchParam}`);
        
        if (response.success) {
            employees.value = response.data;
            resumeData.value = response.resumeData;
            dates.value = response.dates;
            period.value = response.period;
        }
    } catch (error) {
        console.error("Error fetching Uang Makan data:", error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchPeriods();
});

const companyName = "ALL IN KARANGJATI"; // can make dynamic later

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
            </div>
        </div>

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
