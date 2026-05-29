<template>
  <div>
    <label v-if="label" class="block text-sm font-medium text-(--text-main) mb-1">
      {{ label }}
      <span v-if="required" class="text-(--danger)">*</span>
    </label>
    <div class="relative">
      <div v-if="$slots.icon" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
        <slot name="icon" />
      </div>
      <input
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :class="inputClasses"
        @input="$emit('update:modelValue', $event.target.value)"
      />
    </div>
    <p v-if="error" class="text-xs text-(--danger) mt-1">{{ error }}</p>
    <p v-else-if="helper" class="text-xs text-(--text-muted) mt-1">{{ helper }}</p>
  </div>
</template>

<script setup>
import { computed, useSlots } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  helper: { type: String, default: '' },
  required: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const slots = useSlots()

const inputClasses = computed(() => {
  const base = 'w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300'

  const iconPadding = slots.icon ? 'pl-10' : ''

  if (props.disabled) {
    return `${base} ${iconPadding} opacity-50 bg-(--bg-elevated) cursor-not-allowed hover:border-(--border-soft)`
  }

  if (props.error) {
    return `${base} ${iconPadding} border-(--danger) hover:border-(--danger) focus:ring-(--danger)/20`
  }

  return `${base} ${iconPadding}`
})
</script>
