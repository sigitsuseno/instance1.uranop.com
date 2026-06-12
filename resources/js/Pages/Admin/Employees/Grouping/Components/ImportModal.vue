<script setup>
import { ref } from 'vue';
import { useApi } from '../../../../../composables/useApi';
import { useNotificationStore } from '../../../../../Stores/notification';

const props = defineProps({
    show: Boolean,
    activeTab: String,
    activeMonth: Number,
    activeYear: Number
});

const emit = defineEmits(['close', 'success']);

const { post } = useApi();
const notification = useNotificationStore();

const fileInput = ref(null);
const file = ref(null);
const isUploading = ref(false);
const isProcessing = ref(false);
const isDownloading = ref(false);

const step = ref(1); // 1: Upload, 2: Preview
const previewData = ref([]);

const handleFileChange = (e) => {
    file.value = e.target.files[0];
};

const handleUpload = async () => {
    if (!file.value) {
        notification.addNotification('Pilih file terlebih dahulu', 'warning');
        return;
    }

    isUploading.value = true;
    const formData = new FormData();
    formData.append('file', file.value);

    try {
        const response = await fetch('/api/v1/employees/grouping/preview-import', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`,
                'Accept': 'application/json'
            },
            body: formData
        });

        const result = await response.json();
        
        if (response.ok) {
            previewData.value = result.data || [];
            step.value = 2;
        } else {
            notification.addNotification(result.message || 'Gagal membaca file', 'error');
        }
    } catch (error) {
        console.error(error);
        notification.addNotification('Terjadi kesalahan koneksi', 'error');
    } finally {
        isUploading.value = false;
    }
};

const handleProcess = async () => {
    const validItems = previewData.value.filter(item => item.is_valid);
    if (validItems.length === 0) {
        notification.addNotification('Tidak ada data yang valid untuk diimport', 'warning');
        return;
    }

    isProcessing.value = true;
    try {
        const result = await post('/api/v1/employees/grouping/import', {
            items: validItems
        });
        
        notification.addNotification(result.message || 'Import berhasil', 'success');
        emit('success');
        closeModal();
    } catch (error) {
        console.error(error);
        notification.addNotification('Gagal memproses import', 'error');
    } finally {
        isProcessing.value = false;
    }
};

const closeModal = () => {
    step.value = 1;
    file.value = null;
    previewData.value = [];
    if (fileInput.value) fileInput.value.value = '';
    emit('close');
};

const downloadTemplate = async () => {
    isDownloading.value = true;
    try {
        let url = `/api/v1/employees/grouping/export?tab=${props.activeTab || 'employment_type'}&year=${props.activeYear || new Date().getFullYear()}&month=${props.activeMonth || (new Date().getMonth() + 1)}`;
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            }
        });
        
        if (!response.ok) {
            throw new Error('Gagal mendownload template');
        }
        
        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = downloadUrl;
        
        const disposition = response.headers.get('Content-Disposition');
        let filename = `Export_Grouping_${props.activeTab || 'All'}.xlsx`;
        if (disposition && disposition.includes('filename="')) {
            filename = disposition.split('filename="')[1].split('"')[0];
        }
        
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(downloadUrl);
    } catch (error) {
        console.error(error);
        notification.addNotification('Terjadi kesalahan saat mendownload', 'error');
    } finally {
        isDownloading.value = false;
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
        <div class="bg-(--bg-card) w-full max-w-4xl rounded-md shadow-2xl border border-(--border-soft) overflow-hidden animate-in fade-in zoom-in duration-200 flex flex-col max-h-[90vh]">
            
            <div class="p-6 border-b border-(--border-soft) flex items-center justify-between shrink-0">
                <div>
                    <h2 class="text-xl font-bold text-(--text-main)">Import Grouping Karyawan</h2>
                    <p class="text-sm text-(--text-muted) mt-1">
                        {{ step === 1 ? 'Upload file Excel/CSV untuk memetakan karyawan ke grup' : 'Preview Data Import' }}
                    </p>
                </div>
                <button @click="closeModal" class="p-2 hover:bg-(--bg-elevated) rounded-full text-(--text-soft) transition-colors">
                    <i class="bx bx-x text-2xl"></i>
                </button>
            </div>

            <!-- Step 1: Upload -->
            <div v-if="step === 1" class="p-6 space-y-6 flex-1 overflow-y-auto">
                <div class="bg-(--primary)/10 p-4 rounded-md border border-(--primary)/20 flex items-start gap-3">
                    <i class="bx bx-info-circle text-(--primary) text-xl mt-0.5"></i>
                    <div>
                        <h4 class="text-sm font-bold text-(--primary) mb-1">Panduan Import</h4>
                        <ul class="text-sm text-(--text-main) list-disc list-inside space-y-1">
                            <li>Gunakan template CSV/Excel yang disediakan.</li>
                            <li>Kolom A harus berisi NIP karyawan.</li>
                            <li>Kolom B harus berisi Kode Grup referensi (contoh: UM-KBG, PAY-2026-06, permanent).</li>
                        </ul>
                        <button @click="downloadTemplate" :disabled="isDownloading" class="mt-3 text-sm font-semibold text-(--primary) hover:underline flex items-center gap-1 disabled:opacity-50">
                            <i v-if="isDownloading" class="bx bx-loader-alt animate-spin"></i>
                            <i v-else class="bx bx-download"></i> Download Master Excel
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-(--text-main) mb-2">Pilih File</label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"
                        @change="handleFileChange"
                        class="block w-full text-sm text-(--text-main) file:mr-4 file:py-2.5 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-(--primary) file:text-white hover:file:bg-(--primary)/90 cursor-pointer border border-(--border-soft) rounded-md p-1"
                    />
                </div>
            </div>

            <!-- Step 2: Preview -->
            <div v-if="step === 2" class="p-6 space-y-4 flex-1 overflow-y-auto min-h-0">
                <div class="flex items-center gap-4 text-sm mb-2">
                    <div class="flex items-center gap-1 text-(--success) font-medium">
                        <i class="bx bx-check-circle text-lg"></i>
                        {{ previewData.filter(d => d.is_valid).length }} Valid
                    </div>
                    <div class="flex items-center gap-1 text-(--danger) font-medium">
                        <i class="bx bx-x-circle text-lg"></i>
                        {{ previewData.filter(d => !d.is_valid).length }} Invalid
                    </div>
                </div>

                <div class="border border-(--border-soft) rounded-md overflow-hidden">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead class="bg-(--bg-elevated) text-(--text-muted) text-xs uppercase font-medium">
                            <tr>
                                <th class="px-4 py-3 border-b border-(--border-soft)">Baris</th>
                                <th class="px-4 py-3 border-b border-(--border-soft)">NIP</th>
                                <th class="px-4 py-3 border-b border-(--border-soft)">Karyawan</th>
                                <th class="px-4 py-3 border-b border-(--border-soft)">Kode Grup</th>
                                <th class="px-4 py-3 border-b border-(--border-soft)">Grup/Status</th>
                                <th class="px-4 py-3 border-b border-(--border-soft)">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-soft)">
                            <tr v-for="item in previewData" :key="item.row" :class="!item.is_valid ? 'bg-(--danger)/5' : ''">
                                <td class="px-4 py-3 text-center text-(--text-soft)">{{ item.row }}</td>
                                <td class="px-4 py-3 font-mono text-(--text-main)">{{ item.nip }}</td>
                                <td class="px-4 py-3 text-(--text-main)">{{ item.employee_name || '-' }}</td>
                                <td class="px-4 py-3 font-mono text-(--text-main)">{{ item.group_code }}</td>
                                <td class="px-4 py-3 text-(--text-main)">{{ item.group_name || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="item.is_valid" class="px-2 py-1 text-xs rounded-full bg-(--success)/10 text-(--success) font-semibold">Valid</span>
                                    <span v-else class="px-2 py-1 text-xs rounded-full bg-(--danger)/10 text-(--danger) font-semibold" :title="item.error">
                                        {{ item.error }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="previewData.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-(--text-muted)">Tidak ada data untuk ditampilkan</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 border-t border-(--border-soft) flex justify-end gap-3 bg-(--bg-elevated) shrink-0">
                <button 
                    @click="closeModal"
                    class="px-5 py-2.5 text-sm font-bold text-(--text-soft) hover:text-(--text-main) transition-colors"
                >
                    Batal
                </button>
                
                <button 
                    v-if="step === 1"
                    @click="handleUpload"
                    :disabled="isUploading || !file"
                    class="bg-(--primary) text-white px-6 py-2.5 rounded-md text-sm font-bold shadow-md hover:bg-(--primary)/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <i v-if="isUploading" class="bx bx-loader-alt animate-spin text-lg"></i>
                    {{ isUploading ? 'Memproses...' : 'Preview Data' }}
                </button>
                
                <button 
                    v-if="step === 2"
                    @click="handleProcess"
                    :disabled="isProcessing || previewData.filter(d => d.is_valid).length === 0"
                    class="bg-(--success) text-white px-6 py-2.5 rounded-md text-sm font-bold shadow-md shadow-(--success)/20 hover:bg-(--success)/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <i v-if="isProcessing" class="bx bx-loader-alt animate-spin text-lg"></i>
                    <i v-else class="bx bx-save text-lg"></i>
                    {{ isProcessing ? 'Menyimpan...' : 'Simpan Valid Data' }}
                </button>
            </div>
            
        </div>
    </div>
</template>
