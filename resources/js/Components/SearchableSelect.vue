<template>
  <div ref="wrapperRef">
    <label v-if="label" class="block text-sm font-medium text-(--text-main) mb-1">
      {{ label }}
      <span v-if="required" class="text-(--danger)">*</span>
    </label>
    <div class="relative">
      <!-- Search input -->
      <input
        ref="inputRef"
        type="text"
        :value="searchText"
        :disabled="disabled"
        :placeholder="placeholder || 'Cari...'"
        :class="inputClasses"
        autocomplete="off"
        @input="onSearchInput"
        @focus="onFocus"
        @keydown="onKeydown"
        @blur="onBlur"
      />
      <!-- Dropdown chevron -->
      <div
        class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-(--text-muted)"
        :class="{ 'opacity-50': disabled }"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline v-if="open" points="18 15 12 9 6 15" />
          <polyline v-else points="6 9 12 15 18 9" />
        </svg>
      </div>

      <!-- Dropdown menu -->
      <Transition name="combobox-dropdown">
        <ul
          v-if="open && filteredOptions.length > 0"
          class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto rounded-md border border-(--border-soft) bg-(--bg-card) shadow-lg py-1"
        >
          <li
            v-for="(option, idx) in filteredOptions"
            :key="option.value"
            ref="optionRefs"
            :class="[
              'px-3 py-2 cursor-pointer text-sm transition-colors flex items-center',
              idx === highlightedIndex
                ? 'bg-(--primary)/10 text-(--primary)'
                : 'text-(--text-main) hover:bg-(--bg-elevated)'
            ]"
            @mousedown.prevent="selectOption(option)"
            @mouseenter="highlightedIndex = idx"
          >
            <span>{{ option.label }}</span>
            <svg
              v-if="option.value === modelValue"
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4 ml-auto shrink-0 text-(--primary)"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </li>
        </ul>
      </Transition>

      <!-- Empty state -->
      <Transition name="combobox-dropdown">
        <div
          v-if="open && filteredOptions.length === 0 && searchText"
          class="absolute z-50 mt-1 w-full rounded-md border border-(--border-soft) bg-(--bg-card) shadow-lg py-3 px-3 text-center text-sm text-(--text-muted)"
        >
          Tidak ada hasil untuk "{{ searchText }}"
        </div>
      </Transition>
    </div>
    <p v-if="error" class="text-xs text-(--danger) mt-1">{{ error }}</p>
    <p v-else-if="helper" class="text-xs text-(--text-muted) mt-1">{{ helper }}</p>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  options: { type: Array, required: true },
  placeholder: { type: String, default: 'Cari...' },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  helper: { type: String, default: '' },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const wrapperRef = ref(null)
const inputRef = ref(null)
const optionRefs = ref([])

const open = ref(false)
const searchText = ref('')
const highlightedIndex = ref(-1)

// Initialize searchText with current selected label
watch(
  () => [props.modelValue, props.options],
  () => {
    if (!props.modelValue) {
      searchText.value = ''
      return
    }
    const selected = props.options.find(o => o.value === props.modelValue)
    // Only update if user hasn't typed something different
    if (selected && !open.value) {
      searchText.value = selected.label
    }
  },
  { immediate: true }
)

const filteredOptions = computed(() => {
  const q = searchText.value.toLowerCase().trim()
  if (!q) return props.options
  return props.options.filter(o =>
    o.label.toLowerCase().includes(q)
  )
})

const inputClasses = computed(() => {
  let cls = 'w-full px-3 py-2 pr-10 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300'

  if (props.disabled) {
    cls += ' opacity-50 bg-(--bg-elevated) cursor-not-allowed hover:border-(--border-soft)'
  }
  if (props.error) {
    cls += ' border-(--danger) hover:border-(--danger) focus:ring-(--danger)/20'
  }
  return cls
})

// Methods
function onSearchInput(e) {
  searchText.value = e.target.value
  // If user clears the input, clear the model
  if (!e.target.value) {
    emit('update:modelValue', '')
  }
  open.value = true
  highlightedIndex.value = 0
}

function onFocus() {
  if (props.disabled) return
  // On focus, select all text for easy replacement
  nextTick(() => {
    inputRef.value?.select()
  })
  open.value = true
  highlightedIndex.value = -1
}

function onBlur() {
  // Delay close so mousedown on option fires first
  setTimeout(() => {
    if (wrapperRef.value && !wrapperRef.value.contains(document.activeElement)) {
      closeDropdown()
    }
  }, 150)
}

function closeDropdown() {
  open.value = false
  // Restore searchText to selected option's label
  if (props.modelValue) {
    const selected = props.options.find(o => o.value === props.modelValue)
    searchText.value = selected ? selected.label : ''
  } else {
    searchText.value = ''
  }
}

function onKeydown(e) {
  if (!open.value) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      open.value = true
      highlightedIndex.value = 0
      e.preventDefault()
      return
    }
  }

  switch (e.key) {
    case 'ArrowDown':
      highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredOptions.value.length - 1)
      e.preventDefault()
      scrollToHighlighted()
      break
    case 'ArrowUp':
      highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
      e.preventDefault()
      scrollToHighlighted()
      break
    case 'Enter':
      if (highlightedIndex.value >= 0 && highlightedIndex.value < filteredOptions.value.length) {
        selectOption(filteredOptions.value[highlightedIndex.value])
        e.preventDefault()
      }
      break
    case 'Escape':
      closeDropdown()
      e.preventDefault()
      break
  }
}

function scrollToHighlighted() {
  nextTick(() => {
    const el = optionRefs.value[highlightedIndex.value]
    el?.scrollIntoView({ block: 'nearest' })
  })
}

function selectOption(option) {
  emit('update:modelValue', option.value)
  searchText.value = option.label
  open.value = false
  inputRef.value?.blur()
}

// Click outside
function onClickOutside(e) {
  if (wrapperRef.value && !wrapperRef.value.contains(e.target)) {
    closeDropdown()
  }
}

onMounted(() => {
  document.addEventListener('click', onClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside)
})
</script>

<style scoped>
.combobox-dropdown-enter-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.combobox-dropdown-leave-active {
  transition: opacity 0.1s ease, transform 0.1s ease;
}
.combobox-dropdown-enter-from {
  opacity: 0;
  transform: translateY(-6px);
}
.combobox-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
