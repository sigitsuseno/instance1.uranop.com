<template>
  <div :class="cardClasses">
    <div v-if="$slots.header || $slots.title || $slots.subtitle || $slots.actions" class="flex justify-between items-center border-b border-(--border-soft) pb-4 mb-4">
      <div v-if="$slots.header">
        <slot name="header" />
      </div>
      <template v-else>
        <div>
          <h3 v-if="$slots.title" class="text-lg font-semibold text-(--text-main)">
            <slot name="title" />
          </h3>
          <p v-if="$slots.subtitle" class="text-sm text-(--text-muted)">
            <slot name="subtitle" />
          </p>
        </div>
        <div v-if="$slots.actions">
          <slot name="actions" />
        </div>
      </template>
    </div>

    <slot />

    <div v-if="$slots.footer" class="border-t border-(--border-soft) pt-4 mt-4">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  padding: { type: String, default: 'p-6' },
  hoverable: { type: Boolean, default: false },
})

const cardClasses = computed(() => {
  const base = `${props.padding} bg-(--bg-card) border border-(--border-soft) rounded-md shadow-sm transition-all duration-300`
  if (props.hoverable) {
    return `${base} hover:shadow-lg hover:-translate-y-1 hover:border-(--primary)/50 cursor-pointer`
  }
  return base
})
</script>
