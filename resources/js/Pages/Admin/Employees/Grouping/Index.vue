<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useApi } from '../../../../composables/useApi';
import { useNotificationStore } from '../../../../Stores/notification';
import { usePermissionStore } from '../../../../Stores/permission';
import KanbanColumn from './Components/KanbanColumn.vue';
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue';
import ImportModal from './Components/ImportModal.vue';

const router = useRouter();
const permission = usePermissionStore();

const { get, post } = useApi();
const notification = useNotificationStore();

const employees = ref([]);
const groupsMaster = ref([]);
const tabSettings = ref([]);
const enrolledData = ref({});
const filters = ref({ month: new Date().getMonth() + 1, year: new Date().getFullYear() });
const isLoading = ref(true);

const activeTab = ref('employment_type');
const activeMonth = ref(filters.value.month);
const activeYear = ref(filters.value.year);
const searchQuery = ref('');
const selectedIds = ref([]);
const isBatchModalOpen = ref(false);
const showAutoEnrollDialog = ref(false);
const showImportModal = ref(false);

const batchForm = ref({
    employee_ids: [],
    employee_group_id: null,
    payroll_cycle: null,
    processing: false
});

const tabs = computed(() => {
    const defaultTabs = [
        { id: 'employment_type', name: 'Tipe Karyawan', icon: 'bx-user-voice' }
    ];
    
    const dynamicTabs = tabSettings.value.map(setting => ({
        id: setting.tab_id,
        name: setting.tab_name,
        icon: setting.icon || 'bx-folder',
        setting: setting
    }));
    
    return [
        ...defaultTabs,
        ...dynamicTabs,
        { id: 'payroll_cycle', name: 'Siklus Payroll', icon: 'bx-time' },
    ];
});

// --- DRAG & DROP & STAGING LOGIC ---
const draggedEmployeeId = ref(null);
const localEmployees = ref([]);
const enrolledList = ref({}); 
const hasChanges = ref(false);

const fetchData = async () => {
    isLoading.value = true;
    try {
        const data = await get(`/api/v1/employees/grouping?month=${activeMonth.value}&year=${activeYear.value}`);
        
        employees.value = data.employees;
        groupsMaster.value = data.groupsMaster || [];
        tabSettings.value = data.tabSettings || [];
        enrolledData.value = data.enrolledData;
        
        localEmployees.value = JSON.parse(JSON.stringify(employees.value));
        enrolledList.value = JSON.parse(JSON.stringify(enrolledData.value || {}));
        hasChanges.value = false;
        selectedIds.value = [];
        
    } catch (error) {
        console.error("Error fetching data:", error);
        notification.addNotification('Gagal memuat data.', 'error');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchData();
});

watch([activeMonth, activeYear], () => {
    fetchData();
});

const onDragStart = (id) => {
    draggedEmployeeId.value = id;
};

const onDrop = (value) => {
    if (!draggedEmployeeId.value) return;
    
    const employeeIds = selectedIds.value.includes(draggedEmployeeId.value)
        ? [...selectedIds.value]
        : [draggedEmployeeId.value];
    
    if (activeTab.value === 'payroll_cycle') {
        employeeIds.forEach(id => {
            if (value === 'available') {
                delete enrolledList.value[id];
            } else {
                enrolledList.value[id] = {
                    emp_group: value === 'monthly' ? 'Bulanan' : 'Harian/Mingguan',
                    payroll_type: value === 'monthly' ? 'monthly' : 'daily'
                };
            }
        });
        hasChanges.value = true;
    }
    else if (activeTab.value === 'employment_type') {
        localEmployees.value = localEmployees.value.map(emp => {
            if (employeeIds.includes(emp.id)) {
                return { ...emp, employment_status: value };
            }
            return emp;
        });
        hasChanges.value = true;
    } 
    else {
        // Dynamic tabs
        localEmployees.value = localEmployees.value.map(emp => {
            if (employeeIds.includes(emp.id)) {
                if (!emp.dynamic_groups) emp.dynamic_groups = {};
                emp.dynamic_groups[activeTab.value] = value;
                return { ...emp };
            }
            return emp;
        });
        hasChanges.value = true;
    }
    
    draggedEmployeeId.value = null;
};

