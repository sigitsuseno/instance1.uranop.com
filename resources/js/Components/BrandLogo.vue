<script setup>
import { ref, computed } from 'vue'
import { useCompanyStore } from '../Stores/company'

const props = defineProps({
  size: { type: String, default: 'md' }, // sm | md | lg
  glow: { type: Boolean, default: false },
  rounded: { type: String, default: 'rounded-md' },
})

const company = useCompanyStore()

const imgError = ref(false)
const showLogo = computed(() => company.logoUrl && !imgError.value)

const sizeClasses = {
  sm: ['w-8', 'h-8', 'text-base'],
  md: ['w-10', 'h-10', 'text-xl'],
  lg: ['w-14', 'h-14', 'text-3xl'],
}
</script>

<template>
  <div
    v-if="showLogo"
    class="overflow-hidden bg-(--bg-card) border border-(--border-soft) flex items-center justify-center shrink-0"
    :class="[sizeClasses[size] || sizeClasses.md, rounded]"
  >
    <img :src="company.logoUrl" :alt="company.name" @error="imgError = true" class="w-full h-full object-contain" />
  </div>

  <div
    v-else
    class="bg-(--primary) flex items-center justify-center font-black text-white shrink-0"
    :class="[
      ...(sizeClasses[size] || sizeClasses.md),
      rounded,
      glow ? 'shadow-lg shadow-(--primary-glow)' : '',
    ]"
  >
    {{ company.logoInitial }}
  </div>
</template>
