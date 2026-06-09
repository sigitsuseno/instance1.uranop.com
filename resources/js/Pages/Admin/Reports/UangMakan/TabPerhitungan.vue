<script setup>
const props = defineProps({
    employees: {
        type: Array,
        default: () => [],
    },
    period: Object,
});

const formatRupiah = (value) => {
    if (!value || value === 0) return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 0
    }).format(value);
};
</script>

<template>
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-(--text-main)">Tabel Perhitungan Uang Makan</h2>
        </div>

        <div class="overflow-x-auto border border-(--border-soft) rounded-lg">
            <table class="w-full text-sm text-left whitespace-nowrap">
                <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-800 border-b border-(--border-soft) text-(--text-muted)">
                    <tr>
                        <th rowspan="2" class="px-3 py-3 border-r border-(--border-soft) text-center align-middle font-semibold">No</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-(--border-soft) align-middle font-semibold">Nama</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center align-middle font-semibold">Grup UM</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center align-middle font-semibold">UM</th>
                        <th colspan="2" class="px-4 py-2 border-r border-b border-(--border-soft) text-center font-semibold">Lembur Sabtu</th>
                        <th colspan="2" class="px-4 py-2 border-r border-b border-(--border-soft) text-center font-semibold">LEMBUR MINGGU</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center align-middle font-semibold">UANG MAKAN</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center align-middle font-semibold">LEMBUR SABTU</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center align-middle font-semibold">LEMBUR MINGGU</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center align-middle font-semibold">INSENTIF</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center align-middle font-semibold">PBLT</th>
                        <th rowspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center align-middle font-semibold">REVISI</th>
                        <th rowspan="2" class="px-4 py-3 text-center align-middle font-semibold">TOTAL</th>
                    </tr>
                    <tr>
                        <th class="px-3 py-2 border-r border-(--border-soft) text-center font-semibold bg-gray-50 dark:bg-gray-800">DUA</th>
                        <th class="px-3 py-2 border-r border-(--border-soft) text-center font-semibold bg-gray-50 dark:bg-gray-800">FULL</th>
                        <th class="px-3 py-2 border-r border-(--border-soft) text-center font-semibold bg-gray-50 dark:bg-gray-800">1/2 HK</th>
                        <th class="px-3 py-2 border-r border-(--border-soft) text-center font-semibold bg-gray-50 dark:bg-gray-800">L</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-(--border-soft) text-(--text-main)">
                    <tr v-if="!employees || employees.length === 0">
                        <td colspan="15" class="px-4 py-8 text-center text-(--text-muted)">
                            Tidak ada data
                        </td>
                    </tr>
                    
                    <tr v-for="(emp, index) in employees" :key="emp.employee_id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-3 py-2 border-r border-(--border-soft) text-center">{{ index + 1 }}</td>
                        <td class="px-4 py-2 border-r border-(--border-soft) font-medium">{{ emp.employee_name }}</td>
                        <td class="px-4 py-2 border-r border-(--border-soft) text-center">{{ emp.title || '-' }}</td>
                        <td class="px-4 py-2 border-r border-(--border-soft) text-center font-medium">{{ emp.count_um > 0 ? emp.count_um : '-' }}</td>
                        
                        <td class="px-3 py-2 border-r border-(--border-soft) text-center text-blue-600 font-medium">{{ emp.count_sabtu_dua > 0 ? emp.count_sabtu_dua : '-' }}</td>
                        <td class="px-3 py-2 border-r border-(--border-soft) text-center text-blue-600 font-medium">{{ emp.count_sabtu_full > 0 ? emp.count_sabtu_full : '-' }}</td>
                        
                        <td class="px-3 py-2 border-r border-(--border-soft) text-center text-purple-600 font-medium">{{ emp.count_minggu_setengah > 0 ? emp.count_minggu_setengah : '-' }}</td>
                        <td class="px-3 py-2 border-r border-(--border-soft) text-center text-purple-600 font-medium">{{ emp.count_minggu_full > 0 ? emp.count_minggu_full : '-' }}</td>
                        
                        <td class="px-4 py-2 border-r border-(--border-soft) text-right font-medium text-green-600">{{ formatRupiah(emp.nominal_um) }}</td>
                        
                        <td class="px-4 py-2 border-r border-(--border-soft) text-right text-gray-700 dark:text-gray-300">{{ formatRupiah(emp.nominal_sabtu) }}</td>
                        <td class="px-4 py-2 border-r border-(--border-soft) text-right text-gray-700 dark:text-gray-300">{{ formatRupiah(emp.nominal_minggu) }}</td>
                        
                        <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(emp.nominal_insentif) }}</td>
                        <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(emp.nominal_pblt) }}</td>
                        <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(emp.nominal_revisi) }}</td>
                        
                        <td class="px-4 py-2 text-right font-bold text-indigo-600">{{ formatRupiah(emp.total) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