const saveChanges = async () => {
    let payload = { tab: activeTab.value };
    
    if (activeTab.value === 'payroll_cycle') {
        const enrollments = [];
        const disenrollments = [];

        Object.keys(enrolledList.value).forEach(id => {
            const current = enrolledList.value[id];
            const original = enrolledData.value[id];
            
            if (!original || original.payroll_type !== current.payroll_type) {
                enrollments.push({
                    employee_id: id,
                    emp_group: current.emp_group,
                    payroll_type: current.payroll_type
                });
            }
        });

        Object.keys(enrolledData.value).forEach(id => {
            if (!enrolledList.value[id]) {
                disenrollments.push(id);
            }
        });

        payload.month = activeMonth.value;
        payload.year = activeYear.value;
        payload.enrollments = enrollments;
        payload.disenrollments = disenrollments;
    }
    else if (activeTab.value === 'employment_type') {
        const changes = localEmployees.value
            .filter(emp => {
                const original = employees.value.find(e => e.id === emp.id);
                return original && original.employment_status !== emp.employment_status;
            })
            .map(emp => ({
                employee_id: emp.id,
                employment_status: emp.employment_status
            }));

        if (changes.length === 0) return;
        payload.changes = changes;
    }
    else {
        // Dynamic tabs
        const changes = localEmployees.value
            .filter(emp => {
                const original = employees.value.find(e => e.id === emp.id);
                const oldVal = original && original.dynamic_groups ? original.dynamic_groups[activeTab.value] : null;
                const newVal = emp.dynamic_groups ? emp.dynamic_groups[activeTab.value] : null;
                return oldVal !== newVal;
            })
            .map(emp => ({
                employee_id: emp.id,
                group_id: emp.dynamic_groups ? emp.dynamic_groups[activeTab.value] : null
            }));

        if (changes.length === 0) return;
        payload.changes = changes;
    }

    try {
        await post('/api/v1/employees/grouping/bulk-update', payload);
        notification.addNotification('Perubahan berhasil disimpan!', 'success');
        fetchData(); // Reload
    } catch (error) {
        console.error(error);
        notification.addNotification('Gagal menyimpan perubahan.', 'error');
    }
};

const cancelChanges = () => {
    localEmployees.value = JSON.parse(JSON.stringify(employees.value));
    enrolledList.value = JSON.parse(JSON.stringify(enrolledData.value || {}));
    hasChanges.value = false;
    selectedIds.value = [];
};

// --- FILTER & CATEGORY LOGIC ---
const filteredData = computed(() => {
    if (activeTab.value === 'employment_type') {
        return [
            { label: 'PKWTT (Tetap)', value: 'permanent', icon: 'bx-check-double', color: 'var(--success)' },
            { label: 'PKWT (Kontrak)', value: 'contract', icon: 'bx-file', color: 'var(--primary)' },
            { label: 'Harian / Freelance', value: 'freelance', icon: 'bx-walk', color: 'var(--warning)' },
        ];
    } else if (activeTab.value === 'payroll_cycle') {
        return [
            { label: 'Tersedia (Belum Terdaftar)', value: 'available', icon: 'bx-user-plus', color: 'var(--text-soft)' },
            { label: 'Terdaftar - Bulanan', value: 'monthly', icon: 'bx-calendar-check', color: 'var(--success)' },
            { label: 'Terdaftar - Non Bulanan', value: 'non_monthly', icon: 'bx-time-five', color: 'var(--warning)' },
        ];
    } else {
        const setting = tabSettings.value.find(t => t.tab_id === activeTab.value);
        if (!setting) return [];
        
        const myGroups = groupsMaster.value.filter(g => g.group_label === setting.group_label);
        const list = myGroups.map(g => ({
            label: g.name,
            value: g.id,
            icon: setting.icon || 'bx-folder',
            color: 'var(--primary)'
        }));
        list.push({ label: 'Belum Ada Grup', value: null, icon: 'bx-help-circle', color: 'var(--text-soft)' });
        return list;
    }
});

const months = [
    { id: 1, name: 'Jan' }, { id: 2, name: 'Feb' }, { id: 3, name: 'Mar' },
    { id: 4, name: 'Apr' }, { id: 5, name: 'Mei' }, { id: 6, name: 'Jun' },
    { id: 7, name: 'Jul' }, { id: 8, name: 'Agu' }, { id: 9, name: 'Sep' },
    { id: 10, name: 'Okt' }, { id: 11, name: 'Nov' }, { id: 12, name: 'Des' }
];

