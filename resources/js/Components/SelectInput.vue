<template>
  <div>
    <label v-if="label" class="block text-sm font-medium text-(--text-main) mb-1">
      {{ label }}
      <span v-if="required" class="text-(--danger)">*</span>
    </label>
    <div class="relative">
      <select
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        :class="selectClasses"
        @change="$emit('update:modelValue', $event.target.value)"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option
          v-for="option in options"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </option>
      </select>
      <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-(--text-muted)">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </div>
    </div>
    <p v-if="error" class="text-xs text-(--danger) mt-1">{{ error }}</p>
    <p v-else-if="helper" class="text-xs text-(--text-muted) mt-1">{{ helper }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  options: { type: Array, required: true },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  helper: { type: String, default: '' },
  required: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const selectClasses = computed(() => {
  const base = 'w-full px-3 py-2 pr-10 rounded-md border bg-(--bg-card) text-(--text-main) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors appearance-none'

  if (props.disabled) {
    return `${base} opacity-50 bg-(--bg-elevated) cursor-not-allowed`
  }

  if (props.error) {
    return `${base} border-(--danger) ring-(--danger)/25`
  }

  return base
})
</script>
