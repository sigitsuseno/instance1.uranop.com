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
  sm: 'px-3 py-1.5 text-xs h-8',
  md: 'px-4 py-2 text-sm h-10',
  lg: 'px-6 py-3 text-base h-12',
}

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center font-medium rounded-md transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-(--bg-main) active:scale-95'

  if (props.disabled || props.loading) {
    return `${base} opacity-50 cursor-not-allowed ${sizeClasses[props.size]} ${variantClasses[props.variant] || variantClasses.primary}`
  }

  return `${base} hover:-translate-y-[1px] hover:shadow-md ${sizeClasses[props.size]} ${variantClasses[props.variant] || variantClasses.primary}`
})

const variantClasses = {
  primary: 'bg-(--primary) text-white shadow-sm hover:shadow-lg hover:shadow-(--primary-glow) hover:bg-(--primary-hover) focus:ring-(--primary-glow) border border-transparent',
  secondary: 'bg-(--bg-card) text-(--text-main) border border-(--border-strong) shadow-sm hover:bg-(--bg-elevated) focus:ring-(--border-soft)',
  danger: 'bg-(--danger) text-white shadow-sm hover:shadow-lg hover:shadow-red-500/20 hover:bg-red-600 focus:ring-red-500/50 border border-transparent',
  success: 'bg-(--success) text-white shadow-sm hover:shadow-lg hover:shadow-green-500/20 hover:bg-green-600 focus:ring-green-500/50 border border-transparent',
  warning: 'bg-(--warning) text-white shadow-sm hover:shadow-lg hover:shadow-yellow-500/20 hover:bg-yellow-600 focus:ring-yellow-500/50 border border-transparent',
  ghost: 'text-(--text-muted) hover:text-(--text-main) hover:bg-(--bg-elevated) focus:ring-(--border-soft) border border-transparent hover:shadow-none',
}
</script>