const getEmployeesByValue = (value) => {
    let filtered = localEmployees.value;

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        filtered = filtered.filter(e => 
            e.name.toLowerCase().includes(q) || 
            (e.employee_code && e.employee_code.toLowerCase().includes(q))
        );
    }

    if (activeTab.value === 'employment_type') {
        return filtered.filter(e => e.employment_status === value);
    } else if (activeTab.value === 'payroll_cycle') {
        // Filter join_date dan end_date (aktif di periode)
        filtered = filtered.filter(e => e.is_active_in_period);
        
        // Filter selain otoritas JKT
        const jktGroup = groupsMaster.value.find(g => (g.name && g.name.toUpperCase().includes('JKT')) || (g.code && g.code.toUpperCase().includes('JKT')));
        if (jktGroup) {
            filtered = filtered.filter(e => !e.dynamic_groups || e.dynamic_groups['group_es'] !== jktGroup.id);
        }
        
        if (value === 'available') {
            return filtered.filter(e => !enrolledList.value[e.id]);
        } else if (value === 'monthly') {
            return filtered.filter(e => enrolledList.value[e.id]?.payroll_type === 'monthly');
        } else {
            return filtered.filter(e => enrolledList.value[e.id] && enrolledList.value[e.id].payroll_type !== 'monthly');
        }
    } else {
        // Dynamic Tabs
        const setting = tabSettings.value.find(t => t.tab_id === activeTab.value);
        
        if (setting && setting.filters) {
            Object.keys(setting.filters).forEach(key => {
                const allowedValues = setting.filters[key] || [];
                if (allowedValues.length > 0) {
                    filtered = filtered.filter(e => {
                        const val = e[key] ? e[key].toLowerCase() : '';
                        return allowedValues.includes(val);
                    });
                }
            });
        }
        
        return filtered.filter(e => {
            const groupId = e.dynamic_groups ? e.dynamic_groups[activeTab.value] : null;
            return groupId === value;
        });
    }
};

const toggleSelection = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index > -1) {
        selectedIds.value.splice(index, 1);
    } else {
        selectedIds.value.push(id);
    }
};

const selectAllInGroup = (groupEmployees) => {
    const ids = groupEmployees.map(e => e.id);
    const allSelected = ids.every(id => selectedIds.value.includes(id));
    
    if (allSelected) {
        selectedIds.value = selectedIds.value.filter(id => !ids.includes(id));
    } else {
        selectedIds.value = [...new Set([...selectedIds.value, ...ids])];
    }
};



const autoEnroll = () => {
    showAutoEnrollDialog.value = true;
};

const handleAutoEnroll = async () => {
    try {
        const response = await post('/api/v1/employees/grouping/auto-enroll', {
            month: activeMonth.value,
            year: activeYear.value
        });
        notification.addNotification(response?.message || 'Berhasil auto-enroll', 'success');
        fetchData();
    } catch (error) {
        notification.addNotification('Gagal auto-enroll.', 'error');
    } finally {
        showAutoEnrollDialog.value = false;
    }
};

// Batch Action
const openBatchAssign = () => {
    if (selectedIds.value.length === 0) return;
    batchForm.value.employee_ids = selectedIds.value;
    batchForm.value.employee_group_id = null;
    isBatchModalOpen.value = true;
};

const activeTabGroups = computed(() => {
    const setting = tabSettings.value.find(t => t.tab_id === activeTab.value);
    if (!setting) return [];
    return groupsMaster.value.filter(g => g.group_label === setting.group_label);
});

