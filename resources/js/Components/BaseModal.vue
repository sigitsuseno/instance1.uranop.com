<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="show" class="fixed inset-0 z-40 bg-gray-900/60 backdrop-blur-sm" @click.self="handleBackdropClick" />
    </Transition>
    
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform scale-95 opacity-0 translate-y-4"
      enter-to-class="transform scale-100 opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="transform scale-100 opacity-100 translate-y-0"
      leave-to-class="transform scale-95 opacity-0 translate-y-4"
    >
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none p-4">
        <div :class="panelClasses">
          <div class="flex justify-between items-center px-6 py-4 border-b border-(--border-soft)">
            <h3 class="text-lg font-semibold text-(--text-main)">{{ title }}</h3>
            <button
              v-if="closeable"
              class="text-(--text-muted) hover:text-(--text-main) transition-colors"
              @click="close"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>

          <div class="p-6">
            <slot />
          </div>

          <div v-if="$slots.footer" class="px-6 py-4 border-t border-(--border-soft) flex justify-end gap-3">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  size: { type: String, default: 'md' },
  closeable: { type: Boolean, default: true },
})

const emit = defineEmits(['close'])

const sizeClasses = {
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
}

const panelClasses = computed(() => {
  return `bg-(--bg-card) rounded-xl shadow-2xl w-full pointer-events-auto ${sizeClasses[props.size] || sizeClasses.md} max-h-[90vh] overflow-y-auto`
})

function close() {
  emit('close')
}

function handleBackdropClick() {
  if (props.closeable) {
    close()
  }
}

function handleEscape(e) {
  if (e.key === 'Escape' && props.show && props.closeable) {
    close()
  }
}

watch(() => props.show, (val) => {
  if (val) {
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', handleEscape)
  } else {
    document.body.style.overflow = ''
    document.removeEventListener('keydown', handleEscape)
  }
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''
  document.removeEventListener('keydown', handleEscape)
})
</script>
