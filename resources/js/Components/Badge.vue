<template>
  <span :class="badgeClasses">
    <slot />
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'sm' },
})

const sizeClasses = {
  sm: 'px-2 py-0 text-xs',
  md: 'px-2.5 py-0.5 text-sm',
}

const variantClasses = {
  primary: 'bg-(--primary)/10 text-(--primary)',
  success: 'bg-(--success)/10 text-(--success)',
  warning: 'bg-(--warning)/10 text-(--warning)',
  danger: 'bg-(--danger)/10 text-(--danger)',
  info: 'bg-(--primary)/10 text-(--primary)',
  neutral: 'bg-(--bg-elevated) text-(--text-muted)',
}

const badgeClasses = computed(() => {
  return `inline-flex items-center rounded-full font-medium ${sizeClasses[props.size] || sizeClasses.sm} ${variantClasses[props.variant] || variantClasses.primary}`
})
</script>
