<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import draggable from 'vuedraggable'

const { get, post } = useApi()
const notification = useNotificationStore()

const employees = ref([])
const groups = ref([])
const selectedGroupId = ref(null)
const isLoading = ref(true)
const hasChanges = ref(false)
const isSaving = ref(false)
const isAutoNumbering = ref(false)

const reorderCalled = ref(false)

const fetchData = async () => {
    isLoading.value = true
    try {
        const params = {}
        if (selectedGroupId.value) params.group_id = selectedGroupId.value

        const data = await get('/api/v1/employees/ordering', params)

        console.log('Data dari API:', data)
        console.log('Total karyawan:', data.employees?.length)

        employees.value = data.employees || []
        groups.value = data.groups || []
        hasChanges.value = false
        reorderCalled.value = false
    } catch (error) {
        console.error('Error fetching data:', error)
        notification.addNotification('Gagal memuat data.', 'error')
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchData()
})

watch(selectedGroupId, () => {
    fetchData()
})

// Drag selesai → tandai ada perubahan
const onDragEnd = () => {
    hasChanges.value = true
}

// Simpan urutan
const saveOrder = async () => {
    isSaving.value = true
    try {
        const orderedIds = employees.value.map(e => e.id)
        await post('/api/v1/employees/ordering/reorder', { ordered_ids: orderedIds })
        notification.addNotification('Urutan berhasil disimpan!', 'success')
        hasChanges.value = false
    } catch (error) {
        console.error(error)
        notification.addNotification('Gagal menyimpan urutan.', 'error')
    } finally {
        isSaving.value = false
    }
}

// Auto-number
const autoNumber = async () => {
    isAutoNumbering.value = true
    try {
        const payload = { scope: selectedGroupId.value ? 'group' : 'all' }
        if (selectedGroupId.value) payload.group_id = selectedGroupId.value

        const res = await post('/api/v1/employees/ordering/auto-number', payload)
        notification.addNotification(res.message || 'Auto-number berhasil!', 'success')
        fetchData()
    } catch (error) {
        console.error(error)
        notification.addNotification('Gagal auto-number.', 'error')
    } finally {
        isAutoNumbering.value = false
    }
}

// Group label untuk dropdown
const groupOptions = computed(() => {
    const labelMap = {}
    groups.value.forEach(g => {
        if (!labelMap[g.group_label]) {
            labelMap[g.group_label] = { label: g.group_label, items: [] }
        }
        labelMap[g.group_label].items.push(g)
    })
    return Object.values(labelMap)
})
</script>

