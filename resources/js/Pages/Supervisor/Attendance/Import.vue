<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useApi } from '../../../composables/useApi';

const router = useRouter();
const { get, post } = useApi();

const periods = ref([]);
const fileInput = ref(null);
const isLoading = ref(true);

const form = ref({
    attendance_file: null,
    payroll_period_id: '',
    processing: false,
    errors: {}
});

async function fetchPeriods() {
    try {
        const response = await get('/api/v1/supervisor/attendance/import');
        periods.value = response.periods || [];
    } catch (error) {
        console.error('Error fetching periods:', error);
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    fetchPeriods();
});

const handleFileChange = (e) => {
    form.value.errors.attendance_file = null;
    form.value.attendance_file = e.target.files[0];
};

const removeFile = () => {
    form.value.attendance_file = null;
    if (fileInput.value) fileInput.value.value = '';
};

const submit = async () => {
    if (!form.value.attendance_file || !form.value.payroll_period_id) return;
    
    form.value.processing = true;
    form.value.errors = {};
    
    const formData = new FormData();
    formData.append('attendance_file', form.value.attendance_file);
    formData.append('payroll_period_id', form.value.payroll_period_id);
    
    try {
        await post('/api/v1/supervisor/attendance/import', formData);
        removeFile();
        form.value.payroll_period_id = '';
        router.push('/supervisor/attendance');
    } catch (error) {
        console.error('Import failed:', error);
        
        if (error.response?.data?.errors) {
            form.value.errors = error.response.data.errors;
        } else {
            form.value.errors.attendance_file = error.message || 'Terjadi kesalahan saat mengunggah file';
        }
    } finally {
        form.value.processing = false;
    }
};
</script>

<template>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-5xl mx-auto space-y-6">
        <div class="flex items-center space-x-3 mb-6">
            <router-link
                to="/supervisor/attendance"
                class="text-(--text-muted) hover:text-(--primary) transition-colors"
            >
                <i class="bx bx-arrow-back text-2xl"></i>
            </router-link>
            <h1 class="text-2xl md:text-3xl text-(--text-main) font-bold">
                Import Data Absensi (.bin)
            </h1>
        </div>

        <div v-if="isLoading" class="flex justify-center my-12">
            <i class="bx bx-loader-alt animate-spin text-4xl text-indigo-600"></i>
        </div>

        <div v-else class="bg-(--bg-card) border border-(--border-soft) overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 sm:p-10">
                <div class="mb-6 text-(--text-muted)">
                    Pilih file biner absensi (biasanya dengan format <code>HisGLog_*.bin</code>)
                    dari mesin Fingerspot/ZKTeco Anda. Sistem akan secara otomatis membaca dan
                    memasukkannya ke tabel shadow attendance_logs supervisor.
                </div>
                
                <div
                    v-if="form.errors.attendance_file"
                    class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start space-x-3"
                >
                    <i class="bx bx-error-circle text-xl text-red-500 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-red-700">Error Upload File</p>
                        <p class="text-sm text-red-600 mt-1">{{ form.errors.attendance_file }}</p>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-(--text-main)">Periode Payroll</label>
                        <select
                            v-model="form.payroll_period_id"
                            class="mt-1 block w-full rounded-md border border-(--border-soft) bg-(--bg-card) px-3 py-2 text-sm text-(--text-main) focus:border-(--primary) focus:outline-[none] focus:ring-1 focus:ring-(--primary)"
                        >
                            <option value="" disabled>Pilih periode payroll</option>
                            <option v-for="period in periods" :key="period.id" :value="period.id">
                                {{ period.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.payroll_period_id" class="mt-2 text-sm text-red-500">
                            {{ form.errors.payroll_period_id }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-(--text-main)">File Absensi (.bin)</label>
                        <div
                            class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-(--border-soft) border-dashed rounded-lg relative group cursor-pointer hover:border-(--primary) focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-(--primary)"
                        >
                            <div class="space-y-1 text-center">
                                <i
                                    class="bx bx-upload text-4xl text-(--text-muted) group-hover:text-(--primary) transition-colors"
                                ></i>
                                <div class="flex text-sm text-(--text-soft) justify-center">
                                    <label
                                        for="file-upload"
                                        class="relative cursor-pointer bg-transparent rounded-md font-medium text-(--primary) hover:brightness-110 focus-within:outline-[none]"
                                    >
                                        <span>Pilih File</span>
                                        <input
                                            id="file-upload"
                                            ref="fileInput"
                                            name="file-upload"
                                            type="file"
                                            accept=".bin"
                                            class="sr-only"
                                            @change="handleFileChange"
                                        />
                                    </label>
                                    <p class="pl-1">atau tarik dan letakkan disini</p>
                                </div>
                                <p class="text-xs text-(--text-muted)">
                                    Hanya format .bin (Maksimal 20MB)
                                </p>
                            </div>

                            <div
                                v-show="form.attendance_file"
                                class="absolute inset-0 bg-(--bg-card) bg-opacity-95 flex flex-col items-center justify-center rounded-lg border border-(--primary)/50 backdrop-blur-sm z-10"
                            >
                                <i class="bx bxs-file text-4xl text-(--primary)"></i>
                                <p class="mt-2 text-sm font-medium text-(--text-main)">
                                    {{ form.attendance_file?.name }}
                                </p>
                                <p class="mt-1 text-xs text-(--text-muted)">
                                    {{ form.attendance_file ? (form.attendance_file.size / 1024).toFixed(2) : 0 }} KB
                                </p>
                                <button
                                    type="button"
                                    @click.prevent="removeFile"
                                    class="mt-3 text-sm text-red-500 hover:text-red-600 transition-colors bg-red-500/10 px-3 py-1 rounded-full"
                                >
                                    <i class="bx bx-x"></i> Hapus
                                </button>
                            </div>
                        </div>
                        <div v-if="form.errors.attendance_file" class="mt-2 text-sm text-red-500">
                            {{ form.errors.attendance_file }}
                        </div>
                    </div>

                    <div class="flex items-center justify-end border-t border-(--border-soft) pt-6 space-x-3">
                        <router-link
                            to="/supervisor/attendance"
                            class="px-4 py-2 border border-(--border-soft) text-(--text-main) rounded-md text-sm font-medium hover:bg-(--bg-elevated) transition-colors"
                        >
                            Batal
                        </router-link>
                        <button
                            type="submit"
                            :disabled="form.processing || !form.attendance_file || !form.payroll_period_id"
                            class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-(--primary) hover:brightness-110 focus:outline-[none] focus:ring-2 focus:ring-offset-2 focus:ring-(--primary) disabled:opacity-50 transition-all cursor-pointer"
                        >
                            <i v-if="form.processing" class="bx bx-loader-alt animate-spin mr-2"></i>
                            <i v-else class="bx bx-save mr-2"></i>
                            {{ form.processing ? 'Memproses...' : 'Mulai Import' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
