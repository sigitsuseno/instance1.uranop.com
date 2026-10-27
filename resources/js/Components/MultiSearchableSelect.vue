<template>
  <div ref="wrapperRef" class="relative">
    <!-- Trigger -->
    <button
      type="button"
      :disabled="disabled"
      class="w-full h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-sm text-left flex items-center gap-2 transition-colors hover:border-(--border-strong) focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary)"
      :class="disabled ? 'opacity-50 cursor-not-allowed hover:border-(--border-soft)' : 'text-(--text-main)'"
      @click="toggleDropdown"
      @keydown.esc="close"
    >
      <i class="bx bx-user text-base text-(--text-muted) shrink-0"></i>
      <span class="flex-1 min-w-0 truncate" :class="modelValue.length ? 'text-(--text-main)' : 'text-(--text-soft)'">
        {{ triggerLabel }}
      </span>
      <span
        v-if="modelValue.length"
        class="shrink-0 px-1.5 py-0.5 rounded bg-(--primary)/10 text-(--primary) text-[11px] font-semibold"
      >
        {{ modelValue.length }}
      </span>
      <i
        class="bx text-base text-(--text-muted) shrink-0 transition-transform"
        :class="open ? 'bx-chevron-up' : 'bx-chevron-down'"
      ></i>
    </button>

    <!-- Dropdown -->
    <Transition name="ms-dropdown">
      <div
        v-if="open"
        class="absolute z-50 mt-1 left-0 min-w-full w-[320px] max-w-[calc(100vw-2rem)] rounded-md border border-(--border-soft) bg-(--bg-card) shadow-xl overflow-hidden"
      >
        <div class="p-2 border-b border-(--border-soft)">
          <div class="relative">
            <i class="bx bx-search absolute left-2.5 top-1/2 -translate-y-1/2 text-(--text-muted) text-base"></i>
            <input
              ref="searchRef"
              v-model="searchText"
              type="text"
              :placeholder="searchPlaceholder"
              autocomplete="off"
              class="w-full h-9 pl-8 pr-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-sm text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary)"
              @keydown.esc="close"
            />
          </div>
        </div>

        <div class="flex items-center justify-between px-3 py-1.5 border-b border-(--border-soft) bg-(--bg-elevated)/40">
          <button
            type="button"
            class="text-xs font-medium text-(--primary) hover:underline disabled:opacity-50 disabled:no-underline"
            :disabled="filteredOptions.length === 0 || allFilteredSelected"
            @click="selectAllFiltered"
          >
            Pilih semua{{ searchText ? ` (${filteredOptions.length})` : '' }}
          </button>
          <button
            type="button"
            class="text-xs font-medium text-(--text-muted) hover:text-(--danger) disabled:opacity-50"
            :disabled="modelValue.length === 0"
            @click="clearAll"
          >
            Bersihkan
          </button>
        </div>

        <ul class="max-h-64 overflow-y-auto py-1">
          <li v-if="loading" class="px-3 py-6 text-center text-sm text-(--text-muted)">
            <i class="bx bx-loader-alt bx-spin text-lg mr-1"></i>
            Memuat karyawan...
          </li>
          <li v-else-if="filteredOptions.length === 0" class="px-3 py-6 text-center text-sm text-(--text-muted)">
            {{ searchText ? `Tidak ada hasil untuk "${searchText}"` : emptyText }}
          </li>
          <li v-for="option in filteredOptions" :key="option.value">
            <label class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-(--bg-elevated) transition-colors">
              <input
                type="checkbox"
                class="h-4 w-4 rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow) accent-(--primary)"
                :checked="isSelected(option)"
                @change="toggleOption(option)"
              />
              <span class="flex-1 min-w-0">
                <span class="block text-sm text-(--text-main) truncate">{{ option.label }}</span>
                <span v-if="option.sublabel" class="block text-xs text-(--text-muted) font-mono truncate">
                  {{ option.sublabel }}
                </span>
              </span>
            </label>
          </li>
        </ul>

        <div class="flex items-center justify-between gap-2 px-3 py-2 border-t border-(--border-soft) bg-(--bg-elevated)/40">
          <span class="text-xs text-(--text-muted)">
            {{ modelValue.length }} dipilih
          </span>
          <button
            type="button"
            class="px-2.5 py-1 rounded text-xs font-medium text-(--text-main) hover:bg-(--bg-elevated) transition-colors"
            @click="close"
          >
            Selesai
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Semua karyawan' },
  searchPlaceholder: { type: String, default: 'Cari nama / NIP...' },
  emptyText: { type: String, default: 'Tidak ada karyawan' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const wrapperRef = ref(null)
const searchRef = ref(null)

const open = ref(false)
const searchText = ref('')

const filteredOptions = computed(() => {
  const q = searchText.value.trim().toLowerCase()
  if (!q) return props.options

  return props.options.filter((option) => {
    const haystack = `${option.label ?? ''} ${option.sublabel ?? ''}`.toLowerCase()
    return haystack.includes(q)
  })
})

const triggerLabel = computed(() => {
  if (props.modelValue.length === 0) return props.placeholder
  if (props.modelValue.length === 1) {
    const selected = props.options.find((o) => String(o.value) === String(props.modelValue[0]))
    return selected ? selected.label : '1 karyawan dipilih'
  }
  return `${props.modelValue.length} karyawan dipilih`
})

const allFilteredSelected = computed(() => {
  if (filteredOptions.value.length === 0) return false
  const selected = props.modelValue.map(String)
  return filteredOptions.value.every((option) => selected.includes(String(option.value)))
})

function isSelected(option) {
  return props.modelValue.map(String).includes(String(option.value))
}

function toggleOption(option) {
  if (isSelected(option)) {
    emit('update:modelValue', props.modelValue.filter((v) => String(v) !== String(option.value)))
    return
  }
  emit('update:modelValue', [...props.modelValue, option.value])
}

function selectAllFiltered() {
  const selected = props.modelValue.map(String)
  const additions = filteredOptions.value
    .filter((option) => !selected.includes(String(option.value)))
    .map((option) => option.value)

  if (additions.length) {
    emit('update:modelValue', [...props.modelValue, ...additions])
  }
}

function clearAll() {
  emit('update:modelValue', [])
}

function toggleDropdown() {
  if (props.disabled) return
  open.value ? close() : openDropdown()
}

function openDropdown() {
  open.value = true
  searchText.value = ''
  nextTick(() => searchRef.value?.focus())
}

function close() {
  open.value = false
  searchText.value = ''
}

function onClickOutside(event) {
  if (wrapperRef.value && !wrapperRef.value.contains(event.target)) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onClickOutside)
})
</script>

<style scoped>
.ms-dropdown-enter-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.ms-dropdown-leave-active {
  transition: opacity 0.1s ease, transform 0.1s ease;
}
.ms-dropdown-enter-from,
.ms-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
