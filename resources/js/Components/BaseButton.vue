<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="buttonClasses"
    v-bind="$attrs"
  >
    <span v-if="loading" class="mr-2 inline-flex">
      <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </span>
    <span v-if="$slots['icon-left'] && !loading" class="mr-2 inline-flex">
      <slot name="icon-left" />
    </span>
    <slot />
    <span v-if="$slots['icon-right'] && !loading" class="ml-2 inline-flex">
      <slot name="icon-right" />
    </span>
  </button>
</template>

<script setup>
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })
useAttrs()

const props = defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
})

const sizeClasses = {
  sm: 'px-3 py-1.5 text-xs',
  md: 'px-4 py-2 text-sm',
  lg: 'px-6 py-3 text-base',
}

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-(--primary)/25'

  if (props.disabled || props.loading) {
    return `${base} opacity-50 cursor-not-allowed ${sizeClasses[props.size]} ${variantClasses[props.variant] || variantClasses.primary}`
  }

  return `${base} ${sizeClasses[props.size]} ${variantClasses[props.variant] || variantClasses.primary}`
})

const variantClasses = {
  primary: 'bg-(--primary) text-white hover:bg-(--primary-hover)',
  secondary: 'bg-(--bg-elevated) text-(--text-main) border border-(--border-soft) hover:bg-(--border-soft)',
  danger: 'bg-(--danger) text-white hover:bg-(--danger)/90',
  success: 'bg-(--success) text-white hover:bg-(--success)/90',
  warning: 'bg-(--warning) text-white hover:bg-(--warning)/90',
  ghost: 'text-(--text-muted) hover:text-(--text-main) hover:bg-(--bg-elevated)',
}
</script>
