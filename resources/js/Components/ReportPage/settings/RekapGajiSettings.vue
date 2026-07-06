<template>
  <div>
    <p class="text-xs text-(--text-muted) mb-3">
      Atur pemetaan group karyawan ke section laporan.
    </p>

    <!-- Section A -->
    <div class="mb-4">
      <label class="block text-xs font-semibold text-(--text-main) mb-1.5">
        Section A — All In
      </label>
      <div class="flex flex-wrap gap-2">
        <label
          v-for="group in availableGroups"
          :key="'a-' + group"
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border cursor-pointer text-xs transition-colors select-none"
          :class="isInSection('A', group)
            ? 'bg-blue-50 border-blue-300 dark:bg-blue-900/20 dark:border-blue-700'
            : 'border-(--border-soft) hover:bg-(--bg-hover)'"
        >
          <input
            type="checkbox"
            :checked="isInSection('A', group)"
            @change="toggleSection('A', group)"
            class="w-3.5 h-3.5 rounded text-(--primary) focus:ring-(--primary-glow) border-(--border-soft)"
          />
          <span class="font-medium text-(--text-main)">{{ group }}</span>
        </label>
      </div>
      <p v-if="localMapping.A.length === 0" class="text-xs text-amber-600 mt-1">
        Tidak ada group terpilih untuk Section A.
      </p>
    </div>

    <!-- Section B -->
    <div class="mb-2">
      <label class="block text-xs font-semibold text-(--text-main) mb-1.5">
        Section B — Bulanan Print
      </label>
      <div class="flex flex-wrap gap-2">
        <label
          v-for="group in availableGroups"
          :key="'b-' + group"
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border cursor-pointer text-xs transition-colors select-none"
          :class="isInSection('B', group)
            ? 'bg-orange-50 border-orange-300 dark:bg-orange-900/20 dark:border-orange-700'
            : 'border-(--border-soft) hover:bg-(--bg-hover)'"
        >
          <input
            type="checkbox"
            :checked="isInSection('B', group)"
            @change="toggleSection('B', group)"
            class="w-3.5 h-3.5 rounded text-(--primary) focus:ring-(--primary-glow) border-(--border-soft)"
          />
          <span class="font-medium text-(--text-main)">{{ group }}</span>
        </label>
      </div>
      <p v-if="localMapping.B.length === 0" class="text-xs text-amber-600 mt-1">
        Tidak ada group terpilih untuk Section B.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  config: { type: Object, default: () => ({}) },
  availableGroups: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:config'])

const localMapping = ref({
  A: [...(props.config?.section_mapping?.A || ['GRP-ALLIN'])],
  B: [...(props.config?.section_mapping?.B || ['GRP-SS', 'GRP-PS1', 'GRP-GD', 'GRP-SPR'])],
})

// Sync to parent
watch(localMapping, (val) => {
  emit('update:config', { ...props.config, section_mapping: { ...val } })
}, { deep: true })

function isInSection(section, group) {
  return localMapping.value[section]?.includes(group) || false
}

function toggleSection(section, group) {
  const arr = localMapping.value[section]
  const idx = arr.indexOf(group)
  if (idx >= 0) {
    arr.splice(idx, 1)
  } else {
    arr.push(group)
  }
  // Trigger reactivity
  localMapping.value = { ...localMapping.value }
}
</script>
