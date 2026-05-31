<script setup>
import EmployeeCard from './EmployeeCard.vue';
import { computed } from 'vue';

const props = defineProps({
    category: { type: Object, required: true },
    employees: { type: Array, required: true },
    selectedIds: { type: Array, required: true },
    draggedEmployeeId: { type: [Number, String], default: null },
    activeTab: { type: String, required: true },
    enrolledList: { type: Object, default: () => ({}) }
});

const emit = defineEmits(['drop', 'selectAllInGroup', 'dragstart', 'toggleSelection']);

const isAllSelected = computed(() => {
    if (props.employees.length === 0) return false;
    return props.employees.every(e => props.selectedIds.includes(e.id));
});

const handleDrop = () => {
    emit('drop', props.category.value);
};
</script>

<template>
    <div class="flex flex-col h-full min-h-[500px]">
        <!-- Column Header -->
        <div 
            class="sticky top-0 z-20 flex items-center justify-between p-4 bg-(--bg-main) border-b-2 mb-4 rounded-t-md"
            :style="{ borderColor: category.color }"
        >
            <div class="flex items-center gap-3">
                <div 
                    class="w-8 h-8 rounded-md flex items-center justify-center bg-(--bg-card) border border-(--border-soft) shadow-sm"
                    :style="{ color: category.color }"
                >
                    <i :class="['bx', category.icon, 'text-xl']"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-(--text-main)">{{ category.label }}</h3>
                    <p class="text-[10px] text-(--text-soft) font-medium">{{ employees.length }} Orang</p>
                </div>
            </div>
            
            <button 
                @click="emit('selectAllInGroup', employees)"
                class="text-[10px] font-bold text-(--primary) hover:underline"
            >
                {{ employees.length > 0 && isAllSelected ? 'Deselect' : 'Select All' }}
            </button>
        </div>

        <!-- Drop Zone & Employee List -->
        <div 
            class="flex-1 p-2 rounded-md border-2 border-dashed transition-all duration-300 overflow-y-auto max-h-[calc(100vh-300px)] custom-scrollbar"
            :class="[
                draggedEmployeeId 
                    ? 'border-(--primary) bg-(--primary-glow)/20 scale-[1.01]' 
                    : 'border-transparent bg-(--bg-elevated)/30'
            ]"
            @dragover.prevent
            @drop="handleDrop"
        >
            <div class="grid grid-cols-1 gap-3">
                <EmployeeCard
                    v-for="emp in employees" 
                    :key="emp.id"
                    :employee="emp"
                    :isSelected="selectedIds.includes(emp.id)"
                    :activeTab="activeTab"
                    :enrolledList="enrolledList"
                    @dragstart="emit('dragstart', $event)"
                    @toggleSelection="emit('toggleSelection', $event)"
                />

                <div v-if="employees.length === 0" class="py-12 text-center pointer-events-none">
                    <i class="bx bx-subdirectory-right text-3xl text-(--text-muted) mb-2"></i>
                    <p class="text-[10px] text-(--text-soft) font-medium italic">Drop karyawan di sini</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--border-soft);
    border-radius: 4px;
}
</style>
