<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  position: { type: String, default: 'right', validator: (v) => ['left', 'right'].includes(v) },
  width: { type: String, default: 'w-64' },
  closeOnClick: { type: Boolean, default: true },
  closeOnClickOutside: { type: Boolean, default: true },
})

const emit = defineEmits(['open', 'close'])

const isOpen = ref(false)
const dropdownRef = ref(null)

function toggle(event) {
  if (event) {
    event.preventDefault()
    event.stopPropagation()
  }
  isOpen.value = !isOpen.value
  emit(isOpen.value ? 'open' : 'close')
}

function open() {
  isOpen.value = true
  emit('open')
}

function close() {
  isOpen.value = false
  emit('close')
}

function handleClickOutside(event) {
  if (!props.closeOnClickOutside) return
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    close()
  }
}

function handleEscape(event) {
  if (event.key === 'Escape' && isOpen.value) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside, true)
  document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside, true)
  document.removeEventListener('keydown', handleEscape)
})

defineExpose({ open, close, toggle, isOpen })
</script>

<template>
  <div class="relative" ref="dropdownRef">
    <div @click.stop="toggle" class="cursor-pointer">
      <slot name="trigger" :toggle="toggle" :isOpen="isOpen" />
    </div>

    <Transition enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform scale-95 opacity-0"
      enter-to-class="transform scale-100 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform scale-100 opacity-100"
      leave-to-class="transform scale-95 opacity-0">
      <div v-if="isOpen" @click.stop
        :class="[
          'absolute z-50 mt-2 bg-(--bg-card) border border-(--border-soft) rounded-lg shadow-lg py-1',
          position === 'left' ? 'left-0' : 'right-0',
          width
        ]">
        <slot name="content" :close="close" />
      </div>
    </Transition>
  </div>
</template>
