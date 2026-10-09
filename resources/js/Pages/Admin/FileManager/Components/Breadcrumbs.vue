<script setup>
defineProps({
  items: { type: Array, default: () => [] },
})

const emit = defineEmits(['navigate'])
</script>

<template>
  <nav class="flex flex-wrap items-center gap-1 text-sm" aria-label="Lokasi folder">
    <template v-for="(crumb, index) in items" :key="crumb.path">
      <i v-if="index > 0" class="bx bx-chevron-right text-lg text-(--text-soft)"></i>

      <button
        type="button"
        :class="[
          'flex items-center gap-1.5 rounded px-2 py-1 transition-colors',
          index === items.length - 1
            ? 'font-semibold text-(--text-main)'
            : 'text-(--text-muted) hover:bg-(--bg-elevated) hover:text-(--primary)',
        ]"
        :disabled="index === items.length - 1"
        @click="emit('navigate', crumb.path)"
      >
        <i v-if="index === 0" class="bx bx-home-alt text-base"></i>
        <span class="max-w-[12rem] truncate">{{ crumb.name }}</span>
      </button>
    </template>
  </nav>
</template>