<template>
    <div class="min-h-screen bg-(--bg-main) p-6">
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-(--text-main) tracking-tight">Urutan Karyawan</h1>
                <p class="text-(--text-muted) mt-1">Atur urutan tampilan karyawan dengan drag-and-drop.</p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Tombol Auto-Number -->
                <button
                    @click="autoNumber"
                    :disabled="isAutoNumbering"
                    class="bg-(--warning) text-white px-4 py-2.5 rounded-md font-bold shadow-lg shadow-(--warning)/20 flex items-center gap-2 hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i :class="isAutoNumbering ? 'bx bx-loader animate-spin' : 'bx bx-wand'"></i>
                    <span>Auto-Number</span>
                </button>

                <!-- Tombol Simpan -->
                <transition name="slide-fade">
                    <button
                        v-if="hasChanges"
                        @click="saveOrder"
                        :disabled="isSaving"
                        class="bg-(--success) text-white px-6 py-2.5 rounded-md font-bold shadow-lg shadow-(--success)/20 flex items-center gap-2 hover:opacity-90 transition-all animate-pulse disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i :class="isSaving ? 'bx bx-loader animate-spin' : 'bx bx-save'"></i>
                        <span>Simpan Urutan</span>
                    </button>
                </transition>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex justify-center items-center py-20">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-(--primary)"></div>
        </div>

        <div v-else>
            <!-- Filter Group -->
            <div class="mb-6">
                <select
                    v-model="selectedGroupId"
                    class="bg-(--bg-card) border border-(--border-soft) rounded-md px-4 py-2 text-sm font-bold text-(--text-main) focus:ring-2 focus:ring-(--primary) cursor-pointer outline-none"
                >
                    <option :value="null">Semua Karyawan</option>
                    <optgroup v-for="group in groupOptions" :key="group.label" :label="group.label">
                        <option
                            v-for="item in group.items"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </optgroup>
                </select>
                <span class="ml-3 text-xs text-(--text-muted)">
                    {{ employees.length }} karyawan
                </span>
            </div>

            <!-- Drag-to-Reorder List -->
            <div class="bg-(--bg-card) rounded-md border border-(--border-soft) shadow-sm overflow-hidden">
                <!-- Header -->
                <div class="flex items-center gap-4 px-4 py-3 bg-(--bg-main) border-b border-(--border-soft) text-xs font-bold text-(--text-soft) uppercase tracking-wider">
                    <div class="w-8"></div>
                    <div class="w-10 text-center">#</div>
                    <div class="flex-1">Nama Karyawan</div>
                    <div class="w-32 text-right">NIP</div>
                </div>

                <!-- Draggable List -->
                <draggable
                    v-model="employees"
                    item-key="id"
                    handle=".drag-handle"
                    ghost-class="ghosting"
                    drag-class="dragging"
                    @end="onDragEnd"
                    class="divide-y divide-(--border-soft)"
                >
                    <template #item="{ element, index }">
                        <div class="flex items-center gap-4 px-4 py-3 hover:bg-(--bg-main)/50 transition-all cursor-default">
                            <!-- Drag Handle -->
                            <div class="drag-handle w-8 flex items-center justify-center cursor-grab active:cursor-grabbing text-(--text-muted) hover:text-(--text-main) transition-colors">
                                <i class="bx bx-grid-vertical text-lg"></i>
                            </div>

                            <!-- Nomor Urut -->
                            <div class="w-10 text-center">
                                <span
                                    :class="[
                                        'inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold',
                                        element.no_urut
                                            ? 'bg-(--primary) text-white'
                                            : 'bg-(--bg-main) text-(--text-muted) border border-(--border-soft)'
                                    ]"
                                >
                                    {{ element.no_urut || '-' }}
                                </span>
                            </div>

                            <!-- Info Karyawan -->
                            <div class="flex-1 flex items-center gap-3 min-w-0">
                                <!-- Avatar -->
                                <img
                                    v-if="element.photo_url"
                                    :src="element.photo_url"
                                    class="w-8 h-8 rounded-full object-cover border border-(--border-soft) flex-shrink-0"
                                />
                                <div
                                    v-else
                                    class="w-8 h-8 rounded-full border border-(--border-soft) bg-(--primary) text-white flex items-center justify-center font-bold text-[10px] flex-shrink-0"
                                >
                                    {{ element.name ? element.name.charAt(0).toUpperCase() : '?' }}
                                </div>

                                <div class="min-w-0">
                                    <h4 class="text-sm font-bold text-(--text-main) truncate">{{ element.name }}</h4>
                                    <p class="text-[10px] text-(--text-soft)">{{ element.employee_code }}</p>
                                </div>
                            </div>

                            <!-- NIP -->
                            <div class="w-32 text-right">
                                <span class="text-xs font-mono text-(--text-soft)">{{ element.nik || '-' }}</span>
                            </div>
                        </div>
                    </template>
                </draggable>

                <!-- Empty State -->
                <div v-if="employees.length === 0" class="py-16 text-center">
                    <i class="bx bx-user-x text-5xl text-(--text-muted) mb-3"></i>
                    <p class="text-sm text-(--text-soft)">Tidak ada karyawan.</p>
                </div>
            </div>

            <!-- Hint -->
            <div class="mt-4 flex items-center gap-2 text-xs text-(--text-muted)">
                <i class="bx bx-info-circle"></i>
                <span>Seret ikon <i class="bx bx-grid-vertical"></i> untuk mengubah urutan. Urutan ini akan mempengaruhi tampilan di halaman Grouping Karyawan.</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-leave-active { transition: all 0.2s ease-in; }
.slide-fade-enter-from,
.slide-fade-leave-to { opacity: 0; transform: translateX(10px); }

.dragging {
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    border-color: var(--primary) !important;
    opacity: 0.95;
}

.ghosting {
    opacity: 0.4;
    background: color-mix(in srgb, var(--primary-glow, #6366f1) 10%, transparent);
}
</style>
