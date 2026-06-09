<script setup>
import { computed } from 'vue';

const props = defineProps({
    resumeData: {
        type: Array,
        default: () => [],
    }
});

const formatRupiah = (value) => {
    if (!value || value === 0) return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 0
    }).format(value);
};

const totalUangMakan = computed(() => props.resumeData.reduce((sum, item) => sum + (item.uang_makan || 0), 0));
const totalLemburSabtu = computed(() => props.resumeData.reduce((sum, item) => sum + (item.lembur_sabtu || 0), 0));
const totalLemburMinggu = computed(() => props.resumeData.reduce((sum, item) => sum + (item.lembur_minggu || 0), 0));
const totalInsentif = computed(() => props.resumeData.reduce((sum, item) => sum + (item.insentif || 0), 0));
const totalPblt = computed(() => props.resumeData.reduce((sum, item) => sum + (item.pblt || 0), 0));
const totalRevisi = computed(() => props.resumeData.reduce((sum, item) => sum + (item.revisi || 0), 0));
const grandTotal = computed(() => props.resumeData.reduce((sum, item) => sum + (item.total || 0), 0));

</script>

<template>
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4">
        
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-(--text-main)">Resume Uang Makan</h2>
        </div>

        <div class="mb-6 border border-(--border-soft) rounded-lg overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 border-b border-(--border-soft)">
                <h3 class="font-bold text-gray-700 dark:text-gray-200">ALL IN</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left whitespace-nowrap">
                    <thead class="text-xs uppercase border-b border-(--border-soft) text-(--text-muted)">
                        <tr>
                            <th class="px-3 py-3 border-r border-(--border-soft) text-center font-semibold">No.</th>
                            <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">BAGIAN</th>
                            <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">UANG MAKAN</th>
                            <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">LEMBUR SABTU</th>
                            <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">LEMBUR MINGGU</th>
                            <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">INSENTIF</th>
                            <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">PBLT</th>
                            <th class="px-4 py-3 border-r border-(--border-soft) text-center font-semibold">REVISI</th>
                            <th class="px-4 py-3 text-center font-semibold">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--border-soft) text-(--text-main)">
                        <tr v-if="!resumeData || resumeData.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center text-(--text-muted)">
                                Tidak ada data karyawan ALL IN
                            </td>
                        </tr>
                        <tr v-for="(item, index) in resumeData" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-3 py-2 border-r border-(--border-soft) text-center">{{ index + 1 }}</td>
                            <td class="px-4 py-2 border-r border-(--border-soft) font-medium">{{ item.bagian }}</td>
                            <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(item.uang_makan) }}</td>
                            <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(item.lembur_sabtu) }}</td>
                            <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(item.lembur_minggu) }}</td>
                            <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(item.insentif) }}</td>
                            <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(item.pblt) }}</td>
                            <td class="px-4 py-2 border-r border-(--border-soft) text-right">{{ formatRupiah(item.revisi) }}</td>
                            <td class="px-4 py-2 text-right font-bold text-indigo-600">{{ formatRupiah(item.total) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-800 border-t border-(--border-soft)">
                        <tr class="font-bold">
                            <td colspan="2" class="px-4 py-3 border-r border-(--border-soft) text-center text-gray-700 dark:text-gray-200 uppercase">
                                TOTAL
                            </td>
                            <td class="px-4 py-3 border-r border-(--border-soft) text-right text-gray-700 dark:text-gray-200">{{ formatRupiah(totalUangMakan) }}</td>
                            <td class="px-4 py-3 border-r border-(--border-soft) text-right text-gray-700 dark:text-gray-200">{{ formatRupiah(totalLemburSabtu) }}</td>
                            <td class="px-4 py-3 border-r border-(--border-soft) text-right text-gray-700 dark:text-gray-200">{{ formatRupiah(totalLemburMinggu) }}</td>
                            <td class="px-4 py-3 border-r border-(--border-soft) text-right text-gray-700 dark:text-gray-200">{{ formatRupiah(totalInsentif) }}</td>
                            <td class="px-4 py-3 border-r border-(--border-soft) text-right text-gray-700 dark:text-gray-200">{{ formatRupiah(totalPblt) }}</td>
                            <td class="px-4 py-3 border-r border-(--border-soft) text-right text-gray-700 dark:text-gray-200">{{ formatRupiah(totalRevisi) }}</td>
                            <td class="px-4 py-3 text-right text-indigo-700 dark:text-indigo-400">{{ formatRupiah(grandTotal) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</template>