const submitBatch = async () => {
    batchForm.value.processing = true;
    try {
        let payload = {
            employee_ids: batchForm.value.employee_ids,
            employee_group_id: batchForm.value.employee_group_id,
        };
        batchForm.value.employee_ids.forEach(id => {
            if (batchForm.value.employee_group_id !== undefined) {
                const emp = localEmployees.value.find(e => e.id === id);
                if (emp) {
                    if (!emp.dynamic_groups) emp.dynamic_groups = {};
                    emp.dynamic_groups[activeTab.value] = batchForm.value.employee_group_id;
                }
            }
        });
        hasChanges.value = true;
        isBatchModalOpen.value = false;
        selectedIds.value = [];
        batchForm.value = { employee_ids: [], employee_group_id: null, payroll_cycle: null, processing: false };
        saveChanges();
    } catch (e) {
        batchForm.value.processing = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-(--bg-main) p-6">
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-(--text-main) tracking-tight">Grouping Karyawan</h1>
                <p class="text-(--text-muted) mt-1">Kelola penempatan administratif secara fleksibel dan dinamis.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <transition name="slide-fade">
                    <div v-if="hasChanges && permission.can('edit employees')" class="flex items-center gap-2">
                        <button 
                            @click="cancelChanges"
                            class="px-4 py-2.5 rounded-md font-bold text-(--text-soft) hover:text-(--text-main) transition-all"
                        >
                            Batal
                        </button>
                        <button 
                            @click="saveChanges"
                            class="bg-(--success) text-white px-6 py-2.5 rounded-md font-bold shadow-lg shadow-(--success)/20 flex items-center gap-2 hover:opacity-90 transition-all animate-pulse"
                        >
                            <i class="bx bx-save text-xl"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </transition>

                <transition name="slide-fade">
                    <div v-if="selectedIds.length > 0 && !hasChanges && permission.can('edit employees')" class="flex items-center gap-2">
                        <button 
                            @click="openBatchAssign"
                            class="bg-(--primary) text-white px-6 py-2.5 rounded-md font-bold shadow-lg shadow-(--primary-glow) flex items-center gap-2 hover:scale-105 transition-all"
                        >
                            <i class="bx bx-check-circle text-xl"></i>
                            Assign {{ selectedIds.length }} Karyawan
                        </button>
                    </div>
                </transition>

                <div v-if="activeTab === 'payroll_cycle' && !hasChanges && selectedIds.length === 0 && permission.can('edit employees')" class="flex items-center gap-2">
                    <button 
                        @click="autoEnroll"
                        class="bg-(--warning) text-white px-4 py-2.5 rounded-md font-bold shadow-lg shadow-(--warning)/20 flex items-center gap-2 hover:opacity-90 transition-all"
                        title="Auto-Enroll Karyawan"
                    >
                        <i class="bx bx-wand text-xl"></i>
                        <span class="hidden md:inline">Auto Enroll</span>
                    </button>
                </div>

                <div v-if="!hasChanges && selectedIds.length === 0 && permission.can('import employees')" class="flex items-center gap-2">
                    <button 
                        @click="showImportModal = true"
                        class="bg-(--primary) text-white px-4 py-2.5 rounded-md font-bold shadow-lg shadow-(--primary)/20 flex items-center gap-2 hover:opacity-90 transition-all"
                        title="Import Grouping Data"
                    >
                        <i class="bx bx-import text-xl"></i>
                        <span class="hidden md:inline">Import Data</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="isLoading" class="flex justify-center items-center py-20">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-(--primary)"></div>
        </div>

        <div v-else>
            <!-- Tab Navigation -->
            <div class="flex items-center gap-1 bg-(--bg-card) p-1 rounded-md border border-(--border-soft) w-fit mb-8 shadow-sm flex-wrap">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'flex items-center gap-2 px-5 py-2.5 rounded-md text-sm font-bold transition-all duration-300',
                        activeTab === tab.id 
                            ? 'bg-(--bg-main) text-(--primary) shadow-sm border border-(--border-soft)' 
                            : 'text-(--text-soft) hover:text-(--text-main)'
                    ]"
                >
                    <i :class="['bx', tab.icon, 'text-lg']"></i>
                    {{ tab.name }}
                </button>
            </div>

            <div class="bg-(--bg-card)/20 p-2 rounded-md border border-(--border-soft) shadow-sm">
                <!-- Sub-Tabs & Filters -->
                <div class="flex flex-wrap items-center justify-between gap-6 mb-8">
                    <div v-if="activeTab === 'payroll_cycle'" class="flex items-center gap-4 bg-(--bg-card) p-1.5 rounded-md border border-(--border-soft) shadow-sm overflow-x-auto max-w-full">
                        <div class="flex items-center gap-1 border-r border-(--border-soft) pr-2 mr-2">
                            <select v-model="activeYear" class="bg-transparent border-none text-xs font-bold text-(--text-main) focus:ring-0 cursor-pointer outline-none">
                                <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-1">
                            <button 
                                v-for="month in months" 
                                :key="month.id"
                                @click="activeMonth = month.id"
                                :class="[
                                    'px-3 py-1.5 rounded-md text-xs font-bold transition-all whitespace-nowrap',
                                    activeMonth === month.id 
                                        ? 'bg-(--primary) text-white shadow-md' 
                                        : 'text-(--text-soft) hover:bg-(--bg-elevated)'
                                ]"
                            >
                                {{ month.name }}
                            </button>
                        </div>
                    </div>
                    <div v-else class="flex-1"></div>

                    <!-- Search Bar -->
                    <div class="relative w-full md:w-80 group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="bx bx-search text-xl text-(--text-soft) group-focus-within:text-(--primary) transition-colors"></i>
                        </div>
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Cari nama atau NIK karyawan..."
                            class="w-full bg-(--bg-card) border border-(--border-soft) rounded-md py-2.5 pl-12 pr-4 text-xs font-medium placeholder:text-(--text-muted) focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all shadow-sm group-hover:border-(--text-soft) text-(--text-main)"
                        />
                        <div v-if="searchQuery" @click="searchQuery = ''" class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer">
                            <i class="bx bx-x text-xl text-(--text-soft) hover:text-(--danger) transition-colors"></i>
                        </div>
                    </div>
                </div>

                <div 
                    :class="[
                        'grid gap-6 transition-all duration-500 ',
                        activeTab === 'employment_type' ? 'grid-cols-1 md:grid-cols-3' : 'grid-cols-1 lg:grid-cols-3'
                    ]"
                >
                    <KanbanColumn
                        v-for="category in filteredData" 
                        :key="category.label"
                        :category="category"
                        :employees="getEmployeesByValue(category.value)"
                        :selectedIds="selectedIds"
                        :draggedEmployeeId="draggedEmployeeId"
                        :activeTab="activeTab"
                        :enrolledList="enrolledList"
                        @drop="onDrop"
                        @dragstart="onDragStart"
                        @toggleSelection="toggleSelection"
                        @selectAllInGroup="selectAllInGroup"
                    />
                </div>
            </div>
        </div>
        
        <!-- Batch Modal -->
        <div v-if="isBatchModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
            <div class="bg-(--bg-card) w-full max-w-lg rounded-md shadow-2xl border border-(--border-soft) overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-black text-(--text-main)">Batch Assignment</h2>
                        <button @click="isBatchModalOpen = false" class="p-2 hover:bg-(--bg-elevated) rounded-full text-(--text-soft) transition-colors">
                            <i class="bx bx-x text-2xl"></i>
                        </button>
                    </div>

                    <div class="bg-(--bg-elevated) p-4 rounded-md mb-8 border border-(--border-soft)">
                        <div class="flex items-center gap-3">
                            <div class="bg-(--primary) text-white w-8 h-8 rounded-full flex items-center justify-center font-bold shadow-lg shadow-(--primary-glow)">
                                {{ batchForm.employee_ids.length }}
                            </div>
                            <p class="text-sm font-bold text-(--text-main)">Karyawan Terpilih</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitBatch" class="space-y-6">
                        <div class="space-y-2" v-if="activeTab !== 'employment_type' && activeTab !== 'payroll_cycle'">
                            <label class="text-sm font-bold text-(--text-muted) ml-1">Pindahkan ke Grup</label>
                            <select v-model="batchForm.employee_group_id" class="w-full bg-(--bg-main) border border-(--border-soft) rounded-md px-5 py-3 text-sm font-medium focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all text-(--text-main)">
                                <option :value="null">-- Tetap / Tanpa Group --</option>
                                <option v-for="g in activeTabGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                        </div>

                        <div class="flex gap-4 pt-4">
                            <button 
                                type="button" 
                                @click="isBatchModalOpen = false"
                                class="flex-1 py-4 text-sm font-bold text-(--text-soft) hover:text-(--text-main) transition-colors"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="batchForm.processing"
                                class="flex-[2] py-4 bg-(--primary) text-white rounded-md font-bold shadow-xl shadow-(--primary-glow) hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50"
                            >
                                {{ batchForm.processing ? 'Memproses...' : 'Terapkan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Auto Enroll Confirm Dialog -->
        <ConfirmDialog
            :show="showAutoEnrollDialog"
            title="Auto Enroll?"
            message="Daftarkan semua karyawan aktif ke periode ini secara otomatis?"
            confirm-text="Ya, Daftar!"
            cancel-text="Batal"
            variant="warning"
            @confirm="handleAutoEnroll"
            @cancel="showAutoEnrollDialog = false"
        />

        <!-- Import Modal -->
        <ImportModal 
            :show="showImportModal"
            :active-tab="activeTab"
            :active-month="activeMonth"
            :active-year="activeYear"
            @close="showImportModal = false"
            @success="fetchData"
        />
    </div>
</template>

<style scoped>
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(20px);
  opacity: 0;
}

</style>
