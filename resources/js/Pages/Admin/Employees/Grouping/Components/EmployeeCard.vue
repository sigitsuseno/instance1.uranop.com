<script setup>
const props = defineProps({
    employee: { type: Object, required: true },
    isSelected: { type: Boolean, default: false },
    activeTab: { type: String, required: true },
    enrolledList: { type: Object, default: () => ({}) }
});

const emit = defineEmits(['dragstart', 'toggleSelection']);

const getInitials = (name) => {
    if (!name) return '';
    return name
        .split(' ')
        .map(n => n[0])
        .slice(0, 1)
        .join('')
        .toUpperCase();
};

const handleDragStart = (e) => {
    // Optionally set data transfer if needed, but we use parent state
    emit('dragstart', props.employee.id);
};
</script>

<template>
    <div 
        draggable="true"
        @dragstart="handleDragStart"
        @click="emit('toggleSelection', employee.id)"
        :class="[
            'relative group cursor-pointer p-3 rounded-md border transition-all duration-300 bg-(--bg-card) shadow-sm',
            isSelected
                ? 'border-(--primary) ring-2 ring-(--primary-glow)'
                : 'border-(--border-soft) hover:border-(--text-soft)'
        ]"
    >
        <div class="flex items-center gap-3">
            <div class="relative flex-shrink-0">
                <img 
                    v-if="employee.photo_url"
                    :src="employee.photo_url" 
                    class="w-10 h-10 rounded-full object-cover border border-(--border-soft)"
                />
                <div 
                    v-else
                    class="w-10 h-10 rounded-full border border-(--border-soft) bg-(--primary) text-white flex items-center justify-center font-bold text-xs"
                >
                    {{ getInitials(employee.name) }}
                </div>
                <div 
                    v-if="isSelected"
                    class="absolute -top-1 -right-1 w-4 h-4 bg-(--primary) text-white rounded-full flex items-center justify-center border border-(--bg-card) z-10"
                >
                    <i class="bx bx-check text-[10px]"></i>
                </div>
            </div>
            <div class="min-w-0">
                <h4 class="text-xs font-bold text-(--text-main) truncate">{{ employee.name }}</h4>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-[9px] text-(--text-soft) font-bold uppercase tracking-wider bg-(--bg-main) px-1.5 py-0.5 rounded border border-(--border-soft)">
                        {{ employee.employee_code || employee.nik }}
                    </span>
                    <span v-if="activeTab === 'payroll_cycle' && enrolledList[employee.id]" class="text-[9px] font-bold text-(--success) bg-(--success)/10 px-1.5 py-0.5 rounded border border-(--success)/20 uppercase">
                        {{ enrolledList[employee.id].emp_group }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
