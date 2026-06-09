<script setup>
const props = defineProps({
    employees: Array,
    dates: Array,
    period: Object,
});

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
    if (dayName === 'Minggu' || dayName === 'Sunday') return 'bg-green-100';
    return '';
}

function getDayAbbrev(dayName) {
    const map = { 'Minggu': 'Min', 'Senin': 'Sen', 'Selasa': 'Sel', 'Rabu': 'Rab', 'Kamis': 'Kam', 'Jumat': 'Jum', 'Sabtu': 'Sab', 'Sunday': 'Min', 'Monday': 'Sen', 'Tuesday': 'Sel', 'Wednesday': 'Rab', 'Thursday': 'Kam', 'Friday': 'Jum', 'Saturday': 'Sab' };
    return map[dayName] || dayName.substring(0, 3);
}
</script>

<template>
    <div class="space-y-6">
        <div class="overflow-x-auto bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-(--bg-elevated)">
                        <th class="px-2 py-2 text-center text-xs font-bold text-(--text-main) border-r border-(--border-soft) w-10 sticky left-0 bg-(--bg-elevated) z-10" rowspan="2">NO</th>
                        <th class="px-3 py-2 text-left text-xs font-bold text-(--text-main) border-r border-(--border-soft) sticky left-[40px] bg-(--bg-elevated) z-10" rowspan="2">NAMA</th>
                        <th v-for="d in dates" :key="d.date" :colspan="2"
                            :class="['px-1 py-2 text-center border-r border-(--border-soft) text-[10px] font-bold text-(--text-main)', getDayClass(d.day_name)]">
                            {{ new Date(d.date).getDate() }}
                        </th>
                    </tr>
                    <tr class="bg-(--bg-elevated)">
                        <template v-for="d in dates" :key="'header2-'+d.date">
                            <th :class="['px-1 py-1 text-center border-r border-(--border-soft) text-[9px] font-medium text-(--text-muted)', getDayClass(d.day_name)]">
                                {{ getDayAbbrev(d.day_name) }}
                            </th>
                            <th :class="['px-1 py-1 text-center border-r border-(--border-soft) text-[9px] font-bold text-(--text-soft)', getDayClass(d.day_name)]">
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
                        <template v-for="d in dates" :key="'data-'+d.date">
                            <td :class="['px-1 py-1.5 text-center border-r border-(--border-soft) text-xs', getDayClass(d.day_name), getStatusClass(emp.days[d.date]?.status)]">
                                {{ emp.days[d.date]?.status || '' }}
                            </td>
                            <td :class="['px-1 py-1.5 text-center border-r border-(--border-soft) text-xs', getDayClass(d.day_name), emp.days[d.date]?.lembur > 0 ? 'text-orange-600 font-medium' : 'text-gray-300']">
                                {{ emp.days[d.date]?.lembur > 0 ? emp.days[d.date].lembur : '-' }}
                            </td>
                        </template>
                    </tr>
                    <tr v-if="!employees || employees.length === 0">
                        <td :colspan="dates.length * 2 + 2" class="px-4 py-12 text-center text-(--text-muted)">
                            Tidak ada data untuk periode ini
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
