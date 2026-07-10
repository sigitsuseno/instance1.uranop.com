<script setup>
defineProps({
  employee: { type: Object, required: true },
  isSelected: { type: Boolean, default: false },
})

const emit = defineEmits(['dragstart', 'toggleSelection'])

const getInitials = (name) => {
  if (!name) return ''
  return name.split(' ').map(n => n[0]).slice(0, 1).join('').toUpperCase()
}
</script>

<template>
  <div
    draggable="true"
    @dragstart="emit('dragstart', employee.id)"
    @click="emit('toggleSelection', employee.id)"
    :class="[
      'relative group cursor-grab active:cursor-grabbing p-3 rounded-md border transition-all duration-300 bg-(--bg-card) shadow-sm',
      isSelected
        ? 'border-(--primary) ring-2 ring-(--primary-glow)'
        : 'border-(--border-soft) hover:border-(--text-soft)',
    ]"
  >
    <div class="flex items-center gap-3">
      <div class="relative flex-shrink-0">
        <div
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
          <span
            class="text-[9px] text-(--text-soft) font-bold uppercase tracking-wider bg-(--bg-main) px-1.5 py-0.5 rounded border border-(--border-soft)"
          >
            {{ employee.employee_code || employee.nip }}
          </span>
          <span
            v-if="employee.department"
            class="text-[9px] text-(--text-soft) bg-(--bg-main) px-1.5 py-0.5 rounded border border-(--border-soft)"
          >
            {{ employee.department.name }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
